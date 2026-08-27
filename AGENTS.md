# AGENTS.md — tracker-lp-bits

## Project overview

Private BitTorrent tracker built on NexusPHP, modernised with Laravel 12 + Filament 5.
PHP 8.4+, MySQL, Redis, MeiliSearch. Docker Compose stack for local development.

## Tech stack

- **Backend:** Laravel 12, PHP 8.4, Filament 5
- **Database:** MySQL 9 (Docker), Redis 7
- **Search:** MeiliSearch
- **Queue:** Redis (default), Octane-compatible
- **Frontend:** Blade templates, legacy NexusPHP themes, Vite for asset bundling

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
- `tests/` — 87 test files (751 tests)

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

See `STEP_BY_STEP_PLAN.md` for the full sprint-by-sprint progress.
Sprints 0–29 complete. Sprint 22 (observability) in progress.
Key remaining work: Sprint 30 (audit cleanup), test coverage, frontend upgrade.
