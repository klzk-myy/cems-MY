import { test, expect, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';
import { primeMfa } from './helpers/seat';

// Report exports stream real files — these are the suite's first assertions
// on export bytes beyond the receipt/print endpoints. Every export POST sits
// behind `password.confirm`, so each test primes the confirmation window
// through the real /confirm form before submitting.
test.use({ storageState: authFile('admin') });

async function confirmPassword(page: Page): Promise<void> {
    await page.goto('/confirm-password');
    await page.locator('input[name="password"]').fill(USERS.admin.password);
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('load');
}

async function submitForDownload(page: Page, url: string, action: string) {
    await gotoVerified(page, url, USERS.admin);
    await confirmPassword(page);
    await gotoVerified(page, url, USERS.admin);
    const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 30_000 }),
        page.locator(`form[action*="${action}"] button[type="submit"]`).click(),
    ]);
    expect(download.suggestedFilename()).toBeTruthy();
    return download;
}

test('transaction export produces a downloadable file', async ({ page }) => {
    const download = await submitForDownload(page, '/transactions/export', 'export');
    expect(download.suggestedFilename()).toMatch(/csv|xlsx|xls/i);
});

test('position-limit report export produces a downloadable file', async ({ page }) => {
    await submitForDownload(page, '/reports/position-limit', 'position-limit/export');
});

test('MSB2 report export produces a downloadable file', async ({ page }) => {
    const download = await submitForDownload(page, '/reports/msb2', 'msb2/export');
    expect(download.suggestedFilename()).toMatch(/csv|xlsx|xls|xml|pdf/i);
});

test('LMCA report export produces a downloadable file', async ({ page }) => {
    await submitForDownload(page, '/reports/lmca', 'lmca/export');
});

test('quarterly-LVR report export produces a downloadable file', async ({ page }) => {
    await submitForDownload(page, '/reports/quarterly-lvr', 'quarterly-lvr/export');
});
