import { test, expect } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';
import { api, createCustomer, ensureSeated, openTill, primeMfa } from './helpers/seat';

// The wizard SPA advances to step 2 only on a real step1 API success — which
// needs a live session + till + valid customer, unlike the client-side
// gating covered by transaction-wizard.spec.ts.
test('valid step-1 input advances the wizard to customer details', async ({ browser }) => {
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
    // The fresh CI seed ships no customers — step1 needs a real id to pass.
    const customerId = (await createCustomer(tlrCtx, tlrPage)).id;
    await admCtx.close();
    await mgrCtx.close();

    await gotoVerified(tlrPage, '/transactions/wizard', USERS.teller);
    await tlrPage.waitForFunction(() => {
        const el = document.querySelector<HTMLElement>('[x-data="transactionWizard"]');
        return !!(el && ((el as any)._x_dataStack?.length || (el as any).__x));
    });

    await tlrPage.locator('input[x-model="formData.customer_id"]').fill(String(customerId));
    await tlrPage.locator('select[x-model="formData.type"]').selectOption('Buy');
    await tlrPage.locator('select[x-model="formData.currency_code"]').selectOption('USD');
    await tlrPage.locator('input[x-model="formData.quantity"]').fill('100');
    await tlrPage.locator('input[x-model="formData.rate"]').fill('4.70');
    await tlrPage.locator('select[x-model="formData.purpose"]').selectOption('Travel');
    await tlrPage.locator('input[x-model="formData.source_of_funds"]').fill('Salary savings');
    await tlrPage.locator('button[type="submit"]', { hasText: /Continue|Checking/ }).click();

    await expect(tlrPage.locator('h2', { hasText: 'Customer Details' })).toBeVisible({ timeout: 15_000 });
    await tlrCtx.close();
});
