# ClickHouse (Optional Analytics Backend)

ClickHouse is used as an optional analytics backend for high-volume log tables.

## What ClickHouse Stores

- **Announce logs** — every `/announce.php` request (via `AnnounceLogRepository`, surfaced in the Filament `AnnounceLogResource`)
- **Bonus logs** — seed-bonus award events (via `BonusRepository`, written by `SeedBonusJob`)

These tables grow rapidly (millions of rows/day on a busy tracker) and are a poor fit for MySQL's row-based storage. ClickHouse's columnar engine provides fast aggregate queries with minimal disk usage.

## When You Need It

- **Small trackers (< 1k peers):** not required — MySQL handles the volume fine.
- **Medium trackers (1k–10k peers):** recommended for announce logs.
- **Large trackers (10k+ peers):** strongly recommended.

## Configuration

Add the following to `.env`:

```env
CLICKHOUSE_HOST=clickhouse
CLICKHOUSE_PORT=8123
CLICKHOUSE_DATABASE=nexusphp
CLICKHOUSE_USERNAME=default
CLICKHOUSE_PASSWORD=
```

When `CLICKHOUSE_HOST` is empty or unset, the repositories fall back to MySQL automatically.

## Docker Compose

Add a `clickhouse` service to `docker-compose.yml`:

```yaml
clickhouse:
  image: clickhouse/clickhouse-server:24.8
  volumes:
    - clickhouse-data:/var/lib/clickhouse
  ports:
    - "8123:8123"
    - "9000:9000"
  ulimits:
    nofile:
      soft: 262144
      hard: 262144
```

## Schema Creation

Tables are created automatically on first write when ClickHouse is enabled. To pre-create them:

```bash
docker compose exec php php artisan clickhouse:migrate
```

## Retention Policy (TODO)

A TTL-based retention policy should be configured to auto-drop rows older than N days:

```sql
ALTER TABLE announce_logs MODIFY TTL ts + INTERVAL 30 DAY;
ALTER TABLE bonus_logs   MODIFY TTL ts + INTERVAL 90 DAY;
```

This is not yet automated — track in the modernization plan.
