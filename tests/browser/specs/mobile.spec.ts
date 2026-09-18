import { expect, test } from '@playwright/test';
import { AUTH_STATE, BROWSER_TID } from '../fixtures/auth';

/**
 * 390×844 viewport — modern-layout pages must not scroll horizontally.
 * Legacy-layout pages are exempt: their fixed-width tables are
 * stage-4 mobile work, tracked separately.
 */
const MODERN_PAGES = [
  '/index.php',
  '/torrents.php',
  `/details.php?id=${BROWSER_TID}`,
  '/forums.php',
  '/usercp.php',
];

test.describe('mobile viewport', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    storageState: AUTH_STATE,
  });

  for (const path of MODERN_PAGES) {
    test(`${path} has no horizontal scroll`, async ({ page }) => {
      await page.goto(path, { waitUntil: 'networkidle' });
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      );
      expect(overflow, `${path}: horizontal overflow ${overflow}px`).toBeLessThanOrEqual(1);
    });
  }
});
