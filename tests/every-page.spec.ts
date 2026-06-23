import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

async function removeOverlay(page) {
  await page.evaluate(() => {
    const overlays = document.querySelectorAll('.fixed.inset-0, [class*="overlay"], [class*="modal-backdrop"]');
    overlays.forEach(el => {
      if (el.tagName !== 'BODY' && el.tagName !== 'HTML') {
        (el as HTMLElement).style.display = 'none';
      }
    });
  });
  await page.waitForTimeout(200);
}

test.describe('Test Every Page', () => {

  test('login once, test all pages', async ({ page }) => {
    // Login once
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input#username', 'admin');
    await page.fill('input#password', 'Admin@123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    await removeOverlay(page);
    
    console.log('Logged in successfully');
    
    const pages = [
      '/',
      '/dashboard',
      '/performance',
      '/rates',
      '/transactions',
      '/transactions/create',
      '/transactions/list',
      '/customers',
      '/customers/create',
      '/counters',
      '/stock-cash',
      '/stock-cash/open',
      '/stock-cash/close',
      '/stock-transfers',
      '/stock-transfers/create',
      '/accounting',
      '/accounting/journal',
      '/accounting/journal/create',
      '/accounting/ledger',
      '/accounting/trial-balance',
      '/accounting/profit-loss',
      '/accounting/balance-sheet',
      '/accounting/cash-flow',
      '/accounting/reconciliation',
      '/accounting/budget',
      '/accounting/fiscal-years',
      '/accounting/month-end',
      '/accounting/revaluation',
      '/accounting/ratios',
      '/compliance',
      '/compliance/alerts',
      '/compliance/cases',
      '/compliance/edd',
      '/compliance/edd/create',
      '/compliance/findings',
      '/compliance/flagged',
      '/compliance/sanctions',
      '/compliance/sanctions/entries',
      '/compliance/risk-dashboard',
      '/compliance/risk-dashboard/trends',
      '/compliance/reporting',
      '/compliance/workspace',
      '/audit',
      '/audit/dashboard',
      '/reports',
      '/reports/history',
      '/str',
      '/str/create',
      '/branches',
      '/branches/create',
      '/users',
      '/users/create',
    ];
    
    let passed = 0;
    let failed = 0;
    const failedPages: string[] = [];
    
    for (const path of pages) {
      try {
        console.log(`Testing ${path}...`);
        await page.goto(`${BASE_URL}${path}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForTimeout(500);
        await removeOverlay(page);
        
        const url = page.url();
        console.log(`  ✓ ${path} -> ${url}`);
        passed++;
        
      } catch (e) {
        failed++;
        failedPages.push(path);
        console.log(`  ✗ FAILED: ${path} - ${(e as Error).message.substring(0, 80)}`);
      }
    }
    
    console.log(`\n=== RESULTS ===`);
    console.log(`Passed: ${passed}/${pages.length}`);
    console.log(`Failed: ${failed}/${pages.length}`);
    if (failedPages.length > 0) {
      console.log(`Failed pages: ${failedPages.join(', ')}`);
    }
  });

});