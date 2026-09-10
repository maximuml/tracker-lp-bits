#!/usr/bin/env bash
set -euo pipefail

# Check that every CVE entry in .trivyignore has a future expiry date.
# Usage: check_trivy_expiry.sh [path-to-trivyignore]

file="${1:-.trivyignore}"
if [[ ! -f "$file" ]]; then
    echo "Trivy ignore file not found: $file" >&2
    exit 1
fi

today=$(date -u +%Y-%m-%d)
fail=0
line_no=0

while IFS= read -r line; do
    line_no=$((line_no + 1))
    line="${line%%#*}" # strip in-line comments
    line=$(echo "$line" | tr -d '[:space:]')

    if [[ -z "$line" ]]; then
        continue
    fi

    if [[ ! "$line" =~ ^CVE-[0-9]{4}-[0-9]+$ ]]; then
        continue
    fi

    # Read the next non-empty line
    next_line_no=$((line_no + 1))
    expiry=""
    while IFS= read -r raw; do
        if [[ -n "${raw// }" ]]; then
            expiry="$raw"
            break
        fi
        next_line_no=$((next_line_no + 1))
    done < <(tail -n +"$next_line_no" "$file")

    if [[ -z "$expiry" ]]; then
        echo "$file:$line_no: $line has no expiry comment" >&2
        fail=1
        continue
    fi

    if [[ ! "$expiry" =~ expires:\ *([0-9]{4}-[0-9]{2}-[0-9]{2}) ]]; then
        echo "$file:$line_no: $line expiry line does not contain 'expires: YYYY-MM-DD': $expiry" >&2
        fail=1
        continue
    fi

    expire_date="${BASH_REMATCH[1]}"
    if [[ "$expire_date" < "$today" ]]; then
        echo "$file:$line_no: $line expired on $expire_date (today: $today)" >&2
        fail=1
    fi
done < "$file"

if [[ "$fail" -ne 0 ]]; then
    exit 1
fi

echo "All .trivyignore entries have current expiry dates."
