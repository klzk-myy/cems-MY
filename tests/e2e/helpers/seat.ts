import { expect, type BrowserContext, type Page } from '@playwright/test';
import { gotoVerified, resolveMfa } from './auth';
import { baseUrl, type E2eUser } from './env';

// Resolved per-run from the acting user's /user payload — the seed's
// branch ids differ between environments (staging has drifted past the
// seeder's fresh-install set).
async function me(ctx: BrowserContext, page: Page): Promise<{ id: number; branch_id: number }> {
    const res = await api(ctx, page, 'get', '/user');
    return (await res.json()).data;
}

// Session float quantities — the counter opening-request floats become the
// day's TillBalance rows (stock-cash/open then 422s as a same-day duplicate),
// so MYR must cover every Buy payout a spec can stack on one till. Sized for
// the money path's ~RM10.5k buys incl. a retry, without draining the 200k
// branch pool across the run's seated sessions.
const FLOATS = { MYR: '30000', USD: '5000' };
const LIMITS = { MYR: '50000000', USD: '50000000' };

// Stateful Sanctum auth keys off Referer/Origin matching APP_URL; without
// them the session cookie is ignored (401). Stateful requests also traverse
// the web pipeline, so CSRF applies (419 without the token). The token comes
// from the XSRF-TOKEN cookie — never the page meta: the acting page can be a
// bare JSON/error response (e.g. back() landing on a recorded /api/v1 URL),
// which carries no <head> at all and hangs a locator read for the full
// timeout.
export async function api(ctx: BrowserContext, _page: Page, method: 'get' | 'post', path: string, data?: unknown) {
    const base = baseUrl();
    return ctx.request.fetch(`${base}/api/v1${path}`, {
        method,
        data,
        headers: { referer: `${base}/`, origin: base, 'x-xsrf-token': await xsrf(ctx, base) },
    });
}

async function xsrf(ctx: BrowserContext, base: string): Promise<string> {
    const value = (await ctx.cookies(base)).find((c) => c.name === 'XSRF-TOKEN')?.value;
    return decodeURIComponent(value ?? '');
}

// A booking needs a real customer row — the fresh CI seed ships none, so
// journeys register their own through the full create form. Every CDD
// profile field is populated: assertCustomerCddRequirements reads them off
// the record (not the transaction payload), and a bare quick-create leaves
// them null, which 302s any Standard-tier booking back to the form. Name,
// ID and phone are unique per call — id_number_hash/phone_hash are unique
// indexes, and an exact name keeps the typeahead match deterministic.
export async function createCustomer(
    ctx: BrowserContext, page: Page, name?: string, user?: E2eUser,
): Promise<{ id: number; full_name: string }> {
    const base = baseUrl();
    if (user) {
        // Stored sessions can die between specs (idle timeout invalidates
        // server-side; a remember-recaller then masks the bounce as a
        // /dashboard landing). Healing also lands the page on a real
        // csrf-meta route so the token read below is always fresh.
        await gotoVerified(page, '/customers/create', user);
    }
    const suffix = `${Date.now().toString(36)}${Math.floor(Math.random() * 36 ** 3).toString(36)}`;
    const fullName = name ?? `E2E Journey ${suffix}`;
    // phone_hash is a unique blind index — digits must derive from the
    // suffix's actual charcodes (mapping every letter to '2' collides
    // whenever two suffixes share digit positions). Referer points at the
    // form like a real browser so a validation back() lands there, not on
    // '/' — which reads as a misleading /dashboard bounce.
    const res = await ctx.request.post(`${base}/customers`, {
        form: {
            _token: await csrf(page),
            full_name: fullName,
            id_type: 'Passport',
            id_number: `E2E${suffix.toUpperCase()}`,
            date_of_birth: '1990-01-01',
            nationality: 'MY',
            address: '1 Jalan Testing, Kuala Lumpur',
            phone: `+601${suffix.replace(/./g, (c) => String(c.charCodeAt(0) % 10)).slice(-8).padStart(8, '0')}`,
            occupation: 'Engineer',
            employer_name: 'Test Corp',
        },
        headers: { referer: `${base}/customers/create`, origin: base, 'x-xsrf-token': await xsrf(ctx, base) },
    });
    const id = Number(res.url().match(/\/customers\/(\d+)/)?.[1]);
    expect(id, `customer store did not land on /customers/{id} (url=${res.url()})`).toBeGreaterThan(0);
    return { id, full_name: fullName };
}

// Raw session token for classic form posts (_token field). Reads the page
// meta when the current URL is a real document; recovers by loading the
// dashboard when the page is a meta-less JSON or error response.
export async function csrf(page: Page): Promise<string> {
    const meta = page.locator('meta[name="csrf-token"]');
    const token = await meta.getAttribute('content', { timeout: 3_000 }).catch(() => null);
    if (token) {
        return token;
    }
    await page.goto('/dashboard');
    return (await page.locator('meta[name="csrf-token"]').getAttribute('content'))!;
}

export async function primeMfa(page: Page, user: E2eUser) {
    await page.goto('/mfa/verify');
    await resolveMfa(page, user);
}

export async function branchCounters(ctx: BrowserContext, page: Page): Promise<Array<{ id: number; code: string }>> {
    const user = await me(ctx, page);
    const res = await api(ctx, page, 'get', `/branches/${user.branch_id}/counters`);
    const body = await res.json();
    const list = body.data?.data ?? body.data ?? body;
    expect(Array.isArray(list), `counters list: ${JSON.stringify(body).slice(0, 200)}`).toBe(true);
    return list;
}

// Counter sessions have no web UI (CounterService is API-only — CLAUDE.md).
// closeSession demands a declared float for EVERY open till currency
// (MissingClosingFloatException), and variance past the red threshold needs a
// manage_counters supervisor — both supplied here.
export async function closeCounterSession(ctx: BrowserContext, page: Page, counterId: number, supervisorId: number) {
    const res = await api(ctx, page, 'post', `/counters/${counterId}/close`, {
        closing_floats: { MYR: '0', USD: '0', EUR: '0', GBP: '0', SGD: '0', AUD: '0' },
        notes: 'E2E day close',
        supervisor_id: supervisorId,
    });
    // 200 = closed; 404 = no open session on this counter.
    expect([200, 404], `close session on counter ${counterId}: ${await res.text()}`).toContain(res.status());
}

// till-report renders the day's Open/Closed badge for a counter code — the
// probe that distinguishes "till rows still writable" from "closed for the
// day" (a redirect back means no rows exist at all). Probed via request, not
// navigation: back() targets the session's _previous.url, and consecutive
// empty probes on a page ping-pong between till-report URLs
// (ERR_TOO_MANY_REDIRECTS). XHR keeps the probe from writing _previous.url,
// so it can't poison a later real back() either.
async function tillState(mgrCtx: BrowserContext, code: string): Promise<'none' | 'open' | 'closed'> {
    const res = await mgrCtx.request.get(`${baseUrl()}/stock-cash/till-report?till_id=${code}`, {
        maxRedirects: 0,
        headers: { 'x-requested-with': 'XMLHttpRequest' },
    });
    if (res.status() !== 200) {
        return 'none';
    }
    const html = await res.text();
    if (/>\s*Closed\s*</.test(html)) {
        return 'closed';
    }
    if (/>\s*Open\s*</.test(html)) {
        return 'open';
    }
    return 'none';
}

// A day's till rows are immutable once closed — a session-close permanently
// burns that counter's currencies for the business date (fiscal close). The
// teller's seat therefore rotates to whichever counter still has clean till
// space. Returns the counter code the teller is seated on.
export async function ensureSeated(
    tlrCtx: BrowserContext, tlrPage: Page,
    mgrCtx: BrowserContext, mgrPage: Page,
    supervisorId: number,
): Promise<string | null> {
    const counters = await branchCounters(tlrCtx, tlrPage);
    const teller = await me(tlrCtx, tlrPage);
    const clean = [];
    let sessionBlocked = false;

    for (const counter of counters) {
        // Burned counters can't host a seat today — a closed same-day till
        // row rejects both openSession and openTill.
        if (await tillState(mgrCtx, counter.code) === 'closed') {
            continue;
        }
        clean.push(counter);

        // The real morning-open flow: the request lands pending allocations
        // (which approve-and-open requires), then the approval creates the
        // session, its till rows and the active allocations in one call.
        await api(tlrCtx, tlrPage, 'post', `/counters/${counter.id}/opening-request`, {
            requested_floats: FLOATS,
        });
        const approve = await api(mgrCtx, mgrPage, 'post', `/counters/${counter.id}/approve-and-open`, {
            teller_id: teller.id,
            approved_floats: FLOATS,
            daily_limits: LIMITS,
        });
        if (approve.status() === 200) {
            await rejectPendingAllocations(mgrCtx, mgrPage);
            return counter.code; // fresh seat landed
        }
        if ((await approve.text()).includes('already open today')) {
            // Ambient seat: a session from a prior spec already lives here
            // and its tills are still open (state !== 'closed' above). The
            // probe's own pending request (and any stale ones) get rejected.
            await rejectPendingAllocations(mgrCtx, mgrPage);
            return counter.code;
        }
        // "already at a counter" → a session lives elsewhere and blocks
        // every fresh seat until it's closed.
        sessionBlocked = true;
    }

    // A stray session on a burned counter blocks all seating — close it and
    // re-seat on the first clean counter.
    if (sessionBlocked && clean.length) {
        for (const counter of counters) {
            await closeCounterSession(tlrCtx, tlrPage, counter.id, supervisorId);
        }
        for (const counter of clean) {
            await api(tlrCtx, tlrPage, 'post', `/counters/${counter.id}/opening-request`, {
                requested_floats: FLOATS,
            });
            const approve = await api(mgrCtx, mgrPage, 'post', `/counters/${counter.id}/approve-and-open`, {
                teller_id: teller.id,
                approved_floats: FLOATS,
                daily_limits: LIMITS,
            });
            if (approve.status() === 200) {
                await rejectPendingAllocations(mgrCtx, mgrPage);
                return counter.code;
            }
        }
    }

    await rejectPendingAllocations(mgrCtx, mgrPage).catch(() => {});
    return null;
}

// approve-and-open probes and ambient seats leave pending allocation
// requests behind — they accumulate across runs and block the EOD checklist.
// The manager rejects the branch's pending queue through /allocations —
// day-end semantics: no pending request should survive close anyway.
export async function rejectPendingAllocations(
    ctx: BrowserContext, page: Page,
): Promise<void> {
    const token = await csrf(page);
    const base = baseUrl();
    // Rejections shift pagination — always re-read page 1 until it is clear.
    for (let pass = 0; pass < 6; pass++) {
        await page.goto('/allocations?status=pending');
        await page.waitForLoadState('load');
        const ids = await page.locator('a[href*="/allocations/"]').evaluateAll(
            (links) => links.map((a) => (a.getAttribute('href') ?? '').match(/\/allocations\/(\d+)/)?.[1]).filter(Boolean) as string[],
        );
        if (ids.length === 0) { break; }
        for (const id of ids) {
            await ctx.request.post(`${base}/allocations/${id}/reject`, { form: { _token: token } });
        }
    }
}

// settle() is a no-op once a workflow reaches Settled, so floats created
// after a reopen can never be swept by re-settling — they must be returned
// through the manager's return-to-pool endpoint before finalize unlocks.
export async function returnActiveAllocations(
    ctx: BrowserContext, page: Page,
): Promise<void> {
    const token = await csrf(page);
    const base = baseUrl();
    for (let pass = 0; pass < 6; pass++) {
        await page.goto('/allocations?status=active');
        await page.waitForLoadState('load');
        const ids = await page.locator('a[href*="/allocations/"]').evaluateAll(
            (links) => links.map((a) => (a.getAttribute('href') ?? '').match(/\/allocations\/(\d+)/)?.[1]).filter(Boolean) as string[],
        );
        if (ids.length === 0) { break; }
        for (const id of ids) {
            await ctx.request.post(`${base}/allocations/${id}/return-to-pool`, { form: { _token: token } });
        }
    }
}

// stock-cash/open creates the day's till row; a same-day row (open or closed)
// rejects with "already open", which also covers the ambient-seat case.
export async function openTill(ctx: BrowserContext, page: Page, till: string, currency: string) {
    const token = await csrf(page);
    const base = baseUrl();
    const res = await ctx.request.post(`${base}/stock-cash/open`, {
        form: {
            _token: token,
            till_id: till,
            currency_code: currency,
            opening_balance: '100000',
        },
    });
    expect([200, 302, 303, 422]).toContain(res.status());
}
