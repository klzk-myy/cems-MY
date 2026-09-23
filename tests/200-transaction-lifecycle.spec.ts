// Transaction lifecycle coverage: the approval/rejection/cancellation/
// reversal state machine driven through the real web UI. Local-only browser
// probe (AGENTS.md §6) — run via
// `npx playwright test tests/200-transaction-lifecycle.spec.ts`.
//
// Covered flows (all previously browser-uncovered):
//   1. Compliance rejects a pending transaction        → Rejected
//   2. Teller requests cancellation, manager approves  → Cancelled
//   3. Manager rejects a cancellation request           → back to Pending Approval
//   4. Manager reverses a completed transaction; the refund is created
//      WITH a compliance hold, which compliance must clear before approving
//      and completing the refund                        → Reversed / Completed
//
// Amounts are chosen against the seeded thresholds:
//   RM 10,384 (Buy 2200 USD @ 4.72)  ≥ 10k auto-approve  → PendingApproval
//   RM   472 (Buy  100 USD @ 4.72)  <  10k               → auto-Completed
//   both < 50k manager-confirmation threshold, so no extra confirm step.

import { test, expect, type Page } from '@playwright/test';
import {
  BASE_URL,
  MARKET_RATES,
  RUN_SEED,
  ensureCounterSession,
  loginAs,
  logout,
  createCustomer,
  createTransaction,
  readHeaderStatus,
  NATIONALITY_CYCLE,
  getRealName,
  getRealAddress,
  getIdTypeAndNumber,
  getDob,
  phoneFor,
} from './support/helpers';

/** Pending-approval booking: Buy 2200 USD @ card rate = RM 10,384. */
const PENDING_QTY = '2200.00';
/** Auto-completing booking: Buy 100 USD @ card rate = RM 472. */
const SMALL_QTY = '100.00';

let bookingCounter = 0;

/** Create a unique customer and book one USD transaction for them. */
async function bookTransaction(
  page: Page,
  qty: string,
  type: 'Buy' | 'Sell' = 'Buy'
): Promise<string> {
  const i = ++bookingCounter;
  const nationality = NATIONALITY_CYCLE[(i - 1) % NATIONALITY_CYCLE.length];
  const name = getRealName(i, nationality);
  const email = `${RUN_SEED}lc${i}@test.com`;
  const { idType, idNumber } = getIdTypeAndNumber(nationality, i);
  const address = getRealAddress(nationality, i);
  const dob = getDob(nationality, i);

  const created = await createCustomer(
    page, name, email, idType, idNumber, nationality, phoneFor(i), address, dob
  );
  expect(created, `customer ${name} must be created`).toBe(true);

  const rate = type === 'Buy' ? MARKET_RATES.USD.buy : MARKET_RATES.USD.sell;
  const txId = await createTransaction(
    page, name, type, 'USD', 'Travel', 'Salary', 'Employment', qty, rate,
    { idType, idNumber, nationality, phone: phoneFor(i), address, dob, email }
  );
  expect(txId, `transaction (${type} ${qty} USD) must be booked`).not.toBeNull();
  return txId as string;
}

/** Open a transaction's detail page and return its header status. */
async function openTransaction(page: Page, txId: string): Promise<string> {
  await page.goto(`${BASE_URL}/transactions/${txId}`);
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('h1')).toContainText('Transaction Details');
  return readHeaderStatus(page);
}

test.describe('Transaction lifecycle: reject, cancel, reverse', () => {
  test('compliance rejects a pending transaction', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    await ensureCounterSession(page);

    await loginAs(page, 'teller1');
    const txId = await bookTransaction(page, PENDING_QTY);
    expect(await openTransaction(page, txId)).toBe('Pending Approval');
    await logout(page);

    await loginAs(page, 'compliance1');
    await openTransaction(page, txId);
    const rejectBtn = page.locator('form[action$="/reject"] button[type="submit"]');
    await expect(rejectBtn).toBeVisible();
    await rejectBtn.click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Rejected');
    await logout(page);
  });

  test('teller requests cancellation and manager approves it', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    await ensureCounterSession(page);

    // Teller books a pending deal and requests its cancellation.
    await loginAs(page, 'teller1');
    const txId = await bookTransaction(page, PENDING_QTY);
    expect(await openTransaction(page, txId)).toBe('Pending Approval');

    await page.goto(`${BASE_URL}/transactions/${txId}/cancel`);
    await page.waitForLoadState('domcontentloaded');
    const cancelForm = page.locator('form[action$="/cancel"]');
    await expect(cancelForm).toBeVisible();
    await cancelForm.locator('textarea[name="reason"]').fill('Customer entered the wrong currency pair');
    await cancelForm.locator('input[name="confirm_understanding"]').check();
    await cancelForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Pending Cancellation');
    await logout(page);

    // Manager (a different user than the requester) approves the cancellation.
    await loginAs(page, 'manager1');
    await openTransaction(page, txId);
    await page.getByRole('link', { name: 'Approve Cancellation' }).click();
    await page.waitForLoadState('domcontentloaded');
    const approveForm = page.locator('form[action$="/approve-cancellation"]');
    await expect(approveForm).toBeVisible();
    await approveForm.locator('textarea[name="reason"]').fill('Verified with the teller — correct to cancel');
    await approveForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Cancelled');
    await logout(page);
  });

  test('manager rejects a cancellation request', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    await ensureCounterSession(page);

    await loginAs(page, 'teller1');
    const txId = await bookTransaction(page, PENDING_QTY);
    expect(await openTransaction(page, txId)).toBe('Pending Approval');

    await page.goto(`${BASE_URL}/transactions/${txId}/cancel`);
    await page.waitForLoadState('domcontentloaded');
    const cancelForm = page.locator('form[action$="/cancel"]');
    await cancelForm.locator('textarea[name="reason"]').fill('Duplicate booking — please cancel');
    await cancelForm.locator('input[name="confirm_understanding"]').check();
    await cancelForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Pending Cancellation');
    await logout(page);

    // Manager rejects the request — the transaction returns to its prior
    // pending-approval state.
    await loginAs(page, 'manager1');
    await openTransaction(page, txId);
    await page.getByRole('link', { name: 'Reject Cancellation' }).click();
    await page.waitForLoadState('domcontentloaded');
    const rejectForm = page.locator('form[action$="/reject-cancellation"]');
    await expect(rejectForm).toBeVisible();
    await rejectForm.locator('textarea[name="reason"]').fill('Settlement already disbursed — cancellation not possible');
    await rejectForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Pending Approval');
    await logout(page);
  });

  test('manager reverses a completed transaction; compliance clears the hold, approves and completes the refund', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    await ensureCounterSession(page);

    // Teller books a small deal that auto-completes below the threshold.
    await loginAs(page, 'teller1');
    const txId = await bookTransaction(page, SMALL_QTY);
    expect(await openTransaction(page, txId)).toBe('Completed');
    await logout(page);

    // Manager reverses it. The compensating legs are booked immediately and
    // a refund transaction is created — the redirect lands on the refund.
    await loginAs(page, 'manager1');
    await openTransaction(page, txId);
    await page.getByRole('link', { name: 'Reverse Transaction' }).click();
    await page.waitForLoadState('domcontentloaded');
    const reverseForm = page.locator('form[action$="/reverse"]');
    await expect(reverseForm).toBeVisible();
    await reverseForm.locator('textarea[name="reason"]').fill('Customer disputed the rate after settlement was agreed');
    await reverseForm.locator('input[name="confirm_understanding"]').check();
    await reverseForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    const refundMatch = page.url().match(/\/transactions\/(\d+)/);
    expect(refundMatch, `expected a transaction detail URL, got ${page.url()}`).not.toBeNull();
    const refundId = (refundMatch as RegExpMatchArray)[1];
    // If the reversal failed, the URL stays on the original's reverse form
    // and the captured id equals the original — that must not pass silently.
    expect(refundId).not.toBe(txId);
    expect(await readHeaderStatus(page)).toBe('Pending Approval');

    // The original is now Reversed.
    expect(await openTransaction(page, txId)).toBe('Reversed');
    await logout(page);

    // The refund carries a compliance hold: the clear-hold gate must be
    // offered before approval can proceed (the approve service rejects
    // uncleared holds even though the button renders).
    await loginAs(page, 'compliance1');
    await openTransaction(page, refundId);
    const clearHoldBtn = page.locator('form[action$="/clear-hold"] button[type="submit"]');
    await expect(clearHoldBtn).toBeVisible();

    await clearHoldBtn.click();
    await page.waitForLoadState('domcontentloaded');
    await expect(clearHoldBtn).toHaveCount(0);

    // With the hold cleared, compliance approves — refunds stop at Approved
    // (never auto-complete) pending the physical settlement.
    const approveBtn = page.locator('form[action$="/approve"] button[type="submit"]');
    await expect(approveBtn).toBeVisible();
    await approveBtn.click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Approved');

    // Compliance completes the refund — the final leg of the reversal.
    const completeBtn = page.locator('form[action$="/complete-refund"] button[type="submit"]');
    await expect(completeBtn).toBeVisible();
    await completeBtn.click();
    await page.waitForLoadState('domcontentloaded');
    expect(await readHeaderStatus(page)).toBe('Completed');
    await logout(page);
  });


});
