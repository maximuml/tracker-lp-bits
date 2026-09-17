#!/usr/bin/env bash
# Validate .trivyignore: every CVE entry must carry a future
# '# expires: YYYY-MM-DD' comment so exceptions cannot silently
# become permanent.
set -euo pipefail

if [ ! -f .trivyignore ]; then
    echo "No .trivyignore file — nothing to validate."
    exit 0
fi
TODAY=$(date -u +%Y-%m-%d)
ERRORS=0
CVE=""
while IFS= read -r line; do
    # Skip empty lines and full-line comments
    case "$line" in
        ""|"#"*) continue ;;
    esac
    if echo "$line" | grep -qE '^CVE-[0-9]'; then
        CVE="$line"
    elif echo "$line" | grep -qE '^# expires:'; then
        if [ -z "$CVE" ]; then
            echo "::error::Expiry comment without preceding CVE ID: $line"
            ERRORS=$((ERRORS + 1))
            continue
        fi
        EXPIRY=$(echo "$line" | sed -nE 's/^# expires: ([0-9]{4}-[0-9]{2}-[0-9]{2}).*/\1/p')
        if [ -z "$EXPIRY" ]; then
            echo "::error::Missing expiry date for $CVE: $line"
            ERRORS=$((ERRORS + 1))
        elif [ "$EXPIRY" \< "$TODAY" ]; then
            echo "::error::Expired .trivyignore entry: $CVE (expired $EXPIRY). Remove or update."
            ERRORS=$((ERRORS + 1))
        else
            echo "OK: $CVE expires $EXPIRY"
        fi
        CVE=""
    fi
done < .trivyignore
if [ "$ERRORS" -gt 0 ]; then
    echo "::error::$ERRORS invalid or expired .trivyignore entries found."
    exit 1
fi
echo "All .trivyignore entries are valid and not expired."
