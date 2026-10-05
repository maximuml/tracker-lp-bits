import { expect, test } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });

test.describe('UI-06 index page', () => {
    test('latest torrents title has no count claim', async ({ page }) => {
        await page.goto('/index.php', { waitUntil: 'networkidle' });
        // The section is settings-driven — skip when the env renders none.
        if ((await page.locator('.lt-grid').count()) === 0) {
            test.skip(true, 'latest torrents section not rendered in this env');
            return;
        }
        await expect(page.locator('h2', { hasText: 'Latest Torrents' })).toBeVisible();
        await expect(page.locator('h2', { hasText: 'Last 5 Torrent' })).toHaveCount(0);
        const cards = page.locator('.lt-grid .lt-card');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);
        expect(count).toBeLessThanOrEqual(12);
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
        const toggle = page.locator('a[aria-controls="kshoutbox"]');
        const panel = page.locator('#kshoutbox');
        await expect(toggle).toBeVisible();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await toggle.click();
        await expect(panel).toBeHidden();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');

        await page.reload({ waitUntil: 'networkidle' });
        await expect(panel).toBeHidden();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');

        await toggle.click();
        await expect(panel).toBeVisible();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    });

    test('mentions badge surfaces while collapsed', async ({ page }) => {
        await page.goto('/index.php', { waitUntil: 'networkidle' });
        const panel = page.locator('#kshoutbox');
        const toggle = page.locator('a[aria-controls="kshoutbox"]');
        const badge = page.locator('#shoutbox-mentions');
        if (await panel.isHidden()) {
            await toggle.click();
        }
        await expect(panel).toBeVisible();

        // Collapse first — the mention baseline is captured from the live
        // iframe DOM at this moment.
        await toggle.click();
        await expect(panel).toBeHidden();
        await expect(badge).toBeHidden();

        // Deliver a mention row into the panel DOM and broadcast 'refresh' —
        // the shoutbox list is a Livewire component now, so rows morph inside
        // #kshoutbox and the parent scan is triggered by the broadcast/scan
        // timer. The contract under test is "badge = new .shoutrow-mentions-me
        // rows since collapse".
        await page.locator('#kshoutbox').evaluate((panel: HTMLElement) => {
            const tbody = panel.querySelector('tbody') || panel;
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.className = 'shoutrow shoutrow-mentions-me';
            td.textContent = 'ui06 e2e mention';
            tr.appendChild(td);
            tbody.appendChild(tr);
            if (typeof BroadcastChannel !== 'undefined') {
                new BroadcastChannel('nx-shout-shoutbox').postMessage('refresh');
            }
        });
        await expect(badge).toBeVisible({ timeout: 10000 });
        await expect(badge).toContainText('mention');

        // Clicking the badge expands the panel again.
        await badge.click();
        await expect(panel).toBeVisible();
        await expect(badge).toBeHidden();
    });
});
