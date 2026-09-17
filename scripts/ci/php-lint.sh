#!/usr/bin/env bash
# php -l over every PHP file outside generated/vendor trees.
set -euo pipefail

find . -type f -name '*.php' \
    -not -path './vendor/*' \
    -not -path './node_modules/*' \
    -not -path './.docker/*' \
    -not -path './public/vendor/*' \
    -not -path './storage/*' \
    -print0 | xargs -0 -n1 -P4 php -l
