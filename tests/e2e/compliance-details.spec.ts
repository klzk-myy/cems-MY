import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, baseUrl, USERS } from './helpers/env';
import { api, createCustomer, csrf, primeMfa } from './helpers/seat';

// Compliance index surfaces — every list page renders for the officer role.
for (const url of [
    '/compliance/alerts',
    '/compliance/cases',
    '/compliance/findings',
    '/compliance/screening-matches',
    '/compliance/unified',
    '/compliance/pep-approvals',
    '/compliance/edd-review',
    '/compliance/sanctions',
    '/compliance/sanctions/entries',
    '/compliance/sanctions/import-logs',
]) {
    test(`compliance surface ${url} renders`, async ({ page }) => {
        await gotoVerified(page, url, USERS.compliance);
        const res = await page.goto(url);
        expect(res?.status(), url).toBe(200);
        await expect(page.locator('main')).not.toBeEmpty();
    });
}

// Detail pages need real records. A sanctions entry is created through its
// form, then a customer whose full name matches the entry is screened —
// the result opens the match detail, confirm/dismiss controls, and the
// customer screening history/status pages.
test.describe('compliance detail pages', () => {
    let ctx: BrowserContext;
    let page: Page;
    let entryUrl = '';
    let entityName = '';

    test.beforeAll(async ({ browser }) => {
        ctx = await browser.newContext({ storageState: authFile('compliance') });
        page = await ctx.newPage();
        await primeMfa(page, USERS.compliance);
    });
    test.afterAll(async () => { await ctx?.close(); });

    test('sanctions entry create → show → edit', async () => {
        await gotoVerified(page, '/compliance/sanctions/entries/create', USERS.compliance);
        const listId = await page.locator('select[name="list_id"] option:not([value=""])').first().getAttribute('value');
        expect(listId, 'no sanctions list seeded').toBeTruthy();

        await page.selectOption('select[name="list_id"]', listId!);
        entityName = `E2E SANCTIONED ${Date.now().toString(36).toUpperCase()}`;
        await page.fill('input[name="entity_name"]', entityName);
        await page.selectOption('select[name="entity_type"]', 'Individual');
        await Promise.all([
            page.waitForURL(/\/compliance\/sanctions\/entries/),
            page.locator('main form button[type="submit"]').last().click(),
        ]);

        // The store lands back on the (paginated) entries index — use its
        // search filter to locate our row deterministically.
        await page.goto(`/compliance/sanctions/entries?search=${encodeURIComponent(entityName)}`);
        const row = page.locator(`tr:has-text("${entityName}")`).first();
        await expect(row, 'new entry not listed').toBeVisible({ timeout: 15_000 });
        await row.locator('a', { hasText: /View|Edit/ }).first().click();
        await page.waitForLoadState('load');
        entryUrl = page.url().replace(/\/edit$/, '');
        await expect(page.locator('main')).toContainText(entityName);

        await gotoVerified(page, `${entryUrl}/edit`, USERS.compliance);
        const edit = await page.goto(`${entryUrl}/edit`);
        expect(edit?.status()).toBe(200);
        await expect(page.locator('input[name="entity_name"]')).toHaveValue(entityName);
    });

    test('screening match → detail → customer history/status', async ({ browser }) => {
        expect(entityName, 'sanctions entry was not created').toBeTruthy();
        // Customer creation needs CreateTransactions/ManageCustomers —
        // the compliance role may hold neither, so the admin creates it.
        const admCtx = await browser.newContext({ storageState: authFile('admin') });
        const admPage = await admCtx.newPage();
        const customer = await createCustomer(admCtx, admPage, entityName, USERS.admin);
        await admCtx.close();

        // Screen the new customer against the list we just wrote to —
        // the fuzzy match opens a ScreeningResult row.
        const screen = await ctx.request.post(`${baseUrl()}/compliance/screening/${customer.id}`, {
            form: { _token: await csrf(page) },
        });
        expect([200, 302, 303]).toContain(screen.status());

        await gotoVerified(page, '/compliance/screening-matches', USERS.compliance);
        const row = page.locator(`tr:has-text("${entityName}")`).first();
        await expect(row, 'no screening match for sanctioned-name customer').toBeVisible({ timeout: 15_000 });
        await row.locator('a', { hasText: /View|Review/ }).first().click();
        await page.waitForURL(/\/compliance\/screening-matches\/\d+/);
        await expect(page.locator('main')).toContainText(entityName);

        for (const suffix of ['', '/history', '/status']) {
            await gotoVerified(page, `/compliance/screening/${customer.id}${suffix}`, USERS.compliance);
            const res = await page.goto(`/compliance/screening/${customer.id}${suffix}`);
            expect(res?.status(), `screening${suffix}`).toBe(200);
            await expect(page.locator('main')).not.toBeEmpty();
        }
    });
});
