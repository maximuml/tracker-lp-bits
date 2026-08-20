# Proposal: Project Modernization & Update — `tracker-lp-bits`

**Date:** 2026-08-20
**Baseline:** Laravel 12.64, Filament 5.7, PHP 8.4 (declared `>=8.4 <8.6`), PHPUnit 11, PHPStan 2.2, Vite 6, Tailwind 3. ~108 600 LOC PHP / 815 files. Active phased migration (currently at Phase 24).

---

## Current State Assessment

### What's already modern ✓
- Laravel 12 + Filament 5 (current major versions)
- PHP 8.4 target with strict typing in new code
- PHPStan level 8 enforced in CI for `app/` (excluding legacy partials)
- DTOs, ValueObjects, typed Repositories for announce pipeline
- Redis-based queue/cache/session, Horizon for queue dashboard
- Vite 6 + Tailwind 3 for frontend assets
- `composer audit` + `npm audit` in CI
- Docker Compose stack with MySQL 8 / Redis 7 / MeiliSearch
- Feature tests for Phase 20 API parity, security headers, announce

### What's holding the project back ✗
- **9 298 LOC** of legacy `app/Services/Legacy/*_content.php` + **1 826 LOC** in `partials/` excluded from PHPStan and largely untested
- **14 520 LOC** in `app/Http/Controllers/` — several controllers >700 lines mixing view rendering, DB queries, and business logic
- **6 586 LOC** in `app/Nexus/` — procedural NexusPHP core wrapped in a PSR-4 namespace, bypassing Laravel conventions
- Dual admin systems: Filament (177 files / 11 542 LOC) **and** legacy `AdminController`/`StaffController`/`SettingController` (3 400+ LOC)
- Three search/analytics engines shipped simultaneously (MeiliSearch active, Elasticsearch + ClickHouse dormant but wired in)
- Two auth token systems (Passport + Sanctum) with no clear boundary
- Security debt documented in `AUDIT.md` (CSRF, password hashing, public cron, etc.)

---

## Modernization Roadmap

Organized into 6 tracks. Each track is independently shippable. Recommended order follows dependencies, but tracks 1–3 can run in parallel.

### Track 1 — Security Hardening (Weeks 1–2)

**Goal:** Close the critical findings from `AUDIT.md` before any feature work.

| Step | Action | Files | Effort |
|------|--------|-------|--------|
| 1.1 | Re-enable CSRF: replace blanket `$except` with per-route tokens or signed-form middleware. Keep only `tg-webhook/*` exempt. | `app/Http/Middleware/VerifyCsrfToken.php`, all legacy form views | High |
| 1.2 | Protect `/cron`: require `CRON_TOKEN` query param or restrict to loopback via middleware. | `routes/legacy/public.php`, new `CronToken` middleware | Low |
| 1.3 | Migrate password hashing to `password_hash(PASSWORD_ARGON2ID)`. Implement `NexusWebUserProvider::rehashPasswordIfRequired()` to upgrade legacy `sha256`/`md5` hashes on next login. Add `passhash_algo` column to track. | `app/Services/WebAuthService.php`, `app/Auth/NexusWebUserProvider.php`, `app/Services/RegistrationService.php`, migration | Medium |
| 1.4 | Convert passkey-login from GET to POST (one-time exchange token). | `app/Http/Controllers/AuthenticateController.php`, route | Low |
| 1.5 | Validate email in `confirmemail`. Sanitize `.env.example` (remove real APP_KEY, placeholder MeiliSearch key, default `APP_DEBUG=false`). | `app/Http/Controllers/UtilityController.php`, `.env.example` | Low |
| 1.6 | Add explicit whitelist to `AjaxService` dispatcher. | `app/Http/Controllers/UtilityController.php` | Low |

**Exit criteria:** All Critical/High findings from `AUDIT.md` resolved; feature tests covering CSRF on admin routes, password upgrade flow, cron token.

---

### Track 2 — Legacy Content Drain (Weeks 1–6, parallel with Track 1)

**Goal:** Eliminate `app/Services/Legacy/*_content.php` and `partials/` by extracting logic into controllers, repositories, and Blade components. Bring them under PHPStan level 8.

**Strategy:** One content file per PR, following the established Phase 17–24 pattern.

| Priority | File | LOC | Replaces |
|----------|------|-----|----------|
| 1 | `forum_forums_content.php` | 1 161 | Forum listing/rendering → `ForumController` + `ForumRepository` + Blade components |
| 2 | `my_bonus_content.php` | 762 | Bonus shop → `BonusController@bonus` (already exists) + `BonusRepository` |
| 3 | `catmanage_content.php` | 640 | Category management → Filament `SectionResource` (already exists) — delete legacy page |
| 4 | `usercp_content.php` | 629 | User control panel → `UsercpController` (exists) + typed DTOs (Phase 23 started this) |
| 5 | `index_content.php` | 613 | Homepage dashboard → `IndexController@legacy` (exists) + Blade components |
| 6 | `offers_content.php` | 575 | Offers → `OfferController` + `OfferService` (exists) |
| 7 | `messages_content.php` | 424 | PM listing → `MessageController` + `MessageService` (exists) |
| 8 | `usersearch_content.php` | 230 | Admin user search → `UserSearchRepository` (exists) + Filament `UserResource` filtering |
| 9 | `partials/` (22 files, 1 826 LOC) | — | Inline partials → Blade components or `LegacyViewRepository::render` calls |

**Method for each:**
1. Identify the controller that already calls this content file
2. Move query/mutation logic into a repository or service
3. Move rendering into a Blade component (`resources/views/components/`)
4. Delete the `_content.php` file
5. Add feature test for the route
6. Update `phpstan.level8.neon` to remove the exclusion once all are drained

**Exit criteria:** `app/Services/Legacy/` contains only typed service classes (`ForumService`, `MessageService`, `OfferService`, etc.) — no `*_content.php` files. PHPStan level 8 covers 100% of `app/`.

---

### Track 3 — Controller Slimming & Architecture (Weeks 2–8)

**Goal:** Reduce controllers to request/response orchestration; move business logic to services/repositories; introduce Form Requests for validation.

#### 3.1 Split fat controllers

| Controller | LOC | Action |
|------------|-----|--------|
| `AdminController` | 1 003 | Split into `DonorController`, `StatsController`, `WarnedUserController`, `AgentController`, `CheckUserController`. Each <300 LOC. |
| `InfoController` | 965 | Split into `FaqController`, `RulesController`, `NewsController`, `InviteController`, `RssController`, `PollController`. |
| `TorrentActionController` | 915 | Extract bookmark/rss/flush/reseed into `BookmarkService`, `RssService`, `TorrentOpsService`. |
| `StaffController` | 911 | Extract `modtask` into `UserModerationService`. Move mass-messaging to `SystemController` or a `MassMessageService`. |
| `ModerationController` | 752 | Extract ban/ip-search/cheater logic into `ModerationService`. |
| `SystemController` | 735 | Extract backup/massmail/cron-trigger into `BackupService`, `MassMessageService`. |

#### 3.2 Introduce Form Requests

Replace inline `$request->validate()` and manual `SupportContext::getPost()` validation with typed Form Request classes:
- `app/Http/Requests/Moderation/BanRequest.php`
- `app/Http/Requests/Staff/ModtaskRequest.php`
- `app/Http/Requests/Usercp/SettingsRequest.php`
- etc.

This centralizes validation rules and makes them testable.

#### 3.3 Replace `SupportContext` static facade

`SupportContext` is a god-object holding user, globals, query, post, and cache state. Gradually replace with:
- `Auth::user()` for current user
- `$request->input()` / Form Requests for input
- `SiteConfig::current()` for settings
- Dependency-injected services instead of static `SupportContext::getGlobal()`

**Exit criteria:** No controller >400 LOC. All validation in Form Request classes. `SupportContext` reduced to a thin compatibility shim or removed.

---

### Track 4 — Dependency & Infrastructure Consolidation (Weeks 3–4)

#### 4.1 Remove Elasticsearch

**Finding:** `SearchRepository` (ES) is wired into `TorrentActionController` for bookmark/delete sync, but:
- `SCOUT_DRIVER=meilisearch` is the default and only documented driver
- `ELASTICSEARCH_ENABLED` defaults to `null` (disabled)
- `Torrent::shouldBeSearchable()` checks `MeiliSearchRepository::isEnabled()`
- ES methods short-circuit `return true` when disabled

**Action:**
1. Remove `elasticsearch/elasticsearch` from `composer.json`
2. Delete `app/Repositories/SearchRepository.php`, `app/Listeners/SyncTorrentToElasticsearch.php`, `app/Console/Commands/Es*.php`
3. Remove ES calls from `TorrentActionController`, `TorrentEditRepository`, `TorrentAjaxRepository`
4. Remove ES env vars from `.env.example` and config
5. Update `SearchSuggest::addSuggestion` to use MeiliSearch or DB directly

**Reduces:** ~1 000 LOC, 1 major dependency, 6 console commands, 1 event listener.

#### 4.2 Evaluate ClickHouse usage — **DECISION: KEEP**

**Finding:** ClickHouse is used for two high-volume log tables:

1. **`bonus_logs` (seeding category)** — `SeedBonusJob::insertIntoClickHouseBulk()` writes bulk seeding-bonus records every cron cycle. `BonusRepository::getCount()/getList()` reads them for `CATEGORY_SEEDING`. The MySQL `bonus_logs` table handles `CATEGORY_COMMON` (manual transactions like buying medals, cancelling H&R). The two categories are split by `business_type` — seeding types (10001–10005) go to ClickHouse, everything else to MySQL.

2. **`announce_logs`** — `AnnounceLogRepository` reads from ClickHouse for the admin Announce Logs page (`AnnounceLogResource`) and Announce Monitor dashboard (`AnnounceMonitor`). Writes come from the tracker itself. TTL 90 days, `MergeTree` engine partitioned by day.

**Decision rationale:** Both tables are write-heavy log tables with 90-day TTL and daily partitioning — exactly ClickHouse's sweet spot. Moving them to MySQL would create large hot tables that degrade InnoDB performance. The code already gracefully handles ClickHouse being absent (`config('clickhouse.connection.host') == ''` → empty results, `SeedBonusJob` skips insert), so ClickHouse remains **optional** — deployments without it simply don't see seeding bonus logs or announce logs.

**Action taken:** Documented the decision. No code changes needed. ClickHouse stays as an optional analytics backend.

#### 4.3 Consolidate auth: pick Sanctum OR Passport — **DONE (keep Sanctum, remove Passport)**

**Finding:** API routes use `auth:sanctum`. `OauthController::userInfo` used `auth:api` (Passport). Both shipped in `composer.json`. Passport was not fully configured — `Passport::routes()` was never called, so OAuth2 endpoints (`/oauth/token`, `/oauth/authorize`) were not registered. Passport was only used for:
- `auth:api` guard on `GET /oauth/user-info`
- Filament admin resources for oauth tables (clients, access tokens, auth codes, refresh tokens)
- `RemoveOauthTokens` listener (cleared tokens on user disable)
- `OauthClient` model extending `Laravel\Passport\Client`

**Action taken:** Removed Passport entirely, kept Sanctum as the sole token auth system:
- `config/auth.php`: `api` guard driver `passport` → `sanctum`
- `routes/web.php`: `oauth/user-info` middleware `auth:api` → `auth:sanctum`
- `app/Providers/AppServiceProvider.php`: removed `Passport::$clientUuids = false`
- `app/Nexus/Database/NexusDB.php`: removed `Passport::useClientModel()` from `customModel()`
- `app/Exceptions/Handler.php`: removed `PassportAuthenticationException` handler
- `app/Listeners/RemoveOauthTokens.php`: deleted (was Passport-only)
- `app/Providers/EventServiceProvider.php`: removed `RemoveOauthTokens` from `UserDisabled` listeners
- `app/Models/OauthClient.php`: deleted (extended `Laravel\Passport\Client`)
- `app/Filament/Resources/Oauth/`: deleted `AccessTokenResource`, `AuthCodeResource`, `RefreshTokenResource`, `ClientResource` (all Passport model-based). Kept `ProviderResource` (social OAuth providers, not Passport)
- `composer.json`: removed `laravel/passport` dependency and `passport:keys` post-install script

**Note:** OAuth2 database tables (`oauth_access_tokens`, `oauth_auth_codes`, `oauth_refresh_tokens`, `oauth_clients`, `oauth_personal_access_clients`) remain in the database — migrations are not removed. Existing tokens are orphaned but harmless. The `OauthController::redirect/callback` routes (social login) are unaffected — they use `OauthProvider` model, not Passport.

#### 4.4 Remove `minimum-stability: dev` — **DONE**

`composer.json` had `"minimum-stability": "dev"`. With `"prefer-stable": true` this was mostly harmless but allowed unstable deps to resolve. Changed to `"minimum-stability": "stable"`. Verified all 340 locked packages are already stable — no constraints rely on dev versions.

#### 4.5 Upgrade PHP to 8.5 — **DONE**

`composer.json` declares `>=8.4 <8.6` (supports both 8.4 and 8.5). Updated `platform.php` from `8.4.0` to `8.5.0` so Composer resolves dependencies against 8.5 APIs. CI workflow updated from PHP 8.4 to 8.5. Installer `minimumPhpVersion` raised from `8.2.0` to `8.4.0` to match the composer constraint. No 8.4 workarounds were found in the codebase.

**Exit criteria:** Single search engine (MeiliSearch), single auth system, `minimum-stability: stable`, PHP 8.5 supported.

---

### Track 5 — Admin Panel Unification (Weeks 4–10)

**Goal:** Make Filament the canonical admin panel; retire legacy admin pages.

**Current state:**
- Filament has 6 resource groups: User (14 resources), Torrent (5), System, Section, TorrentCustomFields, Oauth
- Legacy admin routes (`routes/legacy/auth.php`) expose ~40 admin pages via `AdminController`, `StaffController`, `SettingController`, `SystemController`

**Strategy:**

| Phase | Legacy page | Filament replacement | Action |
|-------|-------------|---------------------|--------|
| 5.1 | `checkuser`, `takeconfirm` | `UserResource` action | Add "Approve user" action to `UserResource` table; delete legacy routes |
| 5.2 | `donorlist`, `warned`, `nowarn` | `UserResource` filters | Add Donor/Warned filters + bulk actions; delete legacy routes |
| 5.3 | `bans`, `cheaters`, `iphistory`, `ipsearch`, `ipcheck` | New `SecurityResource` group | Create Filament resources for bans, IP history, cheater detection; delete `ModerationController` legacy methods |
| 5.4 | `modtask`, `staffbox`, `staffmess`, `contactstaff` | `UserResource` edit page + `StaffMessageResource` | Move user moderation into `UserResource` edit form; create resource for staff messages |
| 5.5 | `stats`, `allagents`, `donorlist` | Filament dashboard widgets | Create dashboard widgets; delete `AdminController` legacy methods |
| 5.6 | `delacctadmin`, `deletedisabled`, `massmail`, `maxlogin` | Filament `SystemResource` actions | Add destructive actions with confirmation; delete `SystemController` legacy methods |
| 5.7 | `catmanage`, `forummanage`, `moforums`, `fields`, `formats` | `SectionResource`, `ForumResource` (create) | Create Filament resources; delete legacy management pages |

**Exit criteria:** `routes/legacy/auth.php` contains only non-admin authenticated routes (usercp, messages, forums, torrents). All admin operations via `/admin` (Filament). Legacy admin controllers deleted.

---

### Track 6 — Testing & CI (Ongoing, weeks 1–12)

#### 6.1 Feature test coverage for critical paths

| Path | Test file | Priority |
|------|-----------|----------|
| Announce (started/completed/stopped) | `tests/Feature/AnnounceFlowTest.php` | Already partially covered — extend |
| Torrent download (downhash + passkey + auth) | `tests/Feature/TorrentDownloadTest.php` | High |
| Login + 2FA + passkey | `tests/Feature/AuthFlowTest.php` | High |
| Torrent upload + edit | `tests/Feature/TorrentUploadTest.php` | Medium |
| Forum topic + post CRUD | `tests/Feature/ForumFlowTest.php` | Medium |
| Admin moderation (ban/warn/enable) | `tests/Feature/ModerationTest.php` | Medium |
| Shoutbox post/edit/delete/react | `tests/Feature/ShoutboxTest.php` | Low |

#### 6.2 Make `CriticalPathTest` runnable in CI

Currently skipped unless `CRITICAL_PATH_BASE_URL` is set. Wire it to the Docker smoke-test job in `ci.yml` so the legacy page flow is tested on every PR.

#### 6.3 Add PHPStan level 8 to legacy partials incrementally

As each `_content.php` is drained (Track 2), remove its exclusion from `phpstan.level8.neon`. Target: zero exclusions by end of Track 2.

#### 6.4 Add Rector for automated refactoring

Install `rector/rector` as dev dependency. Configure with:
- `LevelSetList::UP_TO_PHP_84`
- `LaravelSetList::LARAVEL_120`
- Custom rules for `SupportContext::getUser()` → `Auth::user()` migration

Run in CI as a non-blocking check initially, then as auto-fix PRs.

#### 6.5 Add Laravel Pint for formatting

`laravel/pint` is not in `composer.json`. Add it, configure `.pint.json` with PSR-12 + Laravel preset, run in CI.

**Exit criteria:** Feature tests cover all critical paths. `CriticalPathTest` runs in CI. PHPStan level 8 has zero exclusions. Rector + Pint in CI.

---

## Dependency Update Summary

| Package | Current | Target | Action |
|---------|---------|--------|--------|
| PHP | 8.4 | 8.5 | Track 4.5 — platform + CI updated to 8.5 |
| Laravel Framework | 12.64 | 12.x latest | Already current, keep updated |
| Filament | 5.7 | 5.x latest | Already current |
| PHPUnit | 11.5 | 12.x | Upgrade when Laravel 12 fully supports it |
| PHPStan | 2.2 | 2.x latest | Already current |
| elasticsearch/elasticsearch | ^9.0 | **remove** | Track 4.1 |
| cybercog/laravel-clickhouse | ^0.2.1 | **keep** (documented) | Track 4.2 — optional analytics backend for bonus_logs + announce_logs |
| laravel/passport | ^13.0 | **removed** | Track 4.3 — Sanctum is sole auth system |
| laravel/sanctum | ^4.0 | keep | — |
| rector/rector | — | add ^2.0 | Track 6.4 |
| laravel/pint | — | add ^1.20 | Track 6.5 |
| tailwindcss | 3.4 | 4.x (when stable ecosystem) | Future |
| vite | 6.4 | 6.x latest | Already current |

---

## Estimated Impact

| Metric | Before | After (target) |
|--------|--------|----------------|
| Total PHP LOC in `app/` | 108 626 | ~85 000 (−22%) |
| Legacy `_content.php` files | 9 (9 298 LOC) | 0 |
| Legacy `partials/` | 22 (1 826 LOC) | 0 |
| `app/Nexus/` LOC | 6 586 | <2 000 (only DB/Plugin/Exam helpers) |
| Controllers >700 LOC | 6 | 0 |
| PHPStan level 8 exclusions | 2 paths | 0 |
| Feature test files | 7 | 20+ |
| Search engines | 3 | 1 (MeiliSearch) |
| Auth token systems | 2 | 1 (Sanctum) |
| Admin systems | 2 (Filament + legacy) | 1 (Filament) |
| CSRF-exempt routes | ~70 | 1 (tg-webhook) |
| Password hashing | sha256 (weak) | argon2id |

---

## Sequencing

```
Week 1-2:   Track 1 (Security) ──────────────────────────────────>
Week 1-6:   Track 2 (Legacy drain) ──────────────────────────────>
Week 2-8:   Track 3 (Controller slimming) ───────────────────────>
Week 3-4:   Track 4 (Dependency consolidation) ──────────────────>
Week 4-10:  Track 5 (Admin unification) ─────────────────────────>
Week 1-12:  Track 6 (Testing & CI) ──────────────────────────────>
```

Tracks 1, 2, and 6 start immediately in parallel. Track 3 depends on Track 2 progress (controllers can't be slimmed until content files are drained). Track 5 depends on Track 3 (admin controllers must be cleaned before Filament migration). Track 4 is independent and quick.

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| CSRF re-enablement breaks legacy forms | Deploy behind feature flag; test each form before removing exemption; provide migration guide for custom plugins |
| Password hash migration locks out users | Keep legacy verifier as fallback; upgrade on successful login; add `passhash_algo` column to track; monitor failed-login spike |
| Filament migration removes pages users depend on | Keep legacy routes as deprecated redirects for one release; announce in release notes; provide URL mapping |
| Rector auto-fixes introduce bugs | Run as separate PRs; require human review; full test suite must pass before merge |
| Removing Elasticsearch breaks installs that use it | Check download/install stats; provide migration script to MeiliSearch; announce deprecation one release ahead |

---

## Getting Started

The lowest-risk, highest-impact starting points:

1. **Track 1.2** (cron token) — 1 file, 30 minutes, closes a High finding
2. **Track 1.5** (`.env.example` sanitization) — 1 file, 15 minutes
3. **Track 1.6** (ajax whitelist) — 1 file, 1 hour
4. **Track 4.1** (remove Elasticsearch) — well-isolated, ~1 000 LOC reduction
5. **Track 6.5** (add Pint) — zero-risk CI addition

These can be shipped as individual PRs this week while planning the larger tracks.
