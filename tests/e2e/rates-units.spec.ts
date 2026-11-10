import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * On /rates/units, changing a currency's quote convention (unit or inverse
 * direction) clears its rate_buy/rate_sell inputs so pre-filled values from
 * the old convention can't be saved under the new one. This is a
 * data-safety behavior implemented only in a page-level script.
 */

test.use({ storageState: authFile('manager') });

test('changing the quote convention clears the staged rate inputs', async ({ page }) => {
  await gotoVerified(page, '/rates/units', USERS.manager);

  const inverseSelect = page.locator('select[name="rate_inverse"]').first();
  await expect(inverseSelect).toBeVisible();

  const formId = await inverseSelect.getAttribute('form');
  expect(formId).toBeTruthy();
  const buyInput = page.locator(`input[name="rate_buy"][form="${formId}"]`);
  const sellInput = page.locator(`input[name="rate_sell"][form="${formId}"]`);

  await buyInput.fill('9.9999');
  await sellInput.fill('8.8888');

  // Flip Direct ↔ Inverse — the change listener must wipe both rate inputs.
  const current = await inverseSelect.inputValue();
  await inverseSelect.selectOption(current === '0' ? '1' : '0');

  await expect(buyInput).toHaveValue('');
  await expect(sellInput).toHaveValue('');
});
