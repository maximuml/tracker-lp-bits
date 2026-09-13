#!/usr/bin/env bash
# W8-04: graceful deployment for the Docker Compose topology.
#
# Order of operations:
#   1. build/pull the new image (skipped with --skip-build, e.g. CI)
#   2. stop queue + scheduler — SIGTERM, Horizon drains in-flight jobs
#      within stop_grace_period (125s); longer maintenance-queue work is
#      requeued via retry_after, never silently dropped
#   3. recreate php on the new image (depends_on pulls assets-init first,
#      so the public-data volume is refreshed — W8-01 stale-assets fix)
#   4. run migrations on the NEW code (expand/contract policy keeps the old
#      schema compatible with code still serving during the swap)
#   5. recreate openresty (SIGQUIT drains connections)
#   6. readiness gate: /health/ready must be 200 before workers return
#   7. bring queue + scheduler back on the new image
#
# Note on downtime: this topology has a single php upstream, so the php
# recreate window produces a brief 502 gap. Graceful here means: no lost
# jobs, drained connections, readiness-gated return — not zero downtime.
# True zero-downtime needs a second php backend (blue-green).
#
# Usage: scripts/deploy.sh [--skip-build]
# Rollback: re-tag the previous image (`docker tag <prev-digest>
# nexusphp_php:prod`) or `git checkout <prev-tag>` + rerun this script.
set -euo pipefail
cd "$(dirname "$0")/.."

SKIP_BUILD=0
[ "${1:-}" = "--skip-build" ] && SKIP_BUILD=1

dc() { docker compose "$@"; }
step() { echo; echo "=== $* ==="; }
fail() { echo "FAIL: $*" >&2; exit 1; }

step "1/7 Build images"
if [ "$SKIP_BUILD" = "0" ]; then
    PREV_IMAGE=$(docker image inspect nexusphp_php:prod --format '{{.Id}}' 2>/dev/null || true)
    echo "Previous image (rollback target): ${PREV_IMAGE:-none}"
    dc build php
else
    echo "--skip-build: using existing nexusphp_php:prod"
fi

step "2/7 Drain queue + scheduler (jobs finish within stop_grace_period)"
dc stop queue scheduler

step "3/7 Sync public assets, recreate php on new image"
# assets-init (prod overlay only) refreshes the public-data named volume —
# without it a deploy keeps serving stale JS/CSS (the W8-01 bug). --no-deps
# on the php recreate below would skip it, so run it explicitly.
if dc config --services | grep -qx assets-init; then
    dc run --rm --no-deps -T assets-init
fi
dc up -d --no-deps --no-build --force-recreate php
for i in $(seq 1 60); do
    status=$(dc ps php --format '{{.Status}}' | tr -d '\r')
    case "$status" in *healthy*) break;; esac
    sleep 2
done
echo "php status: $status"
case "$status" in *healthy*) ;; *) fail "php not healthy after recreate";; esac

step "4/7 Migrations on the new code"
dc exec -T php php artisan migrate --force

step "5/7 Recreate openresty (connections drain via SIGQUIT)"
dc up -d --no-deps --no-build --force-recreate openresty

step "6/7 Readiness gate: /health/ready"
ready=0
for i in $(seq 1 60); do
    code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "http://localhost/health/ready" || true)
    if [ "$code" = "200" ]; then ready=1; break; fi
    sleep 2
done
[ "$ready" = "1" ] || fail "/health/ready did not return 200 within 120s"
echo "READY_OK"

step "7/7 Workers back on the new image"
dc up -d --no-build --force-recreate queue scheduler

echo
echo "DEPLOY_OK"
