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

test('upload form renders section cards and keeps its field contract', async ({ page }) => {
  mysql(`UPDATE users SET stylesheet=5 WHERE id=${userId}`);
  await page.goto('/web/upload', { waitUntil: 'networkidle' });

  const h1 = page.locator('h1').first();
  await expect(h1).toBeVisible();
  await expect(h1).not.toHaveClass(/nx-sr-only/);

  const form = page.locator('#compose');
  await expect(form.locator('section.nx-setcard')).toHaveCount(4);
  await expect(form.locator('#upl-sec-file')).toHaveText('File');
  await expect(form.locator('#upl-sec-descr')).toContainText('Description');
  await expect(form.locator('#upl-sec-media')).toHaveText('Media');
  await expect(form.locator('#upl-sec-publish')).toHaveText('Publish');

  // Field-name / id contract used by upload.js, Livewire and the browser
  // journey spec must stay intact.
  await expect(form.locator('#torrent[name="file"]')).toHaveCount(1);
  await expect(form.locator('#name[name="name"]')).toHaveCount(1);
  await expect(form.locator('#descr[name="descr"]')).toHaveCount(1);
  await expect(form.locator('#browsecat[name="type"]')).toHaveCount(1);
  await expect(form.locator('#browsecat_section')).toHaveCount(1);
  await expect(form.locator('#qr[type="submit"]')).toHaveCount(1);
  await expect(form.locator('#announce-url')).toHaveCount(1);

  // Smilies are collapsed behind <details>, as in the message editor.
  const smilies = form.locator('.bbcode-smilies-details');
  await expect(smilies).toHaveCount(1);
  await expect(smilies).not.toHaveAttribute('open', /.*/);
});

test('selecting a category reveals the taxonomy/tag relation rows', async ({ page }) => {
  mysql(`UPDATE users SET stylesheet=5 WHERE id=${userId}`);
  await page.goto('/web/upload', { waitUntil: 'networkidle' });

  const option = await page.$eval('#browsecat', (select: HTMLSelectElement) =>
    [...select.options].map((o) => o.value).find((value) => value !== '0') ?? '');
  expect(option, 'a seeded category to choose').not.toBe('');
  await page.selectOption('#browsecat', option);
  await expect(page.locator('#browsecat_section [relation]').first()).toBeVisible();
});

test('upload form fits a 390px viewport and the submit button spans it', async ({ page }) => {
  mysql(`UPDATE users SET stylesheet=4 WHERE id=${userId}`);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/web/upload', { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'dark';
  });

  const dimensions = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
  }));
  expect(dimensions.documentWidth).toBeLessThanOrEqual(dimensions.viewport + 1);

  const submit = page.locator('#qr');
  await expect(submit).toBeVisible();
  const box = await submit.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.width).toBeGreaterThan(300);
});

for (const pack of packs) {
  for (const theme of themes) {
    test(`upload form passes axe checks in ${pack.name} ${theme}`, async ({ page }) => {
      mysql(`UPDATE users SET stylesheet=${pack.id} WHERE id=${userId}`);
      await page.goto('/web/upload', { waitUntil: 'networkidle' });
      await page.evaluate((value) => {
        document.documentElement.dataset.theme = value;
      }, theme);

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include('#compose')
        .analyze();
      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  }
}
