/**
 * k6 load test for the announce endpoint.
 *
 * Step 23 of the modernization plan: load test announce separately
 * from web UI, since announce has different performance characteristics
 * (high frequency, lightweight responses, no HTML rendering).
 *
 * This test does NOT enforce budgets (unlike baseline.js) — it's
 * designed to measure throughput and identify bottlenecks under load.
 *
 * Usage:
 *   k6 run tests/Performance/announce.js
 *   k6 run --env BASE_URL=http://127.0.0.1:80 tests/Performance/announce.js
 *
 * CI: Can be run separately from the web UI baseline to isolate
 * announce performance regressions.
 */

import http from 'k6/http';
import { check, group } from 'k6';
import { Trend, Counter } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:80';

// Custom metrics for announce
const announceDuration = new Trend('announce_duration', true);
const announceErrors = new Counter('announce_errors');

// Announce typically returns non-200 for unauthenticated requests
// (missing passkey), but should respond quickly regardless.
export const options = {
  stages: [
    { duration: '10s', target: 20 },   // ramp up to 20 VUs
    { duration: '30s', target: 20 },   // hold at 20 VUs
    { duration: '10s', target: 50 },   // ramp up to 50 VUs
    { duration: '20s', target: 50 },   // hold at 50 VUs
    { duration: '10s', target: 0 },    // ramp down
  ],
  thresholds: {
    // Announce should respond in <500ms even under load
    'announce_duration': ['p(95)<500', 'p(99)<1000'],
    // Error rate (non-200) is expected for unauthenticated announce,
    // but we track it separately
  },
};

export default function () {
  group('announce', () => {
    // Announce without passkey — should get a fast error response
    const res = http.get(`${BASE_URL}/announce.php`);

    announceDuration.add(res.timings.duration);

    const ok = check(res, {
      'announce responds': (r) => r.status !== 0,
      'announce responds quickly': (r) => r.timings.duration < 500,
    });

    if (!ok) {
      announceErrors.add(1);
    }
  });
}

export function handleSummary(data) {
  const report = {
    endpoint: 'announce.php',
    summary: {
      total_requests: data.metrics.http_reqs?.values?.count ?? 0,
      p50_ms: Math.round(data.metrics.announce_duration?.values?.['p(50)'] ?? 0),
      p95_ms: Math.round(data.metrics.announce_duration?.values?.['p(95)'] ?? 0),
      p99_ms: Math.round(data.metrics.announce_duration?.values?.['p(99)'] ?? 0),
      errors: data.metrics.announce_errors?.values?.count ?? 0,
    },
  };

  return {
    stdout: JSON.stringify(report, null, 2) + '\n',
  };
}
