import { expect, test } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { authFile, USERS } from './helpers/env';

/**
 * Report pages invoke window.print() through the delegated [data-print]
 * click handler. The dialog itself can't be asserted headless, but a
 * stubbed window.print proves the wiring from button to handler.
 */

test.use({ storageState: authFile('admin') });

test('the print button calls window.print via the delegated handler', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__printed = 0;
    window.print = () => { (window as any).__printed += 1; };
  });
  await gotoVerified(page, '/reports/msb2', USERS.admin);

  const printBtn = page.locator('[data-print]').first();
  test.skip((await printBtn.count()) === 0, 'no print control on this report');
  await printBtn.click();

  expect(await page.evaluate(() => (window as any).__printed)).toBe(1);
});
