import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Server-side validation errors are a browser-visible contract: the
 * redirected re-render must show the rule's message AND repopulate the
 * user's input via old(). PHPUnit sees the 302 + session errors but never
 * the rendered form state. (HTML5 `required` blocks an empty-field POST
 * before it reaches the server, so the failing value must pass client
 * validation and trip a server-only rule — MyKad format.)
 */

test.use({ storageState: authFile('teller') });

test('a rejected create re-renders errors and keeps the entered values', async ({ page }) => {
  await gotoVerified(page, '/customers/create', USERS.teller);

  await page.locator('input[name="full_name"]').fill('E2E Validation Errors');
  await page.locator('select[name="id_type"]').selectOption({ label: 'MyKad (Malaysian IC)' });
  // Valid HTML5 input, invalid MyKad — MyKadFormatRule is server-only.
  await page.locator('input[name="id_number"]').fill('123');
  await page.locator('select[name="nationality"]').selectOption({ label: 'Malaysian' });
  await page.locator('form[action*="customers"] button[type="submit"]').click();

  // Redirected back to the create form with the rule's message rendered.
  await expect(page).toHaveURL(/customers/);
  await expect(page.getByText(/MyKad ID must be in format/).first()).toBeVisible({ timeout: 15_000 });

  // old() repopulated the submitted values.
  await expect(page.locator('input[name="full_name"]')).toHaveValue('E2E Validation Errors');
  await expect(page.locator('input[name="id_number"]')).toHaveValue('123');
});
