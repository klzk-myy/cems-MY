import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The transaction wizard is a client-side SPA: field gating (validStep1),
 * the live MYR conversion, and the step1 fetch + error-banner wiring all
 * live in Alpine and are invisible to PHPUnit, which can only hit each
 * /wizard/transactions/stepN endpoint in isolation.
 */

test.use({ storageState: authFile('teller') });

async function waitForWizard(page: import('@playwright/test').Page): Promise<void> {
  await page.waitForFunction(() => {
    const el = document.querySelector<HTMLElement>('[x-data="transactionWizard"]');
    return !! (el && ((el as any)._x_dataStack?.length || (el as any).__x));
  });
}

test('step 1 gates Continue on required fields and computes the MYR amount', async ({ page }) => {
  await gotoVerified(page, '/transactions/wizard', USERS.teller);
  await waitForWizard(page);

  const continueBtn = page.locator('button[type="submit"]', { hasText: /Continue|Checking/ });
  await expect(continueBtn).toBeDisabled();

  // Foreign amount × rate drives the readonly MYR field (direct quote,
  // unit 1: 100 USD × 4.70 = 470.00 MYR).
  await page.locator('input[x-model="formData.customer_id"]').fill('1');
  await page.locator('select[x-model="formData.type"]').selectOption('Buy');
  await page.locator('select[x-model="formData.currency_code"]').selectOption('USD');
  await page.locator('input[x-model="formData.quantity"]').fill('100');
  await page.locator('input[x-model="formData.rate"]').fill('4.70');
  await expect(page.locator('input[readonly][\\:value="amountMyr"]')).toHaveValue('470.00');

  // Still gated until purpose + source of funds are set.
  await expect(continueBtn).toBeDisabled();
  await page.locator('select[x-model="formData.purpose"]').selectOption('Travel');
  await page.locator('input[x-model="formData.source_of_funds"]').fill('Salary savings');
  await expect(continueBtn).toBeEnabled();
});

test('a failed step-1 response surfaces in the error banner', async ({ page }) => {
  await gotoVerified(page, '/transactions/wizard', USERS.teller);
  await waitForWizard(page);

  // A customer_id that cannot exist: the firstError() wiring must extract
  // whichever message the server returns (404 "Customer not found." when the
  // teller has an open till, otherwise the till/validation 422) into the
  // banner — the exact copy differs by environment, the render does not.
  await page.locator('input[x-model="formData.customer_id"]').fill('999999999');
  await page.locator('select[x-model="formData.type"]').selectOption('Buy');
  await page.locator('select[x-model="formData.currency_code"]').selectOption('USD');
  await page.locator('input[x-model="formData.quantity"]').fill('100');
  await page.locator('input[x-model="formData.rate"]').fill('4.70');
  await page.locator('select[x-model="formData.purpose"]').selectOption('Travel');
  await page.locator('input[x-model="formData.source_of_funds"]').fill('Salary savings');

  await page.locator('button[type="submit"]', { hasText: /Continue|Checking/ }).click();

  const banner = page.locator('[x-show="errorMessage"]');
  await expect(banner).toBeVisible({ timeout: 15_000 });
  await expect(banner).not.toBeEmpty();
  await expect(page.locator('h2', { hasText: 'Step 2' })).toBeHidden();
});
