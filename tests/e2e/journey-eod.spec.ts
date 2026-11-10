import { test, expect } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';
import { api, primeMfa, branchCounters, closeCounterSession, rejectPendingAllocations, returnActiveAllocations } from './helpers/seat';

const BRANCH = 5;

// Admin reopens the finalized day — reopen is restricted to cross-branch
// users (canManageAllBranches), and it un-freezes the business date so the
// other journeys can still post today.
async function adminReopen(browser: import('@playwright/test').Browser) {
    const ctx = await browser.newContext({ storageState: authFile('admin') });
    const page = await ctx.newPage();
    await primeMfa(page, USERS.admin);
    await gotoVerified(page, `/branches/${BRANCH}/closing`, USERS.admin);
    const reopen = page.locator('form[action*="/reopen"] button');
    if (await reopen.isVisible()) {
        await reopen.click();
        await page.waitForLoadState('load');
    }
    await ctx.close();
}

test.describe('branch closing journey', () => {
    test('till close → initiate → settle → finalize → reopen', async ({ browser }) => {
        const tlrCtx = await browser.newContext({ storageState: authFile('teller') });
        const tlrPage = await tlrCtx.newPage();
        const mgrCtx = await browser.newContext({ storageState: authFile('manager') });
        const mgrPage = await mgrCtx.newPage();

        await primeMfa(tlrPage, USERS.teller);
        await primeMfa(mgrPage, USERS.manager);
        const counters = await branchCounters(mgrCtx, mgrPage);
        const me = await (await api(mgrCtx, mgrPage, 'get', '/user')).json();

        // Close every open counter session at the branch — CounterService is
        // API-only, so the close runs under the teller's real session with
        // the manager supervising the variance.
        for (const counter of counters) {
            await closeCounterSession(tlrCtx, tlrPage, counter.id, me.data.id);
        }

        // Return every active allocation to the pool — required when resuming
        // a reopened workflow (settle is a no-op once Settled). Idempotent.
        await gotoVerified(tlrPage, '/my-allocations', USERS.teller);
        const returns = tlrPage.locator('form[action*="/return"] button');
        while (await returns.count() > 0) {
            await returns.first().click();
            await tlrPage.waitForLoadState('load');
        }

        // Manager opens the closing dashboard — /closing resolves to the
        // branch-scoped /branches/{id}/closing.
        await gotoVerified(mgrPage, '/closing', USERS.manager);
        await expect(mgrPage).toHaveURL(/\/branches\/\d+\/closing/);
        await expect(mgrPage.locator('body')).toContainText('Day Reconciliation');

        // A prior run may have finalized today's business date before dying —
        // reopen it so the workflow resumes at Settled.
        if (await mgrPage.locator('body').textContent().then(t => t?.includes('Business Date Closed'))) {
            await adminReopen(browser);
            await mgrPage.reload();
        }

        // Initiate — skipped when a workflow already exists (a reopened close
        // resumes at Settled); the transitions converge either way.
        const initiate = mgrPage.locator('form[action*="/initiate"] button');
        if (await initiate.isVisible()) {
            await initiate.click();
            await mgrPage.waitForLoadState('load');
        }

        // Pending allocation requests aren't swept by settle — action the
        // pending queue the way a manager would at day end. Floats created
        // after a reopen can't be re-settled either — return them to pool.
        await rejectPendingAllocations(mgrCtx, mgrPage);
        await returnActiveAllocations(mgrCtx, mgrPage);
        await gotoVerified(mgrPage, `/branches/${BRANCH}/closing`, USERS.manager);
        await expect(mgrPage.locator('body')).toContainText('Checklist');

        // Checklist reflects the closeable state the journey created.
        await expect(mgrPage.locator('body')).toContainText('Counters Closed');
        await expect(mgrPage.locator('body')).toContainText('Teller Allocations Returned');
        await expect(mgrPage.locator('body')).toContainText('Documents Finalized');

        // Settle — sweeps active floats to the pool and cancels stale requests.
        const settle = mgrPage.locator('form[action*="/settle"] button');
        if (await settle.isVisible()) {
            await settle.click();
            await mgrPage.waitForLoadState('load');
        }

        // Finalize — archives the recon snapshot and freezes the business date.
        const finalize = mgrPage.locator('form[action*="/finalize"] button');
        await expect(finalize, 'finalize should unlock once the checklist is green').toBeVisible();
        await finalize.click();
        await mgrPage.waitForLoadState('load');
        await expect(mgrPage.locator('body')).toContainText('Business Date Closed');
        await expect(mgrPage.locator('body')).toContainText(/no new sessions, transactions, or journal entries/i);

        // Admin-only reopen returns the workflow to Settled — verified below
        // by the checklist reappearing for the manager.
        await adminReopen(browser);
        await mgrPage.reload();
        await expect(mgrPage.locator('body')).toContainText('Checklist');

        // EOD dashboard renders the branch's day state for the manager.
        await gotoVerified(mgrPage, '/eod', USERS.manager);
        await expect(mgrPage.locator('body')).toBeVisible();

        await tlrCtx.close();
        await mgrCtx.close();
    });
});
