import { test, expect } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';
import { api, createCustomer, ensureSeated, openTill, primeMfa } from './helpers/seat';

// The full wizard money path: step-1 eligibility → step-2 customer CDD →
// step-3 review → step-4 booked transaction. Steps 1→2 are covered by
// wizard-step2; this drives the whole chain because step 3 is where the
// real booking happens (the classic-form journey doesn't touch the SPA).
test('wizard completes all three steps and books the transaction', async ({ browser }) => {
    test.setTimeout(120_000);
    const tlrCtx = await browser.newContext({ storageState: authFile('teller') });
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
    const { id: customerId, full_name: customerName } = await createCustomer(tlrCtx, tlrPage, undefined, USERS.teller);
    await admCtx.close();
    await mgrCtx.close();

    await gotoVerified(tlrPage, '/transactions/wizard', USERS.teller);
    await tlrPage.waitForFunction(() => {
        const el = document.querySelector<HTMLElement>('[x-data="transactionWizard"]');
        return !!(el && ((el as any)._x_dataStack?.length || (el as any).__x));
    });

    // Step 1 — under the RM3k specific threshold so step 2 needs only the
    // occupation field (Simplified tier, no document uploads).
    await tlrPage.locator('input[x-model="formData.customer_id"]').fill(String(customerId));
    await tlrPage.locator('select[x-model="formData.type"]').selectOption('Buy');
    await tlrPage.locator('select[x-model="formData.currency_code"]').selectOption('USD');
    await tlrPage.locator('input[x-model="formData.quantity"]').fill('100');
    await tlrPage.locator('input[x-model="formData.rate"]').fill('4.70');
    await tlrPage.locator('select[x-model="formData.purpose"]').selectOption('Travel');
    await tlrPage.locator('input[x-model="formData.source_of_funds"]').fill('Salary savings');
    await tlrPage.locator('button[type="submit"]', { hasText: /Continue|Checking/ }).click();
    await expect(tlrPage.locator('h2', { hasText: 'Customer Details' })).toBeVisible({ timeout: 15_000 });

    // Step 2 — occupation is the only required field at Simplified tier.
    await tlrPage.locator('input[x-model="formData.occupation"]').fill('Engineer');
    await tlrPage.locator('button[type="submit"]', { hasText: /Review|Saving/ }).click();

    // Step 3 — review renders the computed summary before booking.
    await expect(tlrPage.locator('h2', { hasText: 'Review & Confirm' })).toBeVisible({ timeout: 15_000 });
    await expect(tlrPage.locator('dd', { hasText: customerName })).toBeVisible();
    await expect(tlrPage.locator('dd', { hasText: 'Buy' })).toBeVisible();

    // Step 4 — booking lands a real transaction; ~RM470 auto-completes.
    await tlrPage.locator('button[type="submit"]', { hasText: /Confirm & Submit|Submitting/ }).click();
    await expect(tlrPage.locator('h2', { hasText: 'Transaction Created' })).toBeVisible({ timeout: 20_000 });
    await expect(tlrPage.locator('span', { hasText: /Reference: [A-Z0-9-]+/i })).toBeVisible();
    await expect(tlrPage.locator('span', { hasText: /^COMPLETED$/ })).toBeVisible();
    const viewLink = tlrPage.locator('a', { hasText: 'View Transaction' });
    await expect(viewLink).toBeVisible();
    const txnUrl = await viewLink.getAttribute('href');
    expect(txnUrl).toMatch(/\/transactions\/\d+/);

    // The booked transaction is a real row the teller can open.
    await gotoVerified(tlrPage, txnUrl!, USERS.teller);
    await expect(tlrPage.locator('body')).toContainText(customerName);
    await tlrCtx.close();
});
