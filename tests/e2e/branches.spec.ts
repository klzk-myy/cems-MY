import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Branch deactivation is guarded by an Alpine confirm modal carrying the
 * branch's identity. Only the open/cancel path is exercised — confirming
 * would deactivate a live branch.
 */

test.use({ storageState: authFile('admin') });

test('the deactivate-branch modal opens and cancels without submitting', async ({ page }) => {
  await gotoVerified(page, '/branches', USERS.admin);

  // Each row has its own x-data scope + modal — scope to the clicked row's.
  const rowScope = page.locator('[x-data="branchModals"]')
    .filter({ has: page.getByRole('button', { name: 'Deactivate' }) })
    .first();

  await rowScope.getByRole('button', { name: 'Deactivate' }).click();
  const modalTitle = rowScope.locator('h3', { hasText: /^Deactivate .+\?$/ });
  await expect(modalTitle).toBeVisible();

  await rowScope.getByRole('button', { name: 'Back' }).click();
  await expect(modalTitle).toBeHidden();
});
