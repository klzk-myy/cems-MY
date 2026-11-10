import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Manual-reconciliation entry lines are client-managed rows
 * (reconciliationLines): add/remove rewrites input names by index and a
 * single remaining row cannot be removed.
 */

test.use({ storageState: authFile('admin') });

test('reconciliation lines add and remove with index-named inputs', async ({ page }) => {
  await gotoVerified(page, '/accounting/reconciliation', USERS.admin);

  const dateInputs = page.locator('input[name^="lines["][name$="[date]"]');
  const count = await dateInputs.count();
  test.skip(count === 0, 'reconciliation entry form not on this page');
  await expect(dateInputs).toHaveCount(1);
  await expect(page.locator('button', { hasText: '✕' }).first()).toBeHidden();

  await page.locator('button', { hasText: '+ Add Line' }).click();
  await expect(dateInputs).toHaveCount(2);
  await expect(page.locator('input[name="lines[1][description]"]')).toBeVisible();
  await expect(page.locator('input[name="lines[1][debit]"]')).toBeVisible();

  await page.locator('button', { hasText: '✕' }).last().click();
  await expect(dateInputs).toHaveCount(1);
});
