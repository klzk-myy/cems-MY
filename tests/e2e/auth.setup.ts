import { test as setup } from '@playwright/test';
import { authFile, USERS, type Role } from './helpers/env';
import { loginAndVerify } from './helpers/auth';

/**
 * Authenticate each seeded role once and persist storageState (session +
 * trusted-device cookie). Specs reuse these files instead of logging in
 * per test — the device token keeps mfa.verified routes reachable for 30
 * days, so the suite also survives the 15-minute session-MFA window.
 */
const roles: Role[] = ['teller', 'manager', 'compliance', 'admin'];

for (const role of roles) {
  setup(`authenticate ${role}`, async ({ page }) => {
    await loginAndVerify(page, USERS[role]);
    await page.context().storageState({ path: authFile(role) });
  });
}
