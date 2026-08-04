---
name: testing-tracker-lp-bits
description: How to run end-to-end tests for tracker-lp-bits in the local Docker/MeiliSearch stack.
---

# Testing tracker-lp-bits end-to-end

Use this skill when asked to test the tracker-lp-bits app in the local Docker Compose stack.

## Test environment prerequisites

1. Docker Compose stack must be running: `docker compose up -d`.
2. The `php` container is `php:8.4-fpm-alpine` and `/var/www/html` maps to the repo.
3. A sysop test user must exist. If not, create one and promote it to `UC_SYSOP` with `uploadpos = 'yes'` and `clear_user_cache(<id>)`.
4. The `c_secure_pass` cookie can be generated for browser/curl use with:
   ```php
   App\Support\AuthCookie::buildToken($user->id, $user->auth_key, time() + 86400);
   ```

## Common environment gotchas

- `basic.BASEURL` may be empty in `settings`, producing `http:///announce.php` and breaking `/announce.php`. Set it to `localhost` (or the real host) and clear Redis settings cache.
- `agent_allowed_family` seed for BiglyBT may contain an invalid regex such as `^BiglyBT\ /3...`. Use a valid regex like `/^BiglyBT\/3\.([0-9])\.([0-9])\.([0-9])/` to avoid `preg_match` warnings from `/announce.php`.
- `php artisan meilisearch:import` may report success but swap to an empty `torrents` index because of `MeiliSearchRepository::doImportFromDatabase()`. Verify the document count with:
  ```
  curl -s 'http://localhost:7700/indexes/torrents/stats'
  ```
  If the count is 0, manually add the test torrents to MeiliSearch or fix the import logic.

## Test flow for setlist upload PRs

1. Open `/upload.php` with a logged-in sysop user.
2. Fill **Torrent name** with a name like `Linkin Park - Hamburg, Germany, Volksparkstadion (03.06.2026)`.
3. Click **Fill setlist** — the button should switch to `Loading...` and become disabled, then the **Description** textarea should be populated.
4. Verify `/setlist_lookup.php?name=<name>` returns JSON with `success=true`, `data` (artist/venue/date/sets/source) and `text` (formatted BBCode).
5. Upload torrents via `takeupload.php` (browser file inputs may not work under automation) using the setlist text from the previous step.
6. Verify `/torrents.php` listings and search (`?search=Linkin+Park`, `?search=Hamburg`, `?search=Volksparkstadion`).
7. Verify `/announce.php` returns valid bencode and `details.php` shows seeders/peers.
8. Verify edit/promote/delete flows.
9. Run `composer validate` and `vendor/bin/phpstan analyse`.
10. Check `docker logs nexusphp-php` for new fatals or deprecation warnings.

## Known limitations

- The legacy autocomplete on `/torrents.php` may not register native keystrokes in headless automation; trigger `suggest(0, '<term>')` from the console to verify it.
- GitHub Actions CI may not start due to account billing/spending limits; rely on local Docker verification when that happens.

## Testing `/announce.php` end-to-end

Use these notes when verifying the BitTorrent announce endpoint in the Docker stack.

### Request host / `basic.BASEURL` must match

`AnnounceService::checkTrackerUrl()` compares the current request host with the tracker URL built from `basic.BASEURL`. A mismatch causes a `warning message` response and aborts peer processing.

- `CriticalPathTest` sets `basic.BASEURL` to `openresty`, so it must be run with:
  ```bash
  docker compose exec -e CRITICAL_PATH_BASE_URL=http://openresty php vendor/bin/phpunit \
    --filter testCriticalPath tests/Feature/CriticalPathTest.php --no-coverage
  ```
- For manual tests from the `php` container, request `http://openresty/announce.php` and ensure `basic.BASEURL` is `openresty`:
  ```php
  App\Support\Settings::saveBatch('basic', ['BASEURL' => 'openresty']);
  \Illuminate\Support\Facades\Redis::flushAll();
  ```

### `peer_id` and `info_hash` encoding

`AnnounceRequest` validates `info_hash` and `peer_id` with `strlen() == 20` on the raw binary string. Use 20-byte values and build the query with `PHP_QUERY_RFC3986`:

```php
$peerId = '-qB4' . sprintf('%02d', random_int(0, 99)) . random_bytes(14); // 20 bytes
$query  = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
```

Use `User-Agent: qBittorrent/4.x.x` and a `-qB4...` `peer_id` to pass the `AgentAllowRepository` allow-list check.

### Controlling the peer IP

`Network::clientIp()` reads `HTTP_X_FORWARDED_FOR` first. Use distinct `10.0.0.x` IPs per peer to avoid the same-IP seeder warning (`You cannot seed the same torrent in the same location from more than 1 client.`):

```php
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Forwarded-For: 10.0.0.1']);
```

### Lock / cache behavior

- The short re-announce lock (`isReAnnounce:<md5(passkey+info_hash)>`) has a 5s TTL. A second request within that window returns early and does **not** insert a duplicate peer.
- The `isReAnnounce` early-return response is built by `buildInitialRepDict()`, which now queries the `peers` table live for `complete`/`incomplete` counts, so the response reflects the current peer state even when the `torrent_hash_<infoHash>_content` cache has not expired.

### Missing required parameters

The openresty Lua filter in `.docker/openresty/lua/tracker_filter.lua` rejects requests missing required announce parameters with `400 Bad Request` and a bencoded `failure reason` (e.g. `Missing parameter: port`) before PHP is reached.

## Testing `/scrape` and `/scrape.php` end-to-end

Both `GET /scrape` and `GET /scrape.php` dispatch to `ScrapeController::scrape` through the FPM wrapper. `ScrapeService::parseInfoHashes()` reads `QUERY_STRING` directly, URL-decodes each `info_hash` value, and matches the resulting raw 20-byte binary against `torrents.info_hash`.

- Send `info_hash` values **URL-encoded raw binary** (not hex). Use `rawurlencode($infoHashBinary)`; a custom encoder must zero-pad bytes < 16 (e.g. `%0E`, not `%E`).
- Repeat `info_hash` query parameters for multi-torrent scrape (`info_hash=...&info_hash=...`).
- Valid responses are `200` `Content-Type: text/plain; charset=utf-8` with a `files` dict keyed by raw info_hash.
- Invalid passkey returns `200` with `failure reason` and sets Redis `passkey_invalid:<passkey>` with a 24h TTL.
- Missing `info_hash` returns `200` with `warning message`, `files: []`, plus `interval` / `min interval`.

## Testing `cleanup:run` and the cleanup container

- `docker compose exec php php artisan cleanup:run --force` should exit `0` and stream legacy `cleanup_cli.php` progress, ending with `[CLEANUP_RUN] DONE, cost time: N seconds`.
- The `cleanup` service in `.docker/php/entrypoint.sh` runs the command in a 60s loop. Verify with `docker compose logs --since 2m cleanup`.

## Testing the merged `php8` regression bundle

Typical settings for a full `php8` regression run:

```sql
INSERT INTO settings (name, value) VALUES ('use_challenge_response_authentication','no'),('security.iv','no'),('tweak.where','yes'),('meilisearch.enabled','no'),('torrent.download_support_passkey','yes'),('torrent.approval_status_none_visible','yes') ON DUPLICATE KEY UPDATE value=VALUES(value);
```

Then clear the Redis settings cache (`nexus_settings_in_nexus`, `nexus_settings_in_laravel`).

- `public/torrents/` must exist and be writable by `www-data`; `takeupload.php` writes the `.torrent` file through `getFullDirectory(main.torrent_dir)` which resolves relative to the FPM `getcwd()` (`public/`).
- `storage/framework/views/` must be writable so `TorrentPolicy` denial views can be compiled.
- Generate a fresh `.torrent` with `Rhilip\Bencode\Bencode` and `announce=http://openresty/announce.php`, upload via `/takeupload.php`, capture `info_hash` from the DB for announce/scrape probes.
- For first authenticated downloads, add `letdown=1` (`download.php?id=<id>&letdown=1`) to bypass the `showdlnotice` redirect to `downloadnotice.php`.

## Testing PR 19a+19b auth (signup/confirm/recover/login/logout)

- Disable login-attempt bans or clear `loginattempts` before repeated login probes (`DELETE FROM loginattempts`).
- Use `Rhilip\Bencode\Bencode` to generate a minimal `.torrent` and upload via `/takeupload.php` for regression.
- Signup and confirm resend POSTs need `_token` and a shared cookie jar between the GET form and POST.
- Confirm hash: `md5(str_pad($secret, 20))` (because `Strings::padHash` pads to 20 bytes).
- Recover reset hash: `md5(str_pad($editsecret, 20) . $email . $passhash . str_pad($editsecret, 20))`.
- When `main.smtptype='none'`, `Mail::sentLegacy` writes the email body to `/tmp/nexus-YYYY-MM-DD.log` in the `php` container; read reset/confirm URLs and new passwords from that log.
- Watch for `Mail::sentLegacy`/`SupportContext` globals-drain regression: if auth wrappers do not load `config/allconfig.php`, `$GLOBALS['smtptype']` is empty and `Mail::sent` may call `stderr()`/`Style::cssRow` and throw a `TypeError`.
- Use `AuthCookie::verifyToken($rawCookieValue)` (with `urldecode` if reading from curl jar) to inspect the APP_KEY-encrypted `c_secure_pass` cookie.
