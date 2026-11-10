import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Transfer cancel/reject confirmations are per-action Alpine modals
 * (transferModals). Only open + dismiss is exercised — confirming would
 * mutate a live transfer.
 */

test.use({ storageState: authFile('admin') });

test('the reject modal opens and dismisses on a pending transfer', async ({ page }) => {
  await gotoVerified(page, '/stock-transfers', USERS.admin);

  // Find a row in an actionable status — terminal transfers render no
  // Reject/Cancel buttons.
  const row = page.locator('tr', {
    hasText: /requested|approved|transit/i,
  }).first();
  test.skip((await row.count()) === 0, 'no actionable transfer seeded');
  await row.locator('a[href*="/stock-transfers/"]').first().click();

  const actionBtn = page.getByRole('button', { name: /reject|cancel/i }).first();
  test.skip((await actionBtn.count()) === 0, 'no actionable transfer');

  await actionBtn.click();
  const modal = page.locator('[x-data="transferModals"] [x-show]').filter({ hasText: /reject|cancel/i });
  await expect(modal.first()).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(modal.first()).toBeHidden();
});
