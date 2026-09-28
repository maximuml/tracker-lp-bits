# Performance harness

k6 scenarios run against the Docker stack behind openresty:

- `announce.js` — tracker load gate: invalid-passkey flood, steady announce,
  many peers on one torrent, early re-announce dedup, multi-hash scrape.
- `baseline.js` — web UI page timings.
- `redis-degradation.js` — announce/scrape with Redis stopped (RedisGuard
  fail-open from the DB path).
- `ExplainPlanRegressionTest.php` — PHPUnit EXPLAIN-plan regression gate.
- `compare-results.js` — compares a run's p95 against the CI baseline
  (regression = p95 > 1.5× baseline median AND > 100ms absolute, minus
  `page_health_live_duration` noise-floor shift capped at 200ms).

CI: `.github/workflows/perf-budget.yml` seeds `PerformanceTestDatasetSeeder`
(26 `perf_user_*` accounts, 50 `perf-torrent-*` torrents), extracts
passkeys/hashes via tinker, clears `-perf-` peer rows, runs the scenarios.

Local reproduction (dev stack):

```bash
PASSKEY=$(docker exec nexusphp-php php /var/www/html/artisan tinker \
  --execute='echo App\Models\User::where("username","perf_user_1")->value("passkey");' | tr -d '[:space:]')
INFO_HASH=$(docker exec nexusphp-php php /var/www/html/artisan tinker \
  --execute='echo App\Models\Torrent::where("name","perf-torrent-1")->value("info_hash");' | tr -d '[:space:]')
docker run --rm --network host -v "$PWD:/scripts" grafana/k6 run \
  --env BASE_URL=http://127.0.0.1:80 --env PASSKEY=$PASSKEY \
  --env INFO_HASH=$INFO_HASH /scripts/announce.js
```

## Measured baseline (2026-09-28, local dev stack, php8@`b7e44db`)

Single container host, k6 in Docker on the same network. Numbers are a
local reference, not the CI gate baseline (shared runners differ).

| scenario | p95 | requests | rate |
|---|---|---|---|
| invalid_passkey_flood | 31 ms | 201 | rejected 1.0 |
| steady_announce | 32 ms | 66 | success 1.0 |
| many_peers_one_torrent | 209 ms | 48 | success 1.0 |
| early_announce | 30 ms | 15 | handled 1.0 |
| multi_hash_scrape | 28 ms | 50 | success 1.0 |
| redis_down_announce | 38 ms | 76 | answered 1.0, success 1.0 |

Redis-down: `docker compose stop redis` + warmup announce to trip the
circuit breaker, then 15s of announces — every request answered from the
DB path; recovery on `docker compose start redis` is clean. The first
post-outage request pays one ~3–5s RedisGuard probe, subsequent requests
fail fast until the flag expires.

`many_peers_one_torrent` is the known flake source: tiny iteration counts
on shared runners make its p95 jump around (lock contention on the peer
row); the comparator's 1.5×/100ms gate exists for exactly this.
