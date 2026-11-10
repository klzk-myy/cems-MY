import { defineConfig } from '@playwright/test';
import { loadE2eEnv } from './tests/e2e/helpers/env';

loadE2eEnv();

/**
 * Config for the volume/seeding spec in tests/seed/. It is a data-generation
 * tool, not a behavioral suite — it is excluded from the normal e2e testDir
 * and must be invoked explicitly:
 *
 *   npm run test:seed
 */
export default defineConfig({
  testDir: './tests/seed',
  outputDir: 'test-results/seed',
  timeout: 120_000,
  retries: 0,
  workers: 1,
  reporter: 'line',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? process.env.APP_URL ?? 'http://local.host',
    channel: process.env.E2E_CHANNEL ?? 'chrome',
    headless: !process.env.PW_HEADED,
    launchOptions: { slowMo: Number(process.env.PW_SLOWMO ?? 0) },
  },
});
