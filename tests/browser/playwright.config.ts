import { defineConfig } from '@playwright/test';

const baseURL = process.env.BROWSER_BASE_URL ?? 'http://127.0.0.1:80';

/**
 * Browser smoke gate (ADR 0016).
 *
 * Serial workers: the suite runs against one seeded database, and the
 * signup spec creates a real account — parallel workers would race.
 * Env: BROWSER_BASE_URL, BROWSER_USER, BROWSER_PASS, BROWSER_TID.
 */
export default defineConfig({
  testDir: './specs',
  globalSetup: './global.setup.ts',
  outputDir: './test-results',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI
    ? [['github'], ['html', { open: 'never', outputFolder: 'playwright-report' }]]
    : [['list']],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
});
