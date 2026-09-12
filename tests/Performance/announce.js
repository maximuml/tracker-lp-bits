/**
 * k6 load test for the BitTorrent tracker endpoints (announce + scrape).
 *
 * W5-04: Blocking tracker load gate. Scenarios:
 * - invalid_passkey_flood: bad passkeys must be rejected cheaply
 * - steady_announce: baseline valid announces across several torrents
 * - many_peers_one_torrent: many distinct peer_ids on one info_hash
 * - early_announce: same peer re-announcing inside the dedup window
 * - multi_hash_scrape: scrape with several info_hash params
 *
 * Tracker replies are always HTTP 200 with a bencoded body — failures
 * carry 'failure reason', warnings carry 'warning message'. The
 * BitTorrent peer_id must be exactly 20 bytes.
 *
 * Usage:
 *   k6 run tests/Performance/announce.js
 *   k6 run --env BASE_URL=http://127.0.0.1:80 --env PASSKEY=<passkey> \
 *       --env INFO_HASH=<hash> --env INFO_HASHES=<h1,h2,...> tests/Performance/announce.js
 *   DEBUG_BODY=1 prints the first failing response bodies per scenario.
 *
 * CI: .github/workflows/perf-budget.yml runs this against Docker stack and
 * compares p95 against the baseline artifact (>20% regression fails).
 */

import http from 'k6/http';
import exec from 'k6/execution';
import { check, group, sleep } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:80';
// Passkey from PerformanceTestDatasetSeeder (perf_user_1) — extracted via tinker in CI
const PASSKEY = __ENV.PASSKEY || '';
// Info hash from PerformanceTestDatasetSeeder (perf-torrent-1) — extracted via tinker in CI
const INFO_HASH = __ENV.INFO_HASH || '';
// Comma-separated extra hashes for multi-hash scrape (perf-torrent-1..5)
const INFO_HASHES = (__ENV.INFO_HASHES || INFO_HASH).split(',').filter((h) => h.length > 0);
// Comma-separated passkeys (perf_user_1..10) — the tracker allows only ONE
// leeching peer per (user, torrent), so multi-peer scenarios must spread
// across users, not just peer_ids.
const PASSKEYS = (__ENV.PASSKEYS || PASSKEY).split(',').filter((k) => k.length > 0);
const DEBUG_BODY = __ENV.DEBUG_BODY === '1';

// Per-scenario metrics
const steadyDuration = new Trend('announce_steady_duration', true);
const floodDuration = new Trend('announce_flood_duration', true);
const manyPeersDuration = new Trend('announce_many_peers_duration', true);
const manyPeersSuccess = new Rate('announce_many_peers_success_rate');
const earlyDuration = new Trend('announce_early_duration', true);
const scrapeMultiDuration = new Trend('scrape_multi_duration', true);

const announceErrors = new Counter('announce_errors');
const scrapeErrors = new Counter('scrape_errors');
const steadyRequests = new Counter('steady_announce_requests');
const floodRequests = new Counter('invalid_passkey_flood_requests');
const manyPeersRequests = new Counter('many_peers_requests');
const earlyRequests = new Counter('early_announce_requests');
const scrapeRequests = new Counter('multi_hash_scrape_requests');
const steadySuccess = new Rate('announce_steady_success_rate');
const floodRejected = new Rate('announce_flood_rejected_rate');
const earlyHandled = new Rate('announce_early_handled_rate');
const scrapeSuccess = new Rate('scrape_success_rate');

const loggedBodies = {};

function debugBody(scenario, res) {
  if (!DEBUG_BODY || loggedBodies[scenario]) {
    return;
  }
  loggedBodies[scenario] = true;
  console.log(`[${scenario}] status=${res.status} body=${String(res.body).substring(0, 200)}`);
}

/**
 * Build a protocol-valid peer_id that passes the seeded agent allowlist:
 * qBittorrent 5.x layout '-qB5' + 2 digits + '-' + 12 chars = 20 bytes,
 * unique per (tag, n). The matching User-Agent header is sent per request.
 */
const PEER_ID_PREFIX = '-qB5000-';
const CLIENT_UA = 'qBittorrent/5.0.0';
const TRACKER_PARAMS = { headers: { 'User-Agent': CLIENT_UA } };

function peerId(tag, n) {
  return (PEER_ID_PREFIX + tag + n.toString(36)).padEnd(20, '0');
}

export const options = {
  scenarios: {
    // NOTE: tracker routes sit behind `throttle:tracker` (120 req/min per IP).
    // Valid-traffic scenarios run FIRST and stay under that budget; the
    // passkey flood runs LAST because it deliberately exhausts the limit —
    // after it fires, every request from this IP is 429 for ~a minute.
    // Baseline announce path under modest steady load (~1.6 req/s total).
    steady_announce: {
      executor: 'constant-vus',
      vus: 2,
      duration: '30s',
      exec: 'steadyAnnounce',
      startTime: '0s',
    },
    // Many distinct peers joining one torrent — 8 concurrent VUs x 2
    // iterations = 16 announces spread across users 2-9 (users 0-1 are
    // taken by steady_announce; a second leecher per user+torrent fails).
    many_peers_one_torrent: {
      executor: 'per-vu-iterations',
      vus: 8,
      iterations: 2,
      maxDuration: '30s',
      exec: 'manyPeers',
      startTime: '35s',
    },
    // Same peer_id re-announcing inside the dedup/frequency window —
    // must be answered fast (warning response), not stalled.
    early_announce: {
      executor: 'constant-vus',
      vus: 1,
      duration: '15s',
      exec: 'earlyAnnounce',
      startTime: '42s',
    },
    // Scrape aggregating several info_hash params per request.
    multi_hash_scrape: {
      executor: 'constant-vus',
      vus: 2,
      duration: '20s',
      exec: 'multiHashScrape',
      startTime: '62s',
    },
    // Cheap rejection must stay cheap under a flood of bad passkeys.
    // Both bencoded 'failure reason' and HTTP 429 count as rejected —
    // either way the tracker stays up and answers quickly.
    invalid_passkey_flood: {
      executor: 'constant-arrival-rate',
      rate: 10,
      timeUnit: '1s',
      duration: '20s',
      preAllocatedVUs: 5,
      maxVUs: 10,
      exec: 'invalidPasskeyFlood',
      startTime: '90s',
    },
  },
  thresholds: {
    // Absolute blocking floors — the regression gate adds the relative part.
    'announce_steady_duration': ['p(95)<1000'],
    'announce_flood_duration': ['p(95)<500'],
    'announce_many_peers_duration': ['p(95)<1000'],
    'announce_early_duration': ['p(95)<500'],
    'scrape_multi_duration': ['p(95)<500'],
    'announce_steady_success_rate': ['rate>0.80'],
    'announce_many_peers_success_rate': ['rate>0.80'],
    'announce_flood_rejected_rate': ['rate>0.95'],
    'announce_early_handled_rate': ['rate>0.95'],
    'scrape_success_rate': ['rate>0.80'],
  },
};

function announceUrl(peerId, infoHash, passkey) {
  const key = Math.random().toString(36).substring(7);
  return `${BASE_URL}/announce.php?passkey=${passkey || PASSKEY}&info_hash=${infoHash}` +
    `&peer_id=${encodeURIComponent(peerId)}&port=51413&uploaded=0&downloaded=1024&left=1048576` +
    `&numwant=50&key=${key}&compact=1&supportcrypto=0`;
}

export function invalidPasskeyFlood() {
  group('invalid_passkey_flood', () => {
    // Format-valid but unknown passkey — exercises the full rejection path.
    const badKey = 'deadbeef' + Math.random().toString(16).substring(2, 26).padEnd(24, '0');
    const res = http.get(
      `${BASE_URL}/announce.php?passkey=${badKey}&info_hash=${INFO_HASH}` +
      `&peer_id=${encodeURIComponent(peerId('F', __ITER))}&port=51413&uploaded=0&downloaded=0&left=1&compact=1`,
      TRACKER_PARAMS,
    );
    floodDuration.add(res.timings.duration);
    floodRequests.add(1);
    const rejected = check(res, {
      'flood rejected fast': (r) =>
        r.status === 429 || (r.status === 200 && r.body.indexOf('failure reason') !== -1),
    });
    if (!rejected) {
      debugBody('invalid_passkey_flood', res);
    }
    floodRejected.add(rejected);
  });
}

export function steadyAnnounce() {
  group('steady_announce', () => {
    // The tracker allows one leeching peer per (user, torrent). We keep a
    // stable peer_id per (user, info_hash) pair: the first announce for a
    // pair inserts, later iterations are legal re-announces of the same
    // peer. iterationInTest is scenario-global, so the mapping does not
    // depend on which VU executes the iteration. Users 0-1 are reserved
    // for this scenario — many_peers_one_torrent takes users 2-9.
    const n = INFO_HASHES.length > 0 ? INFO_HASHES.length : 1;
    const seq = exec.scenario.iterationInTest;
    const hashIdx = seq % n;
    const userIdx = Math.floor(seq / n) % 2;
    const peer = peerId('S', userIdx * n + hashIdx);
    const infoHash = INFO_HASHES.length > 0 ? INFO_HASHES[hashIdx] : INFO_HASH;
    const passkey = PASSKEYS[userIdx];
    const res = http.get(announceUrl(peer, infoHash, passkey), TRACKER_PARAMS);
    steadyDuration.add(res.timings.duration);
    steadyRequests.add(1);
    const ok = check(res, {
      'announce 200': (r) => r.status === 200,
      'announce no failure': (r) => r.body.indexOf('failure reason') === -1,
    });
    if (!ok) {
      debugBody('steady_announce', res);
      announceErrors.add(1);
    }
    steadySuccess.add(ok);
    sleep(1.2); // keep total rate under the 120 req/min tracker throttle
  });
}

export function manyPeers() {
  group('many_peers_one_torrent', () => {
    // Each iteration maps to one of users 2-9 (users 0-1 are taken by
    // steady_announce) leeching the same torrent. The peer_id is stable
    // per user, so a user's second iteration is a legal re-announce.
    // iterationInTest is scenario-global — VU scheduling does not matter.
    const i = exec.scenario.iterationInTest;
    const userIdx = 2 + (i % (PASSKEYS.length - 2));
    const peer = peerId('M', userIdx);
    const passkey = PASSKEYS[userIdx];
    const res = http.get(announceUrl(peer, INFO_HASH, passkey), TRACKER_PARAMS);
    manyPeersDuration.add(res.timings.duration);
    manyPeersRequests.add(1);
    const ok = check(res, {
      'peer announce 200': (r) => r.status === 200,
      'peer no failure': (r) => r.body.indexOf('failure reason') === -1,
    });
    if (!ok) {
      debugBody('many_peers_one_torrent', res);
      announceErrors.add(1);
    }
    manyPeersSuccess.add(ok);
  });
}

export function earlyAnnounce() {
  group('early_announce', () => {
    // Last hash + last user — steady_announce already leeches the first
    // hashes as users 0-1, and a second leecher per user+torrent fails.
    const hash = INFO_HASHES.length > 0 ? INFO_HASHES[INFO_HASHES.length - 1] : INFO_HASH;
    const res = http.get(announceUrl(peerId('E', 1), hash, PASSKEYS[PASSKEYS.length - 1]), TRACKER_PARAMS);
    earlyDuration.add(res.timings.duration);
    earlyRequests.add(1);
    // Dedup window answers with a warning response — still HTTP 200 and fast.
    const handled = check(res, {
      'early announce answered': (r) => r.status === 200,
    });
    if (!handled) {
      debugBody('early_announce', res);
    }
    earlyHandled.add(handled);
    sleep(1); // inside the 5s dedup window each iteration
  });
}

export function multiHashScrape() {
  group('multi_hash_scrape', () => {
    const qs = INFO_HASHES.map((h) => `info_hash=${h}`).join('&');
    const res = http.get(`${BASE_URL}/scrape.php?passkey=${PASSKEY}&${qs}`, TRACKER_PARAMS);
    scrapeMultiDuration.add(res.timings.duration);
    scrapeRequests.add(1);
    const ok = check(res, {
      'scrape 200': (r) => r.status === 200,
      'scrape no failure': (r) => r.body.indexOf('failure reason') === -1,
      'scrape has files': (r) => r.body.indexOf('files') !== -1,
    });
    if (!ok) {
      debugBody('multi_hash_scrape', res);
      scrapeErrors.add(1);
    }
    scrapeSuccess.add(ok);
    sleep(0.8); // ~1.7 req/s total — under the tracker throttle
  });
}

export function handleSummary(data) {
  const m = data.metrics;
  const p95 = (name) => Math.round(m[name]?.values?.['p(95)'] ?? 0);
  // Per-scenario Counters — k6 does not emit per-scenario http_reqs
  // submetrics to handleSummary, so request counts come from our own metrics.
  const requests = (name) => m[`${name}_requests`]?.values?.count ?? 0;
  const rate = (name) => (m[name]?.values?.rate ?? 0);

  const report = {
    commit: __ENV.GITHUB_SHA || 'local',
    run_at: new Date().toISOString(),
    scenarios: {
      invalid_passkey_flood: { p95_ms: p95('announce_flood_duration'), requests: requests('invalid_passkey_flood'), rejected_rate: +rate('announce_flood_rejected_rate').toFixed(4) },
      steady_announce: { p95_ms: p95('announce_steady_duration'), requests: requests('steady_announce'), success_rate: +rate('announce_steady_success_rate').toFixed(4) },
      many_peers_one_torrent: { p95_ms: p95('announce_many_peers_duration'), requests: requests('many_peers'), success_rate: +rate('announce_many_peers_success_rate').toFixed(4) },
      early_announce: { p95_ms: p95('announce_early_duration'), requests: requests('early_announce'), handled_rate: +rate('announce_early_handled_rate').toFixed(4) },
      multi_hash_scrape: { p95_ms: p95('scrape_multi_duration'), requests: requests('multi_hash_scrape'), success_rate: +rate('scrape_success_rate').toFixed(4) },
    },
  };

  return {
    stdout: JSON.stringify(report, null, 2) + '\n',
    'perf-results/announce.json': JSON.stringify(report, null, 2),
  };
}
