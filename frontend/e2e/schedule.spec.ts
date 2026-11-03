import { test, expect, request } from '@playwright/test';
import { e2eCredentials } from './helpers';

const BASE = process.env.E2E_BASE_URL || 'http://nginx';

async function loginAndSetToken(page: any) {
  const ctx = await request.newContext({ baseURL: BASE });
  const res = await ctx.post('/api/login', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    form: e2eCredentials(),
  });
  const body = await res.json();

  await page.goto('/dashboard');
  await page.evaluate((token: string) => {
    localStorage.setItem('token', token);
  }, body.token);
  await page.reload();
  await page.waitForLoadState('networkidle');
}

test.describe('memeShift E2E', () => {
  test('ダッシュボード表示と月ナビゲーション', async ({ page }) => {
    test.setTimeout(30000);
    await loginAndSetToken(page);

    await page.waitForSelector('h2');
    const h2text = await page.locator('h2').first().textContent();
    expect(h2text).toMatch(/^\d{4}-\d{2}$/);

    const prevBtn = page.locator('button:has-text("←")');
    const nextBtn = page.locator('button:has-text("→")');
    await expect(prevBtn).toBeVisible();
    await expect(nextBtn).toBeVisible();
  });

  test('自動生成ボタン表示', async ({ page }) => {
    test.setTimeout(30000);
    await loginAndSetToken(page);

    await expect(page.getByRole('button', { name: '勤務自動作成' })).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('button', { name: '高度な自動修正' })).toBeVisible({ timeout: 10000 });
  });

  test('設定ページ遷移', async ({ page }) => {
    test.setTimeout(30000);
    await loginAndSetToken(page);

    const settingsBtn = page.getByRole('button', { name: '設定' });
    await expect(settingsBtn).toBeVisible({ timeout: 10000 });
    await settingsBtn.click();
    await page.waitForURL('**/settings', { timeout: 5000 });
    expect(page.url()).toContain('/settings');
  });
});
