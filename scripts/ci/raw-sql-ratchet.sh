#!/usr/bin/env bash
# Raw-SQL ratchet: DB::select/statement/unprepared call sites may not
# exceed the registry in raw-queries.json.
set -euo pipefail

BASELINE_COUNT=$(php -r 'echo count(json_decode(file_get_contents("raw-queries.json"), true)["queries"] ?? []);')
CURRENT=$(grep -rn 'DB::\(select\|statement\|unprepared\)\s*(' app/ --include="*.php" \
    | grep -v 'Support/Install' \
    | wc -l)
CURRENT_SUBQUERY=$(grep -rn 'DB::table\s*(\s*DB::raw' app/ --include="*.php" \
    | grep -v 'Support/Install' \
    | wc -l)
TOTAL=$((CURRENT + CURRENT_SUBQUERY))
echo "Baseline: $BASELINE_COUNT"
echo "Current raw queries: $CURRENT"
echo "Current raw subqueries: $CURRENT_SUBQUERY"
echo "Total: $TOTAL"
if [ "$TOTAL" -gt "$BASELINE_COUNT" ]; then
    echo "::error::Raw SQL query count increased from $BASELINE_COUNT to $TOTAL. \
See raw-queries.json for the registry of allowed call sites. \
Use query builder instead of DB::select/statement/unprepared."
    exit 1
fi
echo "OK: raw SQL query count ($TOTAL) <= baseline ($BASELINE_COUNT)"
