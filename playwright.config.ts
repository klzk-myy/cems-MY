import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 120000,
  retries: 0,
  workers: 1,
  reporter: 'line',
  // Artifacts land under the gitignored /test-results/ tree — traces,
  // screenshots, and videos are retained for failures so a local probe can
  // be debugged without re-running a multi-hour soak.
  outputDir: './test-results/playwright',
  use: {
    channel: 'chrome',
    headless: !process.env.PW_HEADED,
    launchOptions: { slowMo: Number(process.env.PW_SLOWMO ?? 0) },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 30000,
    navigationTimeout: 30000,
  },
});

