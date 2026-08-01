# Release Notes

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

- Run `php artisan migrate` to apply the BiglyBT agent regex and shoutbox reaction collation migrations.
- Run `php artisan meilisearch:import` to populate the `torrents` index if you are enabling MeiliSearch for the first time.
- Ensure `APP_KEY` is set in `.env`; it is now used by the shoutbox CSRF helpers.

## Full Changelog

- `devin/bump-php-83` (#154) — PHP 8.4 target and `strftime()` replacement.
- `devin/meilisearch-default` (#155) — MeiliSearch Docker service, production mode and default keys.
- `devin/upload-setlist` (#157) — setlist.fm / Linkinpedia lookup on `upload.php`.
- `devin/fix-env-gotchas` (#159) — BASEURL fallback, BiglyBT regex and MeiliSearch import fix.
- `devin/shoutbox-modernization` (#160) — shoutbox toolbar, relative time, edit/delete, reactions, history, SSE.
- `devin/release-blockers` (#161) — CSRF fallback, password hash fix, seed-bonus job lock fix and CI config-cache clear.
