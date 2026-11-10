import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The rates override modal is populated through a custom Alpine event:
 * each row's Override button $dispatch()es 'override-rate' with data-*
 * attributes, and the page-level listener fills the modal. A broken
 * dispatch or detail mapping silently opens an empty modal — invisible to
 * PHPUnit.
 */

test.use({ storageState: authFile('manager') });

test('the override modal opens populated from the clicked rate row', async ({ page }) => {
  await gotoVerified(page, '/rates', USERS.manager);

  const modal = page.locator('[x-show="showOverride"]');
  await expect(modal).toBeHidden();

  const overrideBtn = page.locator('button', { hasText: 'Override' }).first();
  const currency = await overrideBtn.getAttribute('data-currency');
  const buy = await overrideBtn.getAttribute('data-buy');
  expect(currency).toBeTruthy();

  await overrideBtn.click();

  await expect(modal).toBeVisible();
  // The dispatched detail populated the x-model'd fields.
  await expect(modal.locator('input[name="currency_code"]')).toHaveValue(currency!);
  if (buy) {
    await expect(modal.locator('input[name="rate_buy"]')).toHaveValue(buy);
  }

  // Close via the X — the dialog must leave the DOM-visible state again.
  await modal.locator('button[aria-label="Close"]').click();
  await expect(modal).toBeHidden();
});
