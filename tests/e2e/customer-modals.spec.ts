import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Customer freeze/unfreeze/close confirmations are Alpine modals on the
 * customer page. Opening the Freeze dialog and dismissing it (backdrop /
 * Escape) exercises the modal state machine without mutating anything.
 */

test.use({ storageState: authFile('compliance') });

test('the freeze confirmation modal opens and dismisses', async ({ page }) => {
  // Register a uniquely-named customer so the show page exists for this run.
  const suffix = Date.now().toString().slice(-8);

  await gotoVerified(page, '/customers/create', USERS.compliance);
  await page.locator('input[name="full_name"]').fill(`E2E Modal ${suffix}`);
  await page.locator('select[name="id_type"]').selectOption({ label: 'Other ID' });
  await page.locator('input[name="id_number"]').fill(`E2E${suffix}M`);
  await page.locator('select[name="nationality"]').selectOption({ label: 'Malaysian' });
  await page.locator('input[name="date_of_birth"]').fill('1990-01-01');
  await page.locator('input[name="phone"]').fill(`+601${suffix}`);
  await page.locator('textarea[name="address"]').fill('1 E2E Street, Kuala Lumpur');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/customers/create'), { timeout: 15_000 }),
    page.locator('form[action*="customers"] button[type="submit"]').click(),
  ]);

  const freezeBtn = page.getByRole('button', { name: 'Freeze' });
  await expect(freezeBtn).toBeVisible();
  await freezeBtn.click();

  const modal = page.locator('h3', { hasText: /^Freeze Customer$/ });
  await expect(modal).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(modal).toBeHidden();
});
