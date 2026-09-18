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
