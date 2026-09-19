import { expect, test } from '@playwright/test';
import { BROWSER_PASS, BROWSER_USER, login } from '../fixtures/auth';
import { watchIssues } from '../helpers';

/**
 * Real form submit — the rendered page end to end (CSRF, POST /login,
 * c_secure_pass cookie, redirect), not a bypassed request.
 */
test('login form authenticates and redirects', async ({ page }) => {
  const issues = watchIssues(page);

  await login(page);
  expect(page.url()).toMatch(/\/index(\.php)?$/);

  const response = await page.goto('/index.php', { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  expect(issues.pageErrors).toEqual([]);
});

test('login rejects a wrong password', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'networkidle' });
  await page.fill('#login-form input[name="username"]', BROWSER_USER);
  await page.fill('#login-form input[name="password"]', 'definitely-wrong-password');
  await page.click('#login-form [type="submit"]');
  await page.waitForLoadState('networkidle');

  const cookies = await page.context().cookies();
  expect(cookies.some((c) => c.name === 'c_secure_pass'), 'no auth cookie').toBeFalsy();
});
