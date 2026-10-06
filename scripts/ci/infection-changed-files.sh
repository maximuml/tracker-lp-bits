#!/usr/bin/env bash
# W3-04: detect which critical files changed in a PR and build the
# Infection --filter arguments. Prints/exports `skip`, `filter` (core:
# controllers + policies) and `filter_svc` (app/Services + app/Auth)
# for $GITHUB_OUTPUT.
set -euo pipefail

BASE_REF="${1:?usage: infection-changed-files.sh <base-ref>}"

# Changed files in the Services/Auth mutation scope (infection-services-auth.json5)
SVC_FILES=$(git diff --name-only "origin/${BASE_REF}...HEAD" -- \
    'app/Services/*.php' \
    'app/Auth/*.php' \
    | grep -v '^$' || true)

# Changed files in the core mutation scope (infection.json5):
# the critical controllers + policies watchlist
CORE_FILES=$(git diff --name-only "origin/${BASE_REF}...HEAD" -- \
    'app/Http/Controllers/AuthenticateController.php' \
    'app/Http/Controllers/TokenController.php' \
    'app/Policies/TorrentPolicy.php' \
    'app/Policies/UserPolicy.php' \
    'app/Policies/UsercpPolicy.php' \
    'app/Policies/TopicPolicy.php' \
    'app/Policies/PostPolicy.php' \
    'app/Policies/MessagePolicy.php' \
    | grep -v '^$' || true)

build_filter() {
    echo "$1" | sed 's|^app/||' | tr '\n' ',' | sed 's/,$//'
}

if [ -z "$SVC_FILES" ] && [ -z "$CORE_FILES" ]; then
    echo "skip=true" >> "$GITHUB_OUTPUT"
    echo "No critical files changed — skipping mutation testing."
else
    echo "skip=false" >> "$GITHUB_OUTPUT"
    echo "Changed critical files (services/auth):"
    echo "$SVC_FILES"
    echo "Changed critical files (core):"
    echo "$CORE_FILES"
    if [ -n "$SVC_FILES" ]; then
        echo "filter_svc=$(build_filter "$SVC_FILES")" >> "$GITHUB_OUTPUT"
    fi
    if [ -n "$CORE_FILES" ]; then
        echo "filter=$(build_filter "$CORE_FILES")" >> "$GITHUB_OUTPUT"
    fi
fi
