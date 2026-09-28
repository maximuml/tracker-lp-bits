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

## Forum search benchmark (2026-09-28, local dev stack)

Modernization plan step 5 — the SQL-vs-FULLTEXT-vs-MeiliSearch decision is
measured, not guessed. Dataset: 100 000 synthetic `posts` across 5 000
`topics` seeded via a recursive-CTE insert; forum search query shape is
`posts ⋈ topics ⋈ forums` with the `forums.minclassread` gate and the
post-#996 predicate (`topics.subject LIKE ? AND posts.id = topics.firstpost`
OR `posts.body LIKE ?`).

| query | keyword | p50 |
|---|---|---|
| `LIKE %kw%` count | Linkin / lossless / Meteora / padding / concert | 239–289 ms |
| `MATCH … AGAINST` (nat.) count | same five keywords | 164–223 ms |
| `LIKE %kw%` page select (LIMIT 25) | Linkin / padding | 250–274 ms |

Temporary `FULLTEXT` indexes on `posts(body)` / `topics(subject)` used for
the measurement, then dropped with the bench rows — not migrated.

**Decision: keep SQL `LIKE`.** FULLTEXT is ~30–40% faster but still a
full-result scan at this volume — not an order-of-magnitude win, and it
changes match semantics (word boundaries, `innodb_ft_min_token_size`,
stopwords — substring searches like `R&B` or partial nicks would silently
stop matching). MeiliSearch adds infra + a `Searchable` model/index wiring
for a query that stays under ~300 ms at 100k posts. Revisit when posts are
an order of magnitude larger or if ranked/fuzzy search UX is actually
wanted. The remaining cost is the `COUNT(*)` covering the whole result set;
`perPage=0` already short-circuits to count-only (PR #996).
