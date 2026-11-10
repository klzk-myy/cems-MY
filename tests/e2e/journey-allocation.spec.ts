import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { gotoVerified, resolveMfa } from './helpers/auth';
import { authFile, baseUrl, USERS, type E2eUser } from './helpers/env';
import { csrf } from './helpers/seat';

async function primeMfa(page: Page, user: E2eUser) {
    await page.goto('/mfa/verify');
    await resolveMfa(page, user);
}

async function postForm(ctx: BrowserContext, page: Page, path: string, fields: Record<string, string>) {
    const token = await csrf(page);
    return ctx.request.post(`${baseUrl()}${path}`, { form: { _token: token, ...fields } });
}

test.describe('allocation request journey', () => {
    test('teller requests → manager approves → teller accepts', async ({ browser }) => {
        const tlrCtx = await browser.newContext({ storageState: authFile('teller') });
        const tlrPage = await tlrCtx.newPage();
        const mgrCtx = await browser.newContext({ storageState: authFile('manager') });
        const mgrPage = await mgrCtx.newPage();

        // Teller submits a float request for their drawer (counter C04 is the
        // open session counter for teller1). The request lands pending.
        await gotoVerified(tlrPage, '/my-allocations/request', USERS.teller);
        const res = await postForm(tlrCtx, tlrPage, '/my-allocations/request', {
            'lines[0][currency_code]': 'EUR',
            'lines[0][quantity]': '2500',
        });
        expect([200, 302, 303]).toContain(res.status());

        // The request lands pending; the manager finds it in the pending queue
        // and opens its show page to approve.
        await primeMfa(mgrPage, USERS.manager);
        await gotoVerified(mgrPage, '/allocations?status=pending', USERS.manager);
        const row = mgrPage.locator('tr', { hasText: 'EUR' }).first();
        const viewLink = row.locator('a[href*="/allocations/"]').first();
        await viewLink.click();
        await mgrPage.waitForURL(/\/allocations\/\d+/);
        const approveForm = mgrPage.locator('form[action*="/approve"]');
        await expect(approveForm).toBeVisible();
        await approveForm.locator('input[name="approved_quantity"]').fill('2500');
        await approveForm.locator('button[type="submit"]').click();
        await mgrPage.waitForLoadState('load');

        // Teller accepts the funded float — approved becomes active.
        await gotoVerified(tlrPage, '/my-allocations', USERS.teller);
        const acceptBtn = tlrPage.locator('form[action*="accept"] button[type="submit"]');
        await expect(acceptBtn.first()).toBeVisible();
        await acceptBtn.first().click();
        await tlrPage.waitForLoadState('load');
        await expect(tlrPage.locator('body')).toContainText(/Active/i);

        await tlrCtx.close();
        await mgrCtx.close();
    });
});
