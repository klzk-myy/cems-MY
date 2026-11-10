import { test, expect, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';
import { primeMfa } from './helpers/seat';

// Zero-coverage admin/compliance surfaces — index-level render checks.
// /users sits behind mfa.verified on GET, so the session is primed first.
test.use({ storageState: authFile('admin') });

async function rendersOk(page: Page, url: string) {
    const resp = await page.goto(url);
    expect(resp?.status(), `${url} status`).toBeLessThan(400);
    await expect(page.locator('body')).not.toContainText(/Server Error|Whoops/i);
}

for (const url of [
    '/users',
    '/branch-pools',
    '/str',
    '/compliance/cases',
    '/compliance/screening-matches',
    '/compliance/edd-review',
    '/notifications/preferences',
    '/customers/2',
]) {
    test(`surface ${url} renders`, async ({ page }) => {
        await primeMfa(page, USERS.admin);
        await gotoVerified(page, url, USERS.admin);
        await rendersOk(page, url);
    });
}
