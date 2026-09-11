# AGENTS.md — tracker-lp-bits

## Project overview

Private BitTorrent tracker built on NexusPHP, modernised with Laravel 13 + Filament 5.
PHP 8.4+, MySQL, Redis, MeiliSearch. Docker Compose stack for local development.

## Tech stack

- **Backend:** Laravel 13, PHP 8.4, Filament 5
- **Database:** MySQL 9 (Docker), Redis 7
- **Search:** MeiliSearch
- **Queue:** Redis (default), Octane-compatible
- **Frontend:** Blade templates, legacy NexusPHP themes, Vite 8 + Tailwind CSS 4 for asset bundling

## Key directories

- `app/Http/Controllers/` — controllers (legacy + modern)
- `app/Services/` — service classes (PageService, domain services)
- `app/Repositories/` — data access layer
- `app/Support/` — helper classes (Cache, Logger, Auth, HTML, etc.)
- `app/Models/` — Eloquent models
- `app/Filament/` — Filament admin resources
- `app/Support/Install/` — legacy NexusPHP install/update scripts (standalone, `IN_NEXUS=true`)
- `routes/legacy/` — legacy route mappings (PHP file routes)
- `config/` — Laravel configuration
- `database/migrations/` — migrations
- `tests/` — PHPUnit test suites (Unit + Feature + Architecture)
- `.agents/skills/` — E2E testing playbooks; architecture decisions are recorded in this file (see "Architecture Decision Records")

Directory and test counts are intentionally not hard-coded here — they
drifted repeatedly. See `DocsConsistencyTest` for the automated checks
that keep this file in sync.

## Build & run commands

```bash
# Docker stack
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php artisan migrate:fresh --seed --force
docker compose exec php php artisan meilisearch:import

# Create admin user
docker compose exec php php artisan user:reset_id_auto_increment \
  --auto_increment=10001 --admin=sysop --password=TestPass2026 --email=sysop@example.com
```

## Verification commands

```bash
# One-command test runner (W0-06): uses docker-compose.test.yml overlay
# with isolated DB_DATABASE=nexusphp_testing and REDIS_PREFIX=test_
make test              # all suites (migrate:fresh + phpunit --parallel)
make test-unit         # unit only
make test-feature      # feature only
make test-architecture # architecture ratchets only
make test-lint         # Pint + PHPStan

# Or via composer (inside the php container)
docker compose exec -T php composer test
docker compose exec -T php composer test:unit
docker compose exec -T php composer test:lint

# Manual (legacy approach)
docker compose exec -T php vendor/bin/pint --test
docker compose exec -T php vendor/bin/phpstan analyse --no-progress --memory-limit=2G
docker compose exec -T redis redis-cli -a "${REDIS_PASSWORD}" FLUSHDB
DB_DATABASE=nexusphp_testing docker compose exec -T -e DB_DATABASE=nexusphp_testing php vendor/bin/phpunit --no-coverage

# Architecture ratchet tests only
DB_DATABASE=nexusphp_testing docker compose exec -T -e DB_DATABASE=nexusphp_testing php vendor/bin/phpunit --testsuite Architecture --no-coverage

# Security audit
docker compose exec -T php composer audit
```

## Coding conventions

- **PHPStan:** level 8 must pass — all code is strictly typed
- **Pint:** Laravel preset, 1338 files checked
- **Return types:** all public methods should have return type declarations
- **DI:** use constructor injection or `app()` — avoid `new Repository()` in services
- **Facades:** `DB::`, `Cache::`, `Redis::`, `Auth::` — not `NexusDB::` (drained in Sprint 17)
- **SupportContext:** only used in wrapper classes (CurrentUser, Globals, etc.) — not directly in controllers/services
- **Blade escaping:** `{!! !!}` is audited and safe — all helpers escape internally
- **Comments:** do not add/remove comments unless asked

## Architecture notes

- **Legacy bridge:** `app/Support/Install/` contains install/update scripts (standalone, `IN_NEXUS=true`)
- **PageServices:** `IndexPageService`, `UsercpPageService`, `MessagePageService`, etc. — render legacy pages via Blade
- **Events:** `Events::fire()` → `ModelEventEnum` → event classes (legacy event system, not Laravel's Event::dispatch)
- **Settings:** `settings` table → `App\Support\Settings` / `Globals` singleton (cached in Redis)
- **Auth:** custom `NexusWebGuard` + challenge-response authentication + HMAC passkey login
- **Cache:** `LegacyRedisCache` with `allowed_classes: false` (Sprint 19 hardening)

## Architecture Decision Records

Condensed from the former `docs/adr/` directory (Nygard format: Context →
Decision → Consequences). Add new ADRs here as numbered subsections.

### ADR 0001: NexusWebGuard for cookie-based auth (Accepted, Sprint 0)

- **Context:** NexusPHP authenticates via a signed `c_secure_pass` cookie
  (user ID + expiry, encrypted with the app key, or HMAC-signed with the
  per-user `auth_key` in the legacy format). The standard `session` guard
  could not accept existing cookies without forcing re-login, and
  BitTorrent clients authenticate via passkey, not sessions.
- **Decision:** Custom `NexusWebGuard` (`StatefulGuard`) reads the cookie via
  `AuthCookie::verifyToken()`, falls back to legacy HMAC verification using
  `users.auth_key`, resolves the `User` via `NexusWebUserProvider`, and is
  registered as the `nexus-web` guard in `config/auth.php`. A separate
  `passkey` guard handles tracker clients; Sanctum handles the API.
- **Consequences:** Sessions survive the migration; clear web/API/tracker
  separation. Cost: custom guard needs its own security review and manual
  implementation of standard auth features. The legacy HMAC fallback should
  be removed once all cookies have rotated to the encrypted format (W1-04).

### ADR 0002: LegacyRequestMiddleware for URL rewriting (Accepted, Sprint 17; Octane-safe since T-11)

- **Context:** Legacy URLs (`/details.php?id=5`, `/torrents.php`, …) must
  keep working for bookmarks, search engines and announce URLs embedded in
  `.torrent` files. Web-server rewrite rules are untestable and differ per
  server; explicit routes per script would need hundreds of entries.
- **Decision:** Global `LegacyRequestMiddleware` detects the script name
  from `SCRIPT_FILENAME`/`SCRIPT_NAME`, rewrites `/foo.php?id=5` to `/foo?id=5`
  (or `/foo/5`), skips Laravel-only prefixes (`api/`, `livewire/`,
  `filament/`, `horizon/`, `nexusphp/`, `web/`), boots the legacy context
  (`LegacyBootstrap::boot`) and treats Octane worker scripts as `index.php`.
- **Consequences:** All legacy URLs work without web-server config; tested
  by `LegacySmokeTest` / `LegacyHeaderIsolationTest`; per-request reset via
  `CurrentUser::reset()`. Cost: ~0.1 ms regex per request and a 200+ line
  middleware with many edge cases. W2-11 proposes moving rewriting to
  `RouteServiceProvider` or OpenResty, leaving only context bootstrap.

### ADR 0003: SiteConfig typed configuration (Accepted, Sprint 20)

- **Context:** Site settings live in the `settings` table as dot-prefixed
  key/value pairs (`main.sitename`, `security.iv`, …). `Globals::get()`
  returned `mixed`, causing PHPStan level 8 violations, `'yes'` vs `true`
  bugs, and no autocompletion or validation.
- **Decision:** `App\Support\Config\SiteConfig::current()` returns a cached
  `MainConfig`; each prefix has a typed config class (`BasicConfig`,
  `TorrentConfig`, `SecurityConfig`, `BonusConfig`, …) whose accessors cast
  to `bool`/`int`/`string`/enum (e.g. `SecurityConfig::loginType()` returns
  `LoginType`). `Globals` still backs it from the table + Redis cache.
- **Consequences:** Type-safe, IDE-friendly configuration access. Cost:
  each new setting needs a method in the right config class; full removal
  of the `Globals` singleton is deferred to W3-03 (settings schema
  validation).

### ADR 0004: Stateless AnnounceService with AnnounceContext (Accepted, T-18, PR #616)

- **Context:** `/announce` is the hottest path (every client every
  30–90 s). The original service kept mutable per-request state on
  properties (`$this->user`, `$this->torrent`, …), making it untestable in
  isolation and prone to Octane cross-request contamination.
- **Decision:** All state flows through an immutable readonly
  `AnnounceContext` DTO with `with*()` methods (`withUser()`,
  `withTorrent()`, `withTraffic()`, …). Each pipeline step
  (`authenticateUser`, `checkClient`, `loadTorrent`, …) takes a context and
  returns a new one. `lockRowsForUpdate` runs inside `DB::transaction()`
  with the context captured. Sub-components (`TrafficAccountant`,
  `CheaterDetector`, `HitAndRunHandler`, `RateLimiter`, `PeerLifecycle`)
  are stateless and constructor-injected.
- **Consequences:** Octane-safe, each step independently testable, explicit
  data flow, PHPStan level 8 clean. Cost: `AnnounceContext` is ~400 lines
  of `with*()` boilerplate, and the carried `ResponseBuilder` is still
  mutable (candidate for a future immutable refinement).

### ADR 0005: Architecture ratchet tests (Accepted, W0)

- **Context:** Measurable debt (`{!! !!}`, `@php`, `<table>` layouts,
  >500-line repositories, superglobals, GET+POST mixed routes) regressed
  silently because the three existing architecture tests were not in any
  PHPUnit suite and never ran in CI.
- **Decision:** (1) `Architecture` testsuite in `phpunit.xml`; (2) run it
  in CI on every push/PR; (3) new ratchets with baselines captured at the
  time — `LegacyViewSurfaceTest`, `RepositorySizeTest`, `NoSuperglobalsTest`;
  (4) `MAX_ALLOWED_ENTRIES` countdown on `MixedRouteAllowListTest`;
  (5) semantics `assertLessThanOrEqual(baseline, current)` — baselines may
  only be lowered, never raised.
- **Consequences:** Any PR adding a new `{!! !!}` or a 600-line repository
  fails CI; existing ratchets are enforced for the first time. Cost:
  developers must lower baseline constants when reducing counts, and
  `RepositorySizeTest` flags renamed/removed baseline files.

### ADR 0006: Repository contracts for the most-injected repositories (Accepted, step 3.1)

- **Context:** Repositories are injected by concrete class everywhere,
  which blocked unit tests: the hot classes are (or were) `final` and
  Mockery cannot mock final classes, so controllers/services depending on
  e.g. `UserRepository` could only be tested against a real database.
- **Decision:** The ten most-injected repositories expose a contract in
  `App\Contracts\Repositories\<Name>RepositoryInterface`, implemented by
  the concrete class and bound in `AppServiceProvider` (`bind`, not
  `singleton` — repositories are stateless per call site). Consumers
  type-hint the interface in constructors/properties; call sites that only
  need constants or static data keep the concrete import.
- **Consequences:** `Mockery::mock(XInterface::class)` +
  `app()->instance()` works for every covered dependency; verified by
  `RepositoryContractTest` (binding + swap) and
  `RepositoryInterfaceInjectionTest` (controller receives the mock). Cost:
  interface and implementation signatures must be kept in sync; new public
  methods on a covered repository must be added to its interface.

## Testing

- **Unit tests:** `tests/Unit/` — support classes, repositories, services
- **Feature tests:** `tests/Feature/` — CriticalPathTest, LegacySmokeTest, SecurityHeadersTest
- **E2E:** Docker stack + curl smoke tests (see skills in `.agents/skills/`)
- **Login for E2E:** CSRF token from `/login.php` → POST to `/takelogin.php` with `_token`, `username`, `password`
- **Captcha:** disable with `UPDATE settings SET value='no' WHERE name='security.iv'` + flush Redis settings cache

### Database isolation (T-05)

Tests must never run against the dev/production database. Three isolated
databases are used:

| Database | Suite | CI job |
|---|---|---|
| `nexusphp_unit_testing` | Unit | `unit-tests`, `coverage`, `octane` |
| `nexusphp_feature_testing` | Feature (no OpenResty) | `coverage` |
| `nexusphp_e2e_testing` | Feature + OpenResty (CriticalPathTest) | `smoke-test`, `a11y`, `perf-budget` |

`DestructiveEnvironmentGuard` (`app/Support/DestructiveEnvironmentGuard.php`)
is invoked from `Tests\TestCase::setUp()` and from a `CommandStarting` listener
in `AppServiceProvider` (for `migrate:fresh`/`migrate:refresh`/`migrate:reset`/
`db:wipe`). When `APP_ENV=testing`, it refuses to run if the database name does
not contain `test`, `testing` or `e2e`, or if the Redis prefix does not contain
`test`. This prevents accidental data loss from misconfigured `DB_DATABASE`.

`phpunit.xml` sets `DB_DATABASE=nexusphp_testing` as the default; CI overrides
it per-suite. The E2E stack (smoke-test, a11y, perf-budget) sets
`DB_DATABASE=nexusphp_e2e_testing` in `.env` so the Docker containers
(PHP-FPM, OpenResty) use the same isolated database as the test suite.
`CriticalPathTest` reads `E2E_DB_DATABASE` (default `nexusphp_e2e_testing`) to
match the connection used by HTTP requests through OpenResty.

## Modernisation status

- **Sprints T-01 — T-24:** build/test/CI and observability hardening.
- **Sprints 0–55:** core-to-Laravel migration, test seeding and DI cleanup.
- **Waves W0–W4:** architecture ratchets, HTTP contract tests, DI reduction,
  domain decomposition (torrent, user, forum, messages, bonus/exam/cleanup).
  PRs #648–#670.

### Next work (W5–W9)

1. **W5 — SQL & performance:** raw-SQL registry, EXPLAIN regression tests,
   query budgets, tracker load test as a blocking gate.
2. **W6 — Observability:** decompose `MetricsController`, semantic metrics,
   liveness/readiness/diagnostics, secure `/metrics` endpoint.
3. **W7 — Legacy UI & a11y:** component layer, migrate pages by priority,
   reduce `LegacyViewSurfaceTest` baselines, Axe violations as a blocker.
4. **W8 — Production readiness:** exact prod smoke, migration strategy,
   backup/restore verification, graceful deployment, supply-chain hardening.
5. **W9 — Documentation & runbooks:** self-checking docs (see
   `DocsConsistencyTest`), ADRs for new decisions, operational runbooks.

## PHP version

- **CI:** PHP 8.5 (shivammathur/setup-php)
- **Docker:** PHP 8.5-fpm-alpine (`.docker/php/Dockerfile`)
- **composer.json:** `>=8.4 <8.6` — allows both 8.4 and 8.5
- **PHP 8.5 compatibility:** verified — no deprecated features used
  (no backtick operator, no non-canonical casts, no semicolon-terminated
  case statements, no ReflectionMethod::setAccessible()). All PHPUnit suites
  pass on PHP 8.5.9 in Docker and CI.

Key remaining work: expand unit-test coverage for controllers (many blocked
by final repository classes or static methods — see W2-01/W2-02).
