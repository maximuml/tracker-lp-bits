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

## Logging in

The legacy login form uses **challenge-response authentication** when
`use_challenge_response_authentication` is `yes`. The browser will compute the
`response` value with JS and submit normally. If you need to log in via `curl`,
compute it manually:

```python
import json, subprocess, os
resp = subprocess.run([
    'curl', '-s', '-X', 'POST', 'http://localhost/api/challenge',
    '-H', 'Content-Type: application/json',
    '-d', '{"username":"sysop"}'
], capture_output=True, text=True)
data = json.loads(resp.stdout)['data']
challenge, secret = data['challenge'], data['secret']
php = "echo hash_hmac('sha256', hash('sha256', getenv('SECRET') . hash('sha256', getenv('PASSWORD'))), getenv('CHALLENGE'));"
r = subprocess.run([
    'docker', 'exec', '-e', 'CHALLENGE='+challenge, '-e', 'SECRET='+secret,
    '-e', 'PASSWORD=TestPass2026', 'nexusphp-php', 'php', '-r', php
], capture_output=True, text=True)
response = r.stdout.strip()
# Then POST to /takelogin.php with username, password, and response.
```

If you hit the failed-login ban, truncate `loginattempts`:

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
2. **Login button is JS-driven.** The `#submit-btn` on `/login.php` is
   `type=button` and computes the challenge response. If a synthetic click does
   not fire, use the browser console to set the username/password and call
   `document.querySelector('#submit-btn').click()`.
3. **Search form submit.** The search keyword input is `name="search"` inside
   `form[name="searchbox"]`. Pressing `Enter` while the input is focused
   submits the form.
4. **`meilisearch:import` is idempotent.** It swaps a new `torrents_YYYYMMDD_HHMMSS`
   index into `torrents` when an existing `torrents` index is present.
