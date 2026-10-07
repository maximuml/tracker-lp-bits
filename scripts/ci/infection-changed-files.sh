#!/usr/bin/env bash
# W3-04: detect which critical files changed in a PR and report whether the
# mutation-testing legs can be skipped. Prints/exports `skip`, `svc_changed`,
# `core_changed` and `diff_base` (resolved base revision for --git-diff-lines)
# for $GITHUB_OUTPUT.
set -euo pipefail

BASE_REF="${1:?usage: infection-changed-files.sh <base-ref-or-sha>}"

# The base may arrive as a raw commit sha (github.event.pull_request.base.sha —
# immune to a stale origin/<branch> after force-push rebases) or as a branch
# name; resolve the latter through the remote tracking ref.
if [[ "$BASE_REF" =~ ^[0-9a-f]{7,40}$ ]]; then
    BASE_REV="$BASE_REF"
else
    BASE_REV="origin/${BASE_REF}"
fi

# Changed files in the Services/Auth mutation scope (infection-services-auth.json5)
SVC_FILES=$(git diff --name-only "${BASE_REV}...HEAD" -- \
    'app/Services/*.php' \
    'app/Auth/*.php' \
    | grep -v '^$' || true)

# Changed files in the core mutation scope (infection.json5):
# the critical controllers + policies watchlist
CORE_FILES=$(git diff --name-only "${BASE_REV}...HEAD" -- \
    'app/Http/Controllers/AuthenticateController.php' \
    'app/Http/Controllers/TokenController.php' \
    'app/Policies/TorrentPolicy.php' \
    'app/Policies/UserPolicy.php' \
    'app/Policies/UsercpPolicy.php' \
    'app/Policies/TopicPolicy.php' \
    'app/Policies/PostPolicy.php' \
    'app/Policies/MessagePolicy.php' \
    | grep -v '^$' || true)

echo "diff_base=${BASE_REV}" >> "$GITHUB_OUTPUT"

if [ -n "$SVC_FILES" ]; then
    echo "svc_changed=true" >> "$GITHUB_OUTPUT"
fi
if [ -n "$CORE_FILES" ]; then
    echo "core_changed=true" >> "$GITHUB_OUTPUT"
fi

if [ -z "$SVC_FILES" ] && [ -z "$CORE_FILES" ]; then
    echo "skip=true" >> "$GITHUB_OUTPUT"
    echo "No critical files changed — skipping mutation testing."
else
    echo "skip=false" >> "$GITHUB_OUTPUT"
    echo "Diff base: ${BASE_REV}"
    echo "Changed critical files (services/auth):"
    echo "$SVC_FILES"
    echo "Changed critical files (core):"
    echo "$CORE_FILES"
fi
