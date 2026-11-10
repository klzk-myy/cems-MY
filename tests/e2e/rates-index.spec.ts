import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Filter selects carry `data-autosubmit` — the delegated change handler
 * submits the enclosing GET form so the page reloads with the filter in
 * the query string. With no <noscript> fallback in play for browsers,
 * a broken listener leaves the filter dead.
 */

test.use({ storageState: authFile('admin') });

test('the branch filter auto-submits and lands on a filtered URL', async ({ page }) => {
  await gotoVerified(page, '/rates', USERS.admin);

  const branchSelect = page.locator('select[name="branch_id"][data-autosubmit]');
  await expect(branchSelect).toBeVisible();

  // Pick the first concrete branch option (index 0 is "All branches").
  const value = await branchSelect.locator('option').nth(1).getAttribute('value');
  await branchSelect.selectOption(value!);

  await page.waitForURL((url) => url.searchParams.get('branch_id') === value, { timeout: 15_000 });
});
