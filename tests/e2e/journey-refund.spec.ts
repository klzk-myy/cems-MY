import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, baseUrl, USERS } from './helpers/env';
import { api, createCustomer, csrf, ensureSeated, openTill, primeMfa } from './helpers/seat';
import { resolveOpenFlags } from './helpers/compliance';

const RATE = 4.7;

async function createAllocation(ctx: BrowserContext, page: Page, tellerId: number, currency: string, quantity: string) {
    const token = await csrf(page);
    await ctx.request.post(`${baseUrl()}/allocations`, {
        form: {
            _token: token, user_id: String(tellerId),
            'lines[0][currency_code]': currency, 'lines[0][quantity]': quantity,
            daily_limit_myr: '50000000',
        },
    });
}

// Minimal completed-booking bootstrap shared with the transaction journey:
// seat → allocation → classic-form create → admin approve.
test.describe('refund journey', () => {
    test('completed transaction → reversal → refund completion', async ({ browser }) => {
        const tlrCtx = await browser.newContext({ storageState: authFile('teller') });
        const tlrPage = await tlrCtx.newPage();
        const admCtx = await browser.newContext({ storageState: authFile('admin') });
        const admPage = await admCtx.newPage();
        const mgrCtx = await browser.newContext({ storageState: authFile('manager') });
        const mgrPage = await mgrCtx.newPage();
        await primeMfa(tlrPage, USERS.teller);
        await primeMfa(mgrPage, USERS.manager);

        const me = await (await api(mgrCtx, mgrPage, 'get', '/user')).json();
        const till = await ensureSeated(tlrCtx, tlrPage, mgrCtx, mgrPage, me.data.id);
        expect(till, 'no counter with clean till space today').not.toBeNull();
        await gotoVerified(admPage, '/stock-cash', USERS.admin);
        await openTill(admCtx, admPage, till!, 'USD');
        await openTill(admCtx, admPage, till!, 'MYR');
        // Teller id + customer resolve live — seed ids differ across
        // environments and the fresh CI seed ships no customers at all.
        const teller = await (await api(tlrCtx, tlrPage, 'get', '/user')).json();
        await createAllocation(admCtx, admPage, teller.data.id, 'USD', '5000');
        const customerName = (await createCustomer(tlrCtx, tlrPage, undefined, USERS.teller)).full_name;
        await mgrCtx.close();

        // Teller books a small Buy through the classic form.
        await gotoVerified(tlrPage, '/my-allocations', USERS.teller);
        const acceptBtn = tlrPage.locator('form[action*="accept"] button[type="submit"]');
        if (await acceptBtn.count()) {
            await acceptBtn.first().click();
            await tlrPage.waitForLoadState('load');
        }
        await gotoVerified(tlrPage, '/transactions/create', USERS.teller);
        await tlrPage.waitForFunction(() => (window as any).Alpine !== undefined);
        await tlrPage.locator('input[name="full_name"]').click();
        await tlrPage.locator('input[name="full_name"]').pressSequentially(customerName, { delay: 30 });
        await tlrPage.locator('ul li', { hasText: customerName }).first().click();
        await tlrPage.selectOption('select[name="type"]', 'Buy');
        await tlrPage.selectOption('select[name="currency_code"]', 'USD');
        await tlrPage.fill('input[name="quantity"]', (150 + Math.random()).toFixed(2));
        await tlrPage.fill('input[name="rate"]', String(RATE));
        await tlrPage.selectOption('select[name="purpose"]', 'Travel');
        await tlrPage.fill('input[name="source_of_funds"]', 'Salary');
        await tlrPage.fill('input[name="source_of_wealth"]', 'Business');
        if (!(await tlrPage.inputValue('input[name="id_number"]'))) {
            await tlrPage.fill('input[name="id_number"]', 'A12345678');
        }
        if (!(await tlrPage.locator('select[name="id_type"]').inputValue())) {
            await tlrPage.selectOption('select[name="id_type"]', 'Passport');
        }
        if (!(await tlrPage.locator('select[name="nationality"]').inputValue())) {
            await tlrPage.selectOption('select[name="nationality"]', { index: 1 });
        }
        if (!(await tlrPage.inputValue('input[name="date_of_birth"]'))) {
            await tlrPage.fill('input[name="date_of_birth"]', '1990-01-15');
        }
        await tlrPage.fill('textarea[name="address"]', '1 Jalan Testing, Kuala Lumpur');
        await tlrPage.fill('input[name="phone"]', '+60123456789');
        await tlrPage.fill('input[name="occupation"]', 'Engineer');
        await tlrPage.fill('input[name="employer_name"]', 'Test Corp');
        await tlrPage.locator('button:has-text("Create Transaction")').click();
        await tlrPage.waitForURL(/\/transactions\/\d+/);
        const txnUrl = tlrPage.url();

        // Small auto-approved bookings land Completed already; anything on
        // hold/pending goes through the admin clear-hold → approve chain.
        await primeMfa(admPage, USERS.admin);
        await gotoVerified(admPage, txnUrl, USERS.admin);
        const clearBtn = admPage.locator('form[action*="clear-hold"] button');
        if (await clearBtn.count()) {
            await clearBtn.first().click();
            await admPage.waitForLoadState('load');
        }
        const txnId = txnUrl.match(/(\d+)$/)![1];
        const approveBtn = admPage.locator('form[action*="approve"] button:has-text("Approve")');
        if (await approveBtn.count()) {
            await admPage.goto(`/transactions/${txnId}/confirm`);
            if (admPage.url().includes('/confirm')) {
                const { totp } = await import('./helpers/totp');
                await admPage.fill('input[name="mfa_code"]', totp(USERS.admin.totp!));
                await admPage.locator('button:has-text("Confirm Transaction")').click();
                await admPage.waitForLoadState('load');
                await admPage.goto(`/transactions/${txnId}`);
            }
            const [approveResp] = await Promise.all([
                admPage.waitForResponse((r) => /approve/.test(r.url()) && r.request().method() === 'POST', { timeout: 15_000 }).catch(() => null),
                approveBtn.click(),
            ]);
            await admPage.waitForLoadState('load');
            const flash = await admPage
                .locator('.bg-danger-subtle, [class*="danger-subtle"], .bg-success-subtle, [class*="success-subtle"]')
                .allTextContents()
                .then((t) => t.map((s) => s.trim()).filter(Boolean));
            expect(approveResp?.status(), `approve POST flash=${JSON.stringify(flash)}`).toBeLessThan(400);
        }
        // AML flags can still block after hold-clear — resolve them via the
        // compliance flagged list and retry the approval once. Notification-
        // bell items can fake a body-text match — assert the status badge.
        if (!(await admPage.getByText('Completed', { exact: true }).first().count())) {
            await resolveOpenFlags(browser, txnId);
            await gotoVerified(admPage, txnUrl, USERS.admin);
            const retry = admPage.locator('form[action*="approve"] button:has-text("Approve")');
            if (await retry.count()) {
                await retry.click();
                await admPage.waitForLoadState('load');
            }
        }
        await expect(admPage.getByText('Completed', { exact: true }).first()).toBeVisible();

        // Reversal — role:reverse_transactions + a reason → creates the
        // refund transaction pending compliance approval.
        await gotoVerified(admPage, `${txnUrl}/reverse`, USERS.admin);
        await admPage.fill('textarea[name="reason"]', 'Customer returned notes — full reversal.');
        await admPage.locator('input[name="confirm_understanding"]').check();
        const [reverseResp] = await Promise.all([
            admPage.waitForResponse((r) => r.request().method() === 'POST' && /reverse/.test(r.url()), { timeout: 15_000 }).catch(() => null),
            admPage.locator('button:has-text("Reverse Transaction")').click(),
        ]);
        await admPage.waitForLoadState('load');
        const flash = await admPage
            .locator('.bg-danger-subtle, [class*="danger-subtle"], .bg-success-subtle, [class*="success-subtle"]')
            .allTextContents()
            .then((t) => t.map((s) => s.trim()).filter(Boolean));
        expect(reverseResp?.status(), `reverse POST flash=${JSON.stringify(flash)}`).toBeLessThan(400);

        // Redirect lands on the refund transaction (pending_approval, often
        // on its own compliance hold — refunds are inherently high-risk):
        // clear → approve, then the complete-refund form appears.
        const refundClear = admPage.locator('form[action*="clear-hold"] button');
        if (await refundClear.count()) {
            await refundClear.first().click();
            await admPage.waitForLoadState('load');
        }
        const refundApprove = admPage.locator('form[action*="approve"] button:has-text("Approve")');
        if (await refundApprove.count()) {
            await refundApprove.click();
            await admPage.waitForLoadState('load');
        }
        const completeBtn = admPage.locator('form[action*="complete-refund"] button');
        if (!(await completeBtn.count())) {
            // Refund txns attract their own AML flags — resolve and retry.
            const refundId = admPage.url().match(/(\d+)$/)![1];
            await resolveOpenFlags(browser, refundId);
            await gotoVerified(admPage, `/transactions/${refundId}`, USERS.admin);
            const retry = admPage.locator('form[action*="approve"] button:has-text("Approve")');
            if (await retry.count()) {
                await retry.click();
                await admPage.waitForLoadState('load');
            }
        }
        await expect(completeBtn, `no complete-refund form; flash=${JSON.stringify(flash)} url=${admPage.url()}`).toBeVisible();
        await completeBtn.click();
        await admPage.waitForLoadState('load');
        await expect(admPage.getByText('Completed', { exact: true }).first()).toBeVisible();

        await tlrCtx.close();
        await admCtx.close();
    });
});
