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
  // Collapse state persists in localStorage — start from a clean slate so
  // the test is independent of whatever a previous session stored.
  await page.evaluate(() => localStorage.removeItem('sidebarCollapsed'));
  await page.reload();

  const sidebar = page.locator('aside').first();
  await expect(sidebar).toHaveClass(/md:w-64/);

  await page.locator('button[aria-label="Collapse sidebar"]').click();
  await expect(sidebar).toHaveClass(/md:w-20/);
  await expect(sidebar).not.toHaveClass(/md:w-64/);

  // Persistence: a reload keeps the rail collapsed.
  await page.reload();
  await expect(sidebar).toHaveClass(/md:w-20/);

  await page.locator('button[aria-label="Expand sidebar"]').click();
  await expect(sidebar).toHaveClass(/md:w-64/);
});

test('the off-canvas sidebar opens and closes on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await gotoVerified(page, '/dashboard', USERS.teller);

  const sidebar = page.locator('aside').first();
  const scrim = page.locator('div[x-show="sidebarOpen"]');
  const burger = page.locator('button[aria-label="Open navigation"]');

  await expect(burger).toBeVisible();
  await expect(sidebar).toHaveClass(/-translate-x-full/);

  await burger.click();
  await expect(sidebar).toHaveClass(/translate-x-0/);
  await expect(scrim).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(sidebar).toHaveClass(/-translate-x-full/);

  await burger.click();
  await scrim.click({ position: { x: 350, y: 400 } });
  await expect(sidebar).toHaveClass(/-translate-x-full/);
});

test('the header user menu opens, links to account pages, and toggles dark mode', async ({ page }) => {
  await gotoVerified(page, '/dashboard', USERS.teller);
  // Reset persisted theme so the menu always starts on the "Dark mode" label.
  await page.evaluate(() => {
    localStorage.removeItem('darkMode');
    document.documentElement.classList.remove('dark');
  });

  const menuButton = page.locator('button[aria-haspopup="menu"]');
  const menu = page.locator('div[role="menu"]');
  await expect(menu).toBeHidden();

  await menuButton.click();
  await expect(menu).toBeVisible();
  await expect(menuButton).toHaveAttribute('aria-expanded', 'true');
  await expect(menu.getByRole('menuitem', { name: 'Change password' })).toBeVisible();
  await expect(menu.getByRole('menuitem', { name: 'Notification preferences' })).toBeVisible();

  const html = page.locator('html');
  await menu.getByRole('menuitem', { name: 'Dark mode' }).click();
  await expect(html).toHaveClass(/dark/);
  // The label flips once dark mode is on.
  await expect(menu.getByRole('menuitem', { name: 'Light mode' })).toBeVisible();
  await page.evaluate(() => localStorage.removeItem('darkMode'));
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
