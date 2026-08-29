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
- `app/Nexus/` — legacy NexusPHP code (install scripts, plugin system)
- `routes/legacy/` — legacy route mappings (PHP file routes)
- `config/` — Laravel configuration
- `database/migrations/` — 75 migrations
- `tests/` — 104 test files (872 tests)

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

# Static analysis (CI runs 6 levels)
docker compose exec -T php vendor/bin/phpstan analyse --no-progress --memory-limit=2G
docker compose exec -T php vendor/bin/phpstan analyse -c phpstan.level7.neon --no-progress --memory-limit=2G
docker compose exec -T php vendor/bin/phpstan analyse -c phpstan.level8.neon --no-progress --memory-limit=2G

# Tests
docker compose exec -T redis redis-cli FLUSHDB
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

- **Legacy bridge:** `app/Nexus/` contains install scripts and plugin system (standalone, `IN_NEXUS=true`)
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

Sprints 0–51 complete. Recent work:
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

## PHP version

- **CI:** PHP 8.5 (shivammathur/setup-php)
- **Docker:** PHP 8.4-fpm-alpine (`.docker/php/Dockerfile`)
- **composer.json:** `>=8.4 <8.6` — allows both 8.4 and 8.5
- **PHP 8.5 compatibility:** verified — no deprecated features used
  (no backtick operator, no non-canonical casts, no semicolon-terminated
  case statements). All 792 tests pass on PHP 8.5 in CI.
- Docker image upgrade to PHP 8.5 is a future task (not blocking).

Key remaining work: Sprint 30 (audit cleanup), test coverage expansion
(60+ controllers still untested), Docker PHP 8.5 upgrade.
