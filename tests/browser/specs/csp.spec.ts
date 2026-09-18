import { expect, test } from '@playwright/test';
import { AUTH_STATE, BROWSER_TID } from '../fixtures/auth';

/**
 * CSP ratchet. Every page currently violates `style-src` (legacy inline
 * styles); several also violate `script-src` (blocked inline scripts).
 * The baseline records which directives each page violates — a NEW
 * directive type on a page fails. Removing violations needs no test
 * change; shrink a baseline entry in the cleanup PR (stage 4).
 */
const CSP_BASELINE: Record<string, string[]> = {
  '/index.php': ['style-src'],
  '/torrents.php': ['style-src'],
  '/details.php': ['style-src'],
  '/forums.php': ['style-src'],
  '/usercp.php': ['style-src'],
  '/topten.php': ['script-src', 'style-src'],
  '/messages.php': ['script-src', 'style-src'],
  '/staffpanel.php': ['script-src', 'style-src'],
  '/nexusphp': ['script-src', 'style-src'],
};

const DIRECTIVE_RE = /directive '([^']+)'/;

test.use({ storageState: AUTH_STATE });

test.describe('CSP violations do not grow', () => {

  for (const [path, baseline] of Object.entries(CSP_BASELINE)) {
    test(`${path} violates only baseline directives`, async ({ page }) => {
      const directives = new Set<string>();
      page.on('console', (msg) => {
        const m = msg.text().match(DIRECTIVE_RE);
        if (m) {
          // Normalise the per-request nonce so directives compare equal.
          directives.add(m[1].replace(/'nonce-[^']*'/g, '').replace(/\s+/g, ' ').trim());
        }
      });
      const url = path === '/details.php' ? `${path}?id=${BROWSER_TID}` : path;
      await page.goto(url, { waitUntil: 'networkidle' });

      const unexpected = [...directives].filter((d) => !baseline.includes(d));
      expect(
        unexpected,
        `${path}: new CSP violation type ${unexpected.join(', ')} — shrink the baseline only when violations are actually removed`,
      ).toEqual([]);
    });
  }
});
