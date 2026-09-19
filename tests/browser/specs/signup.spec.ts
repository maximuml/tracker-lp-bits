import { expect, test } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login } from '../fixtures/auth';
import { watchIssues } from '../helpers';

/**
 * Real signup: fill the form, click #submit-btn (auth-form.js copies
 * the password into the hidden hash fields and submits), land on
 * /confirm.php (verification=automatic confirms and auto-logs-in),
 * log out, then prove the account works via the real login form.
 *
 * security.maxip caps registrations per IP. CI bumps it to 100 during
 * stack setup; a dev database already over the cap gets a skip, not a
 * failure — the cap is an environment limit, not a code path.
 */
test.afterAll(() => {
  // Remove accounts this spec created — leftover pw% users would
  // eventually trip security.maxip on a shared dev database. No-op
  // where the docker stack isn't reachable (CI cleans up too).
  try {
    execSync(
      `docker compose exec -T php php artisan tinker ` +
        `--execute="DB::table('users')->where('username','like','pw%')->delete();"`,
      { stdio: 'ignore', timeout: 30_000 },
    );
  } catch {
    /* docker stack not available — nothing to clean */
  }
});

test('signup form creates a working account', async ({ page }) => {
  const issues = watchIssues(page);
  // usernames are capped at 12 chars — pw + base36 timestamp fits
  const username = `pw${Date.now().toString(36)}`;
  const password = 'SignupPass2026!';

  await page.goto('/signup.php', { waitUntil: 'networkidle' });
  await page.fill('input[name="wantusername"]', username);
  await page.fill('input.wantpassword', password);
  await page.fill('input.passagain', password);
  await page.fill('input[name="email"]', `${username}@example.com`);
  await page.check('input[name="gender"][value="Male"]');
  await page.check('input[name="rulesverify"]');
  await page.check('input[name="faqverify"]');
  await page.check('input[name="ageverify"]');

  await page.click('#submit-btn');
  await page.waitForLoadState('networkidle');

  const body = await page.locator('body').innerText();
  test.skip(
    /too many account|already being used|no more accounts/i.test(body),
    'security.maxip registration cap — environment limit, raise it in the stack setup',
  );

  // Success redirects to /confirm.php?id=…&secret=… (auto-confirm) or
  // ok.php — anything else is the signup form re-rendered with an error.
  expect(
    page.url(),
    `signup should leave the form (landed on ${page.url()})`,
  ).toMatch(/confirm\.php|ok\.php|index\.php|\/index/);

  // confirm.php auto-logs the account in — the logout button proves it.
  const logoutButton = page.locator('form[action*="logout"] button').first();
  await expect(logoutButton, 'auto-logged-in after confirm').toBeVisible();

  // Log out, then verify the account works through the real login form.
  await logoutButton.click();
  await page.waitForURL(/login/, { timeout: 15_000 });
  await login(page, username, password);

  const response = await page.goto('/index.php', { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  expect(issues.pageErrors).toEqual([]);
});
