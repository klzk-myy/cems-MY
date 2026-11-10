import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The journal reverse dialog is opened by a custom Alpine event
 * ($dispatch('open-reverse-modal') → @open-reverse-modal.window), not by
 * direct state — a break in the dispatch or the window listener leaves a
 * dead button. Only open/close is asserted; reversing is destructive.
 */

test.use({ storageState: authFile('admin') });

test('the reverse modal opens via the dispatched event and closes', async ({ page }) => {
  await gotoVerified(page, '/accounting/journal', USERS.admin);

  const viewLink = page.getByRole('link', { name: 'View' }).first();
  await expect(viewLink).toBeVisible();
  await viewLink.click();

  const reverseBtn = page.getByRole('button', { name: 'Reverse' });
  await expect(reverseBtn).toBeVisible();
  await reverseBtn.click();

  const modal = page.locator('[x-data="journalShow"] [x-show="open"]');
  await expect(modal).toBeVisible();
  await expect(modal.getByRole('button', { name: 'Cancel' })).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(modal).toBeHidden();
});
