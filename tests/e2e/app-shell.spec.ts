import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * App chrome owned by Alpine: the collapsible sidebar (appShell) and the
 * notification bell dropdown (notificationBell) — open/toggle/close
 * behaviour that only exists in the DOM.
 */

test.use({ storageState: authFile('teller') });

test('the sidebar collapses and expands via its toggle', async ({ page }) => {
  await gotoVerified(page, '/dashboard', USERS.teller);

  const sidebar = page.locator('aside').first();
  await expect(sidebar).toHaveClass(/w-64/);

  await page.locator('button[aria-label="Collapse sidebar"]').click();
  await expect(sidebar).toHaveClass(/w-20/);
  await expect(sidebar).not.toHaveClass(/w-64/);

  await page.locator('button[aria-label="Expand sidebar"]').click();
  await expect(sidebar).toHaveClass(/w-64/);
});

test('the notification bell dropdown toggles, escapes, and closes outside', async ({ page }) => {
  await gotoVerified(page, '/dashboard', USERS.teller);

  const bell = page.locator('button[aria-label="Notifications"]');
  const dropdown = page.locator('div[x-show="open"]', { hasText: 'Notifications' });

  await expect(dropdown).toBeHidden();
  await bell.click();
  await expect(dropdown).toBeVisible();
  await expect(dropdown.getByText('Notifications', { exact: true })).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(dropdown).toBeHidden();

  await bell.click();
  await expect(dropdown).toBeVisible();
  // An inert target outside the bell — a nav link or logo would navigate.
  await page.locator('h1, h2').first().click();
  await expect(dropdown).toBeHidden();
});
