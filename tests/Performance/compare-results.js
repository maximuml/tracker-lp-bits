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

// A regression needs BOTH legs: p95 above 1.5x the multi-run median AND
// more than 100ms worse in absolute terms. Shared-runner noise was
// observed up to +110% on ~60ms-baseline pages (deltas 65-77ms), so a
// purely relative threshold false-positives on the lightest scenarios,
// while a purely absolute one misses big relative hits on fast paths.
// Code-level regressions (N+1, dropped index) land at ×2-10 with
// deltas well past 100ms. What this gate honestly cannot see: a uniform
// +50-80ms regression — that sits below the noise floor of shared
// runners and needs trend-watching, not a blocking gate.
const REGRESSION_RATIO = 1.50;
const REGRESSION_ABS_MS = 100;

// announce.json gets a wider gate: those scenarios run the real announce
// write path under multi-VU load, so shared-runner CPU contention scales
// their p95 *multiplicatively* — observed on unrelated/doc-only PRs at
// ×1.6–2.9 (+167–526ms) while every page metric improved. The additive
// noiseShift above cannot absorb that. Values sit just past the observed
// noise: a true announce regression the gate can still see is the
// order-of-magnitude kind (which k6's own absolute budgets also bound at
// p95<1000). Sub-×3 announce drift is honestly undetectable on shared
// runners — trend-watching, not a blocking gate.
const FILE_THRESHOLDS = {
  'announce.json': { ratio: 3.5, absMs: 600 },
};

// Runner noise floor: page_health_live_duration does no app work, so its
// p95 delta vs baseline measures the per-run constant added by the shared
// runner (observed: a uniform +140-170ms on every scenario, health checks
// included). Subtract it from every other scenario's delta so a globally
// slow runner cannot fail a clean run. Capped so a pathological control
// value cannot mask real regressions — the absolute per-scenario budgets
// in the k6 thresholds remain the hard gate for that.
const CONTROL_FILE = 'baseline.json';
const CONTROL_SCENARIO = 'page_health_live_duration';
const NOISE_FLOOR_CAP_MS = 200;

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

// Measure the shared-runner noise floor from the control scenario before
// comparing. The same job runs all files on one runner, so a single global
// shift applies to announce.json too.
let noiseShift = 0;
const controlBase = samples.get(CONTROL_FILE)?.get(CONTROL_SCENARIO);
const controlCurrentPath = path.join(currentDir, CONTROL_FILE);
if (controlBase && controlBase.length > 0 && fs.existsSync(controlCurrentPath)) {
  try {
    const curControl = JSON.parse(fs.readFileSync(controlCurrentPath, 'utf8'))?.scenarios?.[CONTROL_SCENARIO]?.p95_ms;
    if (typeof curControl === 'number' && curControl > 0) {
      noiseShift = Math.min(Math.max(0, curControl - median(controlBase)), NOISE_FLOOR_CAP_MS);
    }
  } catch {
    // No usable control sample — noiseShift stays 0.
  }
}
if (noiseShift > 0) {
  console.log(`Runner noise floor: +${noiseShift.toFixed(0)}ms (control ${CONTROL_SCENARIO}); subtracting from scenario deltas`);
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
  const fileSamples = samples.get(file);

  if (!fileSamples) {
    console.log(`SKIP ${file}: no baseline report`);
    skipped++;
    continue;
  }

  for (const [scenario, cur] of Object.entries(current.scenarios ?? {})) {
    // The control scenario defines the noise floor — its own delta is runner
    // noise by definition, and its absolute budget is enforced by k6.
    // page_health_ready_duration is exempt for the same reason: it probes
    // DB/Redis connectivity, so cold-start connection setup dominates its
    // p95 (observed: +722% one-off spike on php8 and PR runs alike). Its
    // absolute budget stays enforced by the k6 threshold.
    if (scenario === CONTROL_SCENARIO || scenario === 'page_health_ready_duration') {
      console.log(`SKIP ${file}:${scenario}: noise-floor control`);
      skipped++;
      continue;
    }
    const values = fileSamples.get(scenario);
    if (!values || values.length === 0) {
      console.log(`SKIP ${file}:${scenario}: no baseline p95`);
      skipped++;
      continue;
    }

    const limits = FILE_THRESHOLDS[file] ?? { ratio: REGRESSION_RATIO, absMs: REGRESSION_ABS_MS };
    const baseP95 = median(values);
    const adjusted = cur.p95_ms - noiseShift;
    const ratio = adjusted / baseP95;
    const pct = ((ratio - 1) * 100).toFixed(1);
    compared++;

    const delta = adjusted - baseP95;
    if (ratio > limits.ratio && delta > limits.absMs) {
      console.log(
        `REGRESSION ${file}:${scenario}: p95 median(${values.length}) ${baseP95.toFixed(0)}ms -> ${cur.p95_ms}ms (+${pct}%, +${delta.toFixed(0)}ms adjusted)`
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
  console.error(`FAIL: ${regressions} scenario(s) regressed (>${((REGRESSION_RATIO - 1) * 100).toFixed(0)}% AND >${REGRESSION_ABS_MS}ms; per-file overrides may apply).`);
  process.exit(1);
}
