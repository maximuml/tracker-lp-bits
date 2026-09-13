#!/usr/bin/env bash
# W8-04: graceful-deployment drill — run with the PROD stack already up.
#
#   1. dispatch a 25s probe job, then `docker compose stop queue` mid-flight:
#      the stop must take >= the job sleep (SIGTERM drain, not the 10s
#      SIGKILL default) and the job's marker must still land in the cache
#   2. `queue:probe` again after the queue returns — workers resume
#   3. exercise the real operator path: scripts/deploy.sh --skip-build
#      (drain -> php recreate -> migrate -> openresty -> readiness gate ->
#      workers back)
#   4. post-deploy HTTP smoke
set -euo pipefail
cd "$(dirname "$0")/../.."

dc() { docker compose "$@"; }
step() { echo; echo "=== $* ==="; }
fail() { echo "FAIL: $*" >&2; exit 1; }

step "1/4 Queue drain: in-flight job survives compose-stop"
dc exec -T php php artisan queue:probe --sleep=25 --wait --timeout=120 &
PROBE_PID=$!
sleep 5  # let Horizon pick the job up
START=$SECONDS
dc stop queue
ELAPSED=$((SECONDS - START))
echo "compose stop queue took ${ELAPSED}s"
wait "$PROBE_PID" || fail "probe job marker never landed — in-flight job lost on stop"
# Drain means the container stays up until the ~25s job finishes (~15-30s
# from this point). If the signal were ignored, docker would SIGKILL at the
# 125s grace; if the job were cut, the marker would be missing (checked).
[ "$ELAPSED" -ge 12 ] || fail "queue stopped in ${ELAPSED}s — too fast for a drained 25s job"
[ "$ELAPSED" -lt 100 ] || fail "queue stop took ${ELAPSED}s — SIGTERM ignored, ended by SIGKILL at grace"
echo "DRAIN_OK: job finished during graceful stop (${ELAPSED}s)"

step "2/4 Workers resume after restart"
dc up -d --no-build queue
dc exec -T php php artisan queue:probe --sleep=0 --wait --timeout=90 || fail "queue not processing after restart"
echo "RESUME_OK"

step "3/4 Operator deploy path"
scripts/deploy.sh --skip-build

step "4/4 Post-deploy smoke"
for page in /index /login; do
    code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "http://localhost$page")
    [ "$code" -ge 200 ] && [ "$code" -lt 400 ] || fail "page $page returned $code after deploy"
    echo "OK: $page ($code)"
done

echo
echo "GRACEFUL_DEPLOY_VERIFY_OK"
