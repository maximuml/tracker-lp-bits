import { AxeBuilder } from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';

/**
 * ADR 0019 theme toggle: the userbar button cycles data-theme
 * auto → light → dark, persists via POST /web/usercp/theme for
 * authenticated users, and both schemes stay axe color-contrast clean.
 */
test.describe('theme toggle', () => {
  test.use({ storageState: AUTH_STATE });

  test('toggle cycles theme and persists via POST', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });

    const html = page.locator('html');
    await expect(html).toHaveAttribute('data-theme', /^(auto|light|dark)$/);

    const toggle = page.locator('.nxm-theme-toggle').first();
    await expect(toggle).toBeVisible();

    const themeRequest = page.waitForRequest((r) => r.url().includes('/usercp/theme'));
    await toggle.click();

    const next = await html.getAttribute('data-theme');
    expect(['auto', 'light', 'dark']).toContain(next);

    const request = await themeRequest;
    expect(request.method()).toBe('POST');
    expect(request.postData()).toContain(`theme=${next}`);
  });

  test('dark scheme keeps axe color-contrast clean', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const contrast = results.violations.filter((v) => v.id === 'color-contrast');
    expect(contrast, `dark scheme color-contrast: ${JSON.stringify(contrast.map((v) => v.nodes.map((n) => n.target)))}`).toEqual([]);
  });

  test('light scheme keeps axe color-contrast clean', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'light'));

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const contrast = results.violations.filter((v) => v.id === 'color-contrast');
    expect(contrast, `light scheme color-contrast: ${JSON.stringify(contrast.map((v) => v.nodes.map((n) => n.target)))}`).toEqual([]);
  });
});

test.describe('theme toggle — anonymous', () => {
  test('public page toggle persists via localStorage', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'networkidle' });

    const navToggle = page.locator('.nxm-theme-toggle').first();
    await expect(navToggle).toBeVisible();
    await navToggle.click();
    await navToggle.click();

    const theme = await page.locator('html').getAttribute('data-theme');
    expect(['auto', 'light', 'dark']).toContain(theme);
    const stored = await page.evaluate(() => localStorage.getItem('nxm-theme'));
    expect(stored).toBe(theme);
  });
});
