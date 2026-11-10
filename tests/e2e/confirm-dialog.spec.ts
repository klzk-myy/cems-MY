import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Destructive-action forms gate submission through the delegated
 * `data-confirm` listener (window.confirm before submit). If that wiring
 * breaks, either nothing submits or — worse — destructive submits fire
 * with no confirmation at all. Only the cancel path is asserted: the
 * accept path is an ordinary form POST, and actually accepting would
 * disable a currency.
 */

test.use({ storageState: authFile('admin') });

test('dismissing the data-confirm dialog cancels the form submission', async ({ page }) => {
  await gotoVerified(page, '/system/currencies', USERS.admin);

  let dialogMessage = '';
  page.on('dialog', async (dialog) => {
    dialogMessage = dialog.message();
    await dialog.dismiss();
  });

  const disableForm = page.locator('form[data-confirm][action*="currencies"]').first();
  let submitted = false;
  page.on('request', (req) => {
    if (req.method() === 'POST' && req.url().includes('currencies')) {
      submitted = true;
    }
  });

  const urlBefore = page.url();
  await disableForm.locator('button[type="submit"]').click();

  // Give any stray submission a moment to fire before asserting silence.
  await page.waitForTimeout(500);
  expect(dialogMessage).toContain('?');
  expect(submitted).toBe(false);
  expect(page.url()).toBe(urlBefore);
});
