import { type Browser, type BrowserContext, type Page } from '@playwright/test';
import { authFile, USERS } from './env';
import { primeMfa } from './seat';

// High-priority AML flags (Structuring/Velocity) accumulate with repeated
// same-day bookings and hard-block approval. The compliance flagged list
// posts PATCH /compliance/flags/{id}/resolve per row — this resolves every
// open flag on the given transaction through that same endpoint.
export async function resolveOpenFlags(
    browser: Browser,
    txnId: string,
): Promise<void> {
    const cmpCtx: BrowserContext = await browser.newContext({ storageState: authFile('compliance') });
    const cmpPage: Page = await cmpCtx.newPage();
    await primeMfa(cmpPage, USERS.compliance);
    const csrf = await cmpPage.locator('meta[name="csrf-token"]').getAttribute('content');

    // Flags persist via queued monitoring — some land seconds after the
    // approve response. Keep sweeping until a full pass finds nothing new.
    for (let attempt = 0; attempt < 4; attempt++) {
        if (attempt > 0) { await cmpPage.waitForTimeout(2000); }
        let matched = false;
        for (let page = 1; page <= 3; page++) {
            await cmpPage.goto(`/compliance/flagged?status=open&page=${page}`);
            await cmpPage.waitForLoadState('load');
            const forms = cmpPage.locator('form[action*="/flags/"][action*="/resolve"]');
            const count = await forms.count();
            for (let i = 0; i < count; i++) {
                const form = forms.nth(i);
                const rowText = await form.evaluate((f) => f.closest('tr')?.textContent ?? '');
                if (rowText.includes(txnId)) {
                    matched = true;
                    const action = (await form.getAttribute('action'))!;
                    await cmpCtx.request.post(action, { form: { _token: csrf!, _method: 'PATCH' } });
                }
            }
            if (count === 0) { break; }
        }
        if (!matched) { break; }
    }

    await cmpCtx.close();
}
