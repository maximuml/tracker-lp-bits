# Runbooks

Operational runbooks for the tracker. Each entry: **Symptoms → Dashboards →
Actions → Rollback → Post-checks**. Assume `docker compose` commands run from
the deploy root with `COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml`.

Fast triage endpoints:

- `GET /health/live` — process up (unauthenticated)
- `GET /health/ready` — DB + Redis reachable (unauthenticated)
- `GET /health/diag` — per-dependency diagnostics (authenticated)
- `GET /metrics` — Prometheus metrics (bearer `METRICS_TOKEN` in production)
- Horizon dashboard at `/horizon` (staff)
- `php artisan queue:probe --wait` — proves the queue actually runs jobs

---

## DB down (MySQL)

**Symptoms:** `/health/ready` non-200, `nexus_db_up 0`, site 500s, Horizon
jobs failing with connection errors.

**Dashboards:** `nexus_db_up`, `nexus_db_query_count`, `nexus_http_requests_total{status="5xx"}`.

**Actions:**
1. `docker compose ps mysql` — container up? `docker compose logs --tail=100 mysql`.
2. If container is down: `docker compose up -d mysql` and watch
   `docker compose ps mysql` until `healthy`.
3. If it crash-loops: check disk space (`df -h`), `innodb` recovery lines in
   logs, corrupted volume → see **Restore** below.
4. If up but refused: verify credentials (`DB_USERNAME`/`DB_PASSWORD` in
   `.env`) match the mysql container env; check `max_connections` exhaustion
   via `docker compose exec mysql mysql -uroot -p"$DB_PASSWORD" -e "SHOW STATUS LIKE 'Threads_connected'"`.

**Rollback:** n/a — restoring DB availability is the fix.

**Post-checks:** `/health/ready` → 200; `nexus_db_up 1`; announce traffic
resumes (`nexus_http_requests_total` for `/announce`).

## Redis down

**Symptoms:** `/health/ready` non-200 (cache+queue unreachable),
`nexus_redis_up 0`, queued jobs stall, sessions/cache misses spike.

**Dashboards:** `nexus_redis_up`, `nexus_redis_ping_seconds`,
`nexus_cache_misses_total`, `nexus_horizon_pending_jobs`.

**Actions:**
1. `docker compose ps redis` / `docker compose logs --tail=100 redis`.
2. `docker compose up -d redis`; the RedisGuard circuit breaker in the app
   fails open for cache (site degrades, not down) but the queue needs Redis.
3. If the volume is corrupt: Redis state is cache+queue — acceptable to
   `docker compose down redis && docker volume rm <project>_redis-data` then
   `up -d redis`. In-flight queued jobs are lost; verify none are critical
   (`nexus_horizon_pending_jobs` before the wipe).

**Rollback:** n/a.

**Post-checks:** `/health/ready` → 200; `php artisan queue:probe --wait`
→ `PROBE_OK`; `nexus_redis_up 1`.

## MeiliSearch lag / down

**Symptoms:** search results stale or empty, `nexus_meili_up 0`,
`nexus_meili_lag_seconds` growing.

**Dashboards:** `nexus_meili_up`, `nexus_meili_lag_seconds`.

**Actions:**
1. `docker compose ps meilisearch` / `logs --tail=100 meilisearch`.
2. `docker compose up -d meilisearch` — site keeps working; search falls
   back to SQL (`meili_up 0` is a degradation, not an outage).
3. Reindex after recovery: `docker compose exec php php artisan meilisearch:import`
   (weekly re-import also runs via scheduler Mondays 03:00).
4. If the index is corrupt: delete the `meili-data` volume, recreate
   meilisearch, then `meilisearch:import`.

**Rollback:** n/a — search degrades gracefully to SQL.

**Post-checks:** `nexus_meili_up 1`; `nexus_meili_lag_seconds` back under
~60s; spot-check a search query on `/torrents`.

## Queue growing (jobs not draining)

**Symptoms:** `nexus_horizon_pending_jobs` climbing, delayed bonus/hit&run
processing, `queue:probe --wait` times out.

**Dashboards:** `nexus_horizon_pending_jobs`, `nexus_horizon_reserved_jobs`,
`nexus_horizon_failed_jobs`.

**Actions:**
1. `docker compose ps queue` — is Horizon running? `logs --tail=100 queue`.
2. `php artisan horizon:status` — supervisor processes listed?
3. `php artisan queue:probe --wait` — is the queue alive at all?
4. Dead worker: `docker compose restart queue` (SIGTERM drains in-flight
   jobs within 125s grace — do NOT `kill -9`).
5. Poison job failing repeatedly: `php artisan queue:failed` (or the
   `failed_jobs` table) to find it; see **Outbox dead letters**.
6. Genuine overload: raise supervisor processes in `config/horizon.php`
   (`supervisor-1` `processes`) — needs a config change + redeploy.

**Rollback:** if a recent deploy introduced a poison job, redeploy the
previous image digest (`deploy.sh --image <prev-digest>` or re-tag +
`--skip-build`).

**Post-checks:** `nexus_horizon_pending_jobs` trending down;
`queue:probe --wait` < 90s; `nexus_horizon_failed_jobs` not climbing.

## Scheduler stale

**Symptoms:** `nexus_scheduler_heartbeat_age_seconds` > 300, cron tasks
(attendance, cleanup, backups) not running.

**Dashboards:** `nexus_scheduler_up`, `nexus_scheduler_heartbeat_age_seconds`.

**Actions:**
1. `docker compose ps scheduler` / `logs --tail=50 scheduler` — look for a
   stuck `schedule:work` or a crashed process.
2. `docker compose restart scheduler` (SIGTERM, 70s grace).
3. If the heartbeat is fresh but a specific task is missing: check
   `app/Console/Kernel.php` schedule + `horizon` failed jobs — tasks run
   via the queue.

**Rollback:** n/a.

**Post-checks:** `nexus_scheduler_heartbeat_age_seconds` < 120; next
scheduled command observed in logs.

## Outbox dead letters

**Symptoms:** `nexus_outbox_oldest_pending_age_seconds` growing, events/
notifications not delivered, rows piling in the outbox table.

**Dashboards:** `nexus_outbox_latency_seconds`,
`nexus_outbox_oldest_pending_age_seconds`, `nexus_horizon_failed_jobs`.

**Actions:**
1. `php artisan outbox:dispatch --batch=50` manually — does it drain?
   (Scheduler runs it every minute.)
2. Inspect dead letters: `php artisan queue:failed` / `failed_jobs` table
   for `OutboxDispatch`-adjacent failures — a poison payload retries
   forever. `queue:retry <id>` replays one, `queue:forget <id>` drops it.
3. If one payload is poison: replay or drop it explicitly; do not flush the
   whole table (loses legitimate events).

**Rollback:** if a deploy introduced the poison producer, redeploy previous
image (see Rollback under Queue).

**Post-checks:** `nexus_outbox_oldest_pending_age_seconds` returns to ~60s;
no new dead letters after the poison row is handled.

## Announce latency spike

**Symptoms:** `nexus_http_request_duration_seconds` p95 on `/announce`
climbing, clients timing out, `nexus_announce_rejections_total` changing.

**Dashboards:** `nexus_http_request_duration_seconds` (per-route),
`nexus_announce_rejections_total`, `nexus_db_query_count`,
`nexus_redis_ping_seconds`, `nexus_horizon_pending_jobs`.

**Actions:**
1. Correlate: DB slow (`nexus_db_up`/`nexus_db_query_count`)? Redis slow
   (`nexus_redis_ping_seconds`)? Or announce-only?
2. DB: `docker compose exec mysql mysql -e "SHOW PROCESSLIST"` for long
   queries / lock waits — kill the offender.
3. RedisGuard circuit-breaker state — if Redis is flapping, announce spends
   time in retries; fixing Redis fixes announce.
4. A deploy that regressed announce → rollback to previous image digest.

**Rollback:** `deploy.sh --image <prev-digest>` or re-tag previous image +
`deploy.sh --skip-build`.

**Post-checks:** `/announce` p95 back under baseline; k6 `announce`
scenarios in CI are the reference latencies.

## Disk full

**Symptoms:** MySQL/Redis write errors, uploads fail, backup archives fail,
`tar`/`mysqldump` exit nonzero, containers crash-looping.

**Dashboards:** host `df -h`; watch `storage/app/backups/`,
`storage/logs/`, `attachments/`, `torrents/`, docker volumes.

**Actions:**
1. `df -h` + `docker system df` — find the consumer.
2. Usual suspects: `storage/app/backups` (prune old bundles — keep per
   retention policy), `storage/logs`, docker overlay (`docker system prune`
   — careful: only `-f` dangling, never `-a` on a live host), `bitbucket`.
3. MySQL binlog/ibdata growth → check `SHOW BINARY LOGS` if enabled.

**Rollback:** n/a.

**Post-checks:** `df -h` headroom >20%; `/health/ready` 200; next scheduled
`backup:cronjob` succeeds.

## Restore (from backup bundle)

**Symptoms/Trigger:** data loss, corrupted volume, migration to new host.

**Dashboards:** n/a — this is a recovery procedure.

**Actions:**
1. Locate the bundle: `storage/app/backups/<base>.<ts>.tar.gz` (contains
   `*.sql` dump + webroot archive + data dirs). Encrypted bundles need the
   `BACKUP_GPG_RECIPIENT` key first (`gpg -d`).
2. Scratch-verify before touching prod: extract the bundle, then
   `php artisan backup:restore-drill --file=<dump.sql> --compare`
   restores the `.sql` into a scratch DB and diffs per-table row counts.
3. Restore DB: recreate the database, `mysql < dump.sql`, then
   `php artisan migrate --force` to catch up on post-backup migrations.
4. Restore files: extract the web archive over the deploy root —
   `torrents/`, `attachments/`, `bitbucket/` contents land in place.
5. Full procedure + CI verification: `scripts/ci/verify-backup-restore.sh`
   is the executable reference (it proves destroy→restore→parity→HTTP
   smoke end-to-end).

**Rollback:** keep the pre-restore broken state until parity is confirmed —
snapshot the volume first if anything is salvageable.

**Post-checks:** `CHECKSUM TABLE` parity vs manifest where available,
`/health/ready` 200, HTTP smoke on `/index` `/login` `/torrents`,
`queue:probe --wait` OK.

## Token rotation (passkey / auth_key / app key)

**Symptoms/Trigger:** suspected key leak, scheduled rotation, staff
compromise.

**Dashboards:** `nexus_announce_rejections_total` (spike = clients using old
passkeys), `nexus_http_requests_total` filtered to 401/403 statuses.

**Actions:**
1. **Passkey-login signing key (v2):** `php artisan passkey:generate-key
   --rotate` moves the current key to `previous` and generates a new one —
   `PasskeyLoginService` verifies signatures against BOTH keys during the
   transition window. Without `--rotate` the old key is dropped and
   existing v2 login URLs die immediately.
2. **Tracker passkeys (per-user):** announce auth is the per-user
   `users.passkey` — reset via the staff panel per user; bulk
   invalidation = announce rejection flood — stage it.
3. **Passwords / legacy hashes:** `user:reset_password` for single users;
   `users:force-reset-legacy` (dry-run by default, `--apply` to execute)
   flags `must_change_password` for accounts still on legacy hashes.
4. **APP_KEY:** rotating invalidates all encrypted cookies (`c_secure_pass`)
   → every user re-logs in. Edit `.env`, `php artisan config:cache`,
   recreate `php`. Never rotate casually.
5. **METRICS_TOKEN** (bearer for `/metrics`): rotate in `.env`, recreate
   `php` — monitoring must update in lockstep.

**Rollback:** restore the previous key in `.env` (APP_KEY). For passkey
signing, `--rotate` keeps `previous` accepting — if a plain (non-rotate)
regeneration already ran, re-adding the old key material manually is the
only rollback; always prefer `--rotate` on a live fleet.

**Post-checks:** `/health/ready` 200; login flow works; announce
rejections return to baseline; `/metrics` accepts the new token and
rejects the old.
