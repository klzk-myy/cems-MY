// Click-level Playwright coverage for the admin/reporting/pool actions that
// were previously only tested at the HTTP layer: every step drives the real
// UI — buttons, links, forms, and dialogs — not raw requests. Local-only
// browser probe (AGENTS.md §6); run via
// `npx playwright test tests/400-admin-actions.spec.ts`.
//
// Covered flows:
//   1. Preflight — trading branch is tradeable (crashed day-close cleanup).
//   2. Report schedule lifecycle — create via form, pause/resume via the
//      header toggle, delete via the index row (with the confirm dialog).
//   3. Regulatory export with the password.confirm step-up — the first
//      click bounces to /confirm-password; after stepping up, the second
//      click streams the CSV download.
//   4. Pool remittance maker/acknowledger — the KL manager remits MYR
//      surplus to HQ (in transit), and the HQ side acknowledges receipt.

import { test, expect, type Page } from '@playwright/test';
import {
  BASE_URL,
  TEST_PASSWORD,
  apiGet,
  loginAs,
  logout,
  readHeaderStatus,
  ensurePoolAvailable,
} from './support/helpers';

const SCHEDULE_CRON = '0 6 * * *';

test.describe('Admin actions: report schedules, exports, pool remittance', () => {
  test('preflight: trading branch is tradeable', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);

    await loginAs(page, 'manager1');
    const me = await apiGet(page, '/api/v1/user');
    const branchId = me.body?.data?.branch_id ?? 0;
    await logout(page);
    expect(branchId, 'manager1 must have a home branch').toBeGreaterThan(0);

    await loginAs(page, 'admin');
    await page.goto(`${BASE_URL}/branches/${branchId}/closing`);
    await page.waitForLoadState('domcontentloaded');
    const reopenBtn = page.locator('form[action$="/reopen"] button');
    if (await reopenBtn.isVisible().catch(() => false)) {
      console.log('   frozen business date found — reopening');
      await reopenBtn.click();
      await page.waitForLoadState('domcontentloaded');
      await expect(reopenBtn).toHaveCount(0);
    }
    await logout(page);
  });

  test('report schedule lifecycle: create → pause → resume → delete (all via clicks)', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    test.setTimeout(180_000);

    await loginAs(page, 'admin');

    // Clean up stale schedules from crashed earlier runs so the assertions
    // below see exactly one row.
    await page.goto(`${BASE_URL}/reports/schedules`);
    await page.waitForLoadState('domcontentloaded');
    for (let i = 0; i < 10; i++) {
      const stale = page.locator('table tbody tr').filter({ hasText: SCHEDULE_CRON }).first();
      if (!(await stale.count())) {
        break;
      }
      page.once('dialog', (dialog) => dialog.accept());
      await stale.locator('button:has-text("Delete")').click();
      await page.waitForLoadState('domcontentloaded');
    }

    // Create via the form.
    await page.goto(`${BASE_URL}/reports/schedules`);
    await page.waitForLoadState('domcontentloaded');
    await page.getByRole('link', { name: 'Create Schedule' }).click();
    await page.waitForLoadState('domcontentloaded');

    const form = page.locator('form[action$="/reports/schedules"]');
    await expect(form).toBeVisible();
    await form.locator('select[name="report_type"]').selectOption('msb2');
    await form.locator('input[name="cron_expression"]').fill(SCHEDULE_CRON);
    await form.locator('input[name="is_active"]').check();
    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    // The index lists the new schedule; open its detail row.
    const row = page.locator('table tbody tr').filter({ hasText: SCHEDULE_CRON }).first();
    await expect(row).toBeVisible();
    await row.getByRole('link', { name: 'View' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('h1')).toContainText('Schedule');

    // The header toggle doubles as the state indicator: an active schedule
    // offers Pause; a paused one offers Resume.
    const pauseBtn = page.locator('form[action$="/pause"] button[type="submit"]');
    await expect(pauseBtn).toBeVisible();

    // Pause — the toggle flips to Resume.
    await pauseBtn.click();
    await page.waitForLoadState('domcontentloaded');
    const resumeBtn = page.locator('form[action$="/resume"] button[type="submit"]');
    await expect(resumeBtn).toBeVisible();

    // Resume restores the active state.
    await resumeBtn.click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('form[action$="/pause"] button[type="submit"]')).toBeVisible();

    // Delete from the index row, accepting the data-confirm dialog.
    await page.goto(`${BASE_URL}/reports/schedules`);
    await page.waitForLoadState('domcontentloaded');
    const deleteRow = page.locator('table tbody tr').filter({ hasText: SCHEDULE_CRON }).first();
    page.once('dialog', (dialog) => dialog.accept());
    await deleteRow.locator('button:has-text("Delete")').click();
    await page.waitForLoadState('domcontentloaded');

    await expect(
      page.locator('table tbody tr').filter({ hasText: SCHEDULE_CRON }).first(),
      'the deleted schedule must disappear from the index'
    ).toHaveCount(0);
    await logout(page);
  });

  test('regulatory export: password.confirm step-up then CSV download', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    test.setTimeout(180_000);

    await loginAs(page, 'manager1');
    await page.goto(`${BASE_URL}/reports/msb2`);
    await page.waitForLoadState('domcontentloaded');

    // The first export click bounces to the password-confirmation step-up.
    await page.locator('button:has-text("Export Report")').click();
    await page.waitForLoadState('domcontentloaded');
    expect(page.url(), 'the export route must step up with password.confirm').toContain('/confirm-password');

    // Confirm the password; the intended action is not replayed (the POST
    // payload is lost), so the user lands back and clicks export again.
    const confirmForm = page.locator('form[action*="confirm-password"]');
    await confirmForm.locator('input[name="password"]').fill(TEST_PASSWORD);
    await confirmForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    await page.goto(`${BASE_URL}/reports/msb2`);
    await page.waitForLoadState('domcontentloaded');

    // With a fresh confirmation session, the export click streams the CSV.
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 30000 }),
      page.locator('button:has-text("Export Report")').click(),
    ]);
    const filename = download.suggestedFilename();
    console.log(`   downloaded: ${filename}`);
    expect(filename, 'the export must stream a CSV download').toMatch(/\.csv$/i);
    await logout(page);
  });

  test('pool remittance: KL manager remits surplus to HQ, HQ acknowledges', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);
    test.setTimeout(300_000);

    // The KL MYR pool must cover the remittance (past runs can drain it).
    await loginAs(page, 'manager1');
    await ensurePoolAvailable(page, 'MYR', 1000);

    // Open the KL MYR pool page and submit the remit form.
    await page.goto(`${BASE_URL}/branch-pools`);
    await page.waitForLoadState('domcontentloaded');
    const myrRow = page.locator('table tbody tr').filter({ hasText: 'MYR' }).first();
    await expect(myrRow).toBeVisible();
    await myrRow.getByRole('link', { name: 'Manage' }).click();
    await page.waitForLoadState('domcontentloaded');

    const remitForm = page.locator('form[action$="/remit"]');
    await expect(remitForm, 'the MYR pool page must offer the remit form').toBeVisible();
    await remitForm.locator('input[name="amount_myr"]').fill('500');
    // A branch pool only remits to head office — the single (first) option.
    await remitForm.locator('select[name="to_branch_id"]').selectOption({ index: 0 });
    await remitForm.locator('input[name="notes"]').fill('Playwright remittance coverage');
    await remitForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    // The remittance now shows as outbound, awaiting acknowledgement.
    const outbound = page.locator('text=awaiting acknowledgement').first();
    await expect(outbound, 'the remittance must be listed as in transit').toBeVisible();
    console.log('   remittance in transit — switching to the HQ side');
    await logout(page);

    // The receiving side (admin at HQ) acknowledges receipt. Resolve the HQ
    // branch name so the right pool row is opened (admin sees every branch).
    await loginAs(page, 'admin');
    const branchesRes = await apiGet(page, '/api/v1/branches');
    const hq = (branchesRes.body?.data ?? []).find((b: { type: string }) => b.type === 'head_office');
    expect(hq, 'a head office branch must exist').toBeTruthy();

    await page.goto(`${BASE_URL}/branch-pools`);
    await page.waitForLoadState('domcontentloaded');
    const hqRow = page.locator('table tbody tr').filter({ hasText: hq.name }).filter({ hasText: 'MYR' }).first();
    await expect(hqRow).toBeVisible();
    await hqRow.getByRole('link', { name: 'Manage' }).click();
    await page.waitForLoadState('domcontentloaded');

    const acknowledgeBtn = page.locator('form[action$="/acknowledge"] button[type="submit"]');
    await expect(acknowledgeBtn, 'the HQ pool page must show the inbound remittance').toBeVisible();
    await acknowledgeBtn.click();
    await page.waitForLoadState('domcontentloaded');

    await expect(
      page.locator('form[action$="/acknowledge"] button[type="submit"]'),
      'the acknowledged remittance must leave the in-transit list'
    ).toHaveCount(0);
    console.log('   remittance acknowledged');
    await logout(page);
  });



});
