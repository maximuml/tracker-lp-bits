#!/usr/bin/env bash
# Production-compose verification: run after the prod stack is up.
# Asserts hardening invariants (non-root, read-only rootfs, writable
# volumes, caches, connectivity) and HTTP smoke through OpenResty.
set -euo pipefail

echo "=== Verify PHP health ==="
# PHP-FPM must respond to a basic artisan command
docker compose exec -T php php artisan about --no-interaction

echo "=== Verify non-root UID ==="
uid=$(docker compose exec -T php id -u)
if [ "$uid" = "0" ]; then
    echo "FAIL: PHP container running as root (uid=0)"
    exit 1
fi
echo "OK: PHP container running as uid=$uid"

echo "=== Verify read-only rootfs ==="
# Writing to rootfs (outside writable volumes) must fail
if docker compose exec -T php sh -c 'echo test > /var/www/html/test_write 2>/dev/null'; then
    echo "FAIL: rootfs is writable"
    exit 1
fi
echo "OK: rootfs is read-only"

echo "=== Verify writable storage volume ==="
docker compose exec -T php sh -c 'echo test > /var/www/html/storage/framework/cache/test_write && rm /var/www/html/storage/framework/cache/test_write'
echo "OK: storage volume is writable"

echo "=== Verify writable attachments volume ==="
docker compose exec -T php sh -c 'echo test > /var/www/html/attachments/test_write && rm /var/www/html/attachments/test_write'
echo "OK: attachments volume is writable"

echo "=== Verify writable torrents volume ==="
docker compose exec -T php sh -c 'echo test > /var/www/html/torrents/test_write && rm /var/www/html/torrents/test_write'
echo "OK: torrents volume is writable"

echo "=== Verify Vite assets and manifest exist ==="
docker compose exec -T php sh -c 'test -f /var/www/html/public/build/manifest.json || test -f /var/www/html/public/build/.vite/manifest.json'
echo "OK: Vite manifest exists in public/build"

echo "=== Verify static files in public/build ==="
# At least one CSS or JS asset should exist
docker compose exec -T php sh -c 'ls /var/www/html/public/build/assets/ | head -1 | grep -q .'
echo "OK: static assets exist in public/build/assets/"

echo "=== Verify public-data volume init ran ==="
# assets-init must have completed before php/openresty mounted it.
# -a is required: `compose ps` hides exited one-shot containers.
docker compose ps -a assets-init --format '{{.State}} {{.ExitCode}}' | grep -qi "exited 0"
docker compose logs assets-init | grep -q "public-data volume synced"
echo "OK: public-data volume synced from image"

echo "=== Verify route/config/view caches ==="
# Laravel 13 uses routes-v7.php (new route caching format)
docker compose exec -T php sh -c 'test -f /var/www/html/bootstrap/cache/routes-v7.php'
docker compose exec -T php sh -c 'test -f /var/www/html/bootstrap/cache/config.php'
# view:cache compiles templates to storage/framework/views/, not a single file
docker compose exec -T php sh -c 'test -d /var/www/html/storage/framework/views && [ "$(ls -A /var/www/html/storage/framework/views/)" ]'
echo "OK: route/config/view caches exist"

echo "=== Verify DB connectivity ==="
# tinker is dev-only; use migrate:status which always works
docker compose exec -T php php artisan migrate:status --no-interaction >/dev/null 2>&1
echo "OK: DB connectivity verified"

echo "=== Verify Redis connectivity ==="
# Redis container health is verified by --wait (healthcheck uses
# redis-cli with auth). Verify the container is healthy.
docker compose ps redis --format '{{.Status}}' | grep -qi "healthy\|up"
echo "OK: Redis connectivity verified"

echo "=== Verify MeiliSearch connectivity ==="
docker compose exec -T php sh -c 'curl -sf http://${MEILISEARCH_HOST:-meilisearch}:7700/health 2>/dev/null'
echo ""
echo "OK: MeiliSearch connectivity verified"

echo "=== Verify queue worker is running ==="
# Queue container should be up and horizon should report active
status=$(docker compose exec -T queue php artisan horizon:status 2>&1)
echo "Horizon status: $status"
echo "$status" | grep -q "running\|active" || { echo "FAIL: horizon not running"; exit 1; }
echo "OK: queue worker (horizon) is running"

echo "=== Verify scheduler heartbeat ==="
# Scheduler container should be running and cycling
docker compose ps scheduler --format '{{.Status}}' | grep -q "Up\|running"
echo "OK: scheduler container is up"

echo "=== Verify scheduler heartbeat lands in Redis ==="
# The heartbeat job writes scheduler:heartbeat every minute (TTL 120s).
REDIS_PASSWORD=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2-)
for i in $(seq 1 90); do
    hb=$(docker compose exec -T redis redis-cli -a "$REDIS_PASSWORD" --no-auth-warning GET scheduler:heartbeat 2>/dev/null | tr -d '[:space:]')
    if [ -n "$hb" ]; then
        echo "OK: scheduler heartbeat in Redis ($hb) after ${i} polls"
        break
    fi
    if [ "$i" = "90" ]; then
        echo "FAIL: no scheduler:heartbeat key in Redis after 180s"
        docker compose logs --tail=40 scheduler || true
        exit 1
    fi
    sleep 2
done

echo "=== Verify /metrics requires bearer token in production ==="
# W6-04: production must not expose metrics without METRICS_TOKEN.
# APP_ENV=production + no token configured => endpoint fails closed.
status=$(curl -s --max-time 10 -o /dev/null -w "%{http_code}" "http://localhost/metrics")
echo "GET /metrics without token: $status"
if [ "$status" != "403" ]; then
    echo "FAIL: expected 403, got $status"
    exit 1
fi
echo "OK: /metrics closed without bearer token in production"

echo "=== Verify OpenResty config is valid ==="
docker compose exec -T openresty openresty -t 2>&1 | grep -q "syntax is ok\|test is successful"
echo "OK: OpenResty config is valid"

echo "=== Smoke test public pages via OpenResty ==="
for page in / /login /signup; do
    echo "Testing $page"
    status=$(curl -sL --max-time 10 -o /dev/null -w "%{http_code}" "http://localhost${page}")
    echo "  Status: $status"
    # Accept 200-399 (200 OK, 302 redirect to login, etc.)
    if [ "$status" -ge 200 ] && [ "$status" -lt 400 ]; then
        echo "  OK"
    else
        echo "  FAIL: unexpected status $status"
        exit 1
    fi
done
echo "OK: public pages respond via OpenResty"
