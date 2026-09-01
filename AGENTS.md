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

- `app/Http/Controllers/` — 78 controllers (legacy + modern)
- `app/Services/` — 36 service classes (PageService, domain services)
- `app/Repositories/` — data access layer
- `app/Support/` — helper classes (Cache, Logger, Auth, HTML, etc.)
- `app/Models/` — 83 Eloquent models
- `app/Filament/` — Filament admin resources
- `app/Support/Install/` — legacy NexusPHP install/update scripts (standalone, `IN_NEXUS=true`)
- `routes/legacy/` — legacy route mappings (PHP file routes)
- `config/` — Laravel configuration
- `database/migrations/` — 75 migrations
- `tests/` — 108 test files (885 tests)

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
# Lint
docker compose exec -T php vendor/bin/pint --test

# Static analysis (level 8, single canonical phpstan.neon)
docker compose exec -T php vendor/bin/phpstan analyse --no-progress --memory-limit=2G

# Tests (uses isolated nexusphp_testing DB — never truncates dev tables)
docker compose exec -T redis redis-cli -a "${REDIS_PASSWORD}" FLUSHDB
docker compose exec -T php vendor/bin/phpunit --no-coverage

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

## Testing

- **Unit tests:** `tests/Unit/` — support classes, repositories, services
- **Feature tests:** `tests/Feature/` — CriticalPathTest, LegacySmokeTest, SecurityHeadersTest
- **E2E:** Docker stack + curl smoke tests (see skills in `.agents/skills/`)
- **Login for E2E:** CSRF token from `/login.php` → POST to `/takelogin.php` with `_token`, `username`, `password`
- **Captcha:** disable with `UPDATE settings SET value='no' WHERE name='security.iv'` + flush Redis settings cache

## Modernisation status

Sprints 0–55 complete. Recent work:
- Sprint 46: service decomposition (extract ShoutboxService, ThankService,
  TorrentBookmarkService, ComplainService, LocationService, BitbucketService;
  AnnounceService DI cleanup)
- Sprint 47: major dependency upgrades — Laravel 12→13, Tailwind 3→4,
  laravel-vite-plugin 1→3 + Vite 6→8
- Sprint 48: test coverage — added 25 unit tests for 5 API controllers
  (RewardController, HitAndRunController, PeerController, SnatchController,
  AttendanceController) using Mockery + FormRequest validation pattern;
  test count 767 → 792
- Sprint 49: test coverage batch 2 — added 38 unit tests for 5 admin
  CRUD controllers (TagController, MedalController, AgentDenyController,
  ExamController, ExamUserController) with paginator mocks and enum
  validation; test count 792 → 830
- Sprint 50: test coverage batch 3 — added 27 unit tests for 5 admin
  controllers (SettingController, DashboardController, AgentAllowController,
  UploadController, UserMedalController) with paginator mocks and enum
  validation; test count 830 → 857
- Sprint 51: test coverage batch 4 — added 15 unit tests for
  AuthenticateController (login, logout, nasToolsApprove, iyuuApprove,
  challenge) and TorrentController (searchBox, queryByPiecesHash,
  approval permission); test count 857 → 872
- Sprint 52: test coverage batch 5 — added 12 unit tests for
  UserController (index, classes, base, unauthenticated guards for
  show/disable/enable/me/publishTorrent/incrementDecrement, modComment,
  inviteInfo, removeTwoStepAuthentication); test count 872 → 884
- Sprint 53: test coverage batch 6 — added 6 unit tests for
  BookmarkController (store/destroy success + unauthenticated guards)
  and ToolController (notifications success + unauthenticated guard);
  test count 884 → 890
- Sprint 54: test coverage batch 7 — added 3 unit tests for
  TokenController (addToken/delToken unauthenticated guards, delToken
  validation failure); test count 882 → 885
- Sprint 55: Docker PHP 8.5 upgrade — updated Dockerfile (Alpine) and
  DockerfileDebian from php:8.4-fpm to php:8.5-fpm, removed explicit
  opcache install (built-in since 8.5), fixed ReflectionMethod::
  setAccessible() deprecation in test; all 885 tests pass on PHP 8.5.9

## PHP version

- **CI:** PHP 8.5 (shivammathur/setup-php)
- **Docker:** PHP 8.5-fpm-alpine (`.docker/php/Dockerfile`)
- **composer.json:** `>=8.4 <8.6` — allows both 8.4 and 8.5
- **PHP 8.5 compatibility:** verified — no deprecated features used
  (no backtick operator, no non-canonical casts, no semicolon-terminated
  case statements, no ReflectionMethod::setAccessible()). All 885 tests
  pass on PHP 8.5.9 in Docker and CI.

Key remaining work: test coverage expansion (50+ controllers still
untested — many blocked by final repository classes or static methods).
