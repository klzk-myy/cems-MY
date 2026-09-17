import { test, expect, Page } from '@playwright/test';

const BASE_URL = 'http://local.host';
const PASSWORD = 'Password123!';

// Roles -> seeded users. KL02 users: teller1/manager1/compliance1. HQ: admin/accountant1. PNG01: manager2/teller2/compliance2.
const USERS: Record<string, string> = {
  admin: 'admin',
  teller: 'teller1',
  manager: 'manager1',
  compliance: 'compliance1',
  accountant: 'accountant1',
};

const ALL = ['admin', 'teller', 'manager', 'compliance', 'accountant'];
const MGR = ['admin', 'manager'];
const COMP = ['admin', 'compliance'];
const ACCT = ['admin', 'manager', 'accountant'];
const ADMIN_ONLY = ['admin'];
// Permission-based routing means surfaces follow grants, not role names:
// admin is matrix-exempt; teller holds create_transactions/request_stock/
// operate_counters; compliance holds access_compliance/view_reports;
// accountant holds access_accounting/view_reports.
const TRADE = ['admin', 'teller', 'manager'];            // operate_counters|manage_counters
const TXN_VIEW = ['admin', 'teller', 'manager', 'compliance']; // create|manage|approve_transactions
const CUST_VIEW = ['admin', 'teller', 'manager', 'compliance']; // create_transactions|manage_customers|access_compliance
const CREATE_TX = ['admin', 'teller'];                   // create_transactions
const REQ_STOCK = ['admin', 'teller'];                   // request_stock
const REPORTS = ['admin', 'manager', 'compliance', 'accountant']; // view_reports
const RISK_DASH = ['admin', 'manager', 'compliance'];    // view_risk_dashboard
const COA_VIEW = ['admin', 'manager', 'compliance', 'accountant']; // access_compliance|access_accounting
const MAPPINGS = ['admin', 'accountant'];                // manage_account_mappings

// [path, roles allowed (expect 200), note]
// Denied roles are expected to get 403. Transaction 36 and counter C01 belong to KL02 (branch 8).
const MATRIX: [string, string[], string?][] = [
  ['/', ALL],
  ['/dashboard', ALL],
  ['/performance', MGR],
  ['/rates', MGR],

  // Transactions — index/show accept create|manage|approve_transactions;
  // wizard/create are create_transactions only (teller + exempt admin).
  ['/transactions', TXN_VIEW],
  ['/transactions/wizard', CREATE_TX],
  ['/transactions/create', CREATE_TX],
  ['/transactions/batch-upload', MGR],
  ['/transactions/dlq', ADMIN_ONLY],
  ['/transactions/export', MGR],
  ['/transactions/36', TXN_VIEW, 'KL02 txn — accountant (HQ) must 403'],

  // Customers (company-wide) — index is gated; create/show are auth-only
  ['/customers', CUST_VIEW],
  ['/customers/create', ALL],
  ['/customers/4', ALL],

  // Counters — operate_counters|manage_counters on index/ops, manage on create/close
  ['/counters', TRADE],
  ['/counters/create', MGR],
  ['/counters/C01/open', TRADE, 'KL02 counter'],
  ['/counters/C01/status', TRADE],
  ['/counters/C01/history', TRADE],
  ['/counters/C01/close', MGR],

  // Stock & cash — manage_stock / manage_allocations / request_stock
  ['/stock-cash', MGR],
  ['/stock-cash/reconciliation', MGR],
  ['/stock-cash/till-report', MGR],
  ['/allocations', MGR],
  ['/my-allocations', REQ_STOCK],
  ['/my-allocations/request', REQ_STOCK],
  ['/branch-pools', MGR],
  ['/eod', MGR],
  ['/stock-transfers', MGR],
  ['/stock-transfers/create', MGR],

  // Compliance surfaces — access_compliance; risk dashboard is view_risk_dashboard
  ['/compliance', COMP],
  ['/compliance/alerts', COMP],
  ['/compliance/cases', COMP],
  ['/compliance/sanctions', COMP],
  ['/compliance/sanctions/entries', COMP],
  ['/compliance/screening-matches', COMP],
  ['/compliance/findings', COMP],
  ['/compliance/edd-review', COMP],
  ['/compliance/pep-approvals', COMP],
  ['/compliance/risk-dashboard', RISK_DASH],
  ['/compliance/risk-dashboard/trends', RISK_DASH],
  ['/compliance/unified', COMP],
  ['/str', COMP],

  // Accounting — access_accounting group; mappings add manage_account_mappings
  ['/accounting', ACCT],
  ['/accounting/journal', ACCT],
  ['/accounting/journal/create', ACCT],
  ['/accounting/ledger', ACCT],
  ['/accounting/trial-balance', ACCT],
  ['/accounting/profit-loss', ACCT],
  ['/accounting/balance-sheet', ACCT],
  ['/accounting/cash-flow', ACCT],
  ['/accounting/ratios', ACCT],
  ['/accounting/periods', ACCT],
  ['/accounting/fiscal-years', ACCT],
  ['/accounting/revaluation', ACCT],
  ['/accounting/reconciliation', ACCT],
  ['/accounting/budget', ACCT],
  ['/accounting/expenses', ACCT],
  ['/accounting/expenses/create', ACCT],
  ['/accounting/mappings', MAPPINGS],
  ['/closing', MGR],
  ['/accounting/chart-of-accounts', COA_VIEW],

  // Reports — view_reports
  ['/reports', REPORTS],
  ['/reports/msb2', REPORTS],
  ['/reports/lmca', REPORTS],
  ['/reports/quarterly-lvr', REPORTS],
  ['/reports/position-limit', REPORTS],
  ['/reports/monthly-trends', REPORTS],
  ['/reports/profitability', REPORTS],
  ['/reports/customer-analysis', REPORTS],
  ['/reports/compliance-summary', REPORTS],
  ['/reports/schedules', ADMIN_ONLY],

  // Users & admin surfaces
  ['/users', MGR],
  ['/users/create', MGR],
  ['/users/8', MGR, 'teller1 profile: admin+same-branch manager only'],
  ['/admin/role-permissions', ADMIN_ONLY],
  ['/branches', MGR],
  ['/branches/create', ADMIN_ONLY],
  ['/system/currencies', ADMIN_ONLY],
  ['/system/currencies/create', ADMIN_ONLY],
  ['/system/alerts', ADMIN_ONLY],
  ['/admin/audit-logs', COMP],
  ['/test-results', ADMIN_ONLY],
  ['/notifications/preferences', ALL],
];

async function login(page: Page, username: string) {
  for (let attempt = 0; attempt < 3; attempt++) {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input#username', username);
    await page.fill('input#password', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(800);

    // Login is throttled 5/min per IP — a 429 leaves us on /login.
    if (!page.url().includes('/login')) {
      return;
    }
    await page.waitForTimeout(12000 * (attempt + 1));
  }
  throw new Error(`Login failed for ${username} — still on ${page.url()}`);
}

for (const [role, username] of Object.entries(USERS)) {
  test(`RBAC matrix — ${role} (${username})`, async ({ page }) => {
    test.setTimeout(300000);
    await login(page, username);

    const failures: string[] = [];
    const seen: string[] = [];

    for (const [path, allowed, note] of MATRIX) {
      const resp = await page.goto(`${BASE_URL}${path}`, {
        waitUntil: 'domcontentloaded',
        timeout: 20000,
      }).catch(e => null);
      const status = resp ? resp.status() : -1;
      seen.push(`${status} ${path}`);

      if (allowed.includes(role)) {
        if (status !== 200) {
          failures.push(`${path} expected 200 got ${status}${note ? ` (${note})` : ''}`);
        }
      } else {
        if (status !== 403) {
          failures.push(`${path} expected 403 got ${status}${note ? ` (${note})` : ''}`);
        }
      }
    }

    console.log(`\n=== ${role} (${username}) ===`);
    console.log(seen.join('\n'));
    expect(failures, `RBAC mismatches for ${role}:\n${failures.join('\n')}`).toEqual([]);
  });
}

// Cross-branch isolation: PNG01 staff must not see KL02 resources.
// One login per user (login POST is throttled 5/min per IP).
test('Cross-branch isolation — PNG01 users blocked from KL02 resources', async ({ browser }) => {
  test.setTimeout(300000);
  const cases: [string, string[], string][] = [
    // [username, [paths], why]
    ['manager2', ['/transactions/36', '/counters/C01/status', '/counters/C01/close'], 'manager2 (PNG01) on KL02 resources'],
    ['teller2', ['/transactions/36', '/counters/C01/open'], 'teller2 (PNG01) on KL02 resources'],
    ['compliance2', ['/transactions/36'], 'compliance2 (PNG01) on KL02 transaction'],
    ['accountant1', ['/transactions/36'], 'accountant1 (HQ) on KL02 transaction'],
  ];

  const failures: string[] = [];
  for (const [username, paths, why] of cases) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, username);
    for (const path of paths) {
      const resp = await page.goto(`${BASE_URL}${path}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
      const status = resp ? resp.status() : -1;
      console.log(`${username} -> ${path}: ${status}`);
      // Redirect to /login = not actually authenticated — flag separately.
      if (page.url().includes('/login')) {
        failures.push(`${why} ${path}: bounced to /login (auth lost)`);
      } else if (status === 200) {
        failures.push(`${why} ${path}: expected 403/404, got 200`);
      }
    }
    await context.close();
  }
  expect(failures).toEqual([]);
});
