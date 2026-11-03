import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  retries: 1,
  workers: 1,
  reporter: [['html', { outputFolder: 'playwright-report' }], ['list']],
  timeout: 60000,
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://nginx',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    headless: true,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], locale: 'ja-JP' },
    },
  ],
});
