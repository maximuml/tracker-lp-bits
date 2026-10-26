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
let userId = 0;

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
    test(`H&R passes axe and contrast checks in ${pack.name} ${theme}`, async ({ page }) => {
      mysql(`UPDATE users SET stylesheet=${pack.id} WHERE id=${userId}`);
      await page.goto('/web/myhr', { waitUntil: 'networkidle' });
      await page.evaluate((value) => {
        document.documentElement.dataset.theme = value;
      }, theme);

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include('.nx-hr')
        .analyze();
      expect(results.violations.map((violation) => violation.id)).toEqual([]);

      const contrasts = await page.locator('.nx-hr').evaluate((root) => {
        const channels = (color: string) => {
          const srgb = color.match(/^color\(srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*[\d.]+)?\)$/);
          if (srgb) return srgb.slice(1).map(Number);

          return color.match(/[\d.]+/g)!.slice(0, 3).map(Number).map((channel) => channel / 255);
        };
        const luminance = (color: string) => {
          const rgb = channels(color).map((channel) =>
            channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4,
          );
          return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
        };
        const contrast = (first: string, second: string) => {
          const values = [luminance(first), luminance(second)].sort((a, b) => b - a);
          return Number(((values[0] + 0.05) / (values[1] + 0.05)).toFixed(2));
        };
        const badge = root.querySelector<HTMLElement>('.nx-hr__badge');
        const plaque = root.querySelector<HTMLElement>('.nx-hr__rules');
        const card = root.querySelector<HTMLElement>('.nx-hr__status-link');
        const progress = root.querySelector<HTMLProgressElement>('.nx-hr__progress');
        const disabled = root.querySelector<HTMLInputElement>('.remove-hr:disabled');
        const ratio = root.querySelector<HTMLElement>('.nx-hr__ratio--low') ?? root.querySelector<HTMLElement>('.nx-hr__ratio');
        ratio?.classList.add('nx-hr__ratio--low');
        const backgroundColor = (element: HTMLElement) => {
          let current: HTMLElement | null = element;
          while (current && root.contains(current)) {
            const color = getComputedStyle(current).backgroundColor;
            if (color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)') return color;
            current = current.parentElement;
          }

          return getComputedStyle(document.body).backgroundColor;
        };
        const textContrast = (element: HTMLElement) => {
          const style = getComputedStyle(element);
          return contrast(style.color, backgroundColor(element));
        };
        const progressContrast = () => {
          const colorProbe = document.createElement('span');
          colorProbe.style.color = 'var(--nxm-surface-alt)';
          root.append(colorProbe);
          const track = getComputedStyle(colorProbe).color;
          colorProbe.remove();

          return contrast(getComputedStyle(progress!).accentColor, track);
        };

        return {
          badge: badge ? textContrast(badge) : null,
          plaque: plaque ? textContrast(plaque) : null,
          cardBorder: card
            ? contrast(getComputedStyle(card).borderTopColor, getComputedStyle(card).backgroundColor)
            : null,
          progress: progress ? progressContrast() : null,
          disabled: disabled ? textContrast(disabled) : null,
          ratio: ratio ? textContrast(ratio) : null,
        };
      });
      console.log(`H&R contrast ${pack.name} ${theme}: ${JSON.stringify(contrasts)}`);
      if (contrasts.badge !== null) expect(contrasts.badge).toBeGreaterThanOrEqual(4.5);
      if (contrasts.plaque !== null) expect(contrasts.plaque).toBeGreaterThanOrEqual(4.5);
      if (contrasts.cardBorder !== null) expect(contrasts.cardBorder).toBeGreaterThanOrEqual(3);
      if (contrasts.progress !== null) expect(contrasts.progress).toBeGreaterThanOrEqual(3);
      if (contrasts.disabled !== null) expect(contrasts.disabled).toBeGreaterThanOrEqual(4.5);
      if (contrasts.ratio !== null) expect(contrasts.ratio).toBeGreaterThanOrEqual(4.5);
    });
  }
}

test('H&R desktop table cells remain aligned with their headers', async ({ page }) => {
  mysql(`UPDATE users SET stylesheet=4 WHERE id=${userId}`);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('/web/myhr', { waitUntil: 'networkidle' });

  const layout = await page.locator('#hr-table').evaluate((table) => {
    const headers = Array.from(table.querySelectorAll('thead th'));
    const rows = Array.from(table.querySelectorAll('tbody tr'));

    return {
      headers: headers.map((header) => header.getBoundingClientRect().left),
      displays: Array.from(table.querySelectorAll('tbody td')).map((cell) => getComputedStyle(cell).display),
      rows: rows.map((row) => Array.from(row.querySelectorAll('td')).map((cell) => cell.getBoundingClientRect().left)),
    };
  });
  expect(layout.headers).toHaveLength(6);
  expect(layout.rows.length).toBeGreaterThan(0);
  expect(layout.displays.every((display) => display === 'table-cell')).toBe(true);
  for (const row of layout.rows) {
    expect(row).toHaveLength(layout.headers.length);
    row.forEach((left, index) => {
      expect(Math.abs(left - layout.headers[index])).toBeLessThanOrEqual(1);
    });
  }
});

test('H&R cards and first-row action fit a 390px viewport', async ({ page }) => {
  mysql(`UPDATE users SET stylesheet=4 WHERE id=${userId}`);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/web/myhr', { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'dark';
  });

  const dimensions = await page.evaluate(() => {
    const wrapper = document.querySelector<HTMLElement>('.nx-hr .overflow-x-auto');
    return {
      documentWidth: document.documentElement.scrollWidth,
      documentViewport: document.documentElement.clientWidth,
      wrapperScrollWidth: wrapper?.scrollWidth ?? 0,
      wrapperWidth: wrapper?.clientWidth ?? 0,
    };
  });
  expect(dimensions.documentWidth).toBeLessThanOrEqual(dimensions.documentViewport + 1);
  expect(dimensions.wrapperScrollWidth).toBeLessThanOrEqual(dimensions.wrapperWidth + 1);

  const action = page.locator('.nx-hr__row').first().locator('.nx-hr__action input.remove-hr, .nx-hr__action .nx-hr__need').first();
  await expect(action).toBeVisible();
  const box = await action.boundingBox();
  expect(box).not.toBeNull();
  expect(box?.x).toBeGreaterThanOrEqual(0);
  expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(390);
});

test('enabled Remove opens confirmation and Cancel sends no POST', async ({ page }) => {
  mysql(`UPDATE users SET stylesheet=5 WHERE id=${userId}`);
  await page.goto('/web/myhr', { waitUntil: 'networkidle' });

  const remove = page
    .locator('.nx-hr__row')
    .filter({ hasText: 'Browser smoke H&R fixture comment' })
    .locator('.remove-hr');
  await expect(remove).toBeVisible();
  await remove.evaluate((element) => {
    element.removeAttribute('disabled');
    element.classList.add('nx-btn--primary');
  });

  const postRequests: string[] = [];
  page.on('request', (request) => {
    if (request.method() === 'POST' && request.url().includes('/web/hit-and-runs/remove')) {
      postRequests.push(request.url());
    }
  });
  await remove.click();

  const dialog = page.locator('dialog.nx-modal');
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: 'Cancel' }).click();
  await expect(dialog).toBeHidden();
  expect(postRequests).toEqual([]);
});
