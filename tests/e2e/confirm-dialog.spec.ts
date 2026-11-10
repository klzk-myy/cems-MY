import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Destructive-action forms gate submission through the delegated
 * `data-confirm` listener, which opens the shared #confirm-dialog modal
 * (x-confirm-dialog) instead of window.confirm. If that wiring breaks,
 * either nothing submits or — worse — destructive submits fire with no
 * confirmation at all. Only cancel paths are asserted: the accept path is
 * an ordinary form POST, and actually accepting would disable a currency.
 */

test.use({ storageState: authFile('admin') });

test('dismissing the data-confirm dialog cancels the form submission', async ({ page }) => {
  await gotoVerified(page, '/system/currencies', USERS.admin);

  const disableForm = page.locator('form[data-confirm][action*="currencies"]').first();
  let submitted = false;
  page.on('request', (req) => {
    if (req.method() === 'POST' && req.url().includes('currencies')) {
      submitted = true;
    }
  });

  const urlBefore = page.url();
  await disableForm.locator('button[type="submit"]').click();

  const dialog = page.locator('#confirm-dialog');
  await expect(dialog).toBeVisible();
  await expect(dialog.locator('[data-confirm-message]')).toContainText('?');

  await dialog.getByRole('button', { name: 'Cancel' }).click();
  await expect(dialog).toBeHidden();

  // Give any stray submission a moment to fire before asserting silence.
  await page.waitForTimeout(500);
  expect(submitted).toBe(false);
  expect(page.url()).toBe(urlBefore);
});

test('escape cancels the data-confirm dialog without submitting', async ({ page }) => {
  await gotoVerified(page, '/system/currencies', USERS.admin);

  const disableForm = page.locator('form[data-confirm][action*="currencies"]').first();
  let submitted = false;
  page.on('request', (req) => {
    if (req.method() === 'POST' && req.url().includes('currencies')) {
      submitted = true;
    }
  });

  await disableForm.locator('button[type="submit"]').click();
  const dialog = page.locator('#confirm-dialog');
  await expect(dialog).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();

  await page.waitForTimeout(500);
  expect(submitted).toBe(false);
});
