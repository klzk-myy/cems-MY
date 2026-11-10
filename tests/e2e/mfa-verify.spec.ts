import { expect, test } from '@playwright/test';
import { ensureEnrolled, loginAndVerify } from './helpers/auth';
import { savedTotpSecret, USERS } from './helpers/env';
import { totp } from './helpers/totp';

/**
 * The MFA challenge is a real browser surface no saved-state spec touches:
 * a fresh context (no trusted-device cookie) must be challenged at
 * /mfa/verify, a bad code must re-render with an error and keep the gate
 * closed, and a valid TOTP must let the session through.
 */

// Deliberately no storageState — the point is the full login + challenge.
test.use({ storageState: { cookies: [], origins: [] } });

test('a wrong code is rejected and a valid TOTP clears the gate', async ({ page }) => {
  const user = USERS.teller;

  // A fresh-install user is unenrolled — enrol first so a verify gate exists
  // at all (the 30-day enrolment grace otherwise lets them pass unchecked).
  // Then wipe every cookie: enrollment/verification leaves a trusted-device
  // cookie that would otherwise bypass the gate for 30 days, and the next
  // login must be a genuinely untrusted, unverified session.
  await loginAndVerify(page, user);
  await ensureEnrolled(page, user);
  await page.context().clearCookies();

  const secret = user.totp ?? savedTotpSecret(user.username);
  test.skip(!secret, 'no TOTP secret available for teller');

  await page.goto('/login');
  await page.locator('input[name="username"]').fill(user.username);
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('form[action*="login"] button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15_000 });

  // Verify only fires on mfa.verified routes — hitting one bounces to it.
  await page.goto('/transactions/create');
  await page.waitForURL('**/mfa/verify**', { timeout: 15_000 });

  const form = page.locator('form[action*="mfa/verify"], form[action*="mfa.verify"]');
  await form.locator('input[name="code"]').fill('000000');
  await form.locator('button[type="submit"]').click();

  // Rejected: gate stays up, an error surfaces, the field is re-rendered.
  await expect(page).toHaveURL(/mfa\/verify/, { timeout: 15_000 });
  await expect(page.getByText(/invalid|incorrect|failed/i).first()).toBeVisible();
  await expect(form.locator('input[name="code"]')).toBeVisible();

  // verifyUserCode enforces strictly-increasing timesteps: a code from the
  // same 30s window as the most recent verification (auth.setup or another
  // spec's gotoVerified can be seconds behind us) is rejected as a replay.
  // On retry, always wait out the window — the next code is guaranteed to
  // be from a newer timestep than whatever was last recorded.
  for (let attempt = 0; attempt < 2 && page.url().includes('/mfa/verify'); attempt++) {
    const msToNextWindow = 30_000 - (Date.now() % 30_000);
    if (attempt === 0 ? msToNextWindow < 5_000 : true) {
      await page.waitForTimeout(msToNextWindow + 1_000);
    }
    await form.locator('input[name="code"]').fill(totp(secret!));
    await form.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.includes('/mfa/verify'), { timeout: 15_000 }).catch(() => null);
  }
  await expect(page).not.toHaveURL(/mfa\/verify/);
  await expect(page.locator('aside').first()).toBeVisible();
});
