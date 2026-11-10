import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The push-allocation form resolves the pool-availability hint in Alpine:
 * availableFor() looks up pool[branch:currency] where the branch comes
 * from the selected teller's assignment (data-teller-branches) — data the
 * DOM only carries as JSON attributes. A broken lookup shows no hint and
 * the manager flies blind.
 */

test.use({ storageState: authFile('manager') });

test('the pool availability hint follows the selected teller and currency', async ({ page }) => {
  await gotoVerified(page, '/allocations/create', USERS.manager);
  await page.waitForFunction(() => {
    const el = document.querySelector<HTMLElement>('[x-data="allocationCreate"]');
    return !! (el && ((el as any)._x_dataStack?.length || (el as any).__x));
  });

  const currencySelect = page.locator('select[name="lines[0][currency_code]"]');
  const hint = page.getByText('Pool available:');
  await expect(hint.first()).toBeHidden();

  await page.locator('select[name="user_id"]').selectOption({ index: 1 });
  await currencySelect.selectOption('USD');

  await expect(hint.first()).toBeVisible();

  // Multi-currency rows: +1 line, then removal is allowed again.
  await page.locator('button', { hasText: '+ Add Currency' }).click();
  await expect(page.locator('select[name="lines[1][currency_code]"]')).toBeVisible();
  const removeBtns = page.locator('button', { hasText: 'Remove' });
  await expect(removeBtns.first()).toBeEnabled();
  await removeBtns.last().click();
  await expect(page.locator('select[name="lines[1][currency_code]"]')).toHaveCount(0);
});
