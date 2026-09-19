import { expect, test } from '@playwright/test';
import { AUTH_STATE, BROWSER_TID } from '../fixtures/auth';
import { expectCleanPage, watchIssues } from '../helpers';

test.use({ storageState: AUTH_STATE });

const AUTH_PAGES = [
  '/index.php',
  '/torrents.php',
  `/details.php?id=${BROWSER_TID}`,
  '/forums.php',
  '/usercp.php',
  '/topten.php',
  '/messages.php',
  '/upload.php',
  '/staffpanel.php',
  '/nexusphp',
];

test.describe('authenticated pages', () => {
  for (const path of AUTH_PAGES) {
    test(`${path} renders clean`, async ({ page }) => {
      const issues = watchIssues(page);
      const response = await page.goto(path, { waitUntil: 'networkidle' });
      expect(response?.status(), `${path} HTTP status`).toBeLessThan(400);
      await expectCleanPage(page, issues, path);
    });
  }

  test('forums → viewforum → viewtopic navigation is clean', async ({ page }) => {
    const issues = watchIssues(page);
    await page.goto('/forums.php', { waitUntil: 'networkidle' });

    const forumLink = page.locator('a[href*="action=viewforum"]').first();
    await expect(forumLink, 'at least one forum link').toBeVisible();
    await page.goto((await forumLink.getAttribute('href'))!, { waitUntil: 'networkidle' });
    await expectCleanPage(page, issues, 'viewforum');

    // The first forum may legitimately be empty — the index sidebar
    // always carries recent-topic links, so source the topic there.
    await page.goto('/forums.php', { waitUntil: 'networkidle' });
    const topicLink = page.locator('a[href*="action=viewtopic"]').first();
    await expect(topicLink, 'at least one topic link').toBeVisible();
    await page.goto((await topicLink.getAttribute('href'))!, { waitUntil: 'networkidle' });
    await expectCleanPage(page, issues, 'viewtopic');
  });
});
