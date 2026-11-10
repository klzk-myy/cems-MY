import { expect, test } from '@playwright/test';
import { loginAndVerify, logout } from './helpers/auth';
import { USERS } from './helpers/env';

/**
 * Session-authentication smoke tests — run without storageState so each
 * test starts unauthenticated.
 */

test('login lands on the dashboard with rendered navigation', async ({ page }) => {
  await loginAndVerify(page, USERS.teller);

  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.locator('aside nav')).toBeVisible();
  await expect(page.locator('aside nav').getByRole('link', { name: 'Dashboard' })).toBeVisible();
});

test('wrong password is rejected on the login form', async ({ page }) => {
  await page.goto('/login');
  await page.locator('input[name="username"]').fill(USERS.teller.username);
  await page.locator('input[name="password"]').fill('definitely-wrong-password');
  await page.locator('form[action*="login"] button[type="submit"]').click();

  await expect(page).toHaveURL(/\/login/);
  await expect(page.locator('form[action*="login"]')).toBeVisible();
});

test('logout returns to the login page and clears access', async ({ page }) => {
  await loginAndVerify(page, USERS.teller);
  await page.goto('/dashboard');
  await logout(page);

  await expect(page).toHaveURL(/\/login/);

  // The session is gone — an authenticated page must bounce back to login.
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login/);
});
