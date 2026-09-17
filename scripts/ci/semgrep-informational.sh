#!/usr/bin/env bash
# Run the informational Semgrep ruleset and compare per-rule counts
# against .semgrep-baseline.json. The aggregate total used to hide rule
# growth (a rule whose baseline shrank left headroom for others) — fail
# if ANY rule exceeds its own baseline; 'total' stays informational.
set -euo pipefail

semgrep scan --config .semgrep.informational.yml --sarif --output semgrep-info-results.sarif --json > semgrep-info.json 2>/dev/null || true
if [ ! -s semgrep-info.json ]; then
    echo '{"results":[]}' > semgrep-info.json
fi
if [ ! -f semgrep-info-results.sarif ]; then
    echo '{"version":"2.1.0","runs":[{"tool":{"driver":{"name":"Semgrep","informationUri":"https://semgrep.dev","rules":[]}},"results":[]}]}' > semgrep-info-results.sarif
fi
TOTAL=$(python3 -c "import json; print(json.load(open('semgrep-info.json'))['results'].__len__())")
{
    echo "## Semgrep informational results"
    echo "Total findings: **$TOTAL**"
    echo ""
    echo "| Rule | Count |"
    echo "|---|---:|"
    python3 -c "
import json
from collections import Counter
d = json.load(open('semgrep-info.json'))
c = Counter(r['check_id'] for r in d['results'])
for k, v in sorted(c.items()):
    print(f'| {k} | {v} |')
"
} >> "$GITHUB_STEP_SUMMARY"

python3 - <<'PY'
import json
import sys
from collections import Counter

results = json.load(open('semgrep-info.json'))['results']
# check_id may carry a config prefix; rule ids never contain dots.
current = Counter(r['check_id'].rsplit('.', 1)[-1] for r in results)
baseline_doc = json.load(open('.semgrep-baseline.json'))
baseline = baseline_doc['by_rule']

print(f"Total findings: {sum(current.values())} (baseline {baseline_doc['total']})")

failed = False
for rule in sorted(set(current) | set(baseline)):
    cur, base = current.get(rule, 0), baseline.get(rule, 0)
    if cur > base:
        failed = True
        print(f"::error::Semgrep rule '{rule}': {cur} findings > baseline {base}. "
              "Fix the new findings or lower the baseline with justification.")
    else:
        print(f"  {rule}: {cur} <= {base}")

sys.exit(1 if failed else 0)
PY
