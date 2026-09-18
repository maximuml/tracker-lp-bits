# Browser smoke suite (Playwright)

Blocking CI gate for browser-level regressions — ADR 0016. Node is used
for tests only; the project has no Node/Vite build step.

## What it covers

| Spec | Asserts |
|---|---|
| `login.spec.ts` | Real form submit → `/index`, wrong password rejected |
| `signup.spec.ts` | Real signup → confirm.php auto-login → logout → re-login |
| `pages.spec.ts` | 10 authenticated routes + forum navigation: status <400, no `pageerror`, no failed resources, no escaped markup in `innerText` |
| `csp.spec.ts` | Per-page CSP violation-directive baseline — new types fail |
| `a11y.spec.ts` | axe rule-id baseline per page (`a11y-baseline.json`) |
| `mobile.spec.ts` | 390×844 — no horizontal scroll on modern-layout pages |

Baselines are ratchets: they may only shrink. Regenerate the axe
baseline after intentional fixes with
`A11Y_UPDATE_BASELINE=1 npx playwright test a11y`.

## Local run

```bash
# stack up + seeded + sysop admin (per AGENTS.md), then:
cd tests/browser
npm ci
npx playwright install --with-deps chromium
npx playwright test
```

Environment overrides: `BROWSER_BASE_URL` (default
`http://127.0.0.1:80`), `BROWSER_USER`/`BROWSER_PASS` (sysop/TestPass2026),
`BROWSER_TID` (a seeded torrent id, default 1).

Three settings must match the test environment or the suite fails for
environmental reasons:

```bash
# signup spec — registration IP cap
docker compose exec -T php php artisan tinker \
  --execute="DB::table('settings')->where('name','security.maxip')->update(['value'=>100]);"
# baseUrl must equal BROWSER_BASE_URL origin — confirm.php redirects
# there and Chromium applies form-action 'self' to the redirect
docker compose exec -T php php artisan tinker \
  --execute="DB::table('settings')->where('name','basic.baseUrl')->update(['value'=>'http://127.0.0.1']);"
# security.iv seeds to 'yes' — image captcha on login/signup, which
# headless Chromium cannot solve; disable it like PerformanceTestDatasetSeeder
docker compose exec -T php php artisan tinker \
  --execute="DB::table('settings')->where('name','security.iv')->update(['value'=>'no']);"
# base seeders ship reference data only — the suite needs at least one
# torrent (details.php) and one forum topic (forum navigation)
docker compose exec -T php php artisan db:seed --class=BrowserSmokeSeeder
docker compose exec -T redis redis-cli -a "$REDIS_PASSWORD" FLUSHALL
```

`globalSetup` performs one real login (`GET /login` for the CSRF token →
`POST /login`) and stores the session in `.auth/state.json`;
authenticated specs replay it — `/login` is throttled 10 req/min, so
per-test form logins would 429 the suite.
