import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The create form's CDD tier badge and the conditional required markers
 * are computed in Alpine (cddTier + need()) as quantity×rate crosses the
 * RM 3,000 / RM 10,000 thresholds. PHPUnit sees only a rendered initial
 * state — it cannot see the tier change or the `required` attributes
 * flipping on.
 */

test.use({ storageState: authFile('teller') });

test('the CDD tier banner and required fields follow the MYR amount', async ({ page }) => {
  await gotoVerified(page, '/transactions/create', USERS.teller);
  await page.waitForFunction(() => {
    const el = document.querySelector<HTMLElement>('[x-data="transactionForm"]');
    return !! (el && ((el as any)._x_dataStack?.length || (el as any).__x));
  });

  await expect(page.getByText('Simplified CDD')).toBeVisible();
  const phone = page.locator('input[name="phone"]');
  const occupation = page.locator('input[name="occupation"]');

  // USD is unit 1, direct quote: 640 × 4.70 = RM 3,008 → Specific.
  await page.locator('select[name="currency_code"]').selectOption('USD');
  await page.locator('input[name="quantity"]').fill('640');
  await page.locator('input[name="rate"]').fill('4.70');
  await expect(page.getByText('Specific CDD applies')).toBeVisible();
  await expect(phone).not.toHaveAttribute('required', /.+/);

  // 2,130 × 4.70 = RM 10,011 → Standard: phone/occupation become required.
  await page.locator('input[name="quantity"]').fill('2130');
  await expect(page.getByText('Standard CDD applies')).toBeVisible();
  await expect(phone).toHaveAttribute('required', 'required');
  await expect(occupation).toHaveAttribute('required', 'required');
});
