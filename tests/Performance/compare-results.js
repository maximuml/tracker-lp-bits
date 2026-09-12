#!/usr/bin/env node
/**
 * Compare k6 perf results against a stored baseline (W5-04).
 *
 * Usage: node tests/Performance/compare-results.js <baselineDir> <currentDir>
 *
 * Reads *.json reports produced by handleSummary() file outputs
 * ({ scenarios: { name: { p95_ms, ... } } }) and fails (exit 1) when any
 * scenario p95 regresses by more than REGRESSION_THRESHOLD (20%).
 *
 * Missing baseline files/scenarios are skipped with a warning so the gate
 * can bootstrap before the first baseline exists.
 */

const fs = require('fs');
const path = require('path');

const REGRESSION_THRESHOLD = 1.20;

const [, , baselineDir, currentDir] = process.argv;
if (!baselineDir || !currentDir) {
  console.error('usage: compare-results.js <baselineDir> <currentDir>');
  process.exit(2);
}

let regressions = 0;
let compared = 0;
let skipped = 0;

for (const file of fs.readdirSync(currentDir).filter((f) => f.endsWith('.json'))) {
  // The Redis-degradation probe is a correctness check (bounded failure), not
  // a latency baseline — comparing it against a healthy run would be noise.
  if (file.startsWith('redis-')) {
    console.log(`SKIP ${file}: degradation probe, not a baseline metric`);
    skipped++;
    continue;
  }

  const current = JSON.parse(fs.readFileSync(path.join(currentDir, file), 'utf8'));
  const baselinePath = path.join(baselineDir, file);

  if (!fs.existsSync(baselinePath)) {
    console.log(`SKIP ${file}: no baseline report`);
    skipped++;
    continue;
  }

  const baseline = JSON.parse(fs.readFileSync(baselinePath, 'utf8'));

  for (const [scenario, cur] of Object.entries(current.scenarios ?? {})) {
    const base = baseline.scenarios?.[scenario];
    if (!base || !base.p95_ms || base.p95_ms <= 0) {
      console.log(`SKIP ${file}:${scenario}: no baseline p95`);
      skipped++;
      continue;
    }

    const ratio = cur.p95_ms / base.p95_ms;
    const pct = ((ratio - 1) * 100).toFixed(1);
    compared++;

    if (ratio > REGRESSION_THRESHOLD) {
      console.log(`REGRESSION ${file}:${scenario}: p95 ${base.p95_ms}ms -> ${cur.p95_ms}ms (+${pct}%)`);
      regressions++;
    } else {
      console.log(`ok ${file}:${scenario}: p95 ${base.p95_ms}ms -> ${cur.p95_ms}ms (${pct >= 0 ? '+' : ''}${pct}%)`);
    }
  }
}

console.log(`\nCompared ${compared} scenario(s): ${regressions} regression(s), ${skipped} skipped.`);

if (regressions > 0) {
  console.error(`FAIL: ${regressions} scenario(s) regressed by more than 20%.`);
  process.exit(1);
}
