import { test, expect, Page } from '@playwright/test';

const BASE_URL = 'http://local.host';
const PASSWORD = 'Password123!';
const MAX_PAGES = 220;

// Seed the crawl with the full known GET surface so pages not reachable by
// links (e.g. detail pages) are still exercised.
const SEED_PATHS = [
  '/', '/dashboard', '/performance', '/rates',
  '/transactions', '/transactions/wizard', '/transactions/create',
  '/transactions/batch-upload', '/transactions/dlq', '/transactions/export',
  '/transactions/60', '/transactions/64', '/transactions/84', '/transactions/60/receipt',
  '/customers', '/customers/create', '/customers/4', '/customers/32',
  '/counters', '/counters/create', '/counters/C01/status',
  '/counters/C01/history', '/counters/C01/open',
  '/stock-cash', '/stock-cash/reconciliation', '/stock-cash/till-report',
  '/allocations', '/allocations/12', '/my-allocations', '/my-allocations/request',
  '/branch-pools', '/eod',
  '/stock-transfers', '/stock-transfers/create', '/stock-transfers/20', '/stock-transfers/24',
  '/compliance/sanctions/entries/4', '/compliance/sanctions/entries/4/edit',
  '/compliance', '/compliance/alerts', '/compliance/cases', '/compliance/sanctions',
  '/compliance/sanctions/entries', '/compliance/sanctions/import-logs',
  '/compliance/screening-matches', '/compliance/findings', '/compliance/edd-review',
  '/compliance/pep-approvals', '/compliance/risk-dashboard',
  '/compliance/risk-dashboard/trends', '/compliance/unified', '/compliance/flagged',
  '/str',
  '/accounting', '/accounting/journal', '/accounting/journal/create',
  '/accounting/journal/64', '/accounting/ledger', '/accounting/trial-balance',
  '/accounting/profit-loss', '/accounting/balance-sheet', '/accounting/cash-flow',
  '/accounting/ratios', '/accounting/periods', '/accounting/fiscal-years',
  '/accounting/revaluation', '/accounting/reconciliation', '/accounting/budget',
  '/accounting/expenses', '/accounting/expenses/create', '/accounting/chart-of-accounts',
  '/reports', '/reports/msb2', '/reports/lmca', '/reports/quarterly-lvr',
  '/reports/position-limit', '/reports/monthly-trends', '/reports/profitability',
  '/reports/customer-analysis', '/reports/compliance-summary', '/reports/schedules',
  '/users', '/users/create', '/users/8',
  '/branches', '/branches/create', '/branches/8/edit',
  '/system/currencies', '/system/currencies/create', '/system/alerts',
  '/admin/audit-logs', '/admin/role-permissions', '/test-results',
  '/notifications/preferences', '/mfa/setup',
];

// Paths that legitimately return non-200 for admin — seeded anyway to
// confirm the restriction holds.
const EXPECTED_NON_200: Record<string, number> = {
  '/transactions/wizard': 403,       // teller-only
  '/transactions/create': 403,       // teller-only
  '/my-allocations': 403,            // teller-only
  '/my-allocations/request': 403,    // teller-only
  '/transactions/template': -1,      // file download aborts navigation
};

async function login(page: Page, username: string) {
  for (let attempt = 0; attempt < 3; attempt++) {
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

test('Admin route + link crawl', async ({ page }) => {
  test.setTimeout(600000);
  await login(page, 'admin');

  const visited = new Set<string>();
  const queue: string[] = [...SEED_PATHS];
  const badPages: string[] = [];
  const consoleErrors = new Map<string, string[]>();

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      const url = page.url();
      const list = consoleErrors.get(url) ?? [];
      list.push(msg.text().slice(0, 200));
      consoleErrors.set(url, list);
    }
  });

  while (queue.length && visited.size < MAX_PAGES) {
    const path = queue.shift()!;
    if (visited.has(path)) {
      continue;
    }
    visited.add(path);

    const resp = await page.goto(`${BASE_URL}${path}`, {
      waitUntil: 'domcontentloaded',
      timeout: 20000,
    }).catch(() => null);

    const status = resp ? resp.status() : -1;
    const expected = EXPECTED_NON_200[path];
    if (expected !== undefined) {
      if (status !== expected) {
        badPages.push(`${status} ${path} (expected ${expected})`);
      }
      continue;
    }
    if (status !== 200) {
      badPages.push(`${status} ${path}`);
      continue;
    }

    // Harvest same-origin GET links for the crawl frontier.
    const hrefs = await page.evaluate(() =>
      [...document.querySelectorAll('a[href]')]
        .map(a => (a as HTMLAnchorElement).href)
        .filter(h => h.startsWith(location.origin))
    ).catch(() => [] as string[]);

    for (const href of hrefs) {
      try {
        const u = new URL(href);
        const p = u.pathname.replace(/\/$/, '') || '/';
        // Skip actions that mutate state or leave the app shell.
        if (/\/(logout|delete|destroy|approve|reject|cancel|close|dispatch|receive|complete|fund|debit|return|accept|resolve|assign|escalate|dismiss|submit|acknowledge|merge|link|unlink|import|export|print|download|retry|purge|verify|unfreeze|freeze)/i.test(p)) {
          continue;
        }
        if (u.search || p === '/login' || p === '/register') {
          continue;
        }
        if (!visited.has(p) && !queue.includes(p)) {
          queue.push(p);
        }
      } catch { /* ignore malformed hrefs */ }
    }
  }

  console.log(`\nVisited ${visited.size} pages, ${badPages.length} failures`);
  console.log('FAILURES:\n' + badPages.join('\n'));
  if (consoleErrors.size) {
    console.log('CONSOLE ERRORS:');
    for (const [url, errs] of consoleErrors) {
      console.log(`  ${url}: ${errs[0]}`);
    }
  }

  expect(badPages, `Broken pages:\n${badPages.join('\n')}`).toEqual([]);
});
