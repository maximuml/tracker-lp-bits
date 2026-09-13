#!/usr/bin/env bash
# W8-05 supply-chain pin ratchet — runs on every PR.
# Fails when new unpinned third-party material enters the repo:
#   1. GitHub Actions `uses:` not pinned to a 40-char commit SHA
#   2. Dockerfile `FROM` not pinned to a @sha256: digest
#   3. Compose `image:` for third-party images without @sha256:
#      (locally built nexusphp_* images are exempt)
#   4. apk/apt tool installs without a version constraint (~= or =)
#   5. Runtime downloads (curl/wget of remote URLs) in entrypoints/scripts —
#      binary provenance must come from pinned images, not the network
set -euo pipefail
cd "$(dirname "$0")/../.."

fail() { echo "PIN_CHECK_FAIL: $*" >&2; FAILURES=$((FAILURES + 1)); }
FAILURES=0

# --- 1. GitHub Actions must be SHA-pinned -----------------------------------
while IFS= read -r line; do
    fail "unpinned action: $line"
done < <(grep -rhnE 'uses: *[^ ]+' .github/workflows/ \
         | grep -vE '@[0-9a-f]{40}' || true)

# --- 2. Dockerfile FROM must be digest-pinned -------------------------------
while IFS= read -r line; do
    fail "unpinned base image: $line"
done < <(find .docker -name 'Dockerfile*' -exec grep -HnE '^FROM ' {} + \
         | grep -vE '@sha256:' \
         | grep -vE 'FROM (scratch|[a-z-]+ *AS)' || true)

# --- 3. Compose third-party images must be digest-pinned ---------------------
while IFS= read -r line; do
    fail "unpinned compose image: $line"
done < <(grep -hnE '^\s+image: *[^ ]+' docker-compose*.yml \
         | grep -vE 'image: *nexusphp_' \
         | grep -vE '@sha256:' || true)

# --- 4. Tool packages in apk/apt installs need a version ---------------------
# Shared binaries an attacker would swap (fetch/exec/auth tools). Libraries
# float with the pinned base image's repo — tools must not.
# Multi-line installs: a package line inside an `apk add`/`apt-get install`
# block is bare tokens ending with an optional '\'. Collect the block from
# the install command until a line without a trailing backslash.
while IFS= read -r hit; do
    fail "$hit"
done < <(find .docker -name 'Dockerfile*' -print0 | while IFS= read -rd '' df; do
    awk -v file="$df" '
        /apk add|apt-get install/ { inblock=1; first=1 }
        inblock {
            n = split($0, t, /[[:space:]]+/)
            for (i = 1; i <= n; i++) {
                tok = t[i]
                if (tok ~ /^(--|RUN|apk|apt-get|add|install|\$|$)/) continue
                gsub(/\\/, "", tok)
                if (tok == "") continue
                if (tok ~ /^(wget|curl|rsync|git|bash|gettext|openssl|socat|mysql-client|mariadb-client|mariadb-connector-c|openssh|jq|python3|make)$/ && tok !~ /=/) {
                    printf "%s:%d: unpinned tool package %s\n", file, NR, tok
                }
            }
            if ($0 !~ /\\$/) { inblock=0 }
        }
    ' "$df"
done)

# --- 5. No runtime downloads in entrypoints/scripts ---------------------------
# Health checks and loopback calls are fine; fetching remote content is not.
while IFS= read -r line; do
    fail "runtime download: $line"
done < <(grep -rnE '(curl|wget) +[^|]*https?://' .docker/*/entrypoint*.sh scripts/deploy.sh 2>/dev/null \
         | grep -vE '127\.0\.0\.1|localhost' || true)

if [ "$FAILURES" -gt 0 ]; then
    echo "PIN_CHECK_FAILED: $FAILURES violation(s)"
    exit 1
fi
echo "PIN_CHECK_OK"
