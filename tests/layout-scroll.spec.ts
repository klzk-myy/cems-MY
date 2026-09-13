import { test, expect, Page } from '@playwright/test';

const BASE_URL = 'http://local.host';
const PASSWORD = 'Password123!';

const PAGES = [
  '/dashboard', '/transactions', '/transactions/84', '/transactions/create',
  '/customers', '/customers/4', '/counters', '/allocations', '/branch-pools',
  '/stock-transfers', '/stock-transfers/32', '/stock-cash', '/eod',
  '/compliance', '/compliance/sanctions/entries', '/compliance/cases',
  '/compliance/risk-dashboard', '/str', '/accounting', '/accounting/journal',
  '/accounting/trial-balance', '/accounting/balance-sheet', '/accounting/ledger',
  '/reports', '/reports/profitability', '/users', '/users/8', '/branches',
  '/admin/audit-logs', '/admin/role-permissions', '/test-results',
  '/notifications/preferences', '/system/currencies',
];

async function login(page: Page) {
  for (let attempt = 0; attempt < 4; attempt++) {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input#username', 'admin');
    await page.fill('input#password', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(800);
    if (!page.url().includes('/login')) {
      return;
    }
    await page.waitForTimeout(12000 * (attempt + 1));
  }
  throw new Error('admin login failed');
}

test('layout: no double scrollbar, skip link screen-reader-only', async ({ page }) => {
  test.setTimeout(300000);
  await login(page);

  const failures: string[] = [];
  const lines: string[] = [];

  for (const path of PAGES) {
    const resp = await page.goto(`${BASE_URL}${path}`, {
      waitUntil: 'domcontentloaded',
      timeout: 20000,
    }).catch(() => null);
    if (!resp || resp.status() !== 200) {
      lines.push(`${path}: HTTP ${resp?.status()} — skipped`);
      continue;
    }
    await page.waitForTimeout(300);

    const m = await page.evaluate(() => {
      const de = document.documentElement;
      const skip = document.querySelector<HTMLAnchorElement>('a[href="#main-content"]');
      const main = document.querySelector('#main-content');
      const sr = skip ? skip.getBoundingClientRect() : null;
      const cs = skip ? getComputedStyle(skip) : null;
      return {
        bodyOverflow: de.scrollHeight - de.clientHeight,
        mainScrolls: main ? main.scrollHeight > main.clientHeight : null,
        skipPresent: !!skip,
        skipHidden: sr ? sr.width <= 1 && sr.height <= 1 : null,
        skipPos: cs?.position ?? null,
      };
    });

    lines.push(`${path}: bodyOverflow=${m.bodyOverflow}px mainScrolls=${m.mainScrolls} skip=${m.skipPresent ? (m.skipHidden ? 'sr-only' : `VISIBLE pos=${m.skipPos}`) : 'none'}`);

    if (m.bodyOverflow > 1) {
      failures.push(`${path}: body overflows by ${m.bodyOverflow}px (double scrollbar)`);
    }
    if (m.skipPresent && !m.skipHidden) {
      failures.push(`${path}: skip link visible (sr-only not applied, pos=${m.skipPos})`);
    }
  }

  console.log('\n=== LAYOUT CHECK ===\n' + lines.join('\n'));
  expect(failures, `Layout issues:\n${failures.join('\n')}`).toEqual([]);
});
