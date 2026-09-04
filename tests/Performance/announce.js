/**
 * k6 load test for the BitTorrent tracker endpoints (announce + scrape).
 *
 * T-17: Realistic tracker load test with:
 * - Passkey-authenticated announce requests
 * - Scrape endpoint testing
 * - Proper status checks (200 for valid announce, not !== 0)
 * - http_req_failed < 0.01
 * - Separate from web UI baseline (different performance characteristics)
 *
 * Usage:
 *   k6 run tests/Performance/announce.js
 *   k6 run --env BASE_URL=http://127.0.0.1:80 --env PASSKEY=<passkey> tests/Performance/announce.js
 *
 * CI: .github/workflows/perf-budget.yml runs this against Docker stack.
 */

import http from 'k6/http';
import { check, group } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:80';
// Default passkey from PerformanceTestDatasetSeeder (perf_user_1)
const PASSKEY = __ENV.PASSKEY || 'test-passkey-0001-aaaaaaaaaaaaaaaaaaaaaaaaaa';
// Default info_hash from seeder (md5 of 'perf-torrent-1')
const INFO_HASH = __ENV.INFO_HASH || 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

// Custom metrics
const announceDuration = new Trend('announce_duration', true);
const scrapeDuration = new Trend('scrape_duration', true);
const announceErrors = new Counter('announce_errors');
const scrapeErrors = new Counter('scrape_errors');
const announceSuccessRate = new Rate('announce_success_rate');
const scrapeSuccessRate = new Rate('scrape_success_rate');

export const options = {
  stages: [
    { duration: '10s', target: 20 },   // ramp up to 20 VUs
    { duration: '30s', target: 20 },   // hold at 20 VUs
    { duration: '10s', target: 50 },   // ramp up to 50 VUs
    { duration: '20s', target: 50 },   // hold at 50 VUs
    { duration: '10s', target: 0 },    // ramp down
  ],
  thresholds: {
    // T-17: Strict failure rate — < 1%
    'http_req_failed': ['rate<0.01'],
    // Announce should respond in <500ms even under load
    'announce_duration': ['p(95)<500', 'p(99)<1000'],
    // Scrape should respond in <300ms
    'scrape_duration': ['p(95)<300', 'p(99)<500'],
    // Success rate for valid announce requests
    'announce_success_rate': ['rate>0.95'],
    'scrape_success_rate': ['rate>0.95'],
  },
};

export default function () {
  // ── Announce with passkey (authenticated) ─────────────────────────────
  group('announce', () => {
    const params = new URLSearchParams({
      passkey: PASSKEY,
      info_hash: INFO_HASH,
      peer_id: '-k6perf-' + Math.random().toString(36).substring(7),
      port: '51413',
      uploaded: '0',
      downloaded: '0',
      left: '0',
      numwant: '50',
      key: Math.random().toString(36).substring(7),
      compact: '1',
      supportcrypto: '0',
    });

    const res = http.get(`${BASE_URL}/announce.php?${params.toString()}`);
    announceDuration.add(res.timings.duration);

    // T-17: Proper status check — 200 for valid announce
    // (may get 400/403 for invalid passkey, but should not be 0)
    const ok = check(res, {
      'announce status 200': (r) => r.status === 200,
      'announce responds quickly': (r) => r.timings.duration < 500,
    });

    if (ok) {
      announceSuccessRate.add(true);
    } else {
      announceSuccessRate.add(false);
      announceErrors.add(1);
    }
  });

  // ── Scrape with passkey (authenticated) ───────────────────────────────
  group('scrape', () => {
    const params = new URLSearchParams({
      passkey: PASSKEY,
      info_hash: INFO_HASH,
    });

    const res = http.get(`${BASE_URL}/scrape.php?${params.toString()}`);
    scrapeDuration.add(res.timings.duration);

    // T-17: Proper status check — 200 for valid scrape
    const ok = check(res, {
      'scrape status 200': (r) => r.status === 200,
      'scrape responds quickly': (r) => r.timings.duration < 300,
    });

    if (ok) {
      scrapeSuccessRate.add(true);
    } else {
      scrapeSuccessRate.add(false);
      scrapeErrors.add(1);
    }
  });
}

export function handleSummary(data) {
  const report = {
    endpoints: {
      announce: {
        total_requests: data.metrics.announce_duration?.values?.count ?? 0,
        p50_ms: Math.round(data.metrics.announce_duration?.values?.['p(50)'] ?? 0),
        p95_ms: Math.round(data.metrics.announce_duration?.values?.['p(95)'] ?? 0),
        p99_ms: Math.round(data.metrics.announce_duration?.values?.['p(99)'] ?? 0),
        errors: data.metrics.announce_errors?.values?.count ?? 0,
        success_rate: (data.metrics.announce_success_rate?.values?.rate ?? 0).toFixed(4),
      },
      scrape: {
        total_requests: data.metrics.scrape_duration?.values?.count ?? 0,
        p50_ms: Math.round(data.metrics.scrape_duration?.values?.['p(50)'] ?? 0),
        p95_ms: Math.round(data.metrics.scrape_duration?.values?.['p(95)'] ?? 0),
        p99_ms: Math.round(data.metrics.scrape_duration?.values?.['p(99)'] ?? 0),
        errors: data.metrics.scrape_errors?.values?.count ?? 0,
        success_rate: (data.metrics.scrape_success_rate?.values?.rate ?? 0).toFixed(4),
      },
    },
    http_req_failed_rate: data.metrics.http_req_failed?.values?.rate ?? 0,
  };

  return {
    stdout: JSON.stringify(report, null, 2) + '\n',
  };
}
