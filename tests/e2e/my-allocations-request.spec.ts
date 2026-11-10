import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The teller-side allocation request reuses allocationCreate, but resolves
 * the pool hint from the teller's OWN branch (data-branch) rather than a
 * selected user — the same component, the other lookup path.
 */

test.use({ storageState: authFile('teller') });

test('the request form shows the own-branch pool hint per currency', async ({ page }) => {
  await gotoVerified(page, '/my-allocations/request', USERS.teller);
  await page.waitForFunction(() => {
    const el = document.querySelector<HTMLElement>('[x-data="allocationCreate"]');
    return !! (el && ((el as any)._x_dataStack?.length || (el as any).__x));
  });

  const currencySelect = page.locator('select[name="lines[0][currency_code]"], select[name="currency_code"]').first();
  const hint = page.getByText('Pool available:');
  await expect(hint.first()).toBeHidden();

  await currencySelect.selectOption('USD');
  await expect(hint.first()).toBeVisible();
});
