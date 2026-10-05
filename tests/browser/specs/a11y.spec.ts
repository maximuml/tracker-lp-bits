import { AxeBuilder } from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { AUTH_STATE, BROWSER_TID } from '../fixtures/auth';

/**
 * axe-core accessibility ratchet — replaces the a11y.yml CLI scan.
 *
 * `a11y-baseline.json` records the sorted axe rule-ids each page
 * violates today, split into public and authenticated pages. A rule-id
 * NOT in the baseline fails (new violation type); a baseline entry
 * that disappears is tolerated — delete it in the fixing PR (baseline
 * only shrinks, same as the PHPUnit ratchets).
 *
 * Regenerate after intentional a11y fixes:
 *   A11Y_UPDATE_BASELINE=1 npx playwright test a11y
 *
 * /nexusphp is intentionally absent — that is Filament's own UI
 * surface, outside the legacy/modernisation scope.
 */

interface Baseline {
  public: Record<string, string[]>;
  authenticated: Record<string, string[]>;
}

const BASELINE_PATH = join(__dirname, '..', 'a11y-baseline.json');
const UPDATE = process.env.A11Y_UPDATE_BASELINE === '1';
const BASELINE: Baseline = existsSync(BASELINE_PATH)
  ? (JSON.parse(readFileSync(BASELINE_PATH, 'utf-8')) as Baseline)
  : { public: {}, authenticated: {} };

const PAGES: Record<keyof Baseline, string[]> = {
  public: ['/index.php', '/torrents.php', '/forums.php', '/login'],
  authenticated: [
    '/index.php',
    '/torrents.php',
    `/details.php?id=${BROWSER_TID}`,
    '/forums.php',
    '/usercp.php',
    '/topten.php',
    '/messages.php',
  ],
};

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

test.describe('a11y violations do not grow', () => {
  for (const [group, pages] of Object.entries(PAGES) as [keyof Baseline, string[]][]) {
    test.describe(group, () => {
      if (group === 'authenticated') {
        test.use({ storageState: AUTH_STATE });
      }

      for (const path of pages) {
        test(`${group} ${path} has no new axe violations`, async ({ page }) => {
          await page.goto(path, { waitUntil: 'networkidle' });
          const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
          const ruleIds = [...new Set(results.violations.map((v) => v.id))].sort();

          if (UPDATE) {
            BASELINE[group][path] = ruleIds;
            return;
          }

          const baselineIds = BASELINE[group][path];
          test.skip(!baselineIds, `${path} missing from a11y-baseline.json — regenerate it`);
          const unexpected = ruleIds.filter((id) => !baselineIds.includes(id));
          expect(
            unexpected,
            `${path}: new axe violations ${unexpected.join(', ')} — fix them or update a11y-baseline.json`,
          ).toEqual([]);
        });
      }
    });
  }

  test.afterAll(() => {
    if (UPDATE) {
      writeFileSync(BASELINE_PATH, `${JSON.stringify(BASELINE, null, 2)}\n`);
    }
  });
});

test.describe('expanded search panel', () => {
  test.use({ storageState: AUTH_STATE });

  test('expanded /torrents.php filters pass axe and expose keyboard path', async ({
    page,
  }) => {
    await page.goto('/torrents.php', { waitUntil: 'networkidle' });

    const toggle = page.locator('[aria-controls="ksearchboxmain"]');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toHaveAttribute('aria-controls', 'ksearchboxmain');

    // Keyboard: focus the toggle, activate with Enter.
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    const body = page.locator('#ksearchboxmain');
    await expect(body).toBeVisible();

    // Keyboard order: Tab from the toggle reaches a control inside the
    // panel (first category checkbox or select-all button).
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
      const el = document.activeElement;
      return el ? el.closest('#ksearchboxmain') !== null : false;
    });
    expect(focused, 'Tab from toggle should land inside the panel').toBe(true);

    const results = await new AxeBuilder({ page })
      .include('#ksearchboxmain')
      .withTags(TAGS)
      .analyze();
    const ruleIds = [...new Set(results.violations.map((v) => v.id))].sort();
    expect(
      ruleIds,
      `expanded search panel violations: ${ruleIds.join(', ')}`,
    ).toEqual([]);
  });

  test('notification panel toggles with aria state and passes axe', async ({
    page,
  }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });

    const bell = page.locator('#nx-notif-bell');
    const panel = page.locator('#nx-notif-panel');
    await expect(bell).toHaveAttribute('aria-expanded', 'false');

    await bell.click();
    await expect(bell).toHaveAttribute('aria-expanded', 'true');
    await expect(panel).toBeVisible();

    const results = await new AxeBuilder({ page })
      .include('#nx-notif-panel')
      .withTags(TAGS)
      .analyze();
    const ruleIds = [...new Set(results.violations.map((v) => v.id))].sort();
    expect(
      ruleIds,
      `notification panel violations: ${ruleIds.join(', ')}`,
    ).toEqual([]);

    // Escape closes it again.
    await page.keyboard.press('Escape');
    await expect(bell).toHaveAttribute('aria-expanded', 'false');
  });
});

test.describe('compose form', () => {
  test.use({ storageState: AUTH_STATE });

  test('PM compose: labels, named toolbar selects, keyboard preview+submit', async ({
    page,
  }) => {
    await page.goto('/sendmessage?receiver=1', { waitUntil: 'networkidle' });

    // Explicit label association for subject + body (a11y fix targets).
    await expect(page.locator('label[for="subject"]')).toBeVisible();
    await expect(page.locator('input#subject')).toBeVisible();
    await expect(page.locator('label[for="body"]')).toBeVisible();
    await expect(page.locator('textarea#body')).toBeVisible();

    // Toolbar selects expose accessible names.
    for (const name of ['color', 'font', 'size']) {
      await expect(
        page.locator(`select[name="${name}"]`),
      ).toHaveAttribute('aria-label', /.+/);
    }

    // Keyboard path: subject -> (toolbar controls) -> body -> preview -> edit
    // -> submit button.
    await page.locator('input#subject').focus();
    await page.keyboard.type('a11y spec subject');
    let bodyFocused = false;
    for (let i = 0; i < 15 && !bodyFocused; i++) {
      await page.keyboard.press('Tab');
      bodyFocused = await page.evaluate(
        () => document.activeElement?.id === 'body',
      );
    }
    expect(bodyFocused, 'Tab from subject should reach body').toBe(true);
    await page.keyboard.type('a11y spec body');

    await page.getByRole('button', { name: 'Preview' }).focus();
    await page.keyboard.press('Enter');
    await expect(
      page.locator('.nx-box:not(.text-center):has-text("a11y spec body")'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Edit' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('textarea#body')).toBeVisible();

    // The submit control is reachable and enabled from the keyboard.
    const submit = page.locator('#qr');
    await expect(submit).toBeEnabled();
    await submit.focus();
    await expect(submit).toBeFocused();
  });
});
