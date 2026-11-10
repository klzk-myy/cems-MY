import { expect, test } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

const FIXTURES = path.resolve(path.dirname(fileURLToPath(import.meta.url)), 'fixtures');

/**
 * Real file-input upload flow: POST a CSV through the multipart form and
 * assert the import is accepted and queued (row-level results are a job
 * concern, covered by the PHPUnit simulation).
 */

test.use({ storageState: authFile('manager') });

test('batch CSV upload queues an import and shows its results page', async ({ page }) => {
  await gotoVerified(page, '/transactions/batch-upload', USERS.manager);

  const fileInput = page.locator('input[type="file"][name="csv_file"]');
  await expect(fileInput).toBeAttached();
  await fileInput.setInputFiles(path.join(FIXTURES, 'transactions.csv'));

  await Promise.all([
    page.waitForURL(/\/transactions\/import\/\d+/, { timeout: 20_000 }),
    page.locator('form[action*="batch-upload"] button[type="submit"]').click(),
  ]);

  await expect(page).toHaveURL(/\/transactions\/import\/\d+/);
});
