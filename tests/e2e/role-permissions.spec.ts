import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The permission matrix is a plain POST grid, but it embeds a lockout
 * invariant in the DOM: Admin's manage_role_permissions checkbox is
 * rendered checked AND disabled so the matrix cannot revoke its own
 * management rights.
 */

test.use({ storageState: authFile('admin') });

test('the matrix renders and the admin lockout cell stays disabled', async ({ page }) => {
  await gotoVerified(page, '/admin/role-permissions', USERS.admin);

  const boxes = page.locator('input[type="checkbox"][name^="permissions["]');
  await expect(boxes.first()).toBeVisible();
  expect(await boxes.count()).toBeGreaterThan(10);

  const locked = page.locator(
    'input[type="checkbox"][name="permissions[admin][manage_role_permissions]"]'
  );
  await expect(locked).toBeChecked();
  await expect(locked).toBeDisabled();
});
