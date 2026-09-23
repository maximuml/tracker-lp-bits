import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { AUTH_STATE, BROWSER_USER } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });

const REPO_ROOT = join(__dirname, '..', '..', '..');

function tinker(php: string): string {
    return execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'php', 'php', 'artisan', 'tinker', `--execute=${php}`],
        { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'inherit'] },
    );
}

let db = '';

function mysql(sql: string): string {
    return execFileSync(
        'docker',
        [
            'compose', 'exec', '-T', 'mysql', 'sh', '-c',
            'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --batch --skip-column-names "$1"',
            'sh', db,
        ],
        { cwd: REPO_ROOT, input: sql, encoding: 'utf8', stdio: ['pipe', 'pipe', 'inherit'] },
    );
}

test.beforeAll(() => {
    // The mysql container's own MYSQL_DATABASE env can be stale while CI
    // installs into nexusphp_e2e_testing — resolve the live name (same
    // pattern as notifications.spec.ts).
    const out = tinker('echo DB::connection()->getDatabaseName();');
    db = (out.trim().split('\n').pop() ?? '').trim();
    if (!/^[A-Za-z0-9_]+$/.test(db)) {
        throw new Error(`could not resolve app database name: ${JSON.stringify(out)}`);
    }
});

test.afterAll(() => {
    if (db) {
        mysql(`DELETE FROM shoutbox WHERE text LIKE '%ui06 e2e%'`);
    }
});

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

        // Collapse first — the baseline is captured from the live iframe DOM.
        await toggle.click();
        await expect(panel).toBeHidden();
        await expect(badge).toBeHidden();

        // Insert the mention directly: posting via shoutbox.php hits the
        // 60 s per-user post lock (429), and shout rows authored by the
        // viewer are enough to produce a positive delta over the baseline.
        mysql(
            `INSERT INTO shoutbox (userid,date,text,type)
             SELECT id, UNIX_TIMESTAMP(), '@${BROWSER_USER} ui06 e2e mention', 0
             FROM users WHERE username='${BROWSER_USER}' LIMIT 1`,
        );

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
