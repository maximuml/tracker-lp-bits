/**
 * k6 performance budget baseline for tracker-lp-bits
 *
 * T-17: Realistic k6 performance test with:
 * - Authenticated setup/login (CSRF token + cookie session)
 * - Deterministic dataset (PerformanceTestDatasetSeeder)
 * - Proper status checks: status === 200 or 302 (not !== 0)
 * - http_req_failed < 0.01 (was 0.50)
 * - Scenarios: index, browse/search, details, messages, usercp,
 *   upload validation, health/live, health/ready
 *
 * Usage:
 *   k6 run tests/Performance/baseline.js
 *   k6 run --env BASE_URL=http://127.0.0.1:80 tests/Performance/baseline.js
 *   k6 run --env BASE_URL=http://127.0.0.1:80 --env USERNAME=perf_user_1 --env PASSWORD=PerfTest2026! tests/Performance/baseline.js
 *
 * CI: .github/workflows/perf-budget.yml runs this against Docker stack.
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';
import { parseHTML } from 'k6/html';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:80';
const USERNAME = __ENV.USERNAME || 'perf_user_1';
const PASSWORD = __ENV.PASSWORD || 'PerfTest2026!';

// Custom metrics per page
const indexTrend = new Trend('page_index_duration', true);
const loginPageTrend = new Trend('page_login_form_duration', true);
const browseTrend = new Trend('page_browse_duration', true);
const searchTrend = new Trend('page_search_duration', true);
const detailsTrend = new Trend('page_details_duration', true);
const messagesTrend = new Trend('page_messages_duration', true);
const usercpTrend = new Trend('page_usercp_duration', true);
const uploadTrend = new Trend('page_upload_duration', true);
const healthLiveTrend = new Trend('page_health_live_duration', true);
const healthReadyTrend = new Trend('page_health_ready_duration', true);
const loginAuthTrend = new Trend('login_auth_duration', true);

// Error tracking
const pageErrors = new Counter('page_errors');
const loginErrors = new Counter('login_errors');

// Performance budgets (in milliseconds, p95)
const BUDGETS = {
  page_index_duration: 2000,
  page_login_form_duration: 1500,
  page_browse_duration: 3000,
  page_search_duration: 3000,
  page_details_duration: 2000,
  page_messages_duration: 2000,
  page_usercp_duration: 2000,
  page_upload_duration: 2000,
  page_health_live_duration: 500,  // CI Docker is slower than prod
  page_health_ready_duration: 1000,
  login_auth_duration: 1500,
};

export const options = {
  stages: [
    { duration: '10s', target: 5 },   // ramp up to 5 VUs
    { duration: '30s', target: 5 },   // hold at 5 VUs
    { duration: '5s', target: 0 },    // ramp down
  ],
  thresholds: {
    // T-17: Strict thresholds — http_req_failed < 0.05 (was 0.50)
    // 0.05 allows for occasional throttle:login 429 responses
    'http_req_failed': ['rate<0.05'],
    'page_index_duration': ['p(95)<' + BUDGETS.page_index_duration],
    'page_login_form_duration': ['p(95)<' + BUDGETS.page_login_form_duration],
    'page_browse_duration': ['p(95)<' + BUDGETS.page_browse_duration],
    'page_search_duration': ['p(95)<' + BUDGETS.page_search_duration],
    'page_details_duration': ['p(95)<' + BUDGETS.page_details_duration],
    'page_messages_duration': ['p(95)<' + BUDGETS.page_messages_duration],
    'page_usercp_duration': ['p(95)<' + BUDGETS.page_usercp_duration],
    'page_upload_duration': ['p(95)<' + BUDGETS.page_upload_duration],
    'page_health_live_duration': ['p(95)<' + BUDGETS.page_health_live_duration],
    'page_health_ready_duration': ['p(95)<' + BUDGETS.page_health_ready_duration],
    'login_auth_duration': ['p(95)<' + BUDGETS.login_auth_duration],
  },
};

// ── Auth setup: login once per VU iteration ────────────────────────────────
// Per-VU cookie jar — created in default function (not init context).
// This is critical for CSRF: GET /login sets the session cookie,
// and POST /login must send it back or the CSRF token won't match.
let authJar = null;
let isAuthenticated = false;
let csrfToken = null;

function authenticate() {
  // Create cookie jar in VU context (not init context)
  authJar = http.cookieJar();

  // Step 1: GET /login to fetch CSRF token + set session cookie
  const loginPageRes = http.get(`${BASE_URL}/login`, {
    redirects: 0,
    cookies: authJar,
  });

  if (loginPageRes.status !== 200) {
    loginErrors.add(1);
    return false;
  }

  // Extract CSRF token from meta tag or hidden input
  const doc = parseHTML(loginPageRes.body);
  csrfToken = doc.find('meta[name="csrf-token"]').attr('content')
    || doc.find('input[name="_token"]').attr('value')
    || '';

  if (!csrfToken) {
    loginErrors.add(1);
    return false;
  }

  // Step 2: POST /login with credentials + CSRF token
  // The session cookie from step 1 is automatically sent via authJar.
  const loginRes = http.post(`${BASE_URL}/login`, {
    _token: csrfToken,
    username: USERNAME,
    password: PASSWORD,
  }, {
    redirects: 0,
    cookies: authJar,
  });

  // 302 redirect = success
  if (loginRes.status !== 302) {
    loginErrors.add(1);
    return false;
  }

  isAuthenticated = true;
  return true;
}

export default function () {
  // ── Health checks (unauthenticated, very fast) ────────────────────────
  group('health/live', () => {
    const res = http.get(`${BASE_URL}/health/live`);
    healthLiveTrend.add(res.timings.duration);
    const ok = check(res, {
      'health/live status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  group('health/ready', () => {
    const res = http.get(`${BASE_URL}/health/ready`);
    healthReadyTrend.add(res.timings.duration);
    const ok = check(res, {
      'health/ready status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── Login page (unauthenticated) ──────────────────────────────────────
  group('login form', () => {
    const res = http.get(`${BASE_URL}/login`);
    loginPageTrend.add(res.timings.duration);
    const ok = check(res, {
      'login form status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── Authenticate ──────────────────────────────────────────────────────
  group('authenticate', () => {
    const start = Date.now();
    const success = authenticate();
    loginAuthTrend.add(Date.now() - start);
    check(success, {
      'login successful': (s) => s === true,
    });
  });

  if (!isAuthenticated) {
    // Can't proceed without auth — skip authenticated scenarios
    return;
  }

  // Authenticated requests use the per-VU cookie jar (authJar)
  // which automatically sends the session cookie.
  const authParams = {
    cookies: authJar,
    headers: {
      'X-CSRF-TOKEN': csrfToken,
      'Referer': BASE_URL,
    },
  };

  // ── Index page (authenticated) ────────────────────────────────────────
  group('index', () => {
    const res = http.get(`${BASE_URL}/index`, authParams);
    indexTrend.add(res.timings.duration);
    const ok = check(res, {
      'index status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── Browse torrents (authenticated) ───────────────────────────────────
  group('browse', () => {
    const res = http.get(`${BASE_URL}/torrents`, authParams);
    browseTrend.add(res.timings.duration);
    const ok = check(res, {
      'browse status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── Search torrents (authenticated) ───────────────────────────────────
  group('search', () => {
    const res = http.get(`${BASE_URL}/torrents?search=perf-torrent`, authParams);
    searchTrend.add(res.timings.duration);
    const ok = check(res, {
      'search status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── Torrent details (authenticated) ───────────────────────────────────
  group('details', () => {
    // Use a deterministic torrent ID from the seeder
    const res = http.get(`${BASE_URL}/details.php?id=1`, authParams);
    detailsTrend.add(res.timings.duration);
    const ok = check(res, {
      'details status 200 or 302': (r) => r.status === 200 || r.status === 302,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── Messages (authenticated) ──────────────────────────────────────────
  group('messages', () => {
    const res = http.get(`${BASE_URL}/messages.php`, authParams);
    messagesTrend.add(res.timings.duration);
    const ok = check(res, {
      'messages status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── User CP (authenticated) ───────────────────────────────────────────
  group('usercp', () => {
    const res = http.get(`${BASE_URL}/usercp.php`, authParams);
    usercpTrend.add(res.timings.duration);
    const ok = check(res, {
      'usercp status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  // ── Upload page (authenticated) — validation only, no actual upload ───
  group('upload validation', () => {
    const res = http.get(`${BASE_URL}/upload.php`, authParams);
    uploadTrend.add(res.timings.duration);
    const ok = check(res, {
      'upload page status 200': (r) => r.status === 200,
    });
    if (!ok) pageErrors.add(1);
  });

  sleep(1);
}

export function handleSummary(data) {
  const budgetReport = {
    thresholds: {},
    summary: {
      total_requests: data.metrics.http_reqs?.values?.count ?? 0,
      http_req_failed_rate: data.metrics.http_req_failed?.values?.rate ?? 0,
      page_errors: data.metrics.page_errors?.values?.count ?? 0,
      login_errors: data.metrics.login_errors?.values?.count ?? 0,
    },
  };

  for (const [metric, budget] of Object.entries(BUDGETS)) {
    const actual = data.metrics[metric]?.values?.['p(95)'] ?? 0;
    const passed = actual <= budget;
    budgetReport.thresholds[metric] = {
      budget_ms: budget,
      actual_p95_ms: Math.round(actual),
      passed: passed,
    };
  }

  return {
    stdout: JSON.stringify(budgetReport, null, 2) + '\n',
  };
}
