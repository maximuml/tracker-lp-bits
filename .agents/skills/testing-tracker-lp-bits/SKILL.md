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

## Testing PR #188 comment migration (`public/comment.php` wrapper)

- The legacy wrapper dispatches to Laravel `WebCommentController` routes under the `auth.nexus:nexus-web` middleware.
- `GET /comment.php?action=add&pid=<id>&type=torrent` should render `comments.create` using `Frame::composeBegin/composeEnd` (BBCode editor, `textarea name="body"`, submit `id="qr"`).
- `POST /comment.php?action=add&type=torrent` (with `pid` and `body`) should redirect to `details.php?id=<pid>#<newId>`.
- `GET /comment.php?action=add&pid=<id>&type=torrent&sub=quote&cid=<id>` should prefill `[quote=<username>]<text>[/quote]`.
- `GET/POST /comment.php?action=edit&cid=<id>&type=<type>` should load an edit form and redirect to `details.php?id=<pid>`.
- `GET /comment.php?action=delete&cid=<id>&type=<type>` should show confirmation; the `sure=1` link removes the comment.
- `GET /comment.php?action=vieworiginal&cid=<id>&type=<type>` (staff/commanage) should display `comments.ori_text`.
- `type=offer` redirects to `offers.php?id=<id>&off_details=1#<newId>`.
- Anti-flood rejects a second comment by a normal user within 10s with HTTP 403 and `Comment Flooding Not Allowed`.
- Common gotchas found during testing:
  - `CommentRepository::getParent()` casts Eloquent models with `(array)`, producing private-property arrays instead of `['name' => ..., 'owner' => ...]`. Use `$model->toArray()` or `json_decode(json_encode($model), true)`.
  - `public/comment.php` must put the query string into the `$uri` passed to `Request::create()`; `REQUEST_URI` in the `$server` array is overwritten by Symfony and the query is lost for POST requests. This causes `StoreCommentRequest` to fail `type` validation.
  - `public/comment.php` must not strip `cid` from the query when `action=add` because `sub=quote` needs `cid` as a query parameter.
  - The rendered `form action` in `resources/views/comments/_form.blade.php` is not HTML-escaped to `&amp;`; browsers tolerate raw `&`, but the legacy `details.php` quick-comment form uses `htmlspecialchars`.

## Testing php8 PR #199-#205 legacy-page migrations

This section covers the combined `php8` branch migrations (`usercp`, `edit`/`takeedit`, `mybonus`/`myhr`, `topten`/`log`, `index.php`, and `friends`/`messages`/`getrss`/`sendmessage`/`userhistory`/`invite`).

### Login/logout gotchas

- `public/login.php` builds `Illuminate\Http\Request::create($uri, $_SERVER['REQUEST_METHOD'], $_GET, ...)` for **both** GET and POST. This means a form POST to `/login.php` loses `username`/`password`/`_token`, so Laravel returns `419` (CSRF) because no `_token` reaches `VerifyCsrfToken`.
- The direct Laravel `/login` route works as expected (POST returns `302` to `index.php` and sets `c_secure_pass`).
- Workaround for UI automation: navigate to `http://openresty/logout`, then `http://openresty/login`, fill the form, and submit; or use curl against `/login` with a CSRF token extracted from `/login`.

### Download/announce/scrape

- `download.php?id=<id>` redirects to `downloadnotice.php` on the first authenticated download. Append `&letdown=1` to bypass the notice and receive `Content-Type: application/x-bittorrent`.
- `announce.php` requires a 20-byte `peer_id` and an allowed `User-Agent` (e.g. `uTorrent/3000` with peer_id `-UT3000...`).
- `scrape.php` returns a valid `files` dict when `info_hash` is supplied as raw binary URL-encoded.

### Forums `viewunread` state setup

- To make `/forums.php?action=viewunread` list a topic, ensure `users.last_catchup` is `0` (or lower than `topics.lastpost`) **and** `readposts` has no row for that user/topic. Then click **Catch up** to update `last_catchup` and verify `viewunread` shows nothing afterwards.

### Known failures in the bundle

- `/userhistory.php?id=1` (default action, no `action=` query) currently throws `TypeError: App\Support\PageLayout::header(): Argument #1 ($title) must be of type string, null given` because `stdhead()` is called with a `null` title in `resources/views/userhistory/_userhistory_legacy.php`. The named actions (`viewposts`, `viewcomments`) render correctly.
- `public/login.php` POST login is broken as described above.

## Testing PR #27 staff/mod page migrations

- `storage/framework/views/` must be writable by the PHP-FPM worker (`www-data`, gid 82). Also create `public/tmp`; otherwise Blade view compilation fails with `tempnam(): file created in the system's temporary directory` and the first request returns HTTP 500.
- A `cheaters` row, a `comments` row on an existing torrent, an `offers` row, and a pending user (`status='pending'`) are useful for exercising `cheaterbox.php`, `report.php`, `makepoll.php`/`polloverview.php`, and `modtask.php confirmuser`.
- The `bans.php` form can be exercised via `curl` because the native submit button may not register under automation.
- `/modtask.php` `edituser` requires the same fields as the `userdetails.php` edit form, including `email`, `username`, `title`, `avatar`, `signature`, `privacy`, `donor`, `uploadpos`, `downloadpos`, `forumpost`, etc. `cruprfmanage` permission means `email` and `username` cannot be omitted or they will be blanked.
- `/deletemessage.php` originally compared `messages.location` (a `smallint`) to the strings `'in'`, `'out'`, and `'both'`, which never matched. PR #27 fixed this by using the numeric `PM_DELETED`/`saved` semantics from `messages/_messages_legacy.php` and ensuring `lang_deletemessage` is loaded.

## Testing PR #231 unified `nexus.php` dispatcher (public wrapper consolidation)

PR #231 replaces per-page dispatching in `public/*.php` with `require __DIR__ . '/nexus.php';` and a single `Request::create()` call inside `public/nexus.php`.

### What to verify

- `php -l public/nexus.php` and representative wrappers (`index.php`, `torrents.php`, `details.php`, `comment.php`, `takelogin.php`, `takesignup.php`, `takeupload.php`, `confirmemail.php`, `forums.php`, `userdetails.php`).
- `./vendor/bin/phpstan analyse` and `php artisan test --testsuite Unit/Feature` pass.
- `$_GET`/`$_POST`/`$_FILES`/`$_SERVER`/`$_COOKIE` are copied into the Laravel `Request` correctly; special wrappers rewrite `$_GET`/`$nexusRoute` before `nexus.php`.
- Clean URLs without `.php` fall back to `nexus.php` and redirect to the corresponding `.php` wrapper, e.g. `/torrents` -> `302` to `/torrents.php?` and `/details/2` -> `302` to `/details.php?id=2`.
- Special routing wrappers:
  - `details.php` sets `$nexusRoute = '/details/' . $id` and unsets `$_GET['id']`.
  - `comment.php` maps `action=add|edit|delete|vieworiginal` to the Laravel `/comment/*` routes.
  - `takelogin.php` and `takesignup.php` set `$nexusRoute` to `/login` and `/signup`.
- Tracker endpoints: `announce.php` and `scrape.php` define `IN_NEXUS` and still return `200` bencoded responses.
- PATH_INFO: `confirmemail.php/<id>/<32-md5>/<email>` should reach the legacy `confirmemail` partial and return a legacy `<h1>Not Found</h1>` from `httperr()` when the hash does not match.
- POST wrappers (`takesignup.php`, `takeupload.php`, `comment.php`, `takemessage.php`, `takestaffmess.php`, `deletemessage.php`) should produce validation/error pages or redirects, not 405/500.
- Admin/utility wrappers (`catmanage.php`, `forummanage.php`, `moforums.php`, `fields.php`, `formats.php`, `videoformats.php`, `faqactions.php`, `faqmanage.php`) render forms/tables.
- Critical path pages (`index.php`, `torrents.php`, `details.php`, `userdetails.php`, `forums.php`, `offers.php`, `mybonus.php`, `bitbucket-upload.php`, `topten.php`, `log.php`, `staffpanel.php`) render 200.

### Common gotchas

- `takelogin.php` and `takesignup.php` need the CSRF token extracted from the **unauthenticated** form page; fetching the token while logged in may return an empty token because `/login` and `/signup` redirect authenticated users to `index.php`.
- `takeupload.php` requires a valid generated `.torrent` and the `_token`; an empty title should redirect to `/error?error=The+title+cannot+be+empty`.
- `comment.php` POST `action=add` needs `pid=<torrentId>&type=torrent` and `body`; success redirects to `details.php?id=<pid>#<newId>`.
- `comment.php` `action=delete&cid=<id>&type=torrent&sure=1` should redirect to the parent details page.
- `mailtest.php` POST form fields are `action=sendmail` and `email=<address>`; with `smtp.smtptype=none` the expected result is `Unable to send mail. (SMTP disabled or mail not sent)`.
- For `announce.php` and `scrape.php`, build the query with `rawurlencode()` on the raw 20-byte `info_hash` and `peer_id` and use a `User-Agent` from the allow list (`qBittorrent/4.0.0`).

## Testing PR #219 remaining public/*.php migrations

### Pages and expected behavior

- `/adduser.php` GET renders a user-creation form; POST with alphanumeric username (3-20 chars) creates the user and 302s to `userdetails.php?id=<new>`.
- `/bitbucketlog.php` lists uploaded avatar images with `[Delete]` links.
- `/complains.php` is intended to be reachable by both anonymous users (file a complaint) and logged-in admins (view/reply). In the migrated route it is placed inside the `auth.nexus:nexus-web` group, while the partial's `cur_user_check()` aborts for logged-in users, so the page is unusable without a route/middleware change.
- `/confirmemail.php/<id>/<md5>/<email>` relies on `$_SERVER['PATH_INFO']` matching `:/(\d{1,10})/([\w]{32})/(.+):`. The openresty `try_files` does not preserve `PATH_INFO` for these URLs; the request 404s. Needs an explicit rewrite/location or `$_GET` fallback.
- `/cron.php` returns plain text; when `useCronTriggerCleanUp` is true and `autoclean()` does not run, it prints `Clean-up not triggered.`
- `/delete.php?id=<torrentid>` (POST `id`, `reasontype`) should delete the torrent and print `Torrent deleted`. If the page is blank, check that `mkglobal("id")` was followed by `global $id;` so `intval($id)` sees the parsed value.
- `/downloadnotice.php?torrentid=<id>` renders a first-time notice; submitting the form 302s to `download.php?id=<id>&letdown=1`.
- `/email-gateway.php` is intentionally empty (the partial calls `exit(0);`).
- `/increment-bulk.php` GET renders a batch bonus/invite/upload form; `take-increment-bulk.php` POST redirects to `increment-bulk.php?sent=1&type=<type>`.
- `/maxlogin.php` lists `loginattempts`; `?action=ban|unban|delete&id=<row>` mutates the table.
- `/ok.php?type=<type>` renders status messages (`signup`, `sysop`, `confirmed`, `adminactivate`). A blank page means `mkglobal("type")` populated `$GLOBALS['type']` but the local `$type` variable was not declared `global` after `extract($GLOBALS, EXTR_SKIP)`.
- `/setlist_lookup.php?name=...` returns `Content-Type: application/json; charset=utf-8` with `success`, `data`, and `text`.
- `/testip.php` accepts `ip` by GET/POST and prints whether it is banned based on `bans`.
- `/thanks.php` POST `id=<torrentid>` inserts a `thanks` row and awards bonus points; returns empty body on success.

### Common Blade-wrap regression (`mkglobal` / `extract`)

Any partial that starts with `extract($GLOBALS, EXTR_SKIP);` and then calls `mkglobal("foo")` will set `$GLOBALS['foo']` but **not** create the local variable `$foo` (because `EXTR_SKIP` refuses to overwrite the already-extracted copy of `$GLOBALS`? actually because `mkglobal` only updates `$GLOBALS` and PHP variable scope does not auto-bind globals in function-less include). The fix is to add `global $foo;` immediately after `mkglobal("foo")` in the partial, or replace the `mkglobal` call with direct `$_GET`/`$_POST` access. PR #219 hit this in `ok`, `delete`, and several other legacy pages.

### Confirm hash for `confirmemail.php`

- Use the user's `editsecret` padded to 20 chars: `$secret = str_pad($editsecret, 20)`; `$md5 = md5($secret . $email . $secret);`.
- The URL format is `/confirmemail.php/<id>/<md5>/<email>` (or `/confirmemail/<id>/<md5>/<email>` once routing is fixed).

### Testing `takesignup.php` / `signup.php`

- The form at `/signup.php` uses `crypto-js.js`, but the inline script calls `sha256(password)` while `crypto-js.js` only exposes `CryptoJS.SHA256`. The normal "Sign up!" button therefore fails to populate the hidden `wantpassword` and `wantpassword_hashed` fields and the server returns `Don't leave any fields blank.`.
- To test the `/signup` POST route from the browser console, set the hidden `wantpassword` to `CryptoJS.SHA256('password').toString()`, `wantpassword_hashed` to `1`, and `passagain` to the same hash, then submit the form.
- `takesignup.php` sets `$nexusRoute = '/signup'` and `require __DIR__ . '/nexus.php'`, so POST/GET to `takesignup.php` should reach the same Laravel route as `/signup`.
- Direct `curl` POSTs to `takelogin.php` and `takesignup.php` tend to return 419 because curl's cookie handling does not play well with Laravel's encrypted session/XSRF cookies; prefer a real browser session for these endpoints.

## Testing PR #232 view-context and signup/login hashing

PR #232 injects a filtered `$context` into every Blade view (`View::composer('*')`) and replaces the remaining `extract($GLOBALS, EXTR_SKIP)` / `mkglobal()` calls in `delete`/`fastdelete`/`ok` partials with `SupportContext::getRequestInput()`.

### What to verify

- `grep` shows 215 `extract($context, EXTR_SKIP)` in `resources/views` and 0 `extract($GLOBALS` / `mkglobal(` in views.
- `php -l` on changed files; `phpstan analyse`; `php artisan cache:clear config:clear view:clear route:clear`; `redis-cli FLUSHDB`.
- Signup (`/signup.php`): the auth layout loads jQuery, layer, and `js/crypto-js.js`; `Form::passwordHashJs` hashes with `CryptoJS.SHA256(password).toString()`; hidden `wantpassword`/`wantpassword_hashed` are appended; submit reaches `ok.php?type=confirm`.
- Login (`/login.php`): direct password form posts to `takelogin.php` and lands on `index.php`; only uses challenge-response when `security.use_challenge_response_authentication=yes`.
- Critical path: `index`, `torrents.php`, `details.php?id=2`, `download.php?id=2&letdown=1` (bencode), `forums.php`, `offers.php`, `userdetails.php?id=1`, `logout.php`.
- Legacy `public/nexus.php` pages: `/ajax.php`, `/latestcomments.php`, `/shoutbox_sse.php`, `/getattachment.php`, `/attachment.php`, `/image.php`.
- `delete.php` (POST `reasontype` from `edit.php` delete form) and `fastdelete.php?id=<id>&sure=1` remove the torrent and print `Torrent deleted!`.
- `ok.php?type=signup|confirmed|confirm` renders the legacy status messages.
- POST wrappers: `takemessage.php` (success message + new `messages` row), `takeupdate.php` (mark report `dealtwith`), `takeinvite.php` (redirects to `invite.php?id=<uid>&sent=1`; actual insert requires working mail).
- Confirm no `extract(` or `mkglobal` warnings in `docker logs nexusphp-php` during the run.

### Common gotchas

- `/signup.php` only works if `auth.blade.php` includes jQuery and layer; without them the `Sign up!` button does nothing because the inline handler uses `jQuery`/`layer`.
- `/image.php?action=regimage` may return 404 when `security.iv=no` or the configured captcha driver does not implement `outputImage()`; this is environment/config, not necessarily a PR regression.
- `takeinvite.php` redirects with `sent=1` even when `Mail::sent` cannot send because `smtp.smtptype=none`; the insert is gated on `sent_mail()` returning `true`.

## Testing PR #52/53 drain-blade-globals

PR #52/53 removes `include/bittorrent.php` and `include/cleanup_cli.php`, loads `include/core.php` from `artisan`, refactors `CleanupRun` to call `CleanupService::runFull()` directly, drains raw `$_GET`/`$_POST`/`$_SERVER`/`$GLOBALS` usage from ~115 legacy Blade/PHP partials, and routes `public/announce.php` and `public/scrape.php` through `public/nexus.php`.

### What to verify

- `docker compose exec php php artisan cleanup:run --force` exits 0 and prints `Full cleanup is done` + `[CLEANUP_RUN] DONE, cost time: <n> seconds`.
- `include/bittorrent.php` and `include/cleanup_cli.php` do not exist; `artisan` requires `include/core.php`.
- `php -l` on changed files; `grep -R` in `resources/views` for `$_GET|$_POST|$_REQUEST|$_SERVER|$_FILES|$_COOKIE` returns nothing; `extract($context, EXTR_SKIP)` is present.
- `/announce.php` and `/scrape.php` return valid bencode (use a valid `-qB4...` peer_id and 20-byte `info_hash`).
- Signup from `/signup.php` creates a user and lands on `ok.php?type=confirm`; the new user can log in and visit `userdetails.php` / `usercp.php`.
- Critical path (`index`, `torrents`, `details`, `download`, `forums`, `offers`, `userdetails`, `login/logout`) renders without 500.
- SupportContext-heavy pages: `usersearch`, `usercp`, `messages`, `modtask`, `forum`, `offers`, `getrss`, `torrentrss`, `invite`, `bitbucket-upload`, `settings`, `freeleech`, `magic`, `medal`, `task`, `bonus-log`, `uploaders`.
- POST wrappers: `takemessage`, `sendmessage`, `takeinvite`, `modrules`, `moforums`, `deletemessage`, `delete` / `fastdelete`.

### Common gotchas

- After the superglobal draining, search `resources/views/**` for `SupportContext::get*(bare_word)` (e.g. `getQuery(action)` or `getPost(returnto)`) and fix missing string quotes. `/complains.php` and `/forums.php` POST actions (`setlocked`, `hltopic`, `setsticky`) will throw `Undefined constant "..."` if left unquoted.
- Use `http://localhost` (not `http://openresty`) for browser/curl tests when `basic.BASEURL` is set to `localhost`; otherwise redirects and `c_secure_pass` cookies will not match.
- A `c_secure_pass` cookie generated on `localhost` cannot be reused on `openresty`. Generate tokens with `App\Support\AuthCookie::buildToken()` inside the `php` container for curl scripts.
- `takeinvite.php` needs working mail to persist an invite; use `invite.php?id=<uid>` for UI verification and expect `sent=1` redirect.
- `download.php?id=<id>&letdown=1` should return `application/x-bittorrent` and a bencode payload.
- `comment.php` should be POSTed as a normal form (`curl -d ...`), not with `curl -X POST -L`, so the 302 redirect is followed with GET.

## Testing PR #283-#286 combined helper migration (`devin/phase7-5-6-helpers`)

Branch `devin/phase7-5-6-helpers` contains `origin/php8` + PR #283 (Phase 5.2 typed `SiteConfig`), #284 (Phase 7.1), #285 (Phase 7.2-7.4), and #286 (Phase 7.5-7.6 helper migration). A full re-test of this branch exercises all four PRs.

### What to verify

- Lint/static gates: `composer validate --strict`, `php -l` on `git diff --name-only origin/php8...HEAD -- '*.php'`, `php artisan view:cache`, PHPStan default/level5/level5.app/level6 clean.
- Unit/feature suites: `phpunit --testsuite Unit` and `tests/Feature/CriticalPathTest.php` with `-d memory_limit=1G`; note PHPUnit deprecation notices are not test failures.
- `php artisan meilisearch:import` imports torrents; `curl -s 'http://localhost:7700/indexes/torrents/stats'` returns `numberOfDocuments >= 1`.
- `/edit.php?id=<torrent>` must load without `TypeError` when `pos_state_until`/`pick_until` are `null`; `Form::datetimepickerInput()` now accepts `?string`.
- `/takeedit.php` accepts `pos_state=normal` + empty deadline and `pos_state=sticky` + future `pos_state_until`, redirecting to `details.php?id=<id>&edited=1`.
- Re-open `/edit.php` and confirm the selected promotion and deadline are persisted.
- UI helper smoke: `/upload.php`, `/torrents.php` (search + category/promotion filters), `/details.php`, `/usercp.php`, `/settings.php` (Authority/Torrent Settings), `/userdetails.php`, `/messages.php`, `/index.php`, `/downloadnotice.php`, `/download.php`, `/forums.php`, `/offers.php`, `/topten.php`, `/log.php`, `/latestcomments.php`, `/faq.php`, `/rules.php`, `/contactstaff.php`, `/staffpanel.php`, `/mybonus.php`.
- Tracker endpoints: `/announce.php` rejects invalid passkey/info_hash/peer_id with bencoded failure reasons; valid request returns `interval`/`peers` (a `warning message` for frequent requests is expected); `/scrape.php` returns bencode `files` dict (the key is raw 20-byte `info_hash`, so Python `bencode` may need raw-byte key handling).
- `c_secure_pass` cookie for curl scripts can be generated from inside the `php` container with `App\Support\AuthCookie::buildToken()`; set `APP_KEY` explicitly because the standalone script does not boot the Laravel container:
  ```bash
  APP_KEY='base64:...' docker compose exec -T -e APP_KEY="$APP_KEY" php php /var/www/html/build_cookie.php
  ```
- Do not double-encode `info_hash`/`peer_id` in announce URLs; build the query string manually with `urllib.parse.quote(raw_bytes)` rather than passing raw bytes through `requests` params.

### Common gotchas

- `CriticalPathTest` leaves `basic.BASEURL` set to `openresty`; restore it to `localhost` and flush caches before host-side browser/curl tests:
  ```sql
  UPDATE settings SET value='localhost' WHERE name='basic.BASEURL';
  ```
  Then run `php artisan config:clear view:clear route:clear cache:clear`.
- The `php` container has no `bash`; use `sh -c` for inline environment variables.
- Browser file inputs are not reliably drivable; submit `/takeupload.php` and `/takeedit.php` via an authenticated curl/Python session and use the browser to verify the resulting pages.
- `.git` is not mounted inside the `php` container, so `php -l` on changed files must be run from the host (`git diff origin/php8...HEAD -- '*.php' | xargs -P4 -n1 docker compose exec -T php php -l`).
- `downloadnotice.php` may only appear for a normal user's first authenticated download; after that `download.php?id=<id>&letdown=1` returns the `.torrent` directly.

## Testing PR #299 / Phase 11 converted view consolidation

### Scope

PR #299 converts the remaining `resources/views/**/_*_legacy.php` partials to `*.blade.php` and updates `UtilityController` (ajax) and `ShoutboxController` (SSE). All `take*`, staff, utility, and converted public pages should render without `Cannot redeclare` worker fatals or unescaped-HTML regressions.

### What to verify

- Static gates: `composer validate --strict`, `php -l` on changed PHP/Blade files, `php artisan view:cache`, PHPStan default/level5/level5.app/level6 clean.
- Unit/feature suites: `phpunit --testsuite Unit` and `CriticalPathTest` with `-d memory_limit=1G`; restore `basic.BASEURL` to `localhost` and clear caches afterwards.
- `php artisan meilisearch:stats` shows the expected document count; new uploads are searchable.
- `/upload.php` renders with file/name/desc/category/taxonomy/Pick fields; `/takeupload.php` accepts a generated `.torrent` and 302s to `details.php?id=<id>&uploaded=1` (new) or `details.php?id=<id>&existed=1` (duplicate).
- `/edit.php?id=<torrent>` loads with promotion, `pos_state`, and `pos_state_until` fields; `/takeedit.php` persists changes and redirects to `details.php?id=<id>&edited=1`.
- `/torrent.php?id=<id>` alias renders the same content as `/details.php?id=<id>`.
- `/torrents.php?search=<name>` and `/torrents.php?cat401=1&spstate=5` return expected results.
- `/announce.php` and `/scrape.php` return valid bencode; 19-byte `info_hash`/`peer_id` return bencoded failure reasons.
- `/shoutbox_sse.php` streams `text/event-stream` `event: ping` messages.
- `/ajax.php` actions return JSON without PHP worker fatals; `clearShoutBox` should load the `User` model from `SupportContext` and pass it to `Permission::can` to avoid relying on Laravel's `Auth::user()` in legacy AJAX paths.
- `/messages.php` (inbox) should render: `messagemenu()` and `insertJumpTo()` must be defined before they are called in `resources/views/messages/_messages.blade.php`.
- `/delete.php?id=<torrent>`, `/takeinvite.php`, `/checkuser.php` no longer call a missing `bark()` helper; use `\App\Support\LegacyResponse::abort($title, $msg)` instead.
- `/takeconfirm.php` loads `lang/en/lang_takeconfirm.php` which references `$SITENAME`/`$REPORTMAIL`; ensure `LegacyRequestMiddleware` sets these as local variables before requiring language files.
- `/takereseed.php?id=<torrent>` falls back to `id` when `reseedid` is absent and guards against `null` torrent.
- `/ajax.php?action=saveUserMedal` handles string-encoded `params` and validates each entry before indexing.

### Test data / helpers

- Generate a fresh `.torrent` with `bencodepy` and `announce=http://localhost/announce.php` for a clean upload test.
- Use `xdotool` + `scrot` to drive the visible Chrome window and capture named screenshots without the huge HTML dumps from the `computer` screenshot tool:
  ```bash
  WID=$(xdotool search --onlyvisible --name 'NexusPHP')
  xdotool windowactivate $WID
  xdotool key ctrl+l
  xdotool type --delay 10 'http://localhost/<page>.php'
  xdotool key Return
  sleep 3
  scrot -u /home/ubuntu/screenshots/ss_<page>.png
  ```

### PR #299 re-run notes

- The six targeted regressions from the first E2E run (`/messages.php`, `/delete.php?id=<torrent>`, `/takeconfirm.php`, `/takereseed.php?id=<torrent>`, `/ajax.php?action=saveUserMedal`, `/ajax.php?action=clearShoutBox`) were fixed by commit `e7daa1fd`.
- `/thanks.php` is referenced by `public/js/common.js` (`ajax.post('thanks.php', ...)`); add `Route::match(['get', 'post'], '/thanks', [TorrentActionController::class, 'thanks'])->name('thanks.legacy')` to `routes/legacy/auth.php` to make the converted `resources/views/thanks` partial reachable.
- `/page.php` is a dynamic loader and requires a `view` query parameter; the converted `resources/views/page/_page.blade.php` now returns a 400 response when `view` is missing instead of throwing a 500 `RuntimeException`.
- `/image.php` only works with `?action=regimage&imagehash=<valid>`; without params it returns 404 (expected captcha behavior when the image captcha is disabled).

### Phase 12 combined re-run notes

- After the MeiliSearch `visible`/`banned`/`anonymous` yes/no cast fix and the `torrent/_edit` hidden-input fix, keyword search (`/torrents.php?search=<name>`), category/promotion filters, and torrent editing all work for PRs #299-#302.
- `/modtask.php` is a POST-action processor, not a browseable staff page. A GET request without `action` falls through to `puke()` and displays "Permission denied. For security reason, we logged this action" even when the `Permission::can(MANAGE_USER_BASIC_INFO)` check succeeds. To verify it, POST `action=edituser` or `action=confirmuser` with all required fields from `userdetails.php`.
- `/attachments.php` is not a registered route in the combined branch; the route appears to be handled through `attachments/` or not exposed as a public `.php` page, so a 404 there is expected.
- `php -l` on changed files must run from the host side because `.git` is not mounted into the `php` container. Use `git diff --name-only origin/php8...HEAD -- '*.php' | sed 's|^|/var/www/html/|' | xargs -P4 -n1 docker compose exec -T php php -l`.
- `CriticalPathTest` sets `basic.BASEURL='openresty'` and must be followed by a restore to `localhost` plus Redis/Laravel cache clears before host-side browser/curl tests.
- After clearing caches, run `docker compose exec -T php php artisan view:cache` and `docker compose exec -T php php artisan route:cache` so legacy pages do not recompile on every request.

## Testing PR #308 / Phase 14 final-polish (`devin/phase14-final-polish`)

PR #308 removes `app/Support/Legacy/functions.php` and inlines helpers into typed `\App\Support\*` static methods.

### What to verify

- `composer validate --strict`, `php -l` on changed files, `php artisan view:cache`, `php artisan route:cache`, PHPStan default + level6.
- `phpunit --testsuite Unit` (bump memory to `-d memory_limit=1G`) and `tests/Feature/CriticalPathTest.php` with `CRITICAL_PATH_BASE_URL=http://openresty`.
- `/torrentrss.php?passkey=<passkey>` returns valid RSS when a torrent has a `null` `descr` (the fix coerces `$row['descr']` to `(string)` in the view).
- `/staff.php` and `/users.php` for a normal user return a graceful legacy `Permission denied!` page via `app/Exceptions/Handler.php`.
- `/staff.php` and `/users.php` for `sysop` still render the staff/user list.
- Legacy public pages (`forums`, `messages`, `usercp`, `mybonus`, `donate`, `shoutbox`, `shoutbox_history`, `opensearch`) and staff pages (`settings`, `staffpanel`, `staffmess`, `staffbox`, `topten`, `catmanage`, `forummanage`) return `200`.
- API smoke for `normaluser`, `sysop`, and a freshly factory-created user if relevant.

### Common gotchas

- The `InsufficientPermissionException` thrown inside Blade partials is wrapped in nested `Illuminate\View\ViewException` objects; the exception handler must recursively unwrap `ViewException::getPrevious()` and catch `HttpResponseException` from `LegacyResponse::permissionDenied()`.
- After `CriticalPathTest`, restore `basic.BASEURL='localhost'` and clear the Redis keys `nexus_settings_in_nexus` and `nexus_settings_in_laravel` plus Laravel caches before host-side tests.
- The Unit suite may need `php -d memory_limit=1G` to avoid exhausting the default 128 M limit in `RouteServiceProvider`.
- `php -l` on changed files must run from the host because `.git` is not mounted in the `php` container:
  ```bash
  git diff --diff-filter=ACMR --name-only origin/php8...HEAD -- '*.php' '*.blade.php' | sed 's|^|/var/www/html/|' | xargs -P4 -n1 docker compose exec -T php php -l
  ```

## Testing Phase 17 full DB-in-views bridge (PR #320)

Phase 17 migrates the remaining public/admin/listing Blade/PHP views into `resources/legacy/*.php` partials rendered through `App\Repositories\LegacyViewRepository` and routes in `routes/legacy/{public,auth}.php`. The four key runtime fixes are:

1. `App\Auth\Permission::user()` resolves the `nexus-web` guard first, falls back to the default guard, and returns `?User` so legacy cookie-auth routes do not throw `Return value must be of type App\Models\User, null returned`.
2. `.docker/openresty/sites/app.conf.template` removes `$uri/` from `location / try_files`, so the `/torrents` route is not shadowed by the `public/torrents/` directory.
3. `lang/en/lang_userhistory.php` adds the missing `head_user_history` key.
4. `TorrentActionController::downloadnotice` handles POST, writes the `showdlnotice` preference, and redirects to `/download?id=<id>&letdown=1`; `Path::resolve` resolves relative paths against `ROOT_PATH` so the torrent file is served with `Content-Type: application/x-bittorrent`.

### Environment / fixtures

- The `php` container cannot run `git diff` in a worktree, so `php -l` on changed files must be driven from the host:
  ```bash
  git diff --diff-filter=ACMR --name-only origin/php8..HEAD -- '*.php' '*.blade.php' \
    | sed 's|^|/var/www/html/|' \
    | xargs -P4 -n1 docker compose -p tracker-lp-bits exec -T php php -l
  ```
- Many Phase 17 golden paths reference `torrents.id=3` and `users.id=10322` (which does not exist). Create a test torrent fixture:
  ```bash
  cp /home/ubuntu/repos/phase17i-worktree2/torrents/39.torrent \
     /home/ubuntu/repos/phase17i-worktree2/torrents/3.torrent
  ```
  Then in MySQL:
  ```sql
  INSERT INTO torrents (id, info_hash, name, filename, save_as, owner, category,
    source, medium, codec, standard, processing, audiocodec, size, added, type,
    numfiles, sp_state, visible, banned, approval_status, anonymous, url,
    pos_state, cache_stamp, hr, price, pieces_hash)
  VALUES (3, NULL, 'Phase17 Fixture 3', 'phase17_fixture3.torrent',
    'Phase17 Fixture 3', 1, 401, 0, 0, 0, 0, 0, 0, 1234, NOW(), 'single', 1,
    1, 'yes', 'no', 1, 'no', NULL, 'normal', 0, 0, 0, '')
  ON DUPLICATE KEY UPDATE name=VALUES(name), filename=VALUES(filename),
    save_as=VALUES(save_as), owner=VALUES(owner), category=VALUES(category);

  INSERT INTO torrent_extras (torrent_id, descr) VALUES (3, '')
  ON DUPLICATE KEY UPDATE descr=VALUES(descr);
  ```
  For regression tests, insert a `torrent_extras` row with `descr=''` for the fixture torrent so the edit form renders a pre-filled description. The view now casts missing/null `descr` and `technical_info` to strings, so a missing row no longer causes a `TypeError`.
- Generate a fresh sysop cookie for curl/Puppeteer:
  ```bash
  APP_KEY='base64:WUbN2wa2kl3E1VDW4iKaH3RBHw3hKY7BK0hWEkBZmGg='
  docker compose -p tracker-lp-bits exec -T -e APP_KEY="$APP_KEY" php php -r \
    'require "/var/www/html/vendor/autoload.php"; require "/var/www/html/bootstrap/app.php"; echo \App\Support\AuthCookie::buildToken(1, null, time()+3600);' \
    > /home/ubuntu/phase17-cookie.txt
  ```
- `basic.BASEURL` should match the test host. For host-side tests against `http://localhost`:
  ```sql
  UPDATE settings SET value='localhost' WHERE name='basic.BASEURL';
  ```
  Then clear Laravel/Redis caches and rebuild view/route caches.

### Golden-path smoke list

- **Phase 17a**: `/aboutnexus.php`, `/faq.php`, `/rules.php`, `/donate.php`.
- **17b**: `/topten.php?type=1&subtype=0&lim=10`.
- **17c**: `/stats.php`, `/allagents.php`, `/mysql_stats.php`, `/viewfilelist.php?id=3`, `/viewsnatches.php?id=3`, `/searchsuggest.php?q=test`, `/autocomplete_torrents.php?q=test`, `/nowarn.php`.
- **17d**: `/viewpeerlist.php?id=3`, `/getusertorrentlistajax.php?userid=1&type=uploaded|seeding|leeching|completed|incomplete` (use `userid=1` because `10322` does not exist).
- **17e**: `/search.php`.
- **17f**: `/details.php?id=3`, `/edit.php?id=3`, `/upload.php`, `/torrent_info.php?id=3`.
- **17i**: `/staffpanel.php`, `/reports.php`, `/bans.php`, `/cheaterbox.php`, `/iphistory.php`, `/catmanage.php?action=view&type=searchbox|category|source|secondicon`, `/forummanage.php`, `/settings.php`, `/modtask.php`.
- **17j/k/l**: `/downloadnotice.php?torrentid=1&type=firsttime` (GET form, then `POST` with `id=1&type=firsttime&hidenotice=1`), `/download?id=1&letdown=1` and `/download?id=3&letdown=1` (should return `application/x-bittorrent`), `/usercp.php`, `/userdetails.php?id=1`, `/myhr.php`, `/warned.php`, `/user-ban-log.php`.
- **Regression**: `/login.php`, `/signup.php`, `/index.php`, `/torrents.php`, `/torrents`, `/staffpanel.php`.
- **Previously failing routes**: `/userhistory.php?id=1`, `/viewpeerlist.php?id=1`.

### Pass criteria

- All GET/POST routes return HTTP `200` (or `302` for explicit redirects) with no `Whoops`, `Fatal error`, `Parse error`, `Server Error`, `Stack trace`, `Internal Server Error`, or `Return value must be of type` text.
- `/download` and the redirect from `POST /downloadnotice` return `Content-Type: application/x-bittorrent` with a non-empty bencode body.
- `/searchsuggest.php?q=test` returns a JSON array (`["test",[],[]]`); `/autocomplete_torrents.php?q=test` returns a JSON object (`{"torrents":[]}`).
- `/viewfilelist.php?id=3` and `/getusertorrentlistajax.php?userid=1&type=uploaded` return HTML containing a `<table>` or `text_no_record`.
- After browsing, `php artisan view:cache`, `php artisan route:cache`, and `openresty -t` all pass.

### Common gotchas

- `/edit.php?id=<torrent>` needs a matching `torrents` row. A `torrent_extras` row with a non-null `descr` is needed only to pre-fill the description; the view now casts missing/null `descr` and `technical_info` to strings, so `Form::bbcodeEditor()` no longer throws a `TypeError`.
- `LegacyRequestMiddleware` rewrites `.php` URLs (`/details.php?id=3` → `/details/3`, `/viewfilelist.php?id=3` → `/viewfilelist?id=3`) before routing, so no per-page `public/*.php` wrappers are needed for these paths.
- `basic.BASEURL` may be reset to `openresty` after `CriticalPathTest` or `cleanup:run`. Re-set it to `localhost` and clear caches for host-side tests; only the announce/comment URL inside the generated `.torrent` is affected, not the download itself.

## Testing PR #322-#337 (Phase 19 legacy layout migration)

### Scope

All active legacy PHP partials under `app/Services/Legacy/partials/` are now rendered through `resources/views/layouts/legacy.blade.php` with per-page `resources/views/<name>/index.blade.php` wrappers. Affected routes include `/index.php`, `/forums.php`, `/usercp.php`, `/messages.php`, `/sendmessage.php`, `/log.php`, `/news.php`, `/makepoll.php`, `/polloverview.php`, `/catmanage.php`, `/complains.php`, `/offers.php`, `/latestcomments.php`, `/shoutbox_history.php`, `/friends.php`, `/bitbucketlog.php`, `/downloadnotice.php`, `/donated.php`, `/clearcache.php`, `/invite.php`, `/mybonus.php`/`/my_bonus.php`, `/usersearch.php`, and the Filament `/nexusphp` admin panel.

### Environment setup

- Ensure the `nexusphp_php` / `nexusphp-openresty` containers mount `/home/ubuntu/repos/tracker-lp-bits` at `/var/www/html`.
- Set `basic.BASEURL` to `openresty` and clear Laravel/Redis caches.
- Generate a `c_secure_pass` cookie for `id=1` (sysop) using the known `APP_KEY`:
  ```bash
  docker compose -p tracker-lp-bits exec -T -e APP_KEY='base64:WUbN2wa2kl3E1VDW4iKaH3RBHw3hKY7BK0hWEkBZmGg=' php php -r \
    'require "/var/www/html/vendor/autoload.php"; require "/var/www/html/bootstrap/app.php"; echo \App\Support\AuthCookie::buildToken(1, null, time()+3600);' \
    > /home/ubuntu/phase19-cookie.txt
  ```
- Add a minimal forum fixture (`forums.id=179`, `topics.id=185`, `posts.id=139`) so `viewforum`/`viewtopic`/`newtopic` can be exercised.

### Automation notes

- Use Playwright over CDP (`http://localhost:29229`) or launch headful with the Devin Chrome binary at `/opt/.devin/playwright_browsers/chromium-1097/chrome-linux/chrome`.
- The legacy layout wrapper means every rendered page should have exactly one `<html>`, `<head>`, and `<body>` block. Check `page.content()` for `Internal Server Error`, `Fatal error`, `Whoops`, or `Page Expired`.
- Theme switching is exercised via `/usercp.php?action=tracker` by selecting `stylesheet=4` (Classic) or `stylesheet=6` (Dark Passion) and submitting the form; the redirect target is `/usercp.php?action=tracker&type=saved`.

### Known route/shim issues

- `donated.php` was originally missing `resources/views/donated/_donated.blade.php`; the fix is a one-line view that calls `\App\Repositories\LegacyViewRepository::render('donated', get_defined_vars())`.
- `makepoll.php` had `<table><form>` HTML nesting; the browser ejected the `<form>` before its `<input>` elements, preventing submission. The fix is to wrap the `<table>` inside the `<form>`.
- `/my_bonus.php` needs more than a Laravel route alias. `LegacyRequestMiddleware::EXTRA_LANG_FILES` maps the detected `SCRIPT_NAME` to language files. Because `/my_bonus.php` resolves to script `my_bonus` and there is no `lang_my_bonus.php`, `$lang_mybonus` strings are empty. Add `'my_bonus' => ['mybonus.php']` to `EXTRA_LANG_FILES` so the page renders identically to `/mybonus.php`.
- The Filament admin panel at `/nexusphp` must be excluded from legacy URL rewriting. If `LegacyRequestMiddleware` rewrites `/nexusphp` (or `/nexusphp/user/users`, `/livewire/update`, `/api/*`, `/horizon`) to a legacy `SCRIPT_NAME`, Filament returns a 404. Add a `LARAVEL_ONLY_PREFIXES` list (`api`, `livewire`, `filament`, `nexusphp`, `horizon`) and a `passthroughRequest()` path that sets `SCRIPT_NAME=/index.php` and drops `PATH_INFO`.

### Quick pass/fail gate

- All listed routes return HTTP `200` (or `302` for form save redirects) and no fatal text.
- `php artisan view:cache`, `php artisan route:cache`, and `openresty -t` succeed after browsing.
- Screenshot key pages in both desktop (1280x900) and mobile (375x667) viewports; legacy fixed-width themes may overflow horizontally on mobile but must not hide primary content.

## Testing PR #359-#362 (Phase 21 performance / profiling / queue)

### Scope

Phase 21 combines:

- `devin/phase21-perf` (#359, cache/eager-load)
- `devin/phase21-5-3-octane` (#360, MySQL read/write split + stateless `SupportContext`)
- `devin/phase21-4-jobs` (#361, Horizon queue jobs, `cleanup` container removed)
- `devin/phase21-6-profiling` (#362, `X-Queries-Count` header)

### Quick verification checklist

1. `docker compose -p tracker-lp-bits up -d --remove-orphans` must remove the `nexusphp-cleanup` container.
2. `docker exec -i nexusphp-queue pgrep -f horizon` must show `php artisan horizon` and worker processes.
3. Static gates: `composer validate --strict`, `php -l` (inside the `php` container on `/var/www/html/app` and `/var/www/html/routes`), `phpstan` default/level5.app/level6, `phpunit --testsuite Unit`, `php artisan view:cache`, `php artisan route:cache`, `openresty -t`.
4. `X-Queries-Count` header must be present on legacy pages and contain a non-negative integer. Verify with:
   ```bash
   curl -s -D - -o /dev/null -b "c_secure_pass=$(cat cookie.txt)" http://openresty/index.php
   curl -s -D - -o /dev/null -b "c_secure_pass=$(cat cookie.txt)" http://openresty/forums.php
   ```
5. Read/write split sticky behavior: create a temporary `public/rw_test.php` that boots Laravel, runs a `users` `UPDATE`, and compares `DB::connection('mysql')->getReadPdo()` with `getPdo()` in the same request. The two PDO objects must be identical after a write:
   ```php
   $conn = DB::connection('mysql');
   $conn->table('users')->where('id', $sysopId)->update(['last_access' => now()->toDateTimeString()]);
   echo json_encode(['read_is_write' => $conn->getReadPdo() === $conn->getPdo()]);
   ```
6. Stateless `SupportContext`: hit `/index.php` with a sysop cookie and then with a second-user cookie in sequence; each response must contain the matching username (`<b>devintest</b>` vs `<b>crit63522073</b>`) and no cross-request leakage.
7. DB mutations still work: forum new topic (`POST /forums.php action=post&type=new&id=179`), offer add/vote/delete (`/offers.php`), PM send/delete (`/takemessage.php`, `/deletemessage.php`), `usercp.php` personal/forum/tracker save forms, `catmanage.php?action=del`, and shoutbox post/delete.
8. API `POST /api/v1/usercp/settings` and `POST /api/v1/usercp/forum` with a Sanctum Bearer token must return `{"ret":0,...}`.
9. Queue jobs: `AttendanceJob`, `CleanupJob`, `HrCheckJob`, `SeedBonusJob`, `UpdateTorrentSeedersEtc`, `UpdateUserSeedingLeechingTime` should dispatch without throwing. A helper script using `dispatch_sync()` inside the `php` container is fine for E2E verification. Check `failed_jobs` is empty afterwards.
10. `php artisan cleanup:run --force` must complete and print `Full cleanup is done`.
11. Benchmark with `ab` (or `curl` loop) for `/index.php` and `/forums.php` using the sysop cookie; capture `Requests per second`, `Failed requests`, and `X-Queries-Count` values.

### Common gotchas

- `php -l` on the host will fail because `php` is not installed on the VM; run it inside `nexusphp-php` against `/var/www/html/app` and `/var/www/html/routes`.
- Temporary helper scripts that need to be hit from the browser (`rw_test.php`) must be placed under `public/`, not the repo root, because the web root is `/var/www/html/public`.
- `php artisan cleanup:run --force` may report 0 cost time if all cleanup classes ran recently; the important signal is `Full cleanup is done` and no exceptions.
- `ab` cannot pass cookie values containing `=` using `-C`; use `-H "Cookie: c_secure_pass=<token>"` instead.
- `composer validate` must be run from `/var/www/html` inside the `php` container.

## Testing Phase 20c/d/e API parity

Use these notes when verifying the new Sanctum API endpoints for `usercp`, `messages`, `topics`, and nested `posts`.

### Creating an API test user

If the sysop account's password is unknown, generate a fresh factory user with `class = User::CLASS_SYSOP` and a Sanctum token with all abilities:

```php
docker exec -i nexusphp-php php artisan tinker --execute="
use App\\Models\\User;
\$u = User::factory()->admin()->create();
\$t = \$u->createToken('phase20-smoke', ['*'])->plainTextToken;
echo json_encode(['id' => \$u->id, 'username' => \$u->username, 'token' => \$t]);
"
```

The default factory password is `123456`, which validates against `WebAuthService::validatePassword()` because the factory `passhash` is created with the legacy MD5 format and `auth_key` is empty.

### API endpoint request shapes

Send mutation bodies as JSON (`Content-Type: application/json`) to avoid form-data parse issues on `PATCH` requests.

- `GET /api/v1/forums` — returns `{"ret":0,"data":{"data":[...]}}`. Check `X-Queries-Count`.
- `POST /api/v1/usercp/forum` — sets `topicsperpage` and `postsperpage`.
- `POST /api/v1/usercp/tracker` — sets `torrentsperpage`, `pmnum`, `sbnum`, `sbrefresh`, etc. It does **not** update `topicsperpage`/`postsperpage`.
- `POST /api/v1/usercp/security` — requires `current_password` and optionally `privacy`, `resetpasskey`, `new_password`.
- `POST /api/v1/messages` — create with `receiver`, `subject`, `msg`.
- `GET /api/v1/messages` — mailbox list (default `mailbox=0` corresponds to the inbox).
- `GET /api/v1/messages/{id}` — show and auto mark-as-read.
- `PATCH /api/v1/messages/{id}` — update `unread` (`yes`/`no`) or `location`.
- `GET /api/v1/messages-unread` — list unread messages.
- `DELETE /api/v1/messages/{id}` — delete for the authenticated user.
- `POST /api/v1/topics` — create topic in `forumid` with `subject`/`body`; response `firstPost` and `lastPost` should be equal.
- `GET /api/v1/topics/{topic}/posts` — list posts.
- `POST /api/v1/topics/{topic}/posts` — reply.
- `PATCH /api/v1/topics/{topic}/posts/{post}` — edit body.
- `DELETE /api/v1/topics/{topic}/posts/{post}` — delete reply.
- `DELETE /api/v1/topics/{topic}` — delete topic.

### Legacy flow equivalents

- `/usercp.php?action=personal|forum|tracker|security` still render and post to `/usercp.php` with `action=<tab>&type=save`.
- `/sendmessage.php?receiver=<id>` renders; `POST /takemessage.php` sends PM.
- `GET /deletemessage.php?id=<id>&type=in` deletes inbox PM.
- `/forums.php?action=newtopic&forumid=179` renders; `POST /forums.php action=post&id=179&type=new` creates a topic.
- `POST /forums.php action=post&id=<topicid>&type=reply` replies.
- `POST /forums.php action=post&id=<postid>&type=edit` edits.
- `GET /forums.php?action=deletepost&postid=<id>&sure=1` deletes a reply.
- `GET /forums.php?action=deletetopic&topicid=<id>&sure=1` deletes a topic.

### Devin Secrets Needed

- None beyond the existing test DB credentials and `APP_KEY` (already mounted in the Docker stack).

