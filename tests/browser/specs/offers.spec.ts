import { AxeBuilder } from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { AUTH_STATE, BROWSER_USER } from '../fixtures/auth';

const REPO_ROOT = join(__dirname, '..', '..', '..');

test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

function mysql(sql: string, db: string): string {
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

function tinker(php: string): string {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'php', 'php', 'artisan', 'tinker', `--execute=${php}`],
    { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'inherit'] },
  );
}

const packs = [
  { name: 'Classic', id: 4 },
  { name: 'Unshatter', id: 5 },
];
const themes = ['light', 'dark'];

let db = '';
let userId = 0;
let offerId = 0;

test.beforeAll(() => {
  db = tinker('echo DB::connection()->getDatabaseName();').trim().split('\n').pop()?.trim() ?? '';
  if (!/^[A-Za-z0-9_]+$/.test(db)) {
    throw new Error(`could not resolve the app database: ${db}`);
  }
  userId = Number(mysql(`SELECT id FROM users WHERE username='${BROWSER_USER}' LIMIT 1`, db).trim());
  if (!Number.isInteger(userId) || userId <= 0) {
    throw new Error('could not resolve the browser user');
  }
  mysql(
    `INSERT INTO offers (userid,name,descr,added,yeah,against,category,comments,allowed)
     VALUES (${userId},'Spec Offer Alpha','First spec offer.',NOW(),15,8,401,0,1),
            (${userId},'Spec Offer Beta','Second spec offer.',NOW(),2,1,402,3,1)`,
    db,
  );
  offerId = Number(mysql(`SELECT id FROM offers WHERE name='Spec Offer Alpha' LIMIT 1`, db).trim());
  if (!Number.isInteger(offerId) || offerId <= 0) {
    throw new Error('could not seed the offers');
  }
});

test.afterAll(() => {
  if (offerId > 0) {
    mysql(`DELETE FROM offers WHERE name IN ('Spec Offer Alpha','Spec Offer Beta')`, db);
  }
  if (userId > 0) {
    mysql(`UPDATE users SET stylesheet=4 WHERE id=${userId}`, db);
  }
});

test('offers list keeps the field and vote contract', async ({ page }) => {
  await page.goto('/web/offers', { waitUntil: 'networkidle' });

  await expect(page.locator('.nx-off__top .nx-off__h1')).toContainText('Offers');
  await expect(page.locator('details.nx-off__rules summary')).toBeVisible();
  await expect(page.locator('a.nx-off__add')).toHaveAttribute('href', /add_offer=1/);
  await expect(page.locator('.nx-off__search input[name="search"]')).toBeVisible();
  await expect(page.locator('.nx-off__search select[name="category"]')).toBeVisible();

  const row = page.locator('.nx-offtable tr', { hasText: 'Spec Offer Alpha' });
  await expect(row.locator('.nx-off__chip')).toContainText('Pending');
  await expect(row.locator('.nx-off__score')).toContainText('+7');
  await expect(row.locator('.nx-off__meta').nth(1)).toContainText('more to approve');
  await expect(row.locator('form[action="/web/offers/vote"] input[name="vote"][value="yeah"]')).toBeAttached();
  await expect(row.locator('a[href*="off_details=1"]')).toBeAttached();
});

test('offer detail shows heading, chip, actions and comments card', async ({ page }) => {
  await page.goto(`/web/offers?id=${offerId}&off_details=1`, { waitUntil: 'networkidle' });

  await expect(page.locator('.nx-off__back a')).toHaveAttribute('href', '/web/offers');
  await expect(page.locator('.nx-off__detail .nx-off__h1')).toContainText('Spec Offer Alpha');
  await expect(page.locator('.nx-off__detail .nx-off__chip')).toContainText('Pending');
  await expect(page.locator('.nx-off__actions a', { hasText: 'Report Offer' })).toBeVisible();
  await expect(page.locator('#startcomments')).toBeVisible();
});

test('add offer form keeps the field contract', async ({ page }) => {
  await page.goto('/web/offers?add_offer=1', { waitUntil: 'networkidle' });

  const form = page.locator('form#compose');
  await expect(form).toHaveAttribute('action', '/web/offers/create');
  await expect(form.locator('select[name="type"]')).toBeVisible();
  await expect(form.locator('input[name="name"]')).toBeVisible();
  await expect(form.locator('input[name="picture"]')).toBeVisible();
  await expect(form.locator('input#qr[type="submit"]')).toBeVisible();
  await expect(page.locator('.nx-savebar')).toBeVisible();
});

test('offers list fits a 390px viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/web/offers', { waitUntil: 'networkidle' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  await expect(page.locator('.nx-off__add')).toBeVisible();
  await expect(page.locator('.nx-offtable')).toBeVisible();
});

test('offer detail fits a 390px viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`/web/offers?id=${offerId}&off_details=1`, { waitUntil: 'networkidle' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
});

for (const pack of packs) {
  for (const theme of themes) {
    test(`offers list passes axe in ${pack.name} ${theme}`, async ({ page }) => {
      mysql(`UPDATE users SET stylesheet=${pack.id} WHERE id=${userId}`, db);
      await page.goto('/web/offers', { waitUntil: 'networkidle' });
      await page.evaluate((value) => {
        document.documentElement.dataset.theme = value;
      }, theme);
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include(['.nx-off'])
        .analyze();
      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  }
}
