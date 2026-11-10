import { test, expect, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

// The accounting module had only form-level smoke coverage; these verify the
// read views (ledger, statements, periods, fiscal years) render for the
// accounting-capable role.
test.use({ storageState: authFile('admin') });

async function rendersOk(page: Page, url: string) {
    const resp = await page.goto(url);
    expect(resp?.status(), `${url} status`).toBeLessThan(400);
    await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|403|Unauthorized/i);
}

for (const url of [
    '/accounting',
    '/accounting/journal',
    '/accounting/ledger',
    '/accounting/trial-balance',
    '/accounting/profit-loss',
    '/accounting/balance-sheet',
    '/accounting/cash-flow',
    '/accounting/ratios',
    '/accounting/periods',
    '/accounting/fiscal-years',
    '/accounting/revaluation',
    '/accounting/reconciliation',
    '/accounting/reconciliation/report',
    '/accounting/budget',
    '/accounting/expenses',
]) {
    test(`accounting view ${url} renders`, async ({ page }) => {
        await gotoVerified(page, url, USERS.admin);
        await rendersOk(page, url);
    });
}
