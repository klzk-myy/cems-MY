import { test, expect } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';
import { primeMfa } from './helpers/seat';

// Inter-branch custody: source manager creates → destination branch manager
// approves (self-approval across branches is prohibited, so admin stands in
// for the destination branch) → source dispatches → destination receives and
// completes. Every transition is a real form POST on the show page.
test.describe('stock transfer journey', () => {
    test('create → approve → dispatch → receive → complete', async ({ browser }) => {
        const mgrCtx = await browser.newContext({ storageState: authFile('manager') });
        const mgrPage = await mgrCtx.newPage();
        const admCtx = await browser.newContext({ storageState: authFile('admin') });
        const admPage = await admCtx.newPage();

        // Source branch (manager1 @ Kuala Lumpur) requests a transfer to
        // Petaling Jaya through the real create form.
        await gotoVerified(mgrPage, '/stock-transfers/create', USERS.manager);
        // Head Office can't hold foreign stock — source is the manager's own
        // branch (Kuala Lumpur), destination a different active branch.
        await mgrPage.selectOption('select[name="source_branch_name"]', { label: 'Kuala Lumpur Branch' });
        await mgrPage.selectOption('select[name="destination_branch_name"]', { label: 'Petaling Jaya Branch' });
        await mgrPage.selectOption('select[name="type"]', 'Standard');
        await mgrPage.selectOption('select[name="items[0][currency_code]"]', 'USD');
        await mgrPage.fill('input[name="items[0][quantity]"]', '1000');
        await mgrPage.fill('input[name="items[0][rate]"]', '4.70');
        await mgrPage.fill('input[name="items[0][value_myr]"]', '4700');
        await mgrPage.locator('button:has-text("Create Transfer")').click();
        await mgrPage.waitForURL(/\/stock-transfers\/\d+/);
        const showUrl = mgrPage.url();
        await expect(mgrPage.locator('body')).toContainText('Requested');

        // Branch-manager approval — cross-branch requires a different user
        // than the requester; admin satisfies the destination side.
        await primeMfa(admPage, USERS.admin);
        await gotoVerified(admPage, showUrl, USERS.admin);
        const approve = admPage.locator('form[action*="approve-bm"] button');
        await expect(approve).toBeVisible();
        await approve.click();
        await admPage.waitForLoadState('load');
        await expect(admPage.locator('body')).toContainText(/Approved/i);

        // Source-side dispatch (the maker's action).
        await primeMfa(mgrPage, USERS.manager);
        await gotoVerified(mgrPage, showUrl, USERS.manager);
        const dispatch = mgrPage.locator('form[action*="dispatch"] button');
        await expect(dispatch).toBeVisible();
        await dispatch.click();
        await mgrPage.waitForLoadState('load');
        await expect(mgrPage.locator('body')).toContainText(/In Transit/i);

        // Destination receives — per-item quantities then Complete.
        await gotoVerified(admPage, showUrl, USERS.admin);
        const receiveForm = admPage.locator('form[action*="/receive"]');
        if (await receiveForm.isVisible()) {
            for (const input of await receiveForm.locator('input[name*="quantity_received"]').all()) {
                await input.fill('1000');
            }
            await receiveForm.locator('button[type="submit"]').click();
            await admPage.waitForLoadState('load');
        }
        const complete = admPage.locator('form[action*="complete"] button');
        await expect(complete).toBeVisible();
        await complete.click();
        await admPage.waitForLoadState('load');
        await expect(admPage.locator('body')).toContainText('Completed');

        await mgrCtx.close();
        await admCtx.close();
    });
});
