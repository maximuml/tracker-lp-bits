import { AxeBuilder } from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { AUTH_STATE, BROWSER_USER } from '../fixtures/auth';

const REPO_ROOT = join(__dirname, '..', '..', '..');

test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

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

const packs = [
  { name: 'Classic', id: 4 },
  { name: 'Unshatter', id: 5 },
];
const themes = ['light', 'dark'];

let userId = 0;

test.beforeAll(() => {
  db = tinker('echo DB::connection()->getDatabaseName();').trim().split('\n').pop()?.trim() ?? '';
  if (!/^[A-Za-z0-9_]+$/.test(db)) {
    throw new Error(`could not resolve the app database: ${db}`);
  }
  userId = Number(mysql(`SELECT id FROM users WHERE username='${BROWSER_USER}' LIMIT 1`).trim());
  if (!Number.isInteger(userId) || userId <= 0) {
    throw new Error('could not resolve the browser user');
  }
});

test.afterAll(() => {
  if (userId > 0) {
    mysql(`UPDATE users SET stylesheet=5 WHERE id=${userId}`);
  }
});

for (const pack of packs) {
  for (const theme of themes) {
    test(`bonus shop passes axe in ${pack.name} ${theme}`, async ({ page }) => {
      mysql(`UPDATE users SET stylesheet=${pack.id} WHERE id=${userId}`);
      await page.goto('/web/mybonus', { waitUntil: 'networkidle' });
      await page.evaluate((value) => {
        document.documentElement.dataset.theme = value;
      }, theme);

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include(['.nx-bshop', '.nx-bshop__info'])
        .analyze();
      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  }
}

test('bonus shop fits a 390px viewport and keeps a buy button visible', async ({ page }) => {
  mysql(`UPDATE users SET stylesheet=4 WHERE id=${userId}`);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/web/mybonus', { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'light';
  });

  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  );
  expect(overflow).toBeLessThanOrEqual(1);

  const button = page.locator('.nx-bshop__buy input[type="submit"]').first();
  const box = await button.boundingBox();
  expect(box).not.toBeNull();
  expect(box?.y).toBeGreaterThanOrEqual(0);
  expect((box?.y ?? 0) + (box?.height ?? 0)).toBeLessThanOrEqual(844);
});

test('one-time submit reset preserves disabled shop buttons', async ({ page }) => {
  await page.addInitScript(() => {
    document.addEventListener(
      'DOMContentLoaded',
      () => {
        const button = document.querySelector('.nx-bshop__buy input[type="submit"][data-submit-once]');
        if (button instanceof HTMLInputElement) {
          button.disabled = true;
        }
      },
      { once: true },
    );
  });
  await page.goto('/web/mybonus', { waitUntil: 'networkidle' });

  const state = await page.evaluate(() => {
    const buttons = Array.from(
      document.querySelectorAll<HTMLInputElement>('.nx-bshop__buy input[type="submit"][data-submit-once]'),
    );
    const permanentlyDisabled = buttons.find((button) => button.disabled);
    const enabled = buttons.find((button) => !button.disabled);
    if (!permanentlyDisabled || !enabled?.form) {
      return null;
    }

    enabled.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    const disabledWhileSubmitting = enabled.disabled;
    window.dispatchEvent(new Event('pageshow'));

    return {
      permanentlyDisabled: permanentlyDisabled.disabled,
      disabledWhileSubmitting,
      reenabledAfterPageshow: !enabled.disabled,
    };
  });

  expect(state).toEqual({
    permanentlyDisabled: true,
    disabledWhileSubmitting: true,
    reenabledAfterPageshow: true,
  });
});

test('bonus shop gifts chip lands on the gifts group', async ({ page }) => {
  await page.goto('/web/mybonus', { waitUntil: 'networkidle' });
  await page.locator('.nx-bshop__chip', { hasText: 'Gifts' }).click();
  await expect(page).toHaveURL(/\/web\/mybonus#bonus-gifts$/);
  await expect(page.locator('#bonus-gifts')).toBeVisible();
});

test('bonus shop information disclosure opens on click', async ({ page }) => {
  await page.goto('/web/mybonus', { waitUntil: 'networkidle' });
  const info = page.locator('details.nx-bshop__info');
  await expect(info).not.toHaveAttribute('open', '');
  await info.locator('summary').click();
  await expect(info).toHaveAttribute('open', '');
});
