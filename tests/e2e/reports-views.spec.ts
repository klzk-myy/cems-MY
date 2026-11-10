import { test, expect, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

// The exports spec covers download bytes; these tests cover the report
// *views* themselves rendering without error under the report-viewer role.
test.use({ storageState: authFile('admin') });

async function rendersOk(page: Page, url: string) {
    const resp = await page.goto(url);
    expect(resp?.status(), `${url} status`).toBeLessThan(400);
    await expect(page.locator('body')).not.toContainText(/Server Error|Whoops/i);
}

for (const url of [
    '/reports',
    '/reports/msb2',
    '/reports/lmca',
    '/reports/position-limit',
    '/reports/quarterly-lvr',
    '/reports/customer-analysis',
    '/reports/compliance-summary',
]) {
    test(`report view ${url} renders`, async ({ page }) => {
        await gotoVerified(page, url, USERS.admin);
        await rendersOk(page, url);
    });
}
