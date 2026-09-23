import { expect, test } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });

test.describe('UI-06 index page', () => {
    test('latest torrents title has no count claim', async ({ page }) => {
        await page.goto('/index.php', { waitUntil: 'networkidle' });
        const heading = page.locator('h2', { hasText: 'Latest Torrents' });
        await expect(heading).toBeVisible();
        const cards = page.locator('.lt-grid .lt-card');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);
        expect(count).toBeLessThanOrEqual(9);
    });

    test('coverless torrent cards render compact', async ({ page }) => {
        await page.goto('/index.php', { waitUntil: 'networkidle' });
        const empty = page.locator('.lt-cover-empty').first();
        if ((await empty.count()) === 0) {
            test.skip(true, 'no coverless torrents in fixture data');
            return;
        }
        await expect(empty).toBeVisible();
        const box = await empty.boundingBox();
        expect(box!.height).toBeLessThanOrEqual(64);
    });

    test('shoutbox collapses and persists across reload', async ({ page }) => {
        await page.goto('/index.php', { waitUntil: 'networkidle' });
        const toggle = page.locator('#shoutbox-toggle');
        const panel = page.locator('#shoutbox-panel');
        await expect(toggle).toBeVisible();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await toggle.click();
        await expect(panel).toBeHidden();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');

        await page.reload({ waitUntil: 'networkidle' });
        await expect(panel).toBeHidden();
        await expect(page.locator('#shoutbox-toggle')).toHaveAttribute('aria-expanded', 'false');

        await page.locator('#shoutbox-toggle').click();
        await expect(panel).toBeVisible();
        await expect(page.locator('#shoutbox-toggle')).toHaveAttribute('aria-expanded', 'true');
    });

    test('mentions badge surfaces while collapsed', async ({ page }) => {
        await page.goto('/index.php', { waitUntil: 'networkidle' });
        const panel = page.locator('#shoutbox-panel');
        const toggle = page.locator('#shoutbox-toggle');
        const badge = page.locator('#shoutbox-mentions');
        if (await panel.isHidden()) {
            await toggle.click();
        }
        await expect(panel).toBeVisible();

        // Collapse first — the shoutbox has a 60 s per-user post lock, so a
        // second scripted shout would be rejected with 429. One mention after
        // collapse is enough to produce a positive delta over the baseline.
        await toggle.click();
        await expect(panel).toBeHidden();
        await expect(badge).toBeHidden();

        const post = await page.request.get('/shoutbox.php', {
            params: { sent: 'yes', type: 'shoutbox', shbox_text: '@sysop ui06 new mention', shout: 'Shout!' },
        });
        expect(post.status(), 'shout post accepted').toBe(200);

        // Reload the iframe to deliver the shout deterministically — in
        // production the iframe's own SSE/poll refreshes its DOM, and the
        // parent recounts mentions on each 'load' event (plus a 15 s
        // fallback interval for in-place poll updates).
        await page.locator('#iframe-shout-box').evaluate((el: HTMLIFrameElement) => el.contentWindow!.location.reload());
        await expect(badge).toBeVisible({ timeout: 15000 });
        await expect(badge).toContainText('mention');

        // Clicking the badge expands the panel again.
        await badge.click();
        await expect(panel).toBeVisible();
        await expect(badge).toBeHidden();
    });
});
