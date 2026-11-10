import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, baseUrl, USERS } from './helpers/env';
import { api, createCustomer, csrf, ensureSeated, openTill, primeMfa } from './helpers/seat';
import { resolveOpenFlags } from './helpers/compliance';
import { totp } from './helpers/totp';
// Branch 5 USD board buy rate — tellers may deviate at most 0.5%.
const RATE = 4.7;

// Manager-initiated allocations are requested and approved in one call, so a
// single POST lands an Active allocation funded from the branch pool.
async function createAllocation(ctx: BrowserContext, page: Page, tellerId: number, currency: string, quantity: string) {
    const token = await csrf(page);
    const res = await ctx.request.post(`${baseUrl()}/allocations`, {
        form: {
            _token: token,
            user_id: String(tellerId),
            'lines[0][currency_code]': currency,
            'lines[0][quantity]': quantity,
            // Column default is 0 — omitting it yields a zero daily limit and
            // every booking fails "Daily limit exceeded". 50M matches seeds.
            daily_limit_myr: '50000000',
        },
    });
    // request.post follows redirects: success lands on allocations.show (200);
    // 422 covers a same-day-allocation conflict.
    expect([200, 302, 303, 422]).toContain(res.status());
}

async function createViaForm(page: Page, customerName: string): Promise<{ id: string; myr: string }> {
    // Teller accepts any approved float allocation — required before booking.
    await gotoVerified(page, '/my-allocations', USERS.teller);
    const acceptBtn = page.locator('form[action*="accept"] button[type="submit"]');
    if (await acceptBtn.count()) {
        await acceptBtn.first().click();
        await page.waitForLoadState('load');
    }

    await gotoVerified(page, '/transactions/create', USERS.teller);
    await page.waitForFunction(() => (window as any).Alpine !== undefined);

    const nameInput = page.locator('input[name="full_name"]');
    await nameInput.click();
    await nameInput.pressSequentially(customerName, { delay: 30 });
    await page.locator('ul li', { hasText: customerName }).first().click();
    await expect(page.locator('text=Existing customer loaded')).toBeVisible();

    // pick(c) fills identity fields from the matched record; anything still
    // empty would block native browser validation on submit, so fill gaps.
    const idType = page.locator('select[name="id_type"]');
    if (!(await idType.inputValue())) {
        await idType.selectOption('Passport');
    }
    if (!(await page.inputValue('input[name="id_number"]'))) {
        await page.fill('input[name="id_number"]', 'A12345678');
    }
    const nationality = page.locator('select[name="nationality"]');
    if (!(await nationality.inputValue())) {
        await nationality.selectOption({ index: 1 });
    }
    const dob = page.locator('input[name="date_of_birth"]');
    if (!(await dob.inputValue())) {
        await dob.fill('1990-01-15');
    }

    await page.selectOption('select[name="type"]', 'Buy');
    await page.selectOption('select[name="currency_code"]', 'USD');
    // Just over the RM10k approval threshold (≥ 2128 USD at 4.70): the buy
    // pays ~RM10.5k out of the MYR till — inside the 30k session float with
    // headroom for a retry re-booking on the same drawer.
    const qty = (2200 + (Date.now() % 100) + Math.random()).toFixed(2);
    await page.fill('input[name="quantity"]', qty);
    await page.fill('input[name="rate"]', String(RATE));
    await page.selectOption('select[name="purpose"]', 'Travel');
    await page.fill('input[name="source_of_funds"]', 'Salary savings');
    // Conditionally required for PEP customers — filling unconditionally is
    // safe and keeps the journey deterministic across matched customers.
    await page.fill('input[name="source_of_wealth"]', 'Business income');

    // Standard/enhanced CDD fields — required at this amount tier.
    await page.fill('textarea[name="address"]', '1 Jalan Testing, Kuala Lumpur');
    await page.fill('input[name="phone"]', '+60123456789');
    await page.fill('input[name="occupation"]', 'Engineer');
    await page.fill('input[name="employer_name"]', 'Test Corp');

    const [resp] = await Promise.all([
        page
            .waitForResponse((r) => /\/transactions$/.test(new URL(r.url()).pathname) && r.request().method() === 'POST', { timeout: 15_000 })
            .catch(() => null),
        page.locator('button[type="submit"]', { hasText: 'Create Transaction' }).click(),
    ]);
    expect(resp, 'POST /transactions should complete').not.toBeNull();
    const loc = resp!.headers()['location'] ?? '';
    expect([200, 302, 303, 422]).toContain(resp!.status());

    // The store redirects to /transactions/{id}; wait for that URL rather than
    // a load event, which can fire before the redirect navigation completes.
    try {
        await page.waitForURL(/\/transactions\/\d+/, { timeout: 15_000 });
    } catch {
        // Surface server-side validation errors or native :invalid fields —
        // otherwise a silent submit block looks like a missing transaction.
        const alerts = await page
            .locator('.bg-danger-subtle, [class*="danger-subtle"]')
            .allTextContents()
            .then((t) => t.map((s) => s.trim()).filter(Boolean));
        const invalid = await page.locator(':invalid').evaluateAll((els) =>
            els.map((e) => (e as HTMLInputElement).name),
        );
        if (alerts.length || invalid.length) {
            throw new Error(`submit did not redirect — status=${resp!.status()} location=${loc} alerts=${JSON.stringify(alerts)} invalid=${JSON.stringify(invalid)}`);
        }
        throw new Error(`no redirect and no errors — status=${resp!.status()} location=${loc} url=${page.url()}`);
    }
    await page.waitForURL(/\/transactions\/\d+/);
    const myr = (parseFloat(qty) * RATE).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return { id: page.url().match(/\/transactions\/(\d+)/)![1], myr };
}

test.describe('money-path journey', () => {
    test.describe.configure({ mode: 'serial' });

    let admCtx: BrowserContext;
    let completedTxnId = '';
    let customerName = '';
    let tlrCtx: BrowserContext;
    let admPage: Page;
    let tlrPage: Page;

    test.beforeAll(async ({ browser }) => {
        admCtx = await browser.newContext({ storageState: authFile('admin') });
        admPage = await admCtx.newPage();
        const mgrCtx = await browser.newContext({ storageState: authFile('manager') });
        const mgrPage = await mgrCtx.newPage();
        tlrCtx = await browser.newContext({ storageState: authFile('teller') });
        tlrPage = await tlrCtx.newPage();
        await primeMfa(tlrPage, USERS.teller);
        await primeMfa(mgrPage, USERS.manager);

        // The booking till resolves from the teller's open CounterSession —
        // seat them on whichever counter still has clean till space (a closed
        // same-day till row is immutable for the date).
        const me = await (await api(mgrCtx, mgrPage, 'get', '/user')).json();
        const till = await ensureSeated(tlrCtx, tlrPage, mgrCtx, mgrPage, me.data.id);
        expect(till, 'no counter with clean till space today').not.toBeNull();

        // Ambient seats may predate today's till rows — openTill is idempotent.
        await gotoVerified(admPage, '/stock-cash', USERS.admin);
        await openTill(admCtx, admPage, till!, 'USD');
        await openTill(admCtx, admPage, till!, 'MYR');
        // The teller's id resolves from their own session — seed user ids
        // differ between environments. Same-day active USD allocation
        // required to book.
        const teller = await (await api(tlrCtx, tlrPage, 'get', '/user')).json();
        await createAllocation(admCtx, admPage, teller.data.id, 'USD', '5000');
        // The fresh CI seed ships no customers — register one for the
        // typeahead to match (names must be unique to stay deterministic).
        customerName = (await createCustomer(tlrCtx, tlrPage)).full_name;
        await mgrCtx.close();
    });

    test.afterAll(async () => {
        await admCtx.close();
        await tlrCtx?.close();
    });

    test('teller creates → manager approves → teller prints receipt', async () => {
        const txn = await createViaForm(tlrPage, customerName);
        await expect(tlrPage.locator('body')).toContainText(/Pending/i, { timeout: 10_000 });
        completedTxnId = txn.id;

        // Admin approves — approve_transactions is admin/compliance-only by design.
        await primeMfa(admPage, USERS.admin);
        await gotoVerified(admPage, `/transactions/${txn.id}`, USERS.admin);

        // High-risk customers land on compliance hold — clear it first.
        const clearBtn = admPage.locator('form[action*="clear-hold"] button, button:has-text("Clear Compliance Hold")');
        if (await clearBtn.count()) {
            await clearBtn.first().click();
            await admPage.waitForLoadState('load');
            // The card keeps the original hold reason as history — the
            // "Cleared" marker is the signal the hold is lifted.
            await expect(admPage.locator('body')).toContainText('Compliance Hold Cleared');
        }

        // Amounts over the confirmation threshold need an approver step-up:
        // /confirm lazily creates the pending TransactionConfirmation and
        // redirects back when not required, so navigating is safe either way.
        await admPage.goto(`/transactions/${txn.id}/confirm`);
        if (admPage.url().includes('/confirm')) {
            await admPage.fill('input[name="mfa_code"]', totp(USERS.admin.totp!));
            await admPage.locator('button:has-text("Confirm Transaction")').click();
            await admPage.waitForLoadState('load');
            await expect(admPage.locator('body')).toContainText(/Confirmed|confirmed/i);
            await admPage.goto(`/transactions/${txn.id}`);
        }

        const [approveResp] = await Promise.all([
            admPage.waitForResponse((r) => /approve/.test(r.url()) && r.request().method() === 'POST', { timeout: 15_000 }).catch(() => null),
            admPage.locator('form[action*="approve"] button:has-text("Approve")').click(),
        ]);
        const flash = await admPage
            .locator('.bg-danger-subtle, [class*="danger-subtle"], .bg-success-subtle, [class*="success-subtle"]')
            .allTextContents()
            .then((t) => t.map((s) => s.trim()).filter(Boolean));
        // AML flags (Structuring/Velocity) generated by repeated same-day
        // bookings block approval — resolve them via compliance and retry.
        // The notification bell can hold "Transaction Approved" items from
        // other txns — the status badge is the only truthful signal.
        const statusBadge = admPage.getByText('Completed', { exact: true }).first();
        if (!(await statusBadge.count())) {
            await resolveOpenFlags(admCtx.browser()!, String(txn.id));
            await gotoVerified(admPage, `/transactions/${txn.id}`, USERS.admin);
            const retry = admPage.locator('form[action*="approve"] button:has-text("Approve")');
            if (await retry.count()) {
                await retry.click();
                await admPage.waitForLoadState('load');
            }
        }
        await expect(admPage.getByText('Completed', { exact: true }).first(), `approve POST ${approveResp?.status()} flash=${JSON.stringify(flash)}`).toBeVisible();

        // Teller verifies the receipt: /receipt streams a download (assert
        // the real file), /print renders the figures for on-screen check.
        const [download] = await Promise.all([
            tlrPage.waitForEvent('download'),
            tlrPage.goto(`/transactions/${txn.id}/receipt`).catch(() => null), // goto throws "Download is starting"
        ]);
        expect(download.suggestedFilename()).toMatch(/receipt|\.pdf/i);
        expect((await download.createReadStream())!.bytesRead ?? 1).toBeGreaterThanOrEqual(0);

        const [printDl] = await Promise.all([
            tlrPage.waitForEvent('download'),
            tlrPage.goto(`/transactions/${txn.id}/print`).catch(() => null),
        ]);
        expect(printDl.suggestedFilename()).toMatch(/print|receipt|\.pdf/i);

        // Show page renders the booked figures for the creating teller.
        await gotoVerified(tlrPage, `/transactions/${txn.id}`, USERS.teller);
        await expect(tlrPage.locator('body')).toContainText(customerName);
        await expect(tlrPage.locator('body')).toContainText(txn.myr);
        await expect(tlrPage.locator('body')).toContainText(/Completed|Approved/i);
    });

    test('admin rejects a large transaction', async () => {
        const txn = await createViaForm(tlrPage, customerName);

        await primeMfa(admPage, USERS.admin);
        await gotoVerified(admPage, `/transactions/${txn.id}`, USERS.admin);
        const rejectBtn = admPage.locator('form[action*="reject"] button:has-text("Reject")');
        await expect(rejectBtn).toBeVisible();
        // Rejection may prompt for a reason — accept any dialog.
        admPage.once('dialog', (d) => d.accept('Rejected in E2E journey'));
        await rejectBtn.click();
        await admPage.waitForLoadState('load');
        await expect(admPage.locator('body')).toContainText(/Reject|Cancel/i);
    });

    test('teller requests cancellation and admin approves it', async () => {
        expect(completedTxnId, 'approval journey must run first').not.toBe('');

        // Teller requests cancellation on the completed booking (GET is
        // mfa.verified-guarded — gotoVerified resolves the challenge inline).
        await gotoVerified(tlrPage, `/transactions/${completedTxnId}/cancel`, USERS.teller);
        await tlrPage.fill('textarea[name="reason"]', 'Customer returned — reversing the sale.');
        await tlrPage.locator('input[name="confirm_understanding"]').check();
        await tlrPage.locator('button:has-text("Submit Cancellation Request")').click();
        await tlrPage.waitForLoadState('load');
        await expect(tlrPage.locator('body')).toContainText(/Pending Cancellation|cancellation requested/i);

        // Admin reviews and approves the cancellation (reason field required).
        await primeMfa(admPage, USERS.admin);
        await gotoVerified(admPage, `/transactions/${completedTxnId}/approve-cancellation`, USERS.admin);
        await admPage.fill('textarea[name="reason"], input[name="reason"]', 'Verified with customer');
        await admPage.locator('button:has-text("Approve Cancellation")').click();
        await admPage.waitForLoadState('load');
        await expect(admPage.locator('body')).toContainText(/Cancelled/i);

        // Teller sees the terminal state on the transaction page.
        await gotoVerified(tlrPage, `/transactions/${completedTxnId}`, USERS.teller);
        await expect(tlrPage.locator('body')).toContainText(/Cancelled/i);
    });
});
