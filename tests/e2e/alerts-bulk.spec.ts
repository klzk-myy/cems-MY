import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The alerts bulk bar is driven by the delegated `data-check-all` handler:
 * toggling the header checkbox must check/uncheck every row's
 * [data-alert-check] box so bulk assign/resolve carries the right IDs.
 */

test.use({ storageState: authFile('compliance') });

test('the select-all checkbox toggles every alert row checkbox', async ({ page }) => {
  await gotoVerified(page, '/compliance/alerts', USERS.compliance);

  const master = page.locator('input[data-check-all]');
  const boxes = page.locator('input[data-alert-check]');
  const count = await boxes.count();
  test.skip(count === 0, 'no alerts seeded — nothing to toggle');

  await master.check();
  for (let i = 0; i < Math.min(count, 10); i++) {
    await expect(boxes.nth(i)).toBeChecked();
  }

  await master.uncheck();
  for (let i = 0; i < Math.min(count, 10); i++) {
    await expect(boxes.nth(i)).not.toBeChecked();
  }
});
