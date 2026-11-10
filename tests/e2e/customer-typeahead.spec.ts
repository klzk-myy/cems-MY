import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The transaction form's customer_id is set ONLY by the Alpine typeahead —
 * the hidden input has no server-rendered value. PHPUnit cannot see this
 * wiring at all; a broken fetch, CSRF header, or pick() handler silently
 * produces "new customer" submissions instead of matching existing records.
 */

test.use({ storageState: authFile('teller') });

test('customer typeahead loads a matched record and sets customer_id', async ({ page }) => {
  // Register a uniquely-named customer through the real create form so the
  // typeahead always has a deterministic match in the teller's branch.
  const suffix = Date.now().toString().slice(-8);
  const fullName = `E2E Typeahead ${suffix}`;

  await gotoVerified(page, '/customers/create', USERS.teller);
  await page.locator('input[name="full_name"]').fill(fullName);
  await page.locator('select[name="id_type"]').selectOption({ label: 'Other ID' });
  await page.locator('input[name="id_number"]').fill(`E2E${suffix}A`);
  await page.locator('select[name="nationality"]').selectOption({ label: 'Malaysian' });
  await page.locator('input[name="date_of_birth"]').fill('1990-01-01');
  await page.locator('input[name="phone"]').fill(`+601${suffix}`);
  await page.locator('textarea[name="address"]').fill('1 E2E Street, Kuala Lumpur');
  const email = page.locator('input[name="email"]');
  if (await email.count()) {
    await email.fill(`e2e-${suffix}@example.test`);
  }
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/customers/create'), { timeout: 15_000 }),
    page.locator('form[action*="customers"] button[type="submit"]').click(),
  ]);

  // Exercise the actual typeahead on the transaction form.
  await gotoVerified(page, '/transactions/create', USERS.teller);

  // Wait for Alpine to bind the x-data component before typing — keystrokes
  // that land before init are swallowed silently (no input handler, no fetch),
  // which is a load-order race that made this test flaky under box load.
  await page.waitForFunction(() => {
    const el = document.querySelector<HTMLElement>('[x-data="transactionForm"]');
    return !! (el && ((el as any)._x_dataStack?.length || (el as any).__x));
  });

  const nameInput = page.locator('input[name="full_name"]');
  await nameInput.click();
  await nameInput.pressSequentially(fullName, { delay: 30 });

  // Debounced lookup (400ms) + fetch roundtrip — wait for a rendered result.
  const results = page.locator('.relative:has(input[name="full_name"]) ul li');
  await expect(results.first()).toBeVisible({ timeout: 15_000 });
  await expect(results.first()).toContainText(fullName);
  await results.first().click();

  // Behavioral assertions: the hidden input was bound by Alpine, the match
  // banner rendered, and the record's fields gap-filled the form.
  await expect(page.getByText('Existing customer loaded')).toBeVisible();
  expect(await page.locator('input[name="customer_id"]').inputValue()).not.toBe('');
});
