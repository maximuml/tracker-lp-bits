/**
 * k6 performance budget baseline for tracker-lp-bits
 *
 * Step 23 of the modernization plan.
 *
 * Scans key unauthenticated pages and enforces response time budgets.
 * Run against the Docker stack (OpenResty on port 80).
 *
 * Usage:
 *   k6 run tests/Performance/baseline.js
 *   k6 run --env BASE_URL=http://127.0.0.1:80 tests/Performance/baseline.js
 *
 * CI: .github/workflows/perf-budget.yml runs this against Docker stack.
 */

import http from 'k6/http';
import { check, group } from 'k6';
import { Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:80';

// Custom metrics per page
const indexTrend = new Trend('page_index_duration', true);
const loginTrend = new Trend('page_login_duration', true);
const signupTrend = new Trend('page_signup_duration', true);
const healthLiveTrend = new Trend('page_health_live_duration', true);
const healthReadyTrend = new Trend('page_health_ready_duration', true);
const metricsTrend = new Trend('page_metrics_duration', true);
const torrentsTrend = new Trend('page_torrents_duration', true);
const forumsTrend = new Trend('page_forums_duration', true);
const faqTrend = new Trend('page_faq_duration', true);
const rulesTrend = new Trend('page_rules_duration', true);

// Performance budgets (in milliseconds, p95)
const BUDGETS = {
  page_index_duration: 2000,
  page_login_duration: 1500,
  page_signup_duration: 1500,
  page_health_live_duration: 100,
  page_health_ready_duration: 500,
  page_metrics_duration: 200,
  page_torrents_duration: 3000,
  page_forums_duration: 3000,
  page_faq_duration: 2000,
  page_rules_duration: 2000,
};

export const options = {
  stages: [
    { duration: '10s', target: 5 },   // ramp up to 5 VUs
    { duration: '20s', target: 5 },   // hold at 5 VUs
    { duration: '5s', target: 0 },    // ramp down
  ],
  thresholds: {
    // p95 must be under budget; failed requests must be low
    // (some pages redirect to login — 302 counts as non-failed in k6,
    // but 401/403 from auth-protected pages is expected)
    'page_index_duration': ['p(95)<' + BUDGETS.page_index_duration],
    'page_login_duration': ['p(95)<' + BUDGETS.page_login_duration],
    'page_signup_duration': ['p(95)<' + BUDGETS.page_signup_duration],
    'page_health_live_duration': ['p(95)<' + BUDGETS.page_health_live_duration],
    'page_health_ready_duration': ['p(95)<' + BUDGETS.page_health_ready_duration],
    'page_metrics_duration': ['p(95)<' + BUDGETS.page_metrics_duration],
    'page_torrents_duration': ['p(95)<' + BUDGETS.page_torrents_duration],
    'page_forums_duration': ['p(95)<' + BUDGETS.page_forums_duration],
    'page_faq_duration': ['p(95)<' + BUDGETS.page_faq_duration],
    'page_rules_duration': ['p(95)<' + BUDGETS.page_rules_duration],
    // Allow up to 50% failure rate — some pages require auth (401/302)
    'http_req_failed': ['rate<0.50'],
  },
};

export default function () {
  // Health/live — should be very fast
  group('health/live', () => {
    const res = http.get(`${BASE_URL}/health/live`);
    healthLiveTrend.add(res.timings.duration);
    check(res, {
      'health/live status 200': (r) => r.status === 200,
    });
  });

  // Health/ready — checks DB + Redis
  group('health/ready', () => {
    const res = http.get(`${BASE_URL}/health/ready`);
    healthReadyTrend.add(res.timings.duration);
    check(res, {
      'health/ready status 200': (r) => r.status === 200,
    });
  });

  // Index page — heaviest unauthenticated page
  group('index', () => {
    const res = http.get(`${BASE_URL}/index`);
    indexTrend.add(res.timings.duration);
    check(res, {
      'index status 200': (r) => r.status === 200,
    });
  });

  // Login page
  group('login', () => {
    const res = http.get(`${BASE_URL}/login`);
    loginTrend.add(res.timings.duration);
    check(res, {
      'login status 200': (r) => r.status === 200,
    });
  });

  // Signup page
  group('signup', () => {
    const res = http.get(`${BASE_URL}/signup`);
    signupTrend.add(res.timings.duration);
    check(res, {
      'signup status 200': (r) => r.status === 200,
    });
  });

  // Metrics endpoint — Prometheus format, should be fast
  group('metrics', () => {
    const res = http.get(`${BASE_URL}/metrics`);
    metricsTrend.add(res.timings.duration);
    check(res, {
      'metrics status 200': (r) => r.status === 200,
    });
  });

  // Torrents page — listing with search
  group('torrents', () => {
    const res = http.get(`${BASE_URL}/torrents`);
    torrentsTrend.add(res.timings.duration);
    check(res, {
      'torrents responds': (r) => r.status !== 0,
    });
  });

  // Forums page
  group('forums', () => {
    const res = http.get(`${BASE_URL}/forums`);
    forumsTrend.add(res.timings.duration);
    check(res, {
      'forums responds': (r) => r.status !== 0,
    });
  });

  // FAQ page
  group('faq', () => {
    const res = http.get(`${BASE_URL}/faq`);
    faqTrend.add(res.timings.duration);
    check(res, {
      'faq responds': (r) => r.status !== 0,
    });
  });

  // Rules page
  group('rules', () => {
    const res = http.get(`${BASE_URL}/rules`);
    rulesTrend.add(res.timings.duration);
    check(res, {
      'rules responds': (r) => r.status !== 0,
    });
  });
}

export function handleSummary(data) {
  const budgetReport = {
    thresholds: {},
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
