# Release Notes

## Release process & migration policy

- **Cadence:** releases are tagged `v*` (e.g. `v2.0.1`). A tag is cut when a
  wave of work is merged and green on `php8`; there is no fixed calendar —
  each tag must leave `php8` in a deployable state.
- **Previous-release fixture:** CI job `migrations` upgrades a database
  snapshot of the previous tag to HEAD. After tagging a release `vX.Y.Z`,
  regenerate the fixture from a database fully migrated **at that tag**:

  ```bash
  mysqldump -u nexusphp --single-transaction --no-tablespaces \
    --set-gtid-purged=OFF --skip-comments nexusphp \
    > database/schema/vX.Y.Z.mysql.sql
  ```

  then update `PREVIOUS_RELEASE` in `.github/workflows/ci.yml` (`migrations`
  job env). The job fails fast if the snapshot file is missing, so a stale
  pointer can never silently pass.
- **Expand/contract rules:** never edit a migration that shipped in a tag —
  shipped migrations are immutable. Add columns/tables in a new migration
  (expand), migrate data, and drop old structures in a later release
  (contract) once all supported upgrade paths have passed it. Data-writing
  migrations must be idempotent and must make columns nullable *before*
  writing `NULL` into them.
- **Upgrade command:** `php artisan nexus:update` (used by `demo.yml`)
  runs `migrate` against the existing database — the CI `migrations` job
  mirrors exactly this path.

## Backup & restore

- **What is backed up:** `php artisan backup:all --method=tar` produces
  `storage/app/backups/<base>.<ts>.tar.gz` containing a `mysqldump
  --single-transaction` of the database and a `tar.gz` of the web root —
  which includes the data directories `torrents/`, `attachments/` and
  `bitbucket/` (they live outside `public/`, so they are part of the code
  tree). Volatile paths (logs, framework caches, `storage/app/backups`
  itself) are excluded; `.env` **is** included — treat archives as secrets.
- **Scheduled backups:** `backup:cronjob` (every 5 min, gated by the
  `backup.*` site settings: enabled, hourly/daily frequency, retention
  count) calls the same `backupAll` path and can transfer the bundle to
  remote storage; `BACKUP_GPG_RECIPIENT` enables GPG encryption.
- **Restore (operator path):**

  ```bash
  tar -xzf <base>.<ts>.tar.gz -C /tmp/restore
  mysql -u nexusphp -p <db> < /tmp/restore/<base>.database.<ts>.sql
  tar -xzf /tmp/restore/<base>.web.<ts>.tar.gz -C <parent-of-webroot> \
    <base>/torrents <base>/attachments <base>/bitbucket
  ```

  For a code+data restore, extract the web tar fully instead of the three
  directories.
- **Verification:** `php artisan backup:restore-drill --latest` restores the
  newest dump into a scratch database and checks table count; add
  `--compare` to also compare per-table row counts against the source
  (point-in-time check — run it on a quiet window, since live counters
  drift). The push-only CI job `backup-restore`
  (`scripts/ci/verify-backup-restore.sh`) exercises the full cycle
  end-to-end: backup → drill → destroy DB+files → restore → CHECKSUM TABLE
  and sha256 manifest parity → HTTP smoke.
- **RPO/RTO:** RPO is bounded by the configured backup frequency
  (hourly/daily). RTO is dump restore time + file extraction; the CI job
  reports wall-clock timings per phase.

## Deployments

- **Graceful deploy:** `scripts/deploy.sh` — drains the queue/scheduler
  (SIGTERM; Horizon finishes in-flight jobs within `stop_grace_period`),
  resyncs the public-assets volume, recreates `php`, runs `migrate
  --force`, recreates `openresty`, then gates on `/health/ready` before
  bringing workers back. `--skip-build` reuses the current image.
- **Signals:** `php`/`openresty` stop via `SIGQUIT` (graceful for php-fpm
  and nginx); `queue`/`scheduler` via `SIGTERM` — the `php:fpm` base image
  default (`SIGQUIT`) is *not* trapped by artisan/Horizon and would end in
  SIGKILL after the grace window.
- **Grace windows:** queue 125s (covers all supervisors except
  `maintenance`, whose 600s jobs are requeued via `retry_after`),
  scheduler 70s, php 60s, openresty 30s.
- **Rollback:** `docker tag <prev-digest> nexusphp_php:prod && scripts/
  deploy.sh --skip-build`; the script prints the previous image id at
  build time.
- **Caveat:** single `php` upstream → a brief 502 window during its
  recreate is expected; zero-downtime needs a second backend (blue-green).
- **Queue liveness:** `php artisan queue:probe --wait` dispatches a probe
  job and waits for its marker — proves workers actually run jobs, not
  just that Horizon is up.

## Supply chain (W8-05)

- **Signed images:** every merge to `php8` that passes the three prod
  gates (compose smoke, backup/restore, graceful deploy) publishes
  `ghcr.io/<owner>/<repo>/php` and `.../openresty` tagged `sha-<short>`
  and `latest`, cosign-signed keyless (GitHub OIDC → Fulcio) with SLSA
  build-provenance attestations on the digests.
- **Verified deploy:** `scripts/deploy.sh --image ghcr.io/.../php@sha256:…`
  verifies the signature (`scripts/verify-image.sh`), then pulls by the
  *verified digest* — a tag can be re-pointed after signing, a digest
  cannot. Requires `cosign` on PATH; `--skip-verify` exists for local
  testing only. `docker login ghcr.io` is needed while the packages are
  private.
- **Pin ratchet:** `scripts/ci/check-supply-chain-pins.sh` blocks PRs that
  add unpinned `uses:` actions, undigested `FROM`/`image:` references,
  unversioned tool packages (`wget`, `curl`, `git`, …) in Dockerfiles, or
  remote downloads in entrypoints. Existing pins use `~=` (survives apk
  patch bumps) or `@sha256:` digests.
- **No secrets in images:** `.env`, `bootstrap/cache/*`, `storage/*` and
  the upload/backup dirs are dockerignored — a published image can never
  carry the builder's secrets, dev caches or local backups. At runtime
  `./.env` is bind-mounted read-only into `php`/`queue`/`scheduler`
  (required — `deploy.sh` fails fast without it), and the entrypoint
  deletes any inherited `bootstrap/cache/config.php` before validating,
  because a cached config makes Laravel skip `.env` entirely.

## Highlights

This release ships the shoutbox modernization, MeiliSearch-by-default, setlist lookup on upload, and several runtime hardening fixes across the `php8` branch.

## New Features

- **Shoutbox modernization**
  - BBCode toolbar with bold, italic, spoiler, code, quote, link and an emoji picker.
  - Relative timestamps (e.g. "5 min ago") with a tooltip showing the exact date.
  - Edit and delete your own messages within 2 minutes (staff are exempt from the time limit).
  - Discord/Slack-style reactions (👍 🔥 ❤️ 😂 😮 😢) with per-message counts.
  - Shoutbox history page with filters by user, date, text and type, plus pagination.
  - Real-time delivery over Server-Sent Events (`shoutbox_sse.php`) with a polling fallback.

- **MeiliSearch enabled by default**
  - Dedicated `meilisearch` service in Docker Compose, bound to `127.0.0.1` and running in production mode with a configurable master key.
  - All PHP services receive the same `MEILISEARCH_MASTER_KEY`.
  - Search/autocomplete falls back to SQL when MeiliSearch is unreachable or the index is missing.
  - Torrent import is deterministic (`orderBy('id')`) and waits for index updates.

- **Upload setlist lookup**
  - New "Fill setlist" button next to the Torrent name field on `upload.php`.
  - Parses the torrent name into artist / city / state / country / event / date, then fetches the setlist from Linkinpedia (MediaWiki API) with a setlist.fm fallback.
  - Inserts a structured track list into the Description field.

## Fixes & Hardening

- **PHP 8.4 runtime cleanup**
  - Raised PHP requirement to `>=8.4 <8.6`.
  - Replaced deprecated `strftime()` in `public/mysql_stats.php` with a `DateTime`-based locale formatter.

- **Environment / tracker fixes**
  - `Setting::getBaseUrl()` and `include/config.php` now fall back to `$_SERVER['HTTP_HOST']` / `localhost` when `basic.BASEURL` is empty, preventing invalid announce URLs like `http:///announce.php`.
  - Fixed the BiglyBT 3.x peer-id regex in the agent-allowed family seed and migration.
  - MeiliSearch import query now selects the required fields in a deterministic order.

- **Release blockers**
  - `App\Support\Shoutbox` CSRF helpers fall back to `getenv('APP_KEY')` and then `nexus_env('APP_KEY')` when `config('app.key')` is not yet loaded in legacy/FPM pages.
  - `public/takesignup.php` accepts both the `sha256(password)` value sent by the browser and plain passwords used by tests/CLI, eliminating a double-hash bug.
  - `App\Jobs\CalculateUserSeedBonus` no longer applies a shared `WithoutOverlapping` lock when dispatched without a coordination key.
  - `shoutbox_reactions` now uses the `utf8mb4_bin` collation for the `reaction` column, so different 4-byte emoji no longer match each other and multi-emoji reactions work correctly.
  - CI smoke test now clears the Laravel config cache before the Feature test suite.

## Upgrade Notes

- **Security deprecation — legacy `md5` password hashes (step 3.2, ADR 0015).**
  The `ALGO_MD5` verification branch (`md5(secret+password+secret)`,
  only reachable for accounts with empty `auth_key`) is scheduled for
  removal. Deadline **2026-10-17**: before that date run
  `php artisan users:legacy-hash-report` on production and record the
  md5 count here. If md5 users are ≤1% of total or all inactive 6+
  months, run `php artisan users:force-reset-legacy --apply` — flagged
  accounts are forced through password change on next request
  (`must_change_password` + `RequirePasswordChange`), and the md5
  branch is removed in the following release. If the share is larger,
  extend once by 30 days and re-measure; the branch is not kept
  indefinitely.
- Run `php artisan migrate` to apply the BiglyBT agent regex and shoutbox reaction collation migrations.
- Run `php artisan meilisearch:import` to populate the `torrents` index if you are enabling MeiliSearch for the first time.
- Ensure `APP_KEY` is set in `.env`; it is now used by the shoutbox CSRF helpers.

## Full Changelog

- `devin/ci-docker-improvements` (#150) — Docker healthchecks, service dependencies, CI composer/npm cache, and frontend build verification.
- `devin/bump-php-83` (#154) — PHP 8.4 target and `strftime()` replacement.
- `devin/meilisearch-default` (#155) — MeiliSearch Docker service, production mode and default keys.
- `devin/upload-setlist` (#157) — setlist.fm / Linkinpedia lookup on `upload.php`.
- `devin/fix-env-gotchas` (#159) — BASEURL fallback, BiglyBT regex and MeiliSearch import fix.
- `devin/shoutbox-modernization` (#160) — shoutbox toolbar, relative time, edit/delete, reactions, history, SSE.
- `devin/release-blockers` (#161) — CSRF fallback, password hash fix, seed-bonus job lock fix and CI config-cache clear.
