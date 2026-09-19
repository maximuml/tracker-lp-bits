import type { FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { AUTH_STATE, performApiLogin } from './fixtures/auth';

/**
 * Logs in once via the real POST /login contract and stores the
 * resulting cookies in AUTH_STATE. Authenticated specs replay the
 * state instead of re-submitting — /login is throttled 10 req/min.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
  mkdirSync(dirname(AUTH_STATE), { recursive: true });
  const baseURL =
    (config.projects[0]?.use.baseURL as string | undefined) ??
    process.env.BROWSER_BASE_URL ??
    'http://127.0.0.1';
  await performApiLogin(baseURL);
}
