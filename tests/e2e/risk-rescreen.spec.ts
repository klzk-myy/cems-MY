import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The customer risk page's rescreen action is gated by an Alpine
 * confirmation modal (riskCustomer/showRescreen). Open + cancel only —
 * confirming would queue a real rescreen.
 */

test.use({ storageState: authFile('compliance') });

test('the rescreen confirmation modal opens and cancels', async ({ page }) => {
  await gotoVerified(page, '/compliance/risk-dashboard', USERS.compliance);

  const customerLink = page.locator('a[href*="/risk-dashboard/customer/"]').first();
  test.skip((await customerLink.count()) === 0, 'no customers on the risk dashboard');
  // Direct navigation — the row link sits inside a busy table that can
  // keep the click actionability check waiting.
  await page.goto((await customerLink.getAttribute('href'))!);

  const rescreenBtn = page.getByRole('button', { name: /rescreen|re-screen|re-run/i });
  await expect(rescreenBtn).toBeVisible();
  await rescreenBtn.click();

  // Nested overlay+dialog share the x-show — assert the form inside.
  const rescreenForm = page.locator('form[action*="/rescreen"]');
  await expect(rescreenForm).toBeVisible();
  await rescreenForm.getByRole('button', { name: 'Cancel' }).click();
  await expect(rescreenForm).toBeHidden();
});
