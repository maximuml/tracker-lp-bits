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
