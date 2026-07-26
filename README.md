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

- **PHP** 8.2 / 8.3 / 8.4 / 8.5
  - Required extensions: `bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `mysqli`, `gd`, `redis`, `pcntl`, `sockets`, `posix`, `gmp`, `opcache`, `zip`, `intl`, `pdo_sqlite`, `sqlite3`, `pdo_pgsql`
- **Database** — MySQL 5.7+ or PostgreSQL 16+
- **Redis** — 4.0+
- **Other** — supervisor, cron, rsync

## Quick Start with Docker

```bash
cp .env.example .env
# Edit .env so DB_HOST=mysql and REDIS_HOST=redis match docker-compose.yml
docker compose up -d
```

After the containers start, complete the web installer at `http://<your-domain>/install` (or run `php artisan migrate --seed` and create an admin user if you prefer the CLI).

Run the queue worker, scheduler and cleanup workers via the containers started by `docker compose` (see `docker-compose.yml`).

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

CI also runs a Docker smoke test against the application.

## License

This project is based on NexusPHP. See the upstream repository for license details.
