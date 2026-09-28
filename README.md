# NexusPHP

A private BitTorrent tracker solution built on **NexusPHP**, modernised with **Laravel** and **FilamentPHP**.

This is a streamlined fork focused on the core tracker/forum/community experience.

## Features

- **BitTorrent tracker** — announce/scrape, peer/seed/leech handling, torrent upload/edit/delete/download
- **Torrent catalog** — categories, sources, media, codecs, custom tags, search and global search
- **User system** — classes, invites, ratio, seed bonus, passkeys, profile/settings
- **Community** — forum, shoutbox, private messages, polls, news, FAQ, rules
- **Moderation** — reports, complains, warnings, IP bans, failed-login monitoring, IP history
- **Automation** — H&R (hit-and-run), exams/attendance, medals/user meta, SeedBox rules
- **Admin panel** — Filament-based backend plus legacy admin pages
- **Plugin support** — manage installed plugins
- **API / RSS** — tracker announce endpoint and RSS feeds

> Note: this fork intentionally removes several upstream features (non-English languages, subtitle/request/IMDb/PTGen, Hot/Classic picks, NFO, advertisements, torrent claim, funbox, Team, link exchange, upload/download speed and ISP display, small description and deadline fields, plugin marketplace, and the email-domain allowlist/blocklist). The UI is English-only.

## System Requirements

- **PHP** 8.4 / 8.5 (both tested in CI)
  - Required extensions: `bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `gd`, `redis`, `pcntl`, `sockets`, `posix`, `gmp`, `opcache`, `zip`, `intl`, `pdo_sqlite`, `sqlite3`, `pdo_pgsql`
- **Database** — MySQL 8.0+ (tested in CI on MySQL 8.0 and 9.0; Docker uses MySQL 9)
- **Redis** — 7.0+ (tested in CI on Redis 7)
- **MeiliSearch** — 1.6+ (torrent search index)
- **Other** — supervisor, cron, rsync

## Quick Start with Docker

### Development

```bash
cp .env.example .env
# Edit .env so DB_HOST=mysql, REDIS_HOST=redis and MEILISEARCH_HOST=meilisearch match docker-compose.yml
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

After the containers start, install via the CLI (the legacy web installer was removed — it relied on a parallel DB layer):

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan app:install \
  --username=sysop --email=admin@example.com --password='ChooseAStrongPassword'
```

`app:install` checks the environment, merges `.env`, runs migrations + seeders, saves settings, creates symlinks, initialises the tracker announce URL and creates the staff-leader account. Then populate the MeiliSearch index:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan meilisearch:import
```

Run the queue worker, scheduler and cleanup workers via the containers started by `docker compose` (see `docker-compose.yml`).

### Production

The production image is a multi-stage build that pre-bakes `vendor/`, `public/build/`, and Laravel caches. It runs as `www-data` with a read-only rootfs.

```bash
# Build the production image
docker build -f .docker/php/Dockerfile.prod -t nexusphp_php:prod .

# Start the production stack
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Key differences from dev:
- No bind-mounts — code is baked into the image
- `USER www-data` — runs as non-root
- `read_only: true` — rootfs is read-only; only `storage/`, `bootstrap/cache/`, `attachments/`, `torrents/` are writable volumes
- No `composer install` at runtime — vendor is pre-built
- Laravel caches (`config:cache`, `route:cache`, `view:cache`) are built at image build time
- OPcache configured for production (`validate_timestamps=0`)

#### HTTPS

OpenResty serves TLS on :443 when `/certs/fullchain.pem` + `/certs/private.key` exist
and falls back to plain HTTP otherwise. Port :80 always answers ACME challenges
(`/.well-known/acme-challenge/` from the `acme-data` volume) and redirects to HTTPS.

To issue and auto-renew a Let's Encrypt certificate, set `NP_DOMAIN` + `CERTBOT_EMAIL`
in `.env` and start the opt-in certbot sidecar:

```bash
docker compose --profile certbot -f docker-compose.yml -f docker-compose.prod.yml up -d
```

The certbot container requests the cert via HTTP-01 (needs inbound :80 reachable),
copies it into the shared `certs-data` volume, and renews every 12h. A watcher inside
the openresty container re-renders the vhost and reloads nginx whenever the cert
material changes — the site upgrades HTTP→HTTPS without a restart. Use
`CERTBOT_STAGING=yes` to test against the staging CA (untrusted cert, no rate limits).

## Local Development

There is no Node/Vite build step — Blade + legacy assets are served as-is.
The dev stack is Docker Compose (PHP, MySQL, Redis, MeiliSearch, openresty):

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php composer install
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan migrate:fresh --seed --force
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan meilisearch:import
# site on http://127.0.0.1
```

Create an admin account:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php \
  php artisan user:reset_id_auto_increment \
  --auto_increment=10001 --admin=sysop --password=<password> --email=<email>
```

## Testing

PHP suites run inside the php container; `make test` uses the
`docker-compose.test.yml` overlay (isolated `nexusphp_testing` DB and
`test_` Redis prefix) so tests never touch dev data:

```bash
make test              # all suites
make test-unit         # unit only, no DB needed
make test-feature      # legacy-context feature tests
make test-lint         # Pint + PHPStan level 8
make test-performance  # EXPLAIN/query-plan regression tests
docker compose exec -T php composer audit
```

Browser (Playwright/axe) specs live in `tests/browser/` and run against a
prepared dev stack — see `tests/browser/README.md`:

```bash
cd tests/browser
npm install && npx playwright install chromium   # one-time prerequisites
npx playwright test                              # BROWSER_BASE_URL etc. in playwright.config.ts
```

CI runs Pint, PHPStan level 8, Unit tests (PHP 8.4/8.5 × MySQL 8.0/9.0 matrix),
Feature tests, Docker smoke test, CodeQL, gitleaks, Trivy container scan, and SBOM generation.

## License

This project is based on NexusPHP. See the upstream repository for license details.
