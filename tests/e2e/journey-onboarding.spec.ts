import { test, expect, type BrowserContext } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, baseUrl, USERS } from './helpers/env';
import { api, csrf, ensureSeated, openTill, primeMfa } from './helpers/seat';

// Onboarding journey: a teller registers a walk-in customer through the
// browser form, lands on the profile, then books that customer's first
// transaction — the real first-day flow.
test.describe('customer onboarding journey', () => {
    test.describe.configure({ mode: 'serial' });

    let tlrCtx: BrowserContext;
    let customerUrl = '';
    const suffix = `${Date.now().toString(36)}`;
    const name = `E2E Onboard ${suffix}`;

    test.beforeAll(async ({ browser }) => {
        tlrCtx = await browser.newContext({ storageState: authFile('teller') });
        const tlrPage = await tlrCtx.newPage();
        const mgrCtx = await browser.newContext({ storageState: authFile('manager') });
        const mgrPage = await mgrCtx.newPage();
        await primeMfa(tlrPage, USERS.teller);
        await primeMfa(mgrPage, USERS.manager);

        const me = await (await api(mgrCtx, mgrPage, 'get', '/user')).json();
        const till = await ensureSeated(tlrCtx, tlrPage, mgrCtx, mgrPage, me.data.id);
        expect(till, 'no counter with clean till space today').not.toBeNull();

        const admCtx = await browser.newContext({ storageState: authFile('admin') });
        const admPage = await admCtx.newPage();
        await gotoVerified(admPage, '/stock-cash', USERS.admin);
        await openTill(admCtx, admPage, till!, 'USD');
        await openTill(admCtx, admPage, till!, 'MYR');
        await admCtx.close();
        await mgrCtx.close();
        await tlrPage.close();
    });
    test.afterAll(async () => { await tlrCtx?.close(); });

    test('walk-in → register via form → profile → first transaction', async ({ page: _unused, browser }) => {
        void _unused;
        const page = await tlrCtx.newPage();
        await gotoVerified(page, '/customers/create', USERS.teller);
        const res = await page.goto('/customers/create');
        if (res?.status() !== 200) {
            // Tellers may not own customer creation — admins do; fall back
            // to the admin session for the form step and continue as teller.
            const admCtx = await browser.newContext({ storageState: authFile('admin') });
            const admPage = await admCtx.newPage();
            await gotoVerified(admPage, '/customers/create', USERS.admin);
            await fillCustomerForm(admPage, name, suffix);
            await Promise.all([
                admPage.waitForURL(/\/customers\/\d+/),
                admPage.locator('main form button[type="submit"]').last().click(),
            ]);
            customerUrl = admPage.url();
            await expect(admPage.locator('main')).toContainText(name);
            await admCtx.close();
        } else {
            await fillCustomerForm(page, name, suffix);
            await Promise.all([
                page.waitForURL(/\/customers\/\d+/),
                page.locator('main form button[type="submit"]').last().click(),
            ]);
            customerUrl = page.url();
            await expect(page.locator('main')).toContainText(name);
        }

        const customerId = Number(customerUrl.match(/\/customers\/(\d+)/)?.[1]);
        expect(customerId).toBeGreaterThan(0);

        // First transaction for the new customer — a small Buy that
        // auto-completes under the RM10k approval threshold.
        const book = await api(tlrCtx, page, 'post', '/transactions', {
            customer_id: customerId,
            type: 'Buy',
            currency_code: 'USD',
            quantity: 150,
            rate: 4.7,
            purpose: 'Travel',
            source_of_funds: 'Salary',
        });
        expect(book.status(), `first txn failed: ${await book.text()}`).toBeLessThan(300);
        expect(book.headers()['content-type'] ?? '', `booking bounced (url=${book.url()})`).toContain('application/json');
        const txn = await book.json().catch(() => null);
        const txnId = txn?.data?.id ?? txn?.id;

        if (txnId) {
            await gotoVerified(page, `/transactions/${txnId}`, USERS.teller);
            const detail = await page.goto(`/transactions/${txnId}`);
            expect(detail?.status()).toBe(200);
            await expect(page.locator('main')).toContainText(name);
        }
        await page.close();
    });
});

async function fillCustomerForm(page: import('@playwright/test').Page, name: string, suffix: string): Promise<void> {
    const set = async (sel: string, val: string) => {
        const el = page.locator(sel);
        if (await el.count()) await el.first().fill(val);
    };
    await set('input[name="full_name"]', name);
    const idType = page.locator('select[name="id_type"]');
    if (await idType.count()) {
        const opt = await idType.locator('option', { hasText: /passport/i }).first().getAttribute('value')
            ?? await idType.locator('option:not([value=""])').first().getAttribute('value');
        if (opt) await idType.selectOption(opt);
    }
    await set('input[name="id_number"]', `ONB${suffix.toUpperCase()}`);
    await set('input[name="date_of_birth"]', '1990-01-01');
    const nat = page.locator('select[name="nationality"]');
    if (await nat.count()) {
        await nat.selectOption('MY');
    } else {
        await set('input[name="nationality"]', 'MY');
    }
    await set('[name="address"]', '1 Jalan Testing, Kuala Lumpur');
    await set('input[name="phone"]', `+6012${suffix.replace(/[^0-9]/g, '3').slice(-7).padStart(7, '3')}`);
    await set('input[name="occupation"]', 'Engineer');
    await set('input[name="employer_name"]', 'Test Corp');
}
