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
  - Required extensions: `bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `mysqli`, `gd`, `redis`, `pcntl`, `sockets`, `posix`, `gmp`, `opcache`, `zip`, `intl`, `pdo_sqlite`, `sqlite3`, `pdo_pgsql`
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

After the containers start, complete the web installer at `http://<your-domain>/install` (or run `php artisan migrate --seed` and create an admin user if you prefer the CLI). Then populate the MeiliSearch index:

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

## Local Development

```bash
composer install
npm install
npm run dev
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Testing

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
composer audit
```

CI runs Pint, PHPStan level 8, Unit tests (PHP 8.4/8.5 × MySQL 8.0/9.0 matrix),
Feature tests, Docker smoke test, CodeQL, gitleaks, Trivy container scan, and SBOM generation.

## License

This project is based on NexusPHP. See the upstream repository for license details.
