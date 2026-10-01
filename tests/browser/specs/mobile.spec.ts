import { expect, test } from '@playwright/test';
import { AUTH_STATE, BROWSER_TID } from '../fixtures/auth';

/**
 * 390×844 viewport — pages must not scroll horizontally. Covers both the
 * modern chrome and representative legacy-layout pages (swept clean in
 * stage 4.2; data-nx tables become scrollers/cards via modern.css).
 */
const MODERN_PAGES = [
  '/index.php',
  '/torrents.php',
  `/details.php?id=${BROWSER_TID}`,
  '/forums.php',
  '/usercp.php',
];

const LEGACY_PAGES = [
  '/messages.php',
  '/mybonus.php',
  '/topten.php',
  '/staffpanel.php',
  '/settings.php',
  '/userdetails.php?id=1',
];

test.describe('mobile viewport', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    storageState: AUTH_STATE,
  });

  for (const path of [...MODERN_PAGES, ...LEGACY_PAGES]) {
    test(`${path} has no horizontal scroll`, async ({ page }) => {
      await page.goto(path, { waitUntil: 'networkidle' });
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      );
      expect(overflow, `${path}: horizontal overflow ${overflow}px`).toBeLessThanOrEqual(1);
    });
  }

  test('burger toggles collapsed nav+userbar', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });

    const burger = page.locator('.nxm-burger');
    const panel = page.locator('#nxm-collapse');
    await expect(burger).toBeVisible();
    await expect(burger).toHaveAttribute('aria-expanded', 'false');
    await expect(burger).toHaveAttribute('aria-controls', 'nxm-collapse');
    await expect(panel).toBeHidden();

    await burger.click();
    await expect(burger).toHaveAttribute('aria-expanded', 'true');
    await expect(panel).toBeVisible();
    await expect(page.locator('.nxm-nav__list a').first()).toBeVisible();
    await expect(page.locator('details.nxm-usermenu')).toBeVisible();

    await burger.click();
    await expect(burger).toHaveAttribute('aria-expanded', 'false');
    await expect(panel).toBeHidden();

    // Re-open, then Escape closes and returns focus to the button.
    await burger.click();
    await expect(panel).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(burger).toBeFocused();
  });

  test('nav links meet 44px touch target when menu open', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    await page.locator('.nxm-burger').click();

    const heights = await page.locator('.nxm-nav__list a').evaluateAll(
      (els) => els.map((el) => el.getBoundingClientRect().height),
    );
    expect(heights.length).toBeGreaterThan(3);
    for (const h of heights) {
      expect(h).toBeGreaterThanOrEqual(44);
    }
  });

  test('collapsed header stays under 25% of viewport height', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const height = await page.evaluate(
      () => document.querySelector('.nxm-header')!.getBoundingClientRect().height,
    );
    expect(height, `collapsed header ${height}px`).toBeLessThanOrEqual(844 * 0.25);
  });

  test('torrents listing renders as cards at 390px', async ({ page }) => {
    await page.goto('/torrents.php', { waitUntil: 'networkidle' });
    const row = page.locator('table.nx-torrents tbody tr').first();
    if (await row.count() === 0) {
      test.skip(true, 'no torrents seeded');
    }
    const display = await row.evaluate((el) => getComputedStyle(el).display);
    expect(display).toBe('flex');
    // Card stays inside the viewport and meta labels are rendered.
    const width = await row.evaluate((el) => el.getBoundingClientRect().width);
    expect(width).toBeLessThanOrEqual(390);
    await expect(row.locator('.nxm-td-name a').first()).toBeVisible();
  });

  test('burger hidden on desktop viewport', async ({ browser }) => {
    const ctx = await browser.newContext({
      viewport: { width: 1280, height: 900 },
      storageState: AUTH_STATE,
    });
    const page = await ctx.newPage();
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    await expect(page.locator('.nxm-burger')).toBeHidden();
    await expect(page.locator('.nxm-nav__list a').first()).toBeVisible();
    await ctx.close();
  });
});
