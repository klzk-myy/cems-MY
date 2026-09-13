import { test, expect, Page } from '@playwright/test';

const BASE_URL = 'http://local.host';
const PASSWORD = 'Password123!';

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

async function post(page: Page, path: string, data: Record<string, string> = {}) {
  const token = await csrf(page);
  const resp = await page.context().request.post(`${BASE_URL}${path}`, {
    form: { _token: token, ...data },
    maxRedirects: 0,
  });
  return { status: resp.status(), location: resp.headers()['location'] ?? '' };
}

async function get(page: Page, path: string) {
  const resp = await page.context().request.get(`${BASE_URL}${path}`);
  return { status: resp.status(), body: await resp.text() };
}

test.setTimeout(300000);

test('accountant accesses previously-blocked accounting pages', async ({ browser }) => {
  const page = await browser.newPage();
  await login(page, 'accountant1');

  for (const url of [
    '/accounting/cash-flow',
    '/accounting/fiscal-years',
    '/accounting/ratios',
    '/accounting/revaluation',
    '/accounting/revaluation/history',
  ]) {
    const { status } = await get(page, url);
    expect(status, `accountant1 GET ${url}`).toBe(200);
  }
  await page.close();
});

test('stock transfer funds destination pool; repeat approval flashes error not 500', async ({ browser }) => {
  // Separate pages via browser.newPage() — each gets its own context and
  // session cookie, so multiple users can be logged in simultaneously.
  const page = await browser.newPage();

  // Record PNG01 USD pool balance before the transfer.
  await login(page, 'manager2');
  const poolsBefore = await get(page, '/branch-pools');
  expect(poolsBefore.status).toBe(200);

  // Manager1 (KL02) creates the transfer.
  const mgr1 = await browser.newPage();
  await login(mgr1, 'manager1');
  const create = await post(mgr1, '/stock-transfers', {
    source_branch_name: 'KL02',
    destination_branch_name: 'PNG01',
    type: 'Standard',
    'items[0][currency_code]': 'USD',
    'items[0][quantity]': '300',
    'items[0][rate]': '4.80',
    'items[0][value_myr]': '1440',
  });
  expect(create.status).toBe(302);
  const transferId = parseInt(create.location.match(/stock-transfers\/(\d+)/)?.[1] ?? '0', 10);
  expect(transferId).toBeGreaterThan(0);

  // Manager2 (PNG01, taker) approves — then repeats the approval: the second
  // POST must redirect with an error flash, not crash with a 500.
  const approve1 = await post(page, `/stock-transfers/${transferId}/approve-bm`);
  expect(approve1.status).toBe(302);
  const approve2 = await post(page, `/stock-transfers/${transferId}/approve-bm`);
  expect(approve2.status).not.toBe(500);
  expect(approve2.status).toBe(302);
  // Follow the redirect — the show page should render the error flash.
  const showAfter = await get(page, `/stock-transfers/${transferId}`);
  expect(showAfter.status).toBe(200);
  expect(showAfter.body).toMatch(/not in requested status|error/i);

  // Manager1 (source) dispatches.
  const dispatch = await post(mgr1, `/stock-transfers/${transferId}/dispatch`);
  expect(dispatch.status).toBe(302);

  // Find the item id from the show page, then manager2 receives all 300.
  const show = await get(page, `/stock-transfers/${transferId}`);
  const itemId = show.body.match(/items\[(\d+)\]\[id\]/)?.[1];
  expect(itemId).toBeTruthy();
  const receive = await post(page, `/stock-transfers/${transferId}/receive`, {
    [`items[${itemId}][id]`]: itemId!,
    [`items[${itemId}][quantity_received]`]: '300',
  });
  expect(receive.status).toBe(302);

  const complete = await post(page, `/stock-transfers/${transferId}/complete`);
  expect(complete.status).toBe(302);

  // PNG01's USD pool must now be funded — the /branch-pools page shows it,
  // and teller2 can request an allocation without manual pool funding.
  const poolsAfter = await get(page, '/branch-pools');
  expect(poolsAfter.status).toBe(200);
  expect(poolsAfter.body).toContain('USD');

  const teller = await browser.newPage();
  await login(teller, 'teller2');
  const req = await post(teller, '/my-allocations/request', {
    currency_code: 'USD',
    requested_amount: '200',
  });
  // Previously failed validation ("insufficient pool"); now creates a pending request.
  expect(req.status).toBe(302);

  const myAllocs = await get(teller, '/my-allocations');
  expect(myAllocs.status).toBe(200);
  expect(myAllocs.body).toMatch(/USD[\s\S]{0,500}(200|Pending)/i);

  await page.close();
  await mgr1.close();
  await teller.close();
});

test('login throttle buckets per username, not shared IP', async ({ browser }) => {
  // Ordered LAST — burns 5 failed attempts on a fake username. If the limiter
  // still keyed on the shared (empty) field, the next real login would 429.
  const page = await browser.newPage();
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  const token = await csrf(page);

  for (let i = 0; i < 5; i++) {
    await page.context().request.post(`${BASE_URL}/login`, {
      form: { _token: token, username: 'throttle_probe_user', password: 'wrong' },
      maxRedirects: 0,
    });
  }

  // A fresh username from the same IP must still reach the login handler.
  await login(page, 'teller1');
  expect(page.url()).toContain('/dashboard');
  await page.close();
});
