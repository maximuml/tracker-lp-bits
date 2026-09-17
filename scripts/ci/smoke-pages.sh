#!/usr/bin/env bash
# curl smoke over public pages: every arg is a path expected to return
# an HTTP status below 400.
set -euo pipefail

for page in "$@"; do
    echo "Testing $page"
    curl -sfL --max-time 10 "http://localhost${page}" -o /dev/null
done
