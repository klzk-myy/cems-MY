import { defineConfig } from '@playwright/test';
import { loadE2eEnv } from './tests/e2e/helpers/env';

loadE2eEnv();

const baseURL = process.env.E2E_BASE_URL ?? process.env.APP_URL ?? 'http://local.host';

/**
 * Browser e2e suite — behavioral smoke tests for things the PHPUnit HTTP
 * simulation harness cannot cover (Alpine DOM behaviour, file inputs, real
 * asset loads, rendered navigation).
 *
 * Environment:
 *   E2E_BASE_URL            target app (default http://local.host)
 *   E2E_CHANNEL             browser channel (default 'chrome'; use 'chromium' in CI)
 *   E2E_<ROLE>_PASSWORD     credential overrides (default Password123!)
 *   E2E_<ROLE>_TOTP         TOTP secret for pre-enrolled MFA users
 *   PW_HEADED=1             run headed
 *   PW_SLOWMO=<ms>          slow motion
 *
 * tests/e2e/.env.local (gitignored) is loaded for the same keys.
 */
export default defineConfig({
  testDir: './tests/e2e',
  // Artifacts land under the gitignored /test-results/ tree — traces,
  // screenshots, and videos are retained for failures so a local probe can
  // be debugged without re-running a multi-hour soak.
  outputDir: 'test-results/e2e',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'line',
  use: {
    baseURL,
    channel: process.env.E2E_CHANNEL ?? 'chrome',
    headless: !process.env.PW_HEADED,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    launchOptions: { slowMo: Number(process.env.PW_SLOWMO ?? 0) },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 30000,
    navigationTimeout: 30000,
  },
  projects: [
    { name: 'setup', testMatch: 'auth.setup.ts' },
    { name: 'e2e', testMatch: '*.spec.ts', dependencies: ['setup'] },
  ],
});

