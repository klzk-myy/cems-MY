import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The budget form's per-account rows are added/removed in Alpine
 * (budgetRows): each x-for row rewrites its input names by index, so a
 * row-handling bug silently drops or duplicates budget lines server-side.
 */

test.use({ storageState: authFile('admin') });

test('budget rows add and remove with re-indexed input names', async ({ page }) => {
  await gotoVerified(page, '/accounting/budget', USERS.admin);

  const accountSelects = page.locator('select[name^="budgets["][name$="[account_code]"]');
  await expect(accountSelects).toHaveCount(1);
  // The single-row state hides the remove control via x-show.
  await expect(page.locator('button', { hasText: '✕' }).first()).toBeHidden();

  await page.locator('button', { hasText: '+ Add Row' }).click();
  await expect(accountSelects).toHaveCount(2);
  await expect(page.locator('select[name="budgets[1][account_code]"]')).toBeVisible();
  await expect(page.locator('input[name="budgets[1][budget_myr]"]')).toBeVisible();

  await page.locator('button', { hasText: '✕' }).last().click();
  await expect(accountSelects).toHaveCount(1);
  await expect(page.locator('select[name="budgets[1][account_code]"]')).toHaveCount(0);
});
