#!/usr/bin/env node
/**
 * Compare k6 perf results against a stored baseline (W5-04).
 *
 * Usage: node tests/Performance/compare-results.js <baselineDir> <currentDir>
 *
 * Reads *.json reports produced by handleSummary() file outputs
 * ({ scenarios: { name: { p95_ms, ... } } }) and fails (exit 1) when any
 * scenario p95 regresses by more than REGRESSION_THRESHOLD (50%).
 *
 * <baselineDir> may contain either flat *.json files (single baseline run)
 * or one subdirectory per past successful run. With multiple runs the
 * baseline p95 is the per-scenario MEDIAN across runs — a single unusually
 * fast or slow runner can no longer poison or jam the gate, and the gate
 * stays self-recovering: medians keep moving as new runs succeed.
 *
 * Missing baseline files/scenarios are skipped with a warning so the gate
 * can bootstrap before the first baseline exists.
 */

const fs = require('fs');
const path = require('path');

// +50% over the multi-run median: shared-runner p95 variance was observed
// up to ~+40% vs the median, while code-level regressions (N+1, dropped
// index) land at ×2–10. 20% over a single-run baseline false-positived
// on roughly every third runner draw.
const REGRESSION_THRESHOLD = 1.50;

const [, , baselineDir, currentDir] = process.argv;
if (!baselineDir || !currentDir) {
  console.error('usage: compare-results.js <baselineDir> <currentDir>');
  process.exit(2);
}

function median(values) {
  const sorted = [...values].sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  return sorted.length % 2 === 1 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

// Collect baseline scenario p95 samples: file -> scenario -> number[].
const baselineRuns = fs
  .readdirSync(baselineDir, { withFileTypes: true })
  .filter((e) => e.isDirectory())
  .map((e) => path.join(baselineDir, e.name))
  .filter((dir) => fs.readdirSync(dir).some((f) => f.endsWith('.json')));

// Backward compatible: flat *.json directly under baselineDir counts as one run.
const baselineDirs = baselineRuns.length > 0
  ? baselineRuns
  : [baselineDir];

/** @type {Map<string, Map<string, number[]>>} */
const samples = new Map();

for (const dir of baselineDirs) {
  for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.json'))) {
    let report;
    try {
      report = JSON.parse(fs.readFileSync(path.join(dir, file), 'utf8'));
    } catch {
      continue;
    }
    for (const [scenario, data] of Object.entries(report.scenarios ?? {})) {
      if (!data || typeof data.p95_ms !== 'number' || data.p95_ms <= 0) {
        continue;
      }
      if (!samples.has(file)) {
        samples.set(file, new Map());
      }
      const perScenario = samples.get(file);
      if (!perScenario.has(scenario)) {
        perScenario.set(scenario, []);
      }
      perScenario.get(scenario).push(data.p95_ms);
    }
  }
}

console.log(`Baseline runs aggregated: ${baselineDirs.length}`);

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
  const fileSamples = samples.get(file);

  if (!fileSamples) {
    console.log(`SKIP ${file}: no baseline report`);
    skipped++;
    continue;
  }

  for (const [scenario, cur] of Object.entries(current.scenarios ?? {})) {
    const values = fileSamples.get(scenario);
    if (!values || values.length === 0) {
      console.log(`SKIP ${file}:${scenario}: no baseline p95`);
      skipped++;
      continue;
    }

    const baseP95 = median(values);
    const ratio = cur.p95_ms / baseP95;
    const pct = ((ratio - 1) * 100).toFixed(1);
    compared++;

    if (ratio > REGRESSION_THRESHOLD) {
      console.log(
        `REGRESSION ${file}:${scenario}: p95 median(${values.length}) ${baseP95.toFixed(0)}ms -> ${cur.p95_ms}ms (+${pct}%)`
      );
      regressions++;
    } else {
      console.log(
        `ok ${file}:${scenario}: p95 median(${values.length}) ${baseP95.toFixed(0)}ms -> ${cur.p95_ms}ms (${pct >= 0 ? '+' : ''}${pct}%)`
      );
    }
  }
}

console.log(`\nCompared ${compared} scenario(s): ${regressions} regression(s), ${skipped} skipped.`);

if (regressions > 0) {
  console.error(`FAIL: ${regressions} scenario(s) regressed by more than 50%.`);
  process.exit(1);
}
