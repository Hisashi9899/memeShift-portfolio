import { Page } from '@playwright/test';

export function e2eCredentials(): { username: string; password: string } {
  const username = process.env.E2E_USERNAME;
  const password = process.env.E2E_PASSWORD;

  if (!username || !password) {
    throw new Error(
      'E2E_USERNAME and E2E_PASSWORD must be set before running the E2E tests.'
    );
  }

  return { username, password };
}

export async function login(page: Page) {
  const { username, password } = e2eCredentials();
  await page.goto('/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
  await page.waitForLoadState('networkidle');
}

export async function navigateToDashboard(page: Page) {
  await page.goto('/dashboard');
  await page.waitForLoadState('networkidle');
}
