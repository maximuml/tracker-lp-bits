import { test, expect, type Page } from '@playwright/test';
import * as fs from 'fs';
import { watchIssues, leakedMarkup, type PageIssues } from '../helpers';
import { AUTH_STATE } from '../fixtures/auth';

/**
 * Pre-release theme readiness gate. Complements the shell check
 * (scripts/check-theme-readiness.sh) which proves the compiled
 * Tailwind layer exists and matches the sources — this spec proves
 * the browser actually receives it and that a converted component is
 * really styled, then sweeps every supported combination:
 *
 *   Classic + Unshatter (stylesheets 4/5) × light/dark schemes,
 *   plus both Filament admin schemes.
 *
 * Settings hygiene: the user's stylesheet + theme are read BEFORE
 * the sweep and restored in a `finally`, so the check leaves no
 * changed user settings. The admin scheme is localStorage state and
 * is restored the same way.
 */
test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

const OUT_DIR = 'screenshots/theme-readiness';
const STYLESHEETS = [
  { id: '4', name: 'Classic', packLink: false },
  { id: '5', name: 'Unshatter', packLink: true },
] as const;
const SCHEMES = ['light', 'dark'] as const;

/**
 * Clean-page check minus content-level 404s: missing cover/attachment
 * images are seed data, not chrome — theme readiness cares about
 * stylesheet/script/asset failures and uncaught errors.
 */
async function expectThemeClean(page: Page, issues: PageIssues, label: string): Promise<void> {
  expect(await leakedMarkup(page), `${label}: leaked markup`).toBeNull();
  expect(issues.pageErrors, `${label}: uncaught errors`).toEqual([]);
  const assetFailures = issues.httpFailures.filter((f) => !f.includes('/attachments/'));
  expect(assetFailures, `${label}: failed asset requests`).toEqual([]);
}

function trackerForm(page: Page) {
  return page.locator('form[action="/web/usercp/tracker"]');
}

/**
 * Saves the real tracker-settings form — the same POST a user makes.
 * Every field round-trips its current value, so flipping one select
 * changes nothing else.
 */
async function saveTrackerSettings(
  page: Page,
  values: { stylesheet?: string; theme?: string },
): Promise<void> {
  await page.goto('/usercp?action=tracker', { waitUntil: 'networkidle' });
  const form = trackerForm(page);
  await expect(form).toBeVisible();
  if (values.stylesheet !== undefined) {
    await form.locator('select[name="stylesheet"]').selectOption(values.stylesheet);
  }
  if (values.theme !== undefined) {
    await form.locator('select[name="theme"]').selectOption(values.theme);
  }
  const saved = page.waitForResponse(
    (r) => r.url().includes('/web/usercp/tracker') && r.request().method() === 'POST',
  );
  await form.locator('[type=submit]').click();
  expect((await saved).status(), 'tracker settings save').toBeLessThan(400);
  await page.waitForLoadState('networkidle');
}

test('nxt.css is served and styles a converted component', async ({ page }) => {
  const issues = watchIssues(page);
  await page.goto('/index.php', { waitUntil: 'networkidle' });

  const info = await page.evaluate(() => {
    const links = [...document.querySelectorAll('link[rel="stylesheet"]')]
      .map((l) => l.getAttribute('href') ?? '');
    const sheet = [...document.styleSheets]
      .find((s) => (s.href ?? '').includes('nxt.css'));
    let rules = -1;
    if (sheet) {
      try {
        rules = sheet.cssRules.length;
      } catch {
        rules = -2;
      }
    }
    return { linked: links.some((h) => h.includes('nxt.css')), rules };
  });
  expect(info.linked, 'nxt.css not linked in head — missing build; run `make css`').toBe(true);
  expect(info.rules, 'nxt.css loaded but empty — partial build; run `make css`').toBeGreaterThan(50);

  // A utility class that ONLY the Tailwind layer can style: without
  // nxt.css `bg-nxm-surface-alt` has no rule and background stays
  // transparent; with it the computed color is an opaque rgb().
  const ref = page.locator('[class*="bg-nxm-"]').first();
  await expect(ref, 'no bg-nxm-* element rendered on index').not.toHaveCount(0);
  const bg = await ref.evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(bg, 'bg-nxm-* class rendered but unstyled — nxt.css missing/stale?').not.toBe('rgba(0, 0, 0, 0)');

  await expectThemeClean(page, issues, 'nxt.css readiness');
});

test('stylesheet × scheme sweep screenshots all combinations', async ({ page }) => {
  const issues = watchIssues(page);
  fs.mkdirSync(OUT_DIR, { recursive: true });

  await page.goto('/usercp?action=tracker', { waitUntil: 'networkidle' });
  const form = trackerForm(page);
  const originalStylesheet = await form.locator('select[name="stylesheet"]').inputValue();
  const originalTheme = await form.locator('select[name="theme"]').inputValue();

  try {
    for (const ss of STYLESHEETS) {
      for (const scheme of SCHEMES) {
        await saveTrackerSettings(page, { stylesheet: ss.id, theme: scheme });
        await page.goto('/index.php', { waitUntil: 'networkidle' });
        await expect(page.locator('html')).toHaveAttribute('data-theme', scheme);

        // Unshatter loads its own pack stylesheet after modern.css;
        // Classic is the base cascade and emits no pack link.
        await expect(page.locator('link[href*="styles/Unshatter"]')).toHaveCount(ss.packLink ? 1 : 0);

        // Classic legacy chrome must follow the scheme via --nxm-*:
        // its theme.css (loaded on legacy pages only) carries the dark
        // palette, so a legacy page's body bg switches off the beige.
        if (ss.name === 'Classic') {
          await page.goto('/faq.php', { waitUntil: 'networkidle' });
          const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
          const expected = scheme === 'dark' ? 'rgb(35, 36, 30)' : 'rgb(207, 207, 204)';
          expect(bg, `Classic/${scheme} body bg — theme.css not token-driven?`).toBe(expected);
          await expect(page.locator('link[href*="styles/Classic/theme.css"]')).toHaveCount(1);
          await page.goto('/index.php', { waitUntil: 'networkidle' });
        }

        await page.screenshot({ path: `${OUT_DIR}/${ss.name}-${scheme}.png`, fullPage: true });
        await expectThemeClean(page, issues, `${ss.name}/${scheme}`);
        issues.pageErrors.length = 0;
        issues.httpFailures.length = 0;
      }
    }
  } finally {
    await saveTrackerSettings(page, {
      stylesheet: originalStylesheet,
      theme: originalTheme,
    });
  }
});

test('admin panel renders both light and dark schemes', async ({ page }) => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  await page.goto('/nexusphp', { waitUntil: 'networkidle' });

  const original = await page.evaluate(() => localStorage.getItem('theme'));
  try {
    for (const scheme of SCHEMES) {
      await page.evaluate((v) => localStorage.setItem('theme', v), scheme);
      await page.reload({ waitUntil: 'networkidle' });
      const isDark = await page.evaluate(
        () => document.documentElement.classList.contains('dark'),
      );
      expect(isDark, `admin ${scheme} scheme`).toBe(scheme === 'dark');
      await page.screenshot({ path: `${OUT_DIR}/admin-${scheme}.png`, fullPage: true });
    }
  } finally {
    await page.evaluate(
      (v) => (v === null ? localStorage.removeItem('theme') : localStorage.setItem('theme', v)),
      original,
    );
  }
});
