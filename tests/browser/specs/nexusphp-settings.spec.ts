import { expect, test } from '@playwright/test';
import { AUTH_STATE } from '../fixtures/auth';
import { watchIssues } from '../helpers';

/**
 * /nexusphp settings surfaces — sysop-only Filament pages.
 * Verifies the settings form loads all tabs, persists a save through
 * Livewire, and that Tracker URLs validate + normalize input.
 */
test.use({ storageState: AUTH_STATE });

test.describe('nexusphp settings', () => {
  test('settings page renders all tabs and persists a field', async ({
    page,
  }) => {
    const issues = watchIssues(page);
    await page.goto('/nexusphp/system/settings', {
      waitUntil: 'networkidle',
    });

    // All seven tabs render.
    for (const tab of [
      'H&R',
      'Backup',
      'Meilisearch',
      'Image hosting',
      'Permission',
      'Captcha',
      'System',
    ]) {
      await expect(page.getByRole('tab', { name: tab })).toBeVisible();
    }

    // System tab → change alarm_email_receiver → save.
    await page.getByRole('tab', { name: 'System' }).click();
    const field = page.locator('input[id$="alarm_email_receiver"]');
    await expect(field).toBeVisible();
    const marker = `e2e-${Date.now()}@example.com`;
    await field.fill(marker);
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.locator('.fi-no-notification')).toContainText(
      /success|saved/i,
      { timeout: 15_000 },
    );

    // Reload — value must round-trip from the DB, not just the form.
    await page.reload({ waitUntil: 'networkidle' });
    await page.getByRole('tab', { name: 'System' }).click();
    await expect(page.locator('input[id$="alarm_email_receiver"]')).toHaveValue(
      marker,
    );

    // Restore empty value.
    await page.locator('input[id$="alarm_email_receiver"]').fill('');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.locator('.fi-no-notification')).toContainText(
      /success|saved/i,
      { timeout: 15_000 },
    );

    expect(issues.pageErrors).toEqual([]);
  });

  test('tracker urls validate, normalize, edit and delete', async ({
    page,
  }) => {
    const issues = watchIssues(page);
    await page.goto('/nexusphp/system/tracker-urls', {
      waitUntil: 'networkidle',
    });

    // Create with host-only value → must be normalized to scheme://host.
    await page.getByRole('button', { name: 'New tracker url' }).click();
    const modal = page.locator('[role="dialog"]').last();
    const urlInput = modal.locator('input[type="text"]').first();
    await urlInput.fill('e2e-tracker.example.com');
    // Required radios: is_default → no, enabled → enabled.
    await modal
      .getByRole('radiogroup', { name: /is default/i })
      .getByRole('radio', { name: /^no$/i })
      .check();
    await modal
      .getByRole('radiogroup', { name: /^enabled/i })
      .getByRole('radio', { name: /^enabled$/i })
      .check();
    await modal.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page.locator('.fi-no-notification')).toContainText(
      /created|success/i,
      { timeout: 15_000 },
    );
    await expect(
      page.locator('table').getByText(/https?:\/\/e2e-tracker\.example\.com/),
    ).toBeVisible();

    // Invalid value rejected.
    await page.getByRole('button', { name: 'New tracker url' }).click();
    const modal2 = page.locator('[role="dialog"]').last();
    await modal2.locator('input[type="text"]').first().fill('javascript://x');
    await modal2
      .getByRole('radiogroup', { name: /is default/i })
      .getByRole('radio', { name: /^no$/i })
      .check();
    await modal2
      .getByRole('radiogroup', { name: /^enabled/i })
      .getByRole('radio', { name: /^enabled$/i })
      .check();
    await modal2.getByRole('button', { name: 'Create', exact: true }).click();
    // Rejected: dialog stays open, no success notification, no row added.
    await expect(modal2.getByRole('button', { name: 'Cancel' })).toBeVisible({
      timeout: 10_000,
    });
    await expect(page.locator('.fi-no-notification')).toHaveCount(0);
    await modal2.getByRole('button', { name: 'Cancel' }).click();
    await expect(
      page.locator('table').getByText('javascript://x'),
    ).toHaveCount(0);

    // Edit the row: scheme + port + path + trailing slash.
    const row = page.locator('tr', { hasText: 'e2e-tracker.example.com' });
    await row.getByRole('button', { name: /edit/i }).click();
    const editModal = page.locator('[role="dialog"]').last();
    const editUrl = editModal.locator('input[type="text"]').first();
    await editUrl.fill('https://e2e-tracker.example.com:8443/announce.php/');
    await editModal.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.fi-no-notification')).toContainText(
      /saved|success/i,
      { timeout: 15_000 },
    );
    // Trailing slash trimmed, port+path preserved.
    await expect(
      page
        .locator('table')
        .getByText('https://e2e-tracker.example.com:8443/announce.php'),
    ).toBeVisible();

    // Delete the test row.
    const row2 = page.locator('tr', {
      hasText: 'e2e-tracker.example.com:8443',
    });
    await row2.getByRole('button', { name: /delete/i }).click();
    const delModal = page.locator('[role="dialog"], [role="alertdialog"]').last();
    await delModal
      .getByRole('button', { name: /confirm|delete/i })
      .last()
      .click();
    await expect(
      page.locator('table').getByText('e2e-tracker.example.com'),
    ).toHaveCount(0);

    expect(issues.pageErrors).toEqual([]);
  });
});
