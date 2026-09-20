import { expect, test } from '@playwright/test';
import { AUTH_STATE, BROWSER_TID } from '../fixtures/auth';

/**
 * CSP ratchet — all covered pages must be violation-free.
 * Legacy routes run nonce-strict script-src + style-src-elem with
 * style-src-attr 'unsafe-inline' (element.style/CSSOM is allowed —
 * legacy show/hide toggles depend on it); vendored JS that injects
 * <style> gets the request nonce stamped by the bridge in
 * head-assets. Any violation of ANY directive on a page fails.
 */
const CSP_BASELINE: Record<string, string[]> = {
  '/index.php': [],
  '/torrents.php': [],
  '/details.php': [],
  '/forums.php': [],
  '/usercp.php': [],
  '/topten.php': [],
  '/messages.php': [],
  '/staffpanel.php': [],
  '/nexusphp': [],
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
