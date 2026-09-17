import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 120000,
  retries: 0,
  workers: 1,
  reporter: 'line',
  use: {
    channel: 'chrome',
    headless: !process.env.PW_HEADED,
    launchOptions: { slowMo: Number(process.env.PW_SLOWMO ?? 0) },
  },
});
