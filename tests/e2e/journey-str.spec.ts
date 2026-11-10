import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, baseUrl, USERS } from './helpers/env';
import { api, createCustomer, csrf, ensureSeated, openTill, primeMfa } from './helpers/seat';

const RATE = 4.7;

// A single booking over the RM50k velocity threshold flags the customer's
// 24h aggregate (checkAmountThreshold counts the new amount itself), which
// TransactionCreatedListener turns into a FlaggedTransaction + triage Alert.
// The STR path then walks the real compliance chain: alert → case → resolve
// → close → auto-drafted report → submit → acknowledge.
test.describe('STR filing journey', () => {
    test.describe.configure({ mode: 'serial' });

    let tlrCtx: BrowserContext;
    let comCtx: BrowserContext;
    let comPage: Page;
    let caseUrl = '';
    let customerName = '';
    let customerId = 0;

    test.beforeAll(async ({ browser }) => {
        tlrCtx = await browser.newContext({ storageState: authFile('teller') });
        const tlrPage = await tlrCtx.newPage();
        comCtx = await browser.newContext({ storageState: authFile('compliance') });
        comPage = await comCtx.newPage();
        const mgrCtx = await browser.newContext({ storageState: authFile('manager') });
        const mgrPage = await mgrCtx.newPage();
        await primeMfa(tlrPage, USERS.teller);
        await primeMfa(mgrPage, USERS.manager);
        await primeMfa(comPage, USERS.compliance);

        const me = await (await api(mgrCtx, mgrPage, 'get', '/user')).json();
        const till = await ensureSeated(tlrCtx, tlrPage, mgrCtx, mgrPage, me.data.id);
        expect(till, 'no counter with clean till space today').not.toBeNull();

        const admCtx = await browser.newContext({ storageState: authFile('admin') });
        const admPage = await admCtx.newPage();
        await gotoVerified(admPage, '/stock-cash', USERS.admin);
        await openTill(admCtx, admPage, till!, 'USD');
        await openTill(admCtx, admPage, till!, 'MYR');
        await admCtx.close();

        // Active USD allocation is a booking prerequisite (teller custody).
        const teller = await (await api(tlrCtx, tlrPage, 'get', '/user')).json();
        const token = await csrf(mgrPage);
        const res = await mgrCtx.request.post(`${baseUrl()}/allocations`, {
            form: {
                _token: token,
                user_id: String(teller.data.id),
                'lines[0][currency_code]': 'USD',
                'lines[0][quantity]': '15000',
                daily_limit_myr: '50000000',
            },
        });
        expect([200, 302, 303, 422]).toContain(res.status());
        await mgrCtx.close();

        const customer = await createCustomer(tlrCtx, tlrPage, undefined, USERS.teller);
        customerName = customer.full_name;
        customerId = customer.id;

        // One ~RM50k booking trips the velocity check on its own amount —
        // the flag lands on this transaction and feeds the case's STR
        // trigger sum. It stays PendingApproval (hold is fine — approval
        // is not part of this journey). The quantity carries a jitter:
        // checkRecentDuplicate rejects an identical user+qty+currency+type
        // resubmit within 30s, which a retried attempt would otherwise hit.
        const bookRes = await api(tlrCtx, tlrPage, 'post', '/transactions', {
            customer_id: customer.id,
            type: 'Buy',
            currency_code: 'USD',
            quantity: 10700 + Math.floor(Math.random() * 100),
            rate: RATE,
            purpose: 'Travel',
            source_of_funds: 'Salary savings',
            source_of_wealth: 'Business income',
        });
        expect(bookRes.status(), `booking failed: ${JSON.stringify(await bookRes.json().catch(() => null))}`).toBeLessThan(300);
        expect(bookRes.headers()['content-type'] ?? '', `booking bounced (url=${bookRes.url()})`).toContain('application/json');
    });

    test.afterAll(async () => {
        await tlrCtx?.close();
        await comCtx?.close();
    });

    test('flag → alert → case → close → STR draft → submit → acknowledge', async () => {
        test.setTimeout(120_000);

        // Alerts for the new customer land via the queued monitoring
        // listener. The triage index paginates by priority (50/page), so
        // find the ids through the API instead of hunting pages. Matching
        // is on the alert's customer_id — a name match could pull an
        // unrelated alert into the case, and createFromAlerts rejects a
        // multi-customer set outright.
        let alertIds: string[] = [];
        for (let attempt = 0; attempt < 15 && alertIds.length === 0; attempt++) {
            // per_page is capped at 100 — page through the queue until the
            // customer's alerts surface (ambient alerts from other runs
            // may bury them beyond page 1).
            for (let p = 1; p <= 5 && alertIds.length === 0; p++) {
                const res = await api(comCtx, comPage, 'get', `/compliance/alerts?per_page=100&page=${p}`);
                const body = await res.json().catch(() => null);
                const rows: any[] = body?.data?.data ?? body?.data ?? [];
                if (!Array.isArray(rows) || rows.length === 0) break;
                alertIds = rows
                    .filter((a) => Number(a.customer_id ?? a.customer?.id) === customerId)
                    .map((a) => String(a.id));
            }
            if (alertIds.length === 0) {
                await comPage.waitForTimeout(2_000);
            }
        }
        expect(alertIds.length, `no triage alert for ${customerName}`).toBeGreaterThan(0);

        // Create a case from those alerts (the index form posts the same
        // contract — alerts-bulk.spec.ts covers the checkbox UI itself).
        // maxRedirects:0 keeps the raw response: success is a 302 Location
        // to /compliance/cases/{id}; a ValidationException answers 422 JSON
        // (Accept:json) with its error bag; a CaseManagementException is a
        // 302 back to the referer. On any non-success the alerts' live
        // columns are dumped into the error for diagnosis.
        const params = new URLSearchParams();
        params.append('_token', await csrf(comPage));
        for (const id of alertIds) params.append('alert_ids[]', id);
        const caseRes = await comCtx.request.fetch(`${baseUrl()}/compliance/cases`, {
            method: 'POST',
            maxRedirects: 0,
            headers: {
                'content-type': 'application/x-www-form-urlencoded',
                'accept': 'application/json',
                referer: `${baseUrl()}/compliance/alerts`,
                origin: baseUrl(),
            },
            data: params.toString(),
        });
        const location = caseRes.headers()['location'] ?? '';
        caseUrl = new URL(location || '/compliance/cases', baseUrl()).pathname;
        if (caseRes.status() !== 302 || !/\/compliance\/cases\/\d+/.test(caseUrl)) {
            const probe = await api(comCtx, comPage, 'get', '/compliance/alerts?per_page=100');
            const probeBody = await probe.json().catch(() => null);
            const probeRows: any[] = probeBody?.data?.data ?? probeBody?.data ?? [];
            const diag = probeRows
                .filter((a) => alertIds.includes(String(a.id)))
                .map((a) => `alert ${a.id}: customer=${a.customer_id} case=${a.case_id} status=${a.status}`)
                .join(' | ');
            const body = await caseRes.text().catch(() => '');
            throw new Error(`case store rejected (alerts=${alertIds.join(',')} status=${caseRes.status()} loc=${location}): ${body.slice(0, 600) || diag}`);
        }

        // Close requires every linked alert terminal. Dismiss (reject) is
        // used rather than resolve: resolveAlert clears alerts.case_id,
        // which would orphan the case's flagged-transaction link and zero
        // the STR trigger sum. Dismissal keeps the linkage.
        for (const id of alertIds) {
            const res = await comCtx.request.post(`${baseUrl()}/compliance/alerts/${id}/dismiss`, {
                form: { _token: await csrf(comPage), reason: 'E2E STR journey — false positive' },
            });
            expect([200, 302, 303]).toContain(res.status());
        }

        // Closing the case auto-drafts the STR when the flagged aggregate
        // meets the reporting threshold (the ~RM50k booking does).
        const close = await comCtx.request.post(caseUrl, {
            form: { _token: await csrf(comPage), _method: 'PATCH', status: 'closed', resolution: 'WarningIssued', notes: 'E2E close' },
        });
        expect([200, 302, 303], `case close status ${close.status()}`).toContain(close.status());

        await gotoVerified(comPage, '/str?status=Draft', USERS.compliance);
        const row = comPage.locator(`tr:has-text("${customerName}")`).first();
        await expect(row, 'no drafted STR for the case customer').toBeVisible({ timeout: 15_000 });
        await row.locator('a', { hasText: 'View' }).click();
        await comPage.waitForLoadState('load');

        await comPage.fill('input[name="bnm_reference"]', `BNM-E2E-${Date.now().toString(36).toUpperCase()}`);
        await Promise.all([
            comPage.waitForLoadState('load'),
            comPage.locator('button', { hasText: 'Submit to BNM' }).click(),
        ]);
        await expect(comPage.locator('body')).toContainText('Submitted');

        const ack = comPage.locator('button', { hasText: 'Mark Acknowledged' });
        if (await ack.count()) {
            await Promise.all([comPage.waitForLoadState('load'), ack.click()]);
            await expect(comPage.locator('body')).toContainText(/Acknowledged/i);
        }
    });
});
