import {
  request as playwrightRequest,
  expect,
  type Page,
} from '@playwright/test';
import { join } from 'node:path';

export const BROWSER_USER = process.env.BROWSER_USER ?? 'sysop';
export const BROWSER_PASS = process.env.BROWSER_PASS ?? 'TestPass2026';
export const BROWSER_TID = process.env.BROWSER_TID ?? '1';

/**
 * Authenticated storage state written by global.setup.ts. Specs that
 * need a logged-in session use `test.use({ storageState: AUTH_STATE })`
 * instead of re-submitting the form: /login is rate-limited to
 * 10 req/min per IP, so per-test logins would 429 the suite.
 */
export const AUTH_STATE = join(__dirname, '..', '.auth', 'state.json');

/**
 * One real login through the web form's contract: GET /login for the
 * session cookie + CSRF token, then POST /login exactly as the form
 * does. Called once from globalSetup; the context's cookies (including
 * c_secure_pass) are persisted to AUTH_STATE.
 */
export async function performApiLogin(baseURL: string): Promise<void> {
  const ctx = await playwrightRequest.newContext({ baseURL });
  const loginPage = await ctx.get('/login');
  const html = await loginPage.text();
  const token = html.match(/name="_token"[^>]*value="([^"]+)"/)?.[1];
  if (!token) {
    throw new Error('GET /login did not render a CSRF token');
  }

  const res = await ctx.post('/login', {
    form: { _token: token, username: BROWSER_USER, password: BROWSER_PASS },
    maxRedirects: 0,
  });
  // Both success and failure are 302 — success lands on index.php,
  // failure redirects back to /login.
  const location = res.headers()['location'] ?? '';
  if (res.status() !== 302 || /\/login/.test(location)) {
    throw new Error(
      `POST /login rejected credentials: HTTP ${res.status()} → ${location}`,
    );
  }

  await ctx.storageState({ path: AUTH_STATE });
  await ctx.dispose();
}

/**
 * Submit the real login form (exercises the rendered page end to end).
 * For login.spec and signup.spec only — authenticated suites reuse
 * AUTH_STATE instead.
 */
export async function login(
  page: Page,
  username = BROWSER_USER,
  password = BROWSER_PASS,
): Promise<void> {
  await page.goto('/login', { waitUntil: 'networkidle' });
  await page.fill('#login-form input[name="username"]', username);
  await page.fill('#login-form input[name="password"]', password);
  await Promise.all([
    page.waitForURL(/\/index(\.php)?$/, { timeout: 20_000 }),
    page.click('#login-form input[type="submit"]'),
  ]);
  await expect
    .poll(async () =>
      (await page.context().cookies()).some((c) => c.name === 'c_secure_pass'),
    )
    .toBeTruthy();
}
