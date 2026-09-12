/**
 * k6 tracker load under Redis degradation (W5-04).
 *
 * Run by perf-budget.yml while the Redis container is stopped. The tracker
 * must keep serving announces from the database (RedisGuard fail-open):
 * responses stay HTTP 200 without 'failure reason'. A hang, >3s stall, or
 * 5xx/HTML error page is the failure mode this gates against.
 *
 * Usage:
 *   k6 run --env BASE_URL=http://127.0.0.1:80 --env PASSKEY=<passkey> \
 *       --env INFO_HASH=<hash> tests/Performance/redis-degradation.js
 */

import http from 'k6/http';
import exec from 'k6/execution';
import { check, group } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:80';
const PASSKEY = __ENV.PASSKEY || '';
const INFO_HASH = __ENV.INFO_HASH || '';
// The tracker allows one leeching peer per (user, torrent) — rotate the
// seeded perf users and keep a stable peer_id per user so iterations are
// legal re-announces, not rejected second leechers.
const PASSKEYS = (__ENV.PASSKEYS || PASSKEY).split(',').filter((k) => k.length > 0);
// A torrent the other scenarios never touch — earlier runs leave peer rows,
// and a second leeching peer per (user, torrent) is rejected.
const DEGRADED_HASH = __ENV.DEGRADED_HASH || INFO_HASH;

const degradedDuration = new Trend('announce_redis_down_duration', true);
const degradedAnswered = new Rate('announce_redis_down_answered_rate');
const degradedOk = new Rate('announce_redis_down_success_rate');
const degradedErrors = new Counter('announce_redis_down_errors');
const degradedRequests = new Counter('redis_down_requests');

export const options = {
  scenarios: {
    redis_down_announce: {
      executor: 'constant-arrival-rate',
      rate: 5,
      timeUnit: '1s',
      duration: '15s',
      preAllocatedVUs: 3,
      maxVUs: 5,
      exec: 'degradedAnnounce',
    },
  },
  thresholds: {
    // Bounded response under degradation — no hangs, no multi-second stalls.
    // The first request after the outage pays the Redis connect probe once;
    // the shared breaker then fails fast for ~10s, so p95 stays low.
    'announce_redis_down_duration': ['p(95)<3000'],
    'announce_redis_down_answered_rate': ['rate>0.95'],
    'announce_redis_down_success_rate': ['rate>0.8'],
  },
};

export function degradedAnnounce() {
  group('redis_down_announce', () => {
    // Exactly 20 bytes in a qBittorrent-5.x layout so the seeded agent
    // allowlist accepts it; matching User-Agent goes in the header.
    // iterationInTest is scenario-global (unlike __ITER, which is per-VU)
    // — consecutive requests rotate to different users deterministically.
    const u = exec.scenario.iterationInTest % PASSKEYS.length;
    const peerId = ('-qB5000-D' + u.toString(36)).padEnd(20, '0');
    const res = http.get(
      `${BASE_URL}/announce.php?passkey=${PASSKEYS[u]}&info_hash=${DEGRADED_HASH}` +
      `&peer_id=${encodeURIComponent(peerId)}&port=51413&uploaded=0&downloaded=0&left=1&compact=1`,
      { timeout: '10s', headers: { 'User-Agent': 'qBittorrent/5.0.0' } },
    );
    degradedDuration.add(res.timings.duration);
    degradedRequests.add(1);
    // "Answered" = any HTTP status back within the timeout.
    const answered = check(res, { 'answered': (r) => r.status !== 0 });
    degradedAnswered.add(answered);
    degradedOk.add(res.status === 200 && res.body.indexOf('failure reason') === -1);
    if (!answered) {
      degradedErrors.add(1);
    }
  });
}

export function handleSummary(data) {
  const m = data.metrics;
  const report = {
    commit: __ENV.GITHUB_SHA || 'local',
    run_at: new Date().toISOString(),
    scenarios: {
      redis_down_announce: {
        p95_ms: Math.round(m.announce_redis_down_duration?.values?.['p(95)'] ?? 0),
        requests: m.redis_down_requests?.values?.count ?? 0,
        answered_rate: +(m.announce_redis_down_answered_rate?.values?.rate ?? 0).toFixed(4),
        success_rate: +(m.announce_redis_down_success_rate?.values?.rate ?? 0).toFixed(4),
      },
    },
  };

  return {
    stdout: JSON.stringify(report, null, 2) + '\n',
    'perf-results/redis-degradation.json': JSON.stringify(report, null, 2),
  };
}
