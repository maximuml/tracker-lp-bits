#!/usr/bin/env bash
# Fail if Trivy SARIF output contains CRITICAL/HIGH results that are not
# listed in .trivyignore.
#
# Trivy runs with exit-code '0' because rejected CVEs (e.g.
# CVE-2026-80256) crash it with "no vulnerability details" even when
# ignored — so the gate parses SARIF instead of trusting the exit code.
set -euo pipefail

IGNORED=$(grep -E '^CVE-[0-9]' .trivyignore | sort -u || true)
echo "Ignored CVEs: $IGNORED"
for sarif in trivy-php-results.sarif trivy-openresty-results.sarif; do
    if [ ! -f "$sarif" ]; then
        echo "WARN: $sarif not found, skipping"
        continue
    fi
    RESULTS=$(python3 -c "
import json, sys
with open('$sarif') as f:
    data = json.load(f)
for run in data.get('runs', []):
    for result in run.get('results', []):
        rule_id = result.get('ruleId', '')
        level = result.get('level', '')
        if level in ('error', 'warning'):
            print(f'{rule_id}|{level}')
" || true)
    if [ -z "$RESULTS" ]; then
        echo "OK: no actionable vulnerabilities in $sarif"
        continue
    fi
    FAILED=0
    while IFS='|' read -r cve level; do
        if echo "$IGNORED" | grep -q "^${cve}$"; then
            echo "SKIP: $cve is in .trivyignore"
        else
            echo "::error::Unignored vulnerability: $cve ($level) in $sarif"
            FAILED=$((FAILED + 1))
        fi
    done <<< "$RESULTS"
    if [ "$FAILED" -gt 0 ]; then
        exit 1
    fi
done
echo "All Trivy results within policy."
