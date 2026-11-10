import { expect, type Page } from '@playwright/test';
import { savedTotpSecret, saveTotpSecret, type E2eUser } from './env';
import { totp } from './totp';

/**
 * Log in through the real UI, then resolve whatever MFA state the account is
 * in:
 *   - unenrolled + overdue  → complete /mfa/setup enrollment in-page
 *   - enrolled              → submit a TOTP at /mfa/verify, ticking the
 *                             trusted-device flag so subsequent requests are
 *                             covered for 30 days
 *   - MFA off               → nothing to do
 */
export async function loginAndVerify(page: Page, user: E2eUser): Promise<void> {
  await page.goto('/login');
  await expect(page.locator('form[action*="login"]')).toBeVisible();
  await page.locator('input[name="username"]').fill(user.username);
  await page.locator('input[name="password"]').fill(user.password);

  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15_000 }),
    page.locator('form[action*="login"] button[type="submit"]').click(),
  ]);

  if (page.url().includes('password.change') || page.url().includes('password/change')) {
    throw new Error(
      `E2E user ${user.username} landed on forced password change — ` +
        'the suite cannot proceed until the password is rotated manually.'
    );
  }

  await resolveMfa(page, user);
}

/**
 * Navigate to an authenticated/mfa.verified path; if the app bounces to the
 * MFA verify or setup page, resolve it and retry the navigation once.
 */
export async function gotoVerified(page: Page, path: string, user: E2eUser): Promise<void> {
  await page.goto(path);
  const before = page.url();
  await resolveMfa(page, user);
  if (page.url() !== before && !page.url().startsWith(new URL(path, page.url()).origin + path)) {
    await page.goto(path);
  }
}

/**
 * Guarantee the user is TOTP-enrolled. /mfa/setup renders the secret for
 * unenrolled users (enrollInPage captures it into .auth/secrets.json) and
 * redirects enrolled users to /mfa/verify, which resolveMfa then answers.
 */
export async function ensureEnrolled(page: Page, user: E2eUser): Promise<void> {
  await page.goto('/mfa/setup');
  await resolveMfa(page, user);
}

/** Complete whatever MFA step the current URL demands (no-op otherwise). */
export async function resolveMfa(page: Page, user: E2eUser): Promise<void> {
  if (page.url().includes('/login')) {
    // Dead stored session (idle timeout / auth.session invalidation) —
    // re-authenticate through the form, then fall through to whatever
    // MFA state the account now demands.
    await page.locator('input[name="username"]').fill(user.username);
    await page.locator('input[name="password"]').fill(user.password);
    await Promise.all([
      page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15_000 }),
      page.locator('form[action*="login"] button[type="submit"]').click(),
    ]);
  }

  if (page.url().includes('/mfa/setup')) {
    await enrollInPage(page, user);
  }

  if (page.url().includes('/mfa/verify')) {
    await submitVerification(page, user);
  }
}

/**
 * Enrolled-user path: submit a TOTP code. Injects the remember_device flag
 * into the form (the UI omits the checkbox but the controller honours it) so
 * the trusted-device cookie covers later sessions for 30 days.
 */
async function submitVerification(page: Page, user: E2eUser): Promise<void> {
  const secret = user.totp ?? savedTotpSecret(user.username);
  if (!secret) {
    throw new Error(
      `${user.username} is redirected to /mfa/verify but no TOTP secret is available. ` +
        `Set E2E_${user.username.replace(/\d+$/, '').toUpperCase()}_TOTP or E2E_${user.username.toUpperCase()}_TOTP ` +
        'in the environment / tests/e2e/.env.local.'
    );
  }

  for (let attempt = 0; attempt < 2; attempt++) {
    if (attempt > 0) {
      // verifyUserCode enforces strictly-increasing timesteps — a code from
      // the same 30s window as the last accepted verification is rejected
      // as a replay, so wait out the boundary before retrying.
      const msToNextWindow = 30_000 - (Date.now() % 30_000);
      await page.waitForTimeout(msToNextWindow + 1_000);
    }
    const form = page.locator('form[action*="mfa/verify"], form[action*="mfa.verify"]');
    await form.locator('input[name="code"]').fill(totp(secret));
    await form.evaluate((el) => {
      const formEl = el as HTMLFormElement;
      if (!formEl.querySelector('input[name="remember_device"]')) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remember_device';
        input.value = '1';
        formEl.appendChild(input);
      }
    });
    await Promise.all([
      page.waitForURL((url) => !url.pathname.includes('/mfa/verify'), { timeout: 15_000 }).catch(() => null),
      form.locator('button[type="submit"], input[type="submit"]').first().click(),
    ]);

    if (!page.url().includes('/mfa/verify')) {
      return;
    }
    // Code likely rolled over at the step boundary — retry with a fresh one.
  }

  if (page.url().includes('/mfa/verify')) {
    throw new Error(`MFA verification failed for ${user.username} (still on /mfa/verify).`);
  }
}

/**
 * Fresh-install path: the setup page displays the TOTP secret for manual
 * entry — capture it (so later verifications can compute codes), submit a
 * matching code, and pass the recovery-codes screen.
 */
async function enrollInPage(page: Page, user: E2eUser): Promise<void> {
  const secretEl = page.locator('code').first();
  await expect(secretEl).toBeVisible();
  const secret = (await secretEl.innerText()).trim();
  saveTotpSecret(user.username, secret);

  const form = page.locator('form[action*="mfa/setup"], form[action*="mfa.setup"]');
  await form.locator('input[name="code"]').fill(totp(secret));
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/mfa/setup'), { timeout: 15_000 }),
    form.locator('button[type="submit"], input[type="submit"]').first().click(),
  ]);

  if (page.url().includes('/mfa/recovery-codes')) {
    await page.getByRole('link', { name: /continue/i }).first().click();
  }
}

export async function logout(page: Page): Promise<void> {
  await Promise.all([
    page.waitForURL('**/login', { timeout: 15_000 }),
    // Scope to the sidebar form — the header user menu has one too.
    page.locator('aside form[action*="logout"] button[type="submit"]').click(),
  ]);
}
