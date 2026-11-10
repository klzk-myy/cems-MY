import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The manual journal form does double-entry math entirely in Alpine:
 * debit/credit line add-remove (min two rows), live totals, and the
 * "Create Entry" submit gated on balanced. PHPUnit cannot see any of it —
 * it only sees POSTs that already passed the client-side gate.
 */

test.use({ storageState: authFile('admin') });

test('live totals and the balanced gate drive the journal form', async ({ page }) => {
  await gotoVerified(page, '/accounting/journal/create', USERS.admin);

  const debitInputs = page.locator('input[name*="[debit]"]');
  const creditInputs = page.locator('input[name*="[credit]"]');
  const totalDebit = page.locator('[x-text="totalDebit"]');
  const totalCredit = page.locator('[x-text="totalCredit"]');
  const difference = page.locator('[x-text="difference"]');
  const submit = page.locator('button[type="submit"]', { hasText: 'Create Entry' });
  const removeBtns = page.locator('button', { hasText: 'Remove' });

  // Two seeded lines, zero totals, submit gated off, removal locked.
  await expect(debitInputs).toHaveCount(2);
  await expect(totalDebit).toHaveText('0.00');
  await expect(totalCredit).toHaveText('0.00');
  await expect(difference).toHaveText('0.00');
  await expect(submit).toBeDisabled();
  await expect(removeBtns.first()).toBeDisabled();

  // One-sided entry unbalances — totals update live, submit stays gated.
  await debitInputs.first().fill('100');
  await expect(totalDebit).toHaveText('100.00');
  await expect(difference).toHaveText('100.00');
  await expect(submit).toBeDisabled();

  // Offsetting credit balances the entry — the gate releases.
  await creditInputs.nth(1).fill('100');
  await expect(totalCredit).toHaveText('100.00');
  await expect(difference).toHaveText('0.00');
  await expect(submit).toBeEnabled();

  // Rows can be added, and removal unlocks past the two-line minimum.
  await page.locator('button', { hasText: '+ Add Line' }).click();
  await expect(debitInputs).toHaveCount(3);
  await expect(removeBtns.first()).toBeEnabled();
  await removeBtns.last().click();
  await expect(debitInputs).toHaveCount(2);
  await expect(removeBtns.first()).toBeDisabled();
});
