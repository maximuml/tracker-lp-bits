import { test, expect } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';

test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

test('shoutbox delete opens single <dialog> confirm (parent doc)', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    const del = page.locator('a[data-shout-del]').first();
    if ((await del.count()) === 0) {
        test.skip(true, 'no deletable shouts seeded');
        return;
    }
    await del.click();
    const dlg = page.locator('dialog.nx-modal');
    await expect(dlg).toHaveCount(1);
    await dlg.getByRole('button', { name: 'Cancel' }).click();
    await expect(dlg).toHaveCount(0);
});

test('clear-shout opens exactly one <dialog> (no double confirm)', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    const clear = page.locator('#clear-shout-box a');
    if ((await clear.count()) === 0) {
        test.skip(true, 'clear-shout control absent');
        return;
    }
    await clear.click();
    const dlgs = page.locator('dialog.nx-modal');
    await expect(dlgs).toHaveCount(1);
    await dlgs.getByRole('button', { name: 'Cancel' }).click();
    await expect(dlgs).toHaveCount(0);
});
