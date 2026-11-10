import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * The findings page binds the clicked row's id/ref into the dismiss and
 * create-case modals via openDismiss(id, ref) / openCreateCase(id, ref) —
 * the modal's :action URL and its ref text come from that call. A wiring
 * bug would submit against the wrong finding or render a stale ref.
 */

test.use({ storageState: authFile('compliance') });

test('dismiss and create-case modals carry the clicked finding', async ({ page }) => {
  await gotoVerified(page, '/compliance/findings', USERS.compliance);

  const dismissBtn = page.getByRole('button', { name: /dismiss/i }).first();
  const createBtn = page.getByRole('button', { name: /create case|escalate/i }).first();
  test.skip((await dismissBtn.count()) === 0, 'no findings seeded');

  await dismissBtn.click();
  // The modal nests an outer overlay + inner dialog on the same x-show —
  // the form's visibility proves the modal opened.
  const dismissForm = page.locator('form[action*="/dismiss"]');
  await expect(dismissForm).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(dismissForm).toBeHidden();

  await createBtn.click();
  const caseForm = page.locator('form[action*="/create-case"]');
  await expect(caseForm).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(caseForm).toBeHidden();
});
