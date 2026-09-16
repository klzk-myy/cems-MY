import { test, expect, Page, Browser } from '@playwright/test';

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

const ACCOUNTING_PAGES = [
  '/accounting',
  '/accounting/journal',
  '/accounting/journal/create',
  '/accounting/expenses',
  '/accounting/ledger',
  '/accounting/trial-balance',
  '/accounting/profit-loss',
  '/accounting/balance-sheet',
  '/accounting/cash-flow',
  '/accounting/ratios',
  '/accounting/periods',
  '/accounting/fiscal-years',
  '/accounting/revaluation',
  '/accounting/revaluation/history',
  '/accounting/reconciliation',
  '/accounting/reconciliation/report?account_code=1100&from=2026-01-01&to=2026-12-31',
  '/accounting/reconciliation/export?account_code=1100&from=2026-01-01&to=2026-12-31',
  '/accounting/budget',
];

// Pages that must render live data. Marker = a string that only exists if the
// view actually renders the controller-provided data (not hardcoded mockup).
const LIVE_DATA_CHECKS: Array<{ path: string; marker: RegExp; label: string }> = [
  { path: '/accounting/fiscal-years', marker: /FY2026/, label: 'fiscal year list' },
  { path: '/accounting/journal', marker: /JE-\d{6}-\d+/i, label: 'journal entry numbers' },
  { path: '/accounting/trial-balance', marker: /Trial Balance is balanced|Cash \(MYR\)/i, label: 'trial balance data' },
  { path: '/accounting/periods', marker: /2026-0[89]|2026-10/, label: 'real accounting periods' },
  { path: '/accounting/reconciliation', marker: /reconciliation_id|bank_reconcil|1100|1000/i, label: 'reconciliation accounts' },
  { path: '/accounting/revaluation', marker: /USD|EUR|positions/i, label: 'currency positions' },
  { path: '/accounting/revaluation/history', marker: /revaluation|USD|EUR/i, label: 'revaluation history' },
  { path: '/accounting/cash-flow', marker: /cash.flow|operating/i, label: 'cash flow data' },
  { path: '/accounting/ratios', marker: /ratio|current|quick/i, label: 'ratio data' },
  { path: '/accounting/budget', marker: /6200|5100|budget_amount|variance/i, label: 'budget data' },
];

test('accountant: all accounting GET pages return 200', async ({ browser }) => {
  const page = await browser.newPage();
  await login(page, 'accountant1');

  const failures: string[] = [];
  for (const url of ACCOUNTING_PAGES) {
    const { status, body } = await get(page, url);
    const hasError = /Server Error|Something went wrong|Whoops/i.test(body);
    if (status !== 200 || hasError) {
      failures.push(`${url} -> ${status}${hasError ? ' (error page)' : ''}`);
    }
  }
  expect(failures, 'accounting pages failing for accountant').toEqual([]);
  await page.close();
});

test('accounting pages render live data, not hardcoded stubs', async ({ browser }) => {
  const page = await browser.newPage();
  await login(page, 'accountant1');

  const stubPages: string[] = [];
  for (const check of LIVE_DATA_CHECKS) {
    const { status, body } = await get(page, check.path);
    if (status !== 200) {
      stubPages.push(`${check.path} -> HTTP ${status}`);
      continue;
    }
    if (!check.marker.test(body)) {
      stubPages.push(`${check.path} missing ${check.label}`);
    }
  }
  // Every entry here is a stub/broken view — reported as findings.
  expect(stubPages, 'pages not rendering live data').toEqual([]);
  await page.close();
});

test('journal lifecycle: create, unbalanced rejection, reversal, re-reversal guard', async ({ browser }) => {
  const acct = await browser.newPage();
  await login(acct, 'accountant1');

  // Create a balanced manual journal (accountant is HQ-stamped).
  const create = await post(acct, '/accounting/journal', {
    entry_date: '2026-09-13',
    description: 'Audit test — office supplies reclass',
    'lines[0][account_code]': '6213',
    'lines[0][debit]': '123.45',
    'lines[0][credit]': '0',
    'lines[0][description]': 'Dr Office Supplies',
    'lines[1][account_code]': '1050',
    'lines[1][debit]': '0',
    'lines[1][credit]': '123.45',
    'lines[1][description]': 'Cr Petty Cash',
  });
  expect(create.status).toBe(302);
  const entryId = parseInt(create.location.match(/journal\/(\d+)/)?.[1] ?? '0', 10);
  expect(entryId).toBeGreaterThan(0);

  const show = await get(acct, `/accounting/journal/${entryId}`);
  expect(show.status).toBe(200);
  expect(show.body).toContain('Posted');
  expect(show.body).toContain('123.45');

  // Unbalanced entry must be rejected with a form error, not a 500.
  const unbalanced = await post(acct, '/accounting/journal', {
    entry_date: '2026-09-13',
    description: 'Audit test — unbalanced',
    'lines[0][account_code]': '6213',
    'lines[0][debit]': '100',
    'lines[0][credit]': '0',
    'lines[1][account_code]': '1050',
    'lines[1][debit]': '0',
    'lines[1][credit]': '50',
  });
  expect(unbalanced.status).not.toBe(500);
  expect(unbalanced.status).toBe(302);

  // Accountant holds manage_accounting — reversal succeeds.
  const acctReverse = await post(acct, `/accounting/journal/${entryId}/reverse`, {
    reason: 'Audit test reversal',
  });
  expect(acctReverse.status).toBe(302);
  const showAfter = await get(acct, `/accounting/journal/${entryId}`);
  expect(showAfter.body).toContain('Reversed');

  // Re-reversal must flash an error, not crash or double-reverse.
  const admin = await browser.newPage();
  await login(admin, 'admin');
  const rev2 = await post(admin, `/accounting/journal/${entryId}/reverse`, {
    reason: 'second attempt',
  });
  expect(rev2.status).toBe(302);
  const showAfter2 = await get(admin, `/accounting/journal/${entryId}`);
  expect(showAfter2.body).toMatch(/already reversed/i);

  await acct.close();
  await admin.close();
});

test('expense workflow: manager posts, float enforced, admin funds', async ({ browser }) => {
  // Accountant holds post_expenses — the expense form is accessible.
  const acct = await browser.newPage();
  await login(acct, 'accountant1');
  const acctCreate = await get(acct, '/accounting/expenses/create');
  expect(acctCreate.status).toBe(200);
  await acct.close();

  // Manager2 (PNG01): an expense exceeding the petty-cash float must be
  // rejected. Amount is deliberately huge so the test stays valid across
  // re-runs after the float has been funded.
  const mgr2 = await browser.newPage();
  await login(mgr2, 'manager2');
  const denied = await post(mgr2, '/accounting/expenses', {
    account_code: '6205',
    category: 'Utilities',
    description: 'Audit test — exceeds float',
    amount: '999999.00',
    expense_date: '2026-09-13',
  });
  expect(denied.status).toBe(302);
  const idxAfterDeny = await get(mgr2, '/accounting/expenses');
  expect(idxAfterDeny.body).not.toContain('exceeds float');

  // Admin funds PNG01 petty cash RM 500.
  const admin = await browser.newPage();
  await login(admin, 'admin');
  const png = await get(admin, '/accounting/expenses/create');
  const pngBranchId = png.body.match(/option[^>]*value="(\d+)"[^>]*>\s*Penang|value="(\d+)"[^>]*>\s*PNG01/i);
  const branchId = pngBranchId?.[1] ?? pngBranchId?.[2];
  expect(branchId, 'PNG01 branch option on expense create').toBeTruthy();
  const fund = await post(admin, '/accounting/expenses/fund', {
    branch_id: branchId!,
    amount: '500',
    description: 'Audit test funding',
  });
  expect(fund.status).toBe(302);

  // Manager2 now posts the expense successfully.
  const ok = await post(mgr2, '/accounting/expenses', {
    account_code: '6205',
    category: 'Utilities',
    description: 'Audit test — PNG01 expense',
    amount: '25.00',
    expense_date: '2026-09-13',
  });
  expect(ok.status).toBe(302);
  const idx = await get(mgr2, '/accounting/expenses');
  expect(idx.body).toContain('PNG01 expense');

  await mgr2.close();
  await admin.close();
});

test('fiscal year: create, duplicate rejection, close confirm guard', async ({ browser }) => {
  const acct = await browser.newPage();
  await login(acct, 'accountant1');

  const create = await post(acct, '/accounting/fiscal-years', {
    year_code: 'FY2027',
    year: '2027',
  });
  expect(create.status).toBe(302);
  const list = await get(acct, '/accounting/fiscal-years');
  expect(list.body).toContain('FY2027');

  // Duplicate year code must be rejected — count table ROWS for FY2027 only
  // ("FY FY2027</td>" appears once per year row; flash messages don't match).
  const dup = await post(acct, '/accounting/fiscal-years', {
    year_code: 'FY2027',
    year: '2027',
  });
  expect(dup.status).toBe(302); // back with validation error — not created twice
  const list2 = await get(acct, '/accounting/fiscal-years');
  expect(list2.body.match(/FY FY2027<\/td>/g)?.length ?? 0).toBe(1);

  // Closing with a wrong confirm code must be refused. The fiscal-years view
  // exposes no close form (dead buttons — audit finding), so the POST targets
  // FY2026 (id 4) directly; a wrong code must not close it.
  const badClose = await post(acct, '/accounting/fiscal-years/4/close', {
    confirm_code: 'WRONG',
  });
  expect(badClose.status).toBe(302);
  const list3 = await get(acct, '/accounting/fiscal-years');
  // FY2026 must still be Open — not closed silently.
  const fy2026Row = list3.body.match(/FY2026[\s\S]{0,600}?Open/);
  expect(fy2026Row, 'FY2026 still Open after bad confirm code').toBeTruthy();

  await acct.close();
});

test('revaluation run + history (accountant)', async ({ browser }) => {
  const page = await browser.newPage();
  await login(page, 'accountant1');

  const run = await post(page, '/accounting/revaluation/run');
  expect(run.status).toBe(302);

  const history = await get(page, '/accounting/revaluation/history');
  expect(history.status).toBe(200);
  await page.close();
});

test('budget store endpoint accepts posting (accountant)', async ({ browser }) => {
  const page = await browser.newPage();
  await login(page, 'accountant1');

  const store = await post(page, '/accounting/budget', {
    period_code: '2026-09',
    'budgets[0][account_code]': '6205',
    'budgets[0][amount]': '5000',
  });
  expect(store.status).toBe(302);
  await page.close();
});

test('reconciliation import + report endpoints (accountant)', async ({ browser }) => {
  const page = await browser.newPage();
  await login(page, 'accountant1');

  const imp = await post(page, '/accounting/reconciliation/import', {
    account_code: '1100',
    'lines[0][date]': '2026-09-13',
    'lines[0][reference]': 'AUDIT-TEST-1',
    'lines[0][description]': 'Audit test bank line',
    'lines[0][debit]': '100.00',
    'lines[0][credit]': '',
  });
  expect(imp.status).toBe(302);

  const report = await get(page, '/accounting/reconciliation/report?account_code=1100&from=2026-09-01&to=2026-09-30');
  expect(report.status).toBe(200);
  const exp = await get(page, '/accounting/reconciliation/export?account_code=1100&from=2026-09-01&to=2026-09-30');
  expect(exp.status).toBe(200);
  await page.close();
});

test('RBAC denials: teller & compliance blocked from accounting', async ({ browser }) => {
  const teller = await browser.newPage();
  await login(teller, 'teller1');
  for (const url of ['/accounting', '/accounting/journal', '/accounting/trial-balance', '/accounting/fiscal-years']) {
    const { status } = await get(teller, url);
    expect(status, `teller1 ${url}`).not.toBe(200);
  }
  await teller.close();

  const comp = await browser.newPage();
  await login(comp, 'compliance1');
  for (const url of ['/accounting', '/accounting/journal', '/accounting/trial-balance']) {
    const { status } = await get(comp, url);
    expect(status, `compliance1 ${url}`).not.toBe(200);
  }
  // Exception: compliance CAN view the read-only chart of accounts.
  const coa = await get(comp, '/accounting/chart-of-accounts');
  expect(coa.status).toBe(200);
  await comp.close();
});

test('period close via endpoint + closed-period posting blocked', async ({ browser }) => {
  const acct = await browser.newPage();
  await login(acct, 'accountant1');

  // Close the August 2026 period (id from staging DB; period list page is a
  // hardcoded stub so the id cannot be scraped from the UI). ClosePeriodRequest
  // requires period_id + closure_date + reason.
  const close = await post(acct, '/accounting/periods/8/close', {
    period_id: '8',
    closure_date: '2026-08-31',
    reason: 'Audit test — closing August period',
  });
  expect(close.status).toBe(302);
  // Success redirects to the periods index; an already-closed period (re-runs)
  // redirects back(). Either way the backdated-posting check below is the
  // real enforcement assertion.
  expect(close.location).not.toContain('/journal/');

  // Posting a journal dated inside the closed period must be rejected.
  const backdated = await post(acct, '/accounting/journal', {
    entry_date: '2026-08-15',
    description: 'Audit test — closed period posting',
    'lines[0][account_code]': '6213',
    'lines[0][debit]': '10',
    'lines[0][credit]': '0',
    'lines[1][account_code]': '1050',
    'lines[1][debit]': '0',
    'lines[1][credit]': '10',
  });
  expect(backdated.status).toBe(302);
  // Redirect goes back to create with errors — entry must NOT exist.
  expect(backdated.location).not.toMatch(/journal\/\d+/);

  await acct.close();
});
