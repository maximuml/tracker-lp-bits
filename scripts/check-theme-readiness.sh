#!/bin/sh
# Theme/CSS readiness gate — run BEFORE browser acceptance.
#
# A missing or stale Tailwind build silently distorts the UI: the
# head-assets partial links css/nxt.css only when it exists, so an
# unbuilt checkout renders converted components unstyled with no error
# anywhere. This script makes that failure loud instead of visual.
#
# Checks:
#   1. public/css/nxt.css exists, is non-trivial, and contains nxm
#      utility rules (an empty/partial build fails here)
#   2. the output matches a fresh compile of the current sources —
#      byte-compared: the standalone Tailwind CLI only writes output
#      when the bytes differ, so mtimes cannot tell staleness
#   3. a live page actually links nxt.css (catches "built on host but
#      absent inside the container" mounts and half-written files)
#
# Safe on an existing stand: verify mode mutates nothing. --build runs
# the real build (scripts/build-css.sh) first. Never resets or
# migrates the database — for a fresh install the same check works
# right after seeding; for an existing stand it leaves data alone.
#
# Usage:
#   scripts/check-theme-readiness.sh            verify only; fails on stale/missing build
#   scripts/check-theme-readiness.sh --build    rebuild nxt.css first, then verify
#   BASE_URL=http://127.0.0.1:8080 scripts/check-theme-readiness.sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
OUT="$ROOT/public/css/nxt.css"
BASE_URL="${BASE_URL:-http://127.0.0.1}"
MODE="${1:-}"

fail() { echo "FAIL: $*" >&2; exit 1; }
note() { echo "ok: $*"; }

case "$MODE" in
    ""|--build) ;;
    *) fail "unknown argument: $MODE (expected --build)" ;;
esac

if [ "$MODE" = "--build" ]; then
    "$ROOT/scripts/build-css.sh" >/dev/null
    note "nxt.css rebuilt via scripts/build-css.sh"
fi

# 1. File built and non-trivial
[ -s "$OUT" ] || fail "public/css/nxt.css missing or empty — run \`make css\` before acceptance"
SIZE=$(wc -c < "$OUT")
[ "$SIZE" -ge 10000 ] || fail "nxt.css is ${SIZE} bytes — a real build is ~24KB; partial write?"
note "nxt.css present (${SIZE} bytes)"

grep -q '\.bg-nxm-surface' "$OUT" || fail "nxt.css lacks nxm utility rules — output is not a Tailwind build of resources/css/nxt.css"
note "nxm utility rules present in output"

# 2. Freshness: compile current sources to a temp file and byte-compare.
# The standalone CLI skips writing when the new bytes equal the old,
# so a stale-in-content output is only provable by comparison.
BIN=$(ls "$ROOT"/bin/tailwindcss-* 2>/dev/null | head -1)
[ -n "$BIN" ] || fail "no tailwindcss binary in bin/ — run \`make css\` first"
TMP=$(mktemp)
trap 'rm -f "$TMP"' EXIT
"$BIN" -i "$ROOT/resources/css/nxt.css" -o "$TMP" --minify >/dev/null 2>&1 \
    || fail "fresh compile failed — cannot verify freshness"

cmp -s "$TMP" "$OUT" \
    || fail "nxt.css differs from a fresh compile — stale build; rebuild with \`make css\` or re-run with --build"
note "nxt.css matches a fresh compile of current sources"

# 3. Live wiring: the rendered page links the stylesheet
HTML=$(curl -fsS --max-time 15 "$BASE_URL/login") \
    || fail "cannot fetch $BASE_URL/login — is the stack up? (docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d)"
echo "$HTML" | grep -q 'css/nxt\.css' \
    || fail "/login does not link nxt.css — file missing inside the container or the include guard is off"
note "nxt.css linked on live page"

echo "theme readiness: PASS"
