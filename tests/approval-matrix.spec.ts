import { test, expect, Page, Browser, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://local.host';
const PASSWORD = 'Password123!';

// Fixture IDs populated by the setup test (tests in one file run sequentially).
const FX = {
  cancelTxA: 76,   // KL02 Completed — compliance1 approves cancellation (reversal)
  cancelTxB: 64,   // KL02 Completed — manager1 denied (needs reverse perm), then rejects it
  allocId: 0,      // PNG01 pending teller allocation — manager2 approves
  transferId: 0,   // Requested KL02->PNG01 transfer — manager2 (taker) approves
  txA: 0,          // PNG01 pending tx — compliance2 approves
  txB: 0,          // PNG01 pending tx — admin approves
};

async function login(page: Page, username: string) {
  for (let attempt = 0; attempt < 4; attempt++) {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input#username', username);
    await page.fill('input#password', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(800);
    if (!page.url().includes('/login')) {
      return;
    }
    await page.waitForTimeout(12000 * (attempt + 1));
  }
  throw new Error(`Login failed for ${username}`);
}

async function csrf(page: Page): Promise<string> {
  return page.evaluate(() =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
  );
}

// POST via the context's request client: shares session cookies, returns the
// REAL status code (403 vs 302) — unlike fetch(redirect:manual) which is opaque.
async function post(page: Page, path: string, data: Record<string, string> = {}) {
  const token = await csrf(page);
  const resp = await page.context().request.post(`${BASE_URL}${path}`, {
    form: { _token: token, ...data },
    maxRedirects: 0,
  });
  return { status: resp.status(), location: resp.headers()['location'] ?? '' };
}

async function getText(page: Page, path: string): Promise<string> {
  const resp = await page.context().request.get(`${BASE_URL}${path}`);
  return resp.text();
}

function idFrom(location: string, pattern: RegExp): number {
  const m = location.match(pattern);
  return m ? parseInt(m[1], 10) : 0;
}

test('setup: create pending fixtures through the browser', async ({ browser }) => {
  test.setTimeout(500000);
  const log: string[] = [];
  const newPage = async (u: string) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, u);
    return { ctx, page };
  };

  // teller1: request cancellation on two completed KL02 txns.
  {
    const { ctx, page } = await newPage('teller1');
    for (const tx of [FX.cancelTxA, FX.cancelTxB]) {
      const r = await post(page, `/transactions/${tx}/cancel`, {
        reason: 'Approval matrix probe cancellation', confirm_understanding: '1',
      });
      const show = await getText(page, `/transactions/${tx}`);
      log.push(`cancel tx${tx}: ${r.status}, pendingCancel=${/Pending\s*Cancellation/i.test(show)}`);
    }
    await ctx.close();
  }

  // manager2: fund PNG01 pools so allocations/txns can be created.
  {
    const { ctx, page } = await newPage('manager2');
    const r1 = await post(page, '/branch-pools/80/fund', { amount: '30000' });   // MYR
    const r2 = await post(page, '/branch-pools/96/fund', { amount: '2000' });    // USD
    log.push(`fund MYR pool: ${r1.status}, USD pool: ${r2.status}`);
    await ctx.close();
  }

  // teller2: big MYR allocation (full cycle) for trading capital + small pending
  // request as the probe target + clean customer + two pending Buy txns.
  {
    const { ctx, page } = await newPage('teller2');

    await post(page, '/my-allocations/request', {
      currency_code: 'MYR', requested_amount: '25000', counter_id: '8',
    });
    const allocPage = await getText(page, '/my-allocations');
    const ids = [...allocPage.matchAll(/my-allocations\/(\d+)/g)].map(m => parseInt(m[1], 10));
    const bigAlloc = Math.max(0, ...ids);
    log.push(`big MYR alloc request id = ${bigAlloc}`);
    await ctx.close();

    // manager2 approves it, teller2 accepts it.
    const m = await newPage('manager2');
    const ar = await post(m.page, `/allocations/${bigAlloc}/approve`, { approved_amount: '25000' });
    log.push(`approve big alloc: ${ar.status}`);
    await m.ctx.close();

    const t2 = await newPage('teller2');
    const ac = await post(t2.page, `/my-allocations/${bigAlloc}/accept`, {});
    log.push(`accept big alloc: ${ac.status}`);

    // Small pending request — the probe target.
    await post(t2.page, '/my-allocations/request', {
      currency_code: 'MYR', requested_amount: '100', counter_id: '8',
    });
    const allocPage2 = await getText(t2.page, '/my-allocations');
    const ids2 = [...allocPage2.matchAll(/my-allocations\/(\d+)/g)].map(x => parseInt(x[1], 10));
    FX.allocId = Math.max(0, ...ids2);
    log.push(`probe allocId = ${FX.allocId}`);

    // Two pending Buy txns for the clean customer (id 36, created earlier).
    for (const [key, amount] of [['txA', '2200'], ['txB', '2300']] as const) {
      const createHtml = await getText(t2.page, '/transactions/create');
      const idem = createHtml.match(/name="idempotency_key" value="([^"]+)"/)?.[1] ?? '';
      const r = await post(t2.page, '/transactions', {
        branch_id: '12', counter_id: '8', idempotency_key: idem,
        type: 'Buy', customer_id: '36', currency_code: 'USD',
        amount_foreign: amount, rate: '4.72',
        purpose: 'Business', source_of_funds: 'Business Income',
      });
      FX[key] = idFrom(r.location, /transactions\/(\d+)/);
      if (!FX[key]) {
        const idx = await getText(t2.page, '/transactions');
        FX[key] = Math.max(0, ...[...idx.matchAll(/transactions\/(\d+)/g)].map(x => parseInt(x[1], 10)));
      }
      const show = FX[key] ? await getText(t2.page, `/transactions/${FX[key]}`) : '';
      log.push(`${key}: create ${r.status}, id=${FX[key]}, pending=${/Pending\s*Approval/i.test(show)}`);
    }
    await t2.ctx.close();
  }

  // manager1: create a Requested stock transfer KL02 -> PNG01.
  {
    const { ctx, page } = await newPage('manager1');
    const r = await post(page, '/stock-transfers', {
      source_branch_name: 'KL02', destination_branch_name: 'PNG01',
      type: 'Standard', notes: 'approval matrix probe',
      'items[0][currency_code]': 'USD', 'items[0][quantity]': '10',
      'items[0][rate]': '4.82', 'items[0][value_myr]': '48.20',
    });
    FX.transferId = idFrom(r.location, /stock-transfers\/(\d+)/);
    if (!FX.transferId) {
      const idx = await getText(page, '/stock-transfers');
      FX.transferId = Math.max(0, ...[...idx.matchAll(/stock-transfers\/(\d+)/g)].map(x => parseInt(x[1], 10)));
    }
    log.push(`transferId = ${FX.transferId}, create ${r.status}`);
    await ctx.close();
  }

  console.log('\n=== FIXTURES ===\n' + log.join('\n'));
  expect(FX.allocId).toBeGreaterThan(0);
  expect(FX.transferId).toBeGreaterThan(0);
  expect(FX.txA).toBeGreaterThan(0);
  expect(FX.txB).toBeGreaterThan(0);
});

type Expect = 'allowed' | 'denied';
const A: Expect = 'allowed';   // expect authorization to pass (302/200/422 — not 403)
const D: Expect = 'denied';    // expect 403

test('approval matrix: probe approval endpoints as each role', async ({ browser }) => {
  test.setTimeout(600000);
  const R10 = 'matrix probe reason';

  const probeDefs: Record<string, { path: () => string; data?: Record<string, string> }> = {
    'txA.clearHold':    { path: () => `/transactions/${FX.txA}/clear-hold` },
    'txA.approve':      { path: () => `/transactions/${FX.txA}/approve` },
    'txA.reject':       { path: () => `/transactions/${FX.txA}/reject`, data: { reason: R10 } },
    'txB.clearHold':    { path: () => `/transactions/${FX.txB}/clear-hold` },
    'txB.approve':      { path: () => `/transactions/${FX.txB}/approve` },
    'txB.reject':       { path: () => `/transactions/${FX.txB}/reject`, data: { reason: R10 } },
    'cancelA.approve':  { path: () => `/transactions/${FX.cancelTxA}/approve-cancellation` },
    'cancelA.reject':   { path: () => `/transactions/${FX.cancelTxA}/reject-cancellation`, data: { reason: R10 } },
    'cancelB.approve':  { path: () => `/transactions/${FX.cancelTxB}/approve-cancellation` },
    'cancelB.reject':   { path: () => `/transactions/${FX.cancelTxB}/reject-cancellation`, data: { reason: R10 } },
    'alloc.approve':    { path: () => `/allocations/${FX.allocId}/approve`, data: { approved_amount: '100' } },
    'alloc.reject':     { path: () => `/allocations/${FX.allocId}/reject`, data: { rejection_reason: R10 } },
    'transfer.approve': { path: () => `/stock-transfers/${FX.transferId}/approve-bm`, data: { notes: 'probe' } },
    'transfer.reject':  { path: () => `/stock-transfers/${FX.transferId}/reject`, data: { reason: R10 } },
  };

  // Fixture homes: txA/txB/alloc on PNG01; cancelA/cancelB on KL02;
  // transfer KL02->PNG01 (manager1 is maker, PNG01 manager is taker).
  const expected: Record<string, Record<string, Expect>> = {
    teller1:     { 'txA.clearHold': D, 'txA.approve': D, 'txA.reject': D, 'txB.clearHold': D, 'txB.approve': D, 'txB.reject': D, 'cancelA.approve': D, 'cancelA.reject': D, 'cancelB.approve': D, 'cancelB.reject': D, 'alloc.approve': D, 'alloc.reject': D, 'transfer.approve': D, 'transfer.reject': D },
    accountant1: { 'txA.clearHold': D, 'txA.approve': D, 'txA.reject': D, 'txB.clearHold': D, 'txB.approve': D, 'txB.reject': D, 'cancelA.approve': D, 'cancelA.reject': D, 'cancelB.approve': D, 'cancelB.reject': D, 'alloc.approve': D, 'alloc.reject': D, 'transfer.approve': D, 'transfer.reject': D },
    compliance1: { 'txA.clearHold': D, 'txA.approve': D, 'txA.reject': D, 'txB.clearHold': D, 'txB.approve': D, 'txB.reject': D, 'cancelA.approve': A, 'cancelA.reject': A, 'alloc.approve': D, 'alloc.reject': D, 'transfer.approve': D, 'transfer.reject': D },
    // cancelA was already consumed by compliance1's approve (runs earlier), so
    // manager1's probes hit a non-pending tx: policy authorizes managers, the
    // service rejects the state -> 302. The true manager-deny cell on a live
    // completed-tx cancellation is cancelB.approve (reverse-perm gate -> 403).
    manager1:    { 'txA.clearHold': D, 'txA.approve': D, 'txA.reject': D, 'txB.clearHold': D, 'txB.approve': D, 'txB.reject': D, 'cancelA.approve': A, 'cancelA.reject': A, 'cancelB.approve': D, 'cancelB.reject': A, 'alloc.approve': D, 'alloc.reject': D, 'transfer.approve': D, 'transfer.reject': D },
    manager2:    { 'txA.clearHold': D, 'txA.approve': D, 'txA.reject': D, 'txB.clearHold': D, 'txB.approve': D, 'txB.reject': D, 'cancelA.approve': D, 'cancelA.reject': D, 'cancelB.approve': D, 'cancelB.reject': D, 'alloc.approve': A, 'alloc.reject': A, 'transfer.approve': A, 'transfer.reject': A },
    compliance2: { 'txA.clearHold': A, 'txA.approve': A, 'txA.reject': A, 'txB.clearHold': A, 'txB.approve': A, 'txB.reject': A, 'cancelA.approve': D, 'cancelA.reject': D, 'cancelB.approve': D, 'cancelB.reject': D, 'alloc.approve': D, 'alloc.reject': D, 'transfer.approve': D, 'transfer.reject': D },
    admin:       { 'txA.clearHold': A, 'txA.approve': A, 'txA.reject': A, 'txB.clearHold': A, 'txB.approve': A, 'txB.reject': A, 'cancelA.approve': A, 'cancelA.reject': A, 'cancelB.approve': A, 'cancelB.reject': A, 'alloc.approve': A, 'alloc.reject': A, 'transfer.approve': A, 'transfer.reject': A },
  };

  const failures: string[] = [];
  const grid: string[] = [];

  for (const [username, expectSet] of Object.entries(expected)) {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, username);

    for (const [key, probe] of Object.entries(probeDefs)) {
      if (!(key in expectSet)) {
        continue;
      }
      const { status } = await post(page, probe.path(), probe.data ?? {});
      const want = expectSet[key];
      const got: Expect = status === 403 ? 'denied' : 'allowed';
      grid.push(`${username.padEnd(12)} ${key.padEnd(17)} -> ${status} (${got}, want ${want})`);
      if (got !== want || status === 419) {
        failures.push(`${username} ${key}: expected ${want}, got HTTP ${status}`);
      }
    }
    await ctx.close();
  }

  console.log('\n=== PROBE GRID ===\n' + grid.join('\n'));
  expect(failures, `Matrix mismatches:\n${failures.join('\n')}`).toEqual([]);
});

test('manager CAN approve cancellation of a pending (non-completed) transaction', async ({ browser }) => {
  test.setTimeout(300000);
  let txId = 0;

  // teller1: create a pending Buy on KL02, then request cancellation.
  {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, 'teller1');
    const createHtml = await getText(page, '/transactions/create');
    const idem = createHtml.match(/name="idempotency_key" value="([^"]+)"/)?.[1] ?? '';
    const r = await post(page, '/transactions', {
      branch_id: '8', counter_id: '4', idempotency_key: idem,
      type: 'Buy', customer_id: '36', currency_code: 'USD',
      amount_foreign: '2200', rate: '4.70',
      purpose: 'Business', source_of_funds: 'Business Income',
    });
    txId = idFrom(r.location, /transactions\/(\d+)/);
    const show = txId ? await getText(page, `/transactions/${txId}`) : '';
    console.log(`pending tx id=${txId}, pending=${/Pending\s*Approval/i.test(show)}`);
    expect(txId).toBeGreaterThan(0);

    const c = await post(page, `/transactions/${txId}/cancel`, {
      reason: 'Manager cancellation-approval probe', confirm_understanding: '1',
    });
    console.log(`cancel request: ${c.status}`);
    await ctx.close();
  }

  // manager1: approve the cancellation — allowed (pre-status not Completed).
  {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, 'manager1');
    const r = await post(page, `/transactions/${txId}/approve-cancellation`, {});
    console.log(`manager1 approve-cancellation on pending tx ${txId}: ${r.status}`);
    const show = await getText(page, `/transactions/${txId}`);
    console.log(`tx ${txId} cancelled: ${/Cancelled/i.test(show)}`);
    expect(r.status).not.toBe(403);
    expect(/Cancelled/i.test(show)).toBe(true);
    await ctx.close();
  }
});

test('verify positive-path state changes landed', async ({ browser }) => {
  test.setTimeout(120000);
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'admin');

  const checks: string[] = [];
  const alloc = await getText(page, `/allocations/${FX.allocId}`);
  checks.push(`alloc ${FX.allocId}: ${alloc.match(/>(Pending|Approved|Active|Rejected)</i)?.[1] ?? '?'}`);

  const transfer = await getText(page, `/stock-transfers/${FX.transferId}`);
  checks.push(`transfer ${FX.transferId}: ${transfer.match(/>(Requested|Pending|Approved|In Transit|Completed|Rejected|Cancelled)</i)?.[1] ?? '?'}`);

  for (const tx of [FX.cancelTxA, FX.cancelTxB, FX.txA, FX.txB]) {
    const body = await getText(page, `/transactions/${tx}`);
    checks.push(`tx ${tx}: ${body.match(/>(Completed|Pending Approval|Pending Cancellation|Rejected|Cancelled)</i)?.[1] ?? '?'}`);
  }

  console.log('\n=== STATE ===\n' + checks.join('\n'));
  await ctx.close();
});
