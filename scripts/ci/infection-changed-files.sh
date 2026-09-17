#!/usr/bin/env bash
# W3-04: detect which critical files changed in a PR and build the
# Infection --filter argument. Prints/exports `skip` and `filter` for
# $GITHUB_OUTPUT.
set -euo pipefail

BASE_REF="${1:?usage: infection-changed-files.sh <base-ref>}"

# Get changed files in app/ that are in the Infection source scope
CHANGED_FILES=$(git diff --name-only "origin/${BASE_REF}...HEAD" -- \
    'app/Http/Controllers/AuthenticateController.php' \
    'app/Http/Controllers/TokenController.php' \
    'app/Services/Announce/*.php' \
    'app/Services/AttachmentMutationService.php' \
    'app/Services/OutboxService.php' \
    'app/Services/OutboxDispatcher.php' \
    'app/Services/ThankService.php' \
    'app/Services/TorrentBookmarkService.php' \
    'app/Policies/TorrentPolicy.php' \
    'app/Policies/UserPolicy.php' \
    'app/Policies/UsercpPolicy.php' \
    'app/Policies/TopicPolicy.php' \
    'app/Policies/PostPolicy.php' \
    'app/Policies/MessagePolicy.php' \
    | grep -v '^$' || true)

if [ -z "$CHANGED_FILES" ]; then
    echo "skip=true" >> "$GITHUB_OUTPUT"
    echo "No critical files changed — skipping mutation testing."
else
    echo "skip=false" >> "$GITHUB_OUTPUT"
    echo "Changed critical files:"
    echo "$CHANGED_FILES"
    # Build --filter argument for Infection
    FILTER=$(echo "$CHANGED_FILES" | sed 's|^app/||' | tr '\n' ',' | sed 's/,$//')
    echo "filter=$FILTER" >> "$GITHUB_OUTPUT"
fi
