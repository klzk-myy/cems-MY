import { expect, test } from '@playwright/test';
import { authFile } from './helpers/env';

const nav = (page: import('@playwright/test').Page) => page.locator('aside nav');

test.describe('role-gated navigation', () => {
  test.describe('as teller', () => {
    test.use({ storageState: authFile('teller') });

    test('sees operational links but not admin ones', async ({ page }) => {
      await page.goto('/dashboard');

      await expect(nav(page).getByRole('link', { name: 'Dashboard' })).toBeVisible();
      await expect(nav(page).getByRole('link', { name: 'Transactions' })).toBeVisible();
      await expect(nav(page).getByRole('link', { name: 'My Allocations' })).toBeVisible();

      // Admin/manager-only surfaces must not be rendered for a teller.
      await expect(nav(page).getByRole('link', { name: 'Role Permissions' })).toHaveCount(0);
      await expect(nav(page).getByRole('link', { name: 'Users' })).toHaveCount(0);
      await expect(nav(page).getByRole('link', { name: 'Sanction Sources' })).toHaveCount(0);
    });
  });

  test.describe('as admin', () => {
    test.use({ storageState: authFile('admin') });

    test('sees admin-gated links', async ({ page }) => {
      await page.goto('/dashboard');

      await expect(nav(page).getByRole('link', { name: 'Role Permissions' })).toBeVisible();
      await expect(nav(page).getByRole('link', { name: 'Users' })).toBeVisible();
      await expect(nav(page).getByRole('link', { name: 'Branches' })).toBeVisible();
      await expect(nav(page).getByRole('link', { name: 'Currencies' })).toBeVisible();
    });
  });

  test.describe('as compliance officer', () => {
    test.use({ storageState: authFile('compliance') });

    test('sees compliance surfaces', async ({ page }) => {
      await page.goto('/dashboard');

      await expect(nav(page).getByRole('link', { name: 'Compliance' })).toBeVisible();
      await expect(nav(page).getByRole('link', { name: 'Screening' })).toBeVisible();
      await expect(nav(page).getByRole('link', { name: 'Risk Dashboard' })).toBeVisible();
    });
  });
});
