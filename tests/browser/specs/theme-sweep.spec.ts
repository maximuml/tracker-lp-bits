import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import { watchIssues, expectCleanPage } from '../helpers';
import { AUTH_STATE } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });

const PAGES = ['/index.php', '/torrents.php', '/details.php?id=1', '/forums.php', '/usercp.php', '/upload.php'];
const THEMES = ['light', 'dark', 'auto'] as const;

async function setTheme(page: import('@playwright/test').Page, theme: string) {
  await page.goto('/index.php');
  const status = await page.evaluate(async (t) => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const res = await fetch('/web/usercp/theme', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': meta ? meta.getAttribute('content') ?? '' : '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: `theme=${t}`,
    });
    await res.text(); // drain body — navigating away mid-flight aborts the request
    return res.status;
  }, theme);
  expect(status, `set theme ${theme}`).toBe(204);
}

test.describe.configure({ mode: 'serial' });

for (const theme of THEMES) {
  test(`theme sweep: ${theme} — all pages clean`, async ({ page }) => {
    const issues = watchIssues(page);
    await setTheme(page, theme);
    fs.mkdirSync(`screenshots/${theme}`, { recursive: true });

    for (const path of PAGES) {
      await page.goto(path);
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
      const name = path.replace(/[^a-z]/g, '_');
      await page.screenshot({ path: `screenshots/${theme}/${name}.png` });
      await expectCleanPage(page, issues, `${theme} ${path}`);
      issues.pageErrors.length = 0;
      issues.httpFailures.length = 0;
    }
  });
}

test('font size variants render', async ({ page }) => {
  const issues = watchIssues(page);
  fs.mkdirSync('screenshots/fonts', { recursive: true });
  await setTheme(page, 'light');
  for (const size of ['small', 'medium', 'large']) {
    await page.goto('/index.php');
    await page.evaluate((s) => { document.documentElement.setAttribute('data-fontsize', s); }, size);
    await page.screenshot({ path: `screenshots/fonts/index_${size}.png` });
  }
  await expectCleanPage(page, issues, 'fontsize sweep');
});

test('restore auto theme', async ({ page }) => {
  await setTheme(page, 'auto');
});
