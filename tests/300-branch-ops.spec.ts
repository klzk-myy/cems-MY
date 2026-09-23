// Branch operations coverage: stock transfers (maker/taker), branch
// day-close, and the MFA gate. Local-only browser probe (AGENTS.md §6) —
// run via `npx playwright test tests/300-branch-ops.spec.ts`.
//
// Covered flows (all previously browser-uncovered):
//   1. Preflight — the KL branch must be tradeable (a crashed earlier
//      day-close run may have left its business date frozen; admin reopens).
//   2. Stock transfer maker/taker — KL manager creates → admin approves as
//      taker (destination branch) → KL manager dispatches → admin receives
//      → admin completes.
//   3. Branch day-close — manager initiates → settles → finalizes (freezes
//      the business date); branch managers cannot reopen, admin can.
//   4. MFA gate — a dedicated throwaway teller account is enrolled,
//      gated, verified, and disabled (self-cleaning; never touches a real
//      staging account's authenticator).
//
// Staging reality: only teller1/manager1/compliance1/admin exist on the
// live DB (the AGENTS.md table lists manager2/teller2, but they are not
// seeded), so admin plays the cross-branch/taker roles and the MFA test
// provisions its own account. Branch names are DISCOVERED from the create
// form at runtime (the maker rule limits a non-admin manager's source
// options to their own branch), so the suite does not depend on hardcoded
// seed names.

import { test, expect, type Page } from '@playwright/test';
import {
  BASE_URL,
  TEST_PASSWORD,
  apiGet,
  closeOpenCounterSessions,
  loginAs,
  logout,
  readHeaderStatus,
  loadMfaState,
  saveMfaState,
  clearMfaState,
  totpAt,
  currentTimestep,
} from './support/helpers';

// Dedicated throwaway account for the MFA flow — enrolling MFA here can
// never lock a real staging account out of its authenticator.
const MFA_USERNAME = 'pw-mfa-teller';

/**
 * Resolve manager1's branch id. Each test resolves this itself — Playwright
 * restarts the worker after a failed test, so module-level state must not
 * be shared across tests.
 */
async function resolveManagerBranchId(page: Page): Promise<number> {
  await loginAs(page, 'manager1');
  const me = await apiGet(page, '/api/v1/user');
  const id: number = me.body?.data?.branch_id ?? 0;
  await logout(page);
  expect(id, 'manager1 must have a home branch').toBeGreaterThan(0);
  return id;
}

/**
 * Drive an active closure workflow (Initiated/Settled) to Finalized,
 * closing open counter sessions via the API when settlement is blocked.
 * The caller must be on the branch closing page, logged in with
 * manage_branch_closing, and pass the branch id for the counter API.
 */
async function driveClosureToFinalized(page: Page, branchId: number): Promise<void> {
  let status = await readHeaderStatus(page);

  if (status === 'Initiated') {
    await page.locator('form[action$="/settle"] button').click();
    await page.waitForLoadState('domcontentloaded');
    status = await readHeaderStatus(page);

    if (status !== 'Settled') {
      // Settlement is blocked while counter sessions are open (e.g. the
      // booking suites leave one) — close them and retry.
      console.log('   open counters block settlement — closing them');
      await closeOpenCounterSessions(page, branchId);
      await page.goto(`${BASE_URL}/branches/${branchId}/closing`);
      await page.waitForLoadState('domcontentloaded');
      await page.locator('form[action$="/settle"] button').click();
      await page.waitForLoadState('domcontentloaded');
      status = await readHeaderStatus(page);
    }
    expect(status, 'settlement must succeed once counters are closed').toBe('Settled');
    console.log('   settled — custody returned to pool');
  }

  if (status === 'Settled') {
    await page.locator('form[action$="/finalize"] button').click();
    await page.waitForLoadState('domcontentloaded');
    console.log('   business date frozen');
  }
}

test.describe('Branch operations: stock transfers, day-close, MFA', () => {
  test('preflight: trading branch is tradeable (reopen frozen day if needed)', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);

    const branchId = await resolveManagerBranchId(page);

    // A crashed earlier day-close run can leave EITHER an active
    // Initiated/Settled workflow (the business date freezes at initiation,
    // blocking transactions and journals) OR a finalized frozen date. Drive
    // any active workflow to finalized, then reopen — only cross-branch
    // users (admin/accountant) may reopen.
    await loginAs(page, 'admin');
    await page.goto(`${BASE_URL}/branches/${branchId}/closing`);
    await page.waitForLoadState('domcontentloaded');

    await driveClosureToFinalized(page, branchId);

    const reopenBtn = page.locator('form[action$="/reopen"] button');
    if (await reopenBtn.isVisible().catch(() => false)) {
      console.log('   frozen business date found — reopening');
      await reopenBtn.click();
      await page.waitForLoadState('domcontentloaded');
      await expect(reopenBtn).toHaveCount(0);
    }
    await expect(page.getByText('no new sessions, transactions, or journal entries').first()).toHaveCount(0);
    await logout(page);
  });

  test('stock transfer maker/taker: create → taker approve → dispatch → receive → complete', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    test.setTimeout(300_000);

    // Maker: manager1 creates a transfer from their own branch (the only
    // source option the maker rule allows) to another trading branch.
    const branchId = await resolveManagerBranchId(page);
    await loginAs(page, 'manager1');
    await page.goto(`${BASE_URL}/stock-transfers/create`);
    await page.waitForLoadState('domcontentloaded');
    const createForm = page.locator('form[action$="/stock-transfers"]');
    await expect(createForm).toBeVisible();

    const sourceName = await createForm
      .locator('select[name="source_branch_name"] option:not([value=""])')
      .first()
      .getAttribute('value');
    expect(sourceName, 'manager1 must see their own branch as a source option').toBeTruthy();
    await logout(page);

    // Destination: another TRADING branch (head office holds no foreign
    // stock and is rejected as a transfer party) — resolve via the admin
    // branches API, which exposes each branch's type.
    await loginAs(page, 'admin');
    const branchesRes = await apiGet(page, '/api/v1/branches');
    const tradingBranches: Array<{ id: number; name: string; type: string }> = branchesRes.body?.data ?? [];
    const destination = tradingBranches.find((b) => b.type === 'branch' && b.id !== branchId);
    expect(destination, 'a second trading branch must exist as a destination').toBeTruthy();
    await logout(page);
    const destName = (destination as { name: string }).name;

    await loginAs(page, 'manager1');
    await page.goto(`${BASE_URL}/stock-transfers/create`);
    await page.waitForLoadState('domcontentloaded');
    await createForm.locator('select[name="source_branch_name"]').selectOption(sourceName as string);
    await createForm.locator('select[name="destination_branch_name"]').selectOption({ label: destName as string });
    await createForm.locator('select[name="type"]').selectOption('Standard');
    await createForm.locator('select[name="items[0][currency_code]"]').selectOption('USD');
    await createForm.locator('input[name="items[0][quantity]"]').fill('100');
    // Rate/value are display hints — the service re-derives both from the
    // source position's cost basis.
    await createForm.locator('input[name="items[0][rate]"]').fill('4.7200');
    await createForm.locator('input[name="items[0][value_myr]"]').fill('472.00');
    await createForm.locator('textarea[name="notes"]').fill('Playwright maker/taker coverage transfer');
    await createForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    const transferMatch = page.url().match(/\/stock-transfers\/(\d+)/);
    expect(transferMatch, `expected the transfer detail page, got ${page.url()}`).not.toBeNull();
    const transferId = (transferMatch as RegExpMatchArray)[1];
    expect(await readHeaderStatus(page)).toBe('Requested');
    console.log(`   transfer #${transferId}: ${sourceName} → ${destName}`);
    await logout(page);

    // Taker approval: the destination side approves (admin acts as the
    // cross-branch taker on this staging DB — no second branch manager
    // account exists).
    await loginAs(page, 'admin');
    await page.goto(`${BASE_URL}/stock-transfers/${transferId}`);
    await page.waitForLoadState('domcontentloaded');
    const approveBmBtn = page.locator('form[action$="/approve-bm"] button[type="submit"]');
    await expect(approveBmBtn).toBeVisible();
    await approveBmBtn.click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Branch Manager Approved');
    await logout(page);

    // Source dispatch.
    await loginAs(page, 'manager1');
    await page.goto(`${BASE_URL}/stock-transfers/${transferId}`);
    await page.waitForLoadState('domcontentloaded');
    const dispatchBtn = page.locator('form[action$="/dispatch"] button[type="submit"]');
    await expect(dispatchBtn).toBeVisible();
    await dispatchBtn.click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('In Transit');
    await logout(page);

    // Destination receives the full quantity, then completes.
    await loginAs(page, 'admin');
    await page.goto(`${BASE_URL}/stock-transfers/${transferId}`);
    await page.waitForLoadState('domcontentloaded');
    const receiveForm = page.locator('form[action$="/receive"]');
    await expect(receiveForm).toBeVisible();
    const receivedInputs = receiveForm.locator('input[name*="quantity_received"]');
    const inputCount = await receivedInputs.count();
    expect(inputCount).toBeGreaterThan(0);
    for (let i = 0; i < inputCount; i++) {
      await receivedInputs.nth(i).fill('100');
    }
    await receiveForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Received');

    const completeBtn = page.locator('form[action$="/complete"] button[type="submit"]');
    await expect(completeBtn).toBeVisible();
    await completeBtn.click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Completed');
    await logout(page);
  });

  test('branch day-close: initiate → settle → finalize, then admin reopens', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    test.setTimeout(300_000);

    // /closing resolves the manager's own branch and forwards to it.
    const klBranchId = await resolveManagerBranchId(page);
    await loginAs(page, 'manager1');
    await page.goto(`${BASE_URL}/closing`);
    await page.waitForURL(/\/branches\/\d+\/closing/);
    await page.waitForLoadState('domcontentloaded');
    const branchMatch = page.url().match(/\/branches\/(\d+)\/closing/);
    expect(branchMatch).not.toBeNull();
    const closingBranchId = (branchMatch as RegExpMatchArray)[1];
    expect(Number(closingBranchId)).toBe(klBranchId);

    // Resume-or-start state machine: a crashed earlier run may have left an
    // Initiated or Settled workflow behind — continue it rather than fail.
    let status = await readHeaderStatus(page);
    if (status !== 'Initiated' && status !== 'Settled') {
      const frozen = await page.getByText('no new sessions, transactions, or journal entries').isVisible().catch(() => false);
      expect(frozen, 'business date still frozen — the preflight reopen failed').toBe(false);

      const initiateBtn = page.locator('form[action$="/initiate"] button');
      await expect(initiateBtn).toBeVisible();
      await initiateBtn.click();
      await page.waitForLoadState('domcontentloaded');
      status = await readHeaderStatus(page);
      expect(status).toBe('Initiated');
      console.log('   closure workflow initiated');
    }

    // Settle (closing open counter sessions when blocked) and finalize —
    // the finalize step freezes the branch's business date.
    await driveClosureToFinalized(page, klBranchId);
    await expect(page.getByText('no new sessions, transactions, or journal entries').first()).toBeVisible();

    // RBAC negative check: branch managers must NOT be offered a reopen —
    // only cross-branch users (admin/accountant) may reopen a finalized day.
    await expect(page.locator('form[action$="/reopen"] button')).toHaveCount(0);

    // Cross-branch reopen restores trading (leaves the branch tradeable for
    // the next run — this test is self-cleaning).
    await logout(page);
    await loginAs(page, 'admin');
    await page.goto(`${BASE_URL}/branches/${klBranchId}/closing`);
    await page.waitForLoadState('domcontentloaded');
    const reopenBtn = page.locator('form[action$="/reopen"] button');
    await expect(reopenBtn).toBeVisible();
    await reopenBtn.click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByText('no new sessions, transactions, or journal entries').first()).toHaveCount(0);
    console.log('   business date reopened by admin');
    await logout(page);
  });

  test('MFA gate: enrollment gates sensitive routes until verified, then disable restores access', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    test.setTimeout(300_000);

    // Provision the dedicated throwaway account (once — re-runs reuse it).
    // A login attempt is the existence probe: only when it fails does admin
    // create the account. The store route is gated by password.confirm, so
    // the first submit bounces to /confirm-password and the form is
    // re-submitted after stepping up.
    let haveAccount = false;
    try {
      await loginAs(page, MFA_USERNAME);
      haveAccount = true;
      await logout(page);
    } catch {
      haveAccount = false;
    }

    if (!haveAccount) {
      const klBranchId = await resolveManagerBranchId(page);
      await loginAs(page, 'admin');
      for (let attempt = 0; attempt < 2; attempt++) {
        await page.goto(`${BASE_URL}/users/create`);
        await page.waitForLoadState('domcontentloaded');
        const userForm = page.locator('form[action$="/users"]');
        await expect(userForm).toBeVisible();
        await userForm.locator('input[name="username"]').fill(MFA_USERNAME);
        await userForm.locator('input[name="email"]').fill(`${MFA_USERNAME}@playwright.local`);
        await userForm.locator('input[name="password"]').fill(TEST_PASSWORD);
        await userForm.locator('input[name="password_confirmation"]').fill(TEST_PASSWORD);
        await userForm.locator('select[name="role"]').selectOption('teller');
        await userForm.locator('select[name="branch_id"]').selectOption(String(klBranchId));
        await userForm.locator('input[name="is_active"]').check();
        await userForm.locator('button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');

        if (page.url().includes('/confirm-password')) {
          const confirmForm = page.locator('form[action*="confirm-password"]');
          await confirmForm.locator('input[name="password"]').fill(TEST_PASSWORD);
          await confirmForm.locator('button[type="submit"]').click();
          await page.waitForLoadState('domcontentloaded');
          continue;
        }
        break;
      }
      expect(page.url(), `user creation must complete (got ${page.url()})`).not.toContain('/users/create');
      console.log(`   account provisioned: ${MFA_USERNAME}`);
      await logout(page);
    } else {
      console.log(`   account already provisioned: ${MFA_USERNAME}`);
    }

    await loginAs(page, MFA_USERNAME);

    // ── Enrollment (or reuse from a previous run) ──
    // The setup page shows the base32 secret exactly once; enrolled users are
    // redirected to /mfa/verify instead. Persist the secret so re-runs can
    // still verify and disable (see helpers for the reset procedure).
    const saved = loadMfaState();
    let secret = '';
    await page.goto(`${BASE_URL}/mfa/setup`);
    await page.waitForLoadState('domcontentloaded');

    if (!page.url().includes('/mfa/verify')) {
      secret = ((await page.locator('code').first().textContent()) ?? '').trim();
      expect(secret, 'the setup page must expose the manual-entry key').toMatch(/^[A-Z2-7]{16,}$/);

      const setupForm = page.locator('form[action$="/mfa/setup"]');
      await setupForm.locator('input[name="code"]').fill(totpAt(secret, currentTimestep()));
      await setupForm.locator('button[type="submit"]').click();
      await page.waitForURL('**/mfa/recovery-codes', { timeout: 15000 });

      const recoveryCodes = (await page.locator('div.font-mono').allTextContents())
        .map((c) => c.trim())
        .filter((c) => c.length > 0);
      expect(recoveryCodes.length).toBeGreaterThan(0);
      saveMfaState({ username: MFA_USERNAME, secret, recoveryCodes });
      console.log(`   enrolled — ${recoveryCodes.length} recovery codes captured`);
    } else if (saved?.username === MFA_USERNAME && saved.secret) {
      secret = saved.secret;
      console.log('   already enrolled — reusing persisted secret');
    } else {
      test.skip(true, `${MFA_USERNAME} is MFA-enrolled but the secret is unknown — disable MFA on it or restore tests/support/.mfa-state.json`);
    }

    // ── The gate: sensitive routes redirect to MFA verification ──
    await page.goto(`${BASE_URL}/transactions/create`);
    await page.waitForLoadState('domcontentloaded');
    expect(page.url(), 'an enrolled teller must be bounced to MFA verification').toContain('/mfa/verify');

    // ── Verify with a fresh TOTP code → the session becomes MFA-verified ──
    const verifyStep = currentTimestep();
    const verifyForm = page.locator('form[action$="/mfa/verify"]');
    await verifyForm.locator('input[name="code"]').fill(totpAt(secret, verifyStep));
    await verifyForm.locator('button[type="submit"]').click();
    // The middleware does not preserve the intended URL, so a successful
    // verify lands on the dashboard fallback — the contract is that the
    // gated route is now directly reachable without another bounce.
    await page.waitForURL(/\/(dashboard|transactions\/create)/, { timeout: 15000 });
    await page.goto(`${BASE_URL}/transactions/create`);
    await page.waitForLoadState('domcontentloaded');
    expect(page.url(), 'a verified session must reach the booking form directly').not.toContain('/mfa/verify');
    await expect(page.locator('form[action*="transactions"]')).toBeVisible();
    console.log('   verified — booking form reachable');

    // ── Disable restores ungated access (self-cleaning) ──
    await page.goto(`${BASE_URL}/mfa/trusted-devices`);
    await page.waitForLoadState('domcontentloaded');
    const disableForm = page.locator('form[action$="/mfa/disable"]');
    await expect(disableForm).toBeVisible();

    // Replay protection: the disable code must come from a strictly newer
    // timestep than the one consumed by verify (±1-step tolerance applies,
    // so max(verifyStep + 1, now) is always accepted and always fresh).
    const disableStep = Math.max(verifyStep + 1, currentTimestep());
    await disableForm.locator('input[name="current_password"]').fill(TEST_PASSWORD);
    await disableForm.locator('input[name="code"]').fill(totpAt(secret, disableStep));
    await disableForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    // The disable card only renders for enrolled users.
    await expect(page.locator('form[action$="/mfa/disable"]')).toHaveCount(0);
    clearMfaState();
    console.log('   MFA disabled');

    // ── The gate is gone after disable ──
    await page.goto(`${BASE_URL}/transactions/create`);
    await page.waitForLoadState('domcontentloaded');
    expect(page.url(), 'an un-enrolled teller must reach the booking form directly').not.toContain('/mfa/verify');
    await expect(page.locator('form[action*="transactions"]')).toBeVisible();
    await logout(page);
  });



});
