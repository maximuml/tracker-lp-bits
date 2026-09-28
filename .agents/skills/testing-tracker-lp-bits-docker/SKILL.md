---
name: testing-tracker-lp-bits-docker
description: |
  How to bring up the tracker-lp-bits Docker Compose stack, prepare an admin
  test user, and verify MeiliSearch / legacy public-page flows end-to-end.
  Use whenever testing a Docker, MeiliSearch, or legacy PHP page change in
  maximuml/tracker-lp-bits (or the equivalent NexusPHP fork).
---

# Testing tracker-lp-bits with Docker Compose

## Devin Secrets Needed

None. The stack is fully local and the test admin user is created inside the
running `php` container.

## Quick start

```bash
cd /home/ubuntu/repos/tracker-lp-bits
cp .env.example .env
# Ensure these three values are set:
#   DB_HOST=mysql
#   REDIS_HOST=redis
#   MEILISEARCH_HOST=meilisearch
docker compose up -d --build
docker compose exec php composer install --no-interaction
docker compose exec php php artisan migrate:fresh --seed --force
docker compose exec php php artisan meilisearch:import
```

## Creating a test admin user

`DatabaseSeeder` does **not** create an admin. Use the built-in reset command
after migrations:

```bash
docker compose exec php php artisan user:reset_id_auto_increment \
  --auto_increment=10001 --admin=sysop --password=TestPass2026 --email=sysop@example.com
```

This truncates a large list of user-related tables and inserts a staff-leader
user with `id = 1` (the command forces `id` to `1`). It is fine for a fresh
migrated database.

## Disabling the image captcha for browser login

The legacy login page uses `security.iv` from `settings`. For browser-based
smoke tests, disable it:

```bash
docker exec -t nexusphp-mysql mysql -unexusphp -pnexusphp \
  -e "UPDATE settings SET value='no' WHERE name='security.iv';" nexusphp
docker exec nexusphp-redis redis-cli DEL nexus_settings_in_nexus nexus_settings_in_laravel
```

If `security.iv` is `yes`, `check_code()` rejects login attempts that do not
include the correct `imagehash` / `imagestring`.

## Browser smoke suite (primary verification)

`tests/browser/` is the blocking Playwright gate (ADR 0016) — run it before
hand-probing pages: real login/signup forms, authenticated page smoke,
forum navigation, escaped-markup detection, CSP + axe baselines, mobile
overflow. `tests/browser/README.md` documents the settings prerequisites
(`security.iv=no`, `security.maxip`, `basic.baseUrl` matching the browser
origin) and `BrowserSmokeSeeder` fixtures.

```bash
cd tests/browser && npm ci && npx playwright test
```

## Logging in

The main login form is a plain `POST /login` with `_token`, `username`,
`password` (plaintext), optional `two_step_code`, and — when `security.iv`
is enabled — `imagehash`/`imagestring`. Success returns `302` to
`index.php`/`/index` and sets the `c_secure_pass` cookie. There is no
challenge-response on this form: `data-auth-form="challenge"` (HMAC over
`/api/challenge`) exists only for the usercp security-confirmation form,
and `data-auth-form="hash"` only for `/signup` — see
`public/js/auth-form.js`.

```bash
# curl: shared cookie jar for the CSRF token + session
curl -sc /tmp/jar -b /tmp/jar http://localhost/login -o /tmp/login.html
TOKEN=$(grep -oP 'name="_token" value="\K[^"]+' /tmp/login.html | head -1)
curl -s -b /tmp/jar -c /tmp/jar -o /dev/null -w '%{http_code} %{redirect_url}' \
  -X POST http://localhost/login \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "username=sysop" \
  --data-urlencode "password=TestPass2026"
# expect: 302 http://localhost/index.php ; jar now holds c_secure_pass
```

Do **not** POST to `/login.php` — the legacy wrapper builds the Laravel
request without the POST body and the form returns `419`. Do not POST to
`/takelogin.php` either; it maps to `/login` but direct `/login` is the
canonical route.

`/login` is throttled (`throttle:login`, 10 req/min per IP) — repeated
logins in automation 429 quickly; log in once and reuse the cookie. If you
hit the failed-login ban, truncate `loginattempts`:

```bash
docker exec -t nexusphp-mysql mysql -unexusphp -pnexusphp \
  -e "TRUNCATE TABLE loginattempts;" nexusphp
```

## Key endpoints to smoke test

- `http://localhost/torrents.php` — legacy torrent list (SQL fallback when no
  search term is provided).
- `http://localhost/torrents.php?search=Linkin+Park` — MeiliSearch-backed
  search when `meilisearch.enabled` is `yes` and a search term is present.
- `http://localhost/login.php`, `/signup.php`, `/topten.php` — other legacy
  public pages.

## Proving MeiliSearch is used

Watch the `php` container logs after a search:

```bash
docker logs --since 1m nexusphp-php 2>&1 | grep "get client with url"
```

A working integration prints:

```text
get client with url: http://meilisearch:7700, master key:
```

## Common gotchas

1. **Stale settings cache.** Settings are cached in Redis under
   `nexus_settings_in_nexus` and `nexus_settings_in_laravel`. After manually
   editing the `settings` table, delete those keys or restart the `php`
   container.
2. **Signup button is JS-driven.** `#submit-btn` on `/signup` is
   `type=button`; `auth-form.js` (`data-auth-form="hash"`) fills the hidden
   `wantusername`/`wantpassword` fields on click. A plain form POST without
   those fields fails validation — click the real button. A `gender` radio
   is required even though the request marks it nullable.
3. **Search form submit.** The search keyword input is `name="search"` inside
   `form[name="searchbox"]`. Pressing `Enter` while the input is focused
   submits the form.
4. **`meilisearch:import` is idempotent.** It swaps a new `torrents_YYYYMMDD_HHMMSS`
   index into `torrents` when an existing `torrents` index is present.
5. **`basic.baseUrl` must match the browser origin.** `confirm.php`
   redirects to `basic.baseUrl` after `POST /signup`; Chromium applies CSP
   `form-action 'self'` to the redirect, so `baseUrl=localhost` while
   browsing via `127.0.0.1` silently aborts the confirmation navigation.
6. **`security.maxip` caps signups per IP** (seeded `2`). Docker-gateway
   test accounts trip it — raise it before exercising signup.
7. **Seeded data is reference-only.** `migrate:fresh --seed` creates no
   torrents and no forum topics; `php artisan db:seed
   --class=BrowserSmokeSeeder` adds the minimal fixtures the browser
   suite needs (idempotent).
8. **Toast notifications.** `toast.js` polls `ajax.php action=getToastNotifications`
   every 30s keyed on `localStorage.toast_last_pm_id_<uid>`. To capture a toast
   visually, send a PM from a second account and catch the ~6s window before
   auto-dismiss (a MutationObserver clone can pin it for screenshots).
9. **Chromeless pages follow `prefers-color-scheme`, not the theme toggle.**
   moresmilies.php and framework error pages have no site chrome — emulate dark
   via Playwright `colorScheme: 'dark'` to verify their dark rendering.
10. **Login form reliability.** GUI login-field coordinates drift between runs;
    Tab/Enter navigation from the focused field is more reliable than
    coordinate clicks.
11. **Announce testing must use the BASEURL-canonical host.** With
    `basic.baseUrl=localhost`, announcing to `http://127.0.0.1/announce.php`
    returns a 200 dict containing `"warning message: you should announce to: …"`
    and registers NOTHING — `ResponseBuilder::warn()` throws
    `TrackerWarningException`, so the pipeline aborts before peer insert (by
    design). Always curl announce to the host that matches BASEURL.
12. **Valid announce needs allowlisted client + matching UA.** A peer_id with a
    banned/unknown prefix gets a "Banned Client" failure; use a real client
    identity, e.g. peer_id `-TR4000-…` + `User-Agent: Transmission/4.0.0`.
    Port 6881 is in the seeded blacklist — announce on another port.
13. **Seed-bonus accrual runs via `php artisan cleanup:run`** (the batch job
    precomputes Redis data first). `cleanup --action=seed_bonus` alone fails
    with "no data from idStr". Probe one task: `php artisan cleanup:tasks
    <task>` (e.g. `prune-peers`).
14. **mybonus exchange via curl may 419 but still commit** — a stale `_token`
    produces 419 after the mutation runs. Prefer the in-browser exchange path
    for clean verification.
15. **Announce peer flow.** `peers.torrent` is the torrent-id column (not
    `torrent_id`). Recipe: GET
    `http://localhost/announce?passkey=..&info_hash=<raw-byte-%XX-encoded>&peer_id=-TR4000-<12>&port=51413&event=started`
    with `User-Agent: Transmission/4.0.0` registers a peer; `event=stopped`
    removes the row (allow a beat before asserting).
16. **usercp Security password fields are class-based** (`.password`/
    `.passagain`, no name attributes) and submit via a `type=button` JS
    handler — fill by class, click the form's button, expect the two-step
    confirm flow.
17. **Staff modcomments live in `user_modify_logs`** (user_id/content), not
    `users.modcomment`.
18. **`docker exec nexusphp-mysql mysql` default DB may be `nexusphp_testing`**
    — always qualify `nexusphp.<table>` or pass the db name explicitly.
19. **Bitbucket upload form is at `/bitbucket-upload.php`** (not
    bitbucket.php).
20. **Index sections are settings-gated** — `main.show_top_uploader`,
    `main.showlastxforumposts`, `main.showpolls`, etc. Toggle
    `settings.value` + `php artisan cache:clear` to verify hidden sections,
    then restore.
21. **Top-uploader tabs** — `.tr-top-uploader-tab [data-table]` cells toggle
    `.top-uploader` tables via `nx-hidden`; admin clear-shoutbox is
    `#clear-shout-box` → `layer.confirm` → `ajax.php action=clearShoutBox`
    (needs `SHOUT_CSRF` global).
22. **Signup password fields are class-based** (`.wantpassword`/`.passagain`)
    hashed into hidden inputs by JS — fill classes, not
    `input[name=wantpassword]`.
23. **Announce `peer_id` must be exactly 20 bytes** (`-TR4000-` + 12 chars);
    "already downloading the same torrent" means a stale peer row — use
    another user/torrent.
23. **Filament admin (/nexusphp):** log in via the APP `/login` first —
    `/nexusphp/login` renders but its session does not issue `c_secure_pass`,
    so the cookie guard still treats you as guest and bounces to `/login`.
    Resource URLs are nav-group namespaced: `nexusphp/user/users`,
    `nexusphp/system/settings` (singleton edit page — no `/1/edit` path),
    `nexusphp/torrent/torrents`, `nexusphp/section/*`, `nexusphp/security/*`.
    Full list: `php artisan route:list | grep nexusphp`.
24. **Shoutbox reactions:** rows expose `+` add button `[data-shout-picker=ID]`
    → opens `#shout-reaction-picker-ID` (options carry `data-close-picker`).
    Click posts `ajax.php?action=shoutboxReact` (id+reaction); applied badge
    is `.shout-reaction[data-shout-react=ID][data-emoji=X]` WITHOUT
    `data-close-picker` — include that exclusion when asserting "no badge"
    or you will match hidden picker options. Toggle deletes the
    `shoutbox_reactions` row; DOM badge may lag ~1s until poll refresh.
    History page = `/shoutbox_history.php`, filters `user/from/to/search`.
25. **Stale compiled blades → blanket 500s after a git checkout.** If the
    stack stays up across a `git checkout`/`merge` that adds or changes
    blades, authed pages can 500 with `touch(): Utime failed: Operation not
    permitted at BladeCompiler.php:215` (compiled views owned by another
    uid). Fix: `docker compose … exec -T php php artisan view:clear`.
26. **Theme runs:** `users.stylesheet` = row id in `stylesheets`
    (4=Classic, 5=Unshatter, …); guest default = `settings`
    `main.defstylesheet`. `users.theme` (light/dark/auto) picks the
    variant inside the stylesheet — check both. Verify theme actually
    applied via `<link href="styles/<Name>/theme.css">`, then eyeball
    contrast (computed body bg/fg luminance) + `scrollWidth` overflow.
    Restore both DB values and `cache:clear` afterwards.
