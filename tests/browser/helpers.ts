import { expect, type Page } from '@playwright/test';

/**
 * Text patterns that only appear when markup was escaped into visible
 * text — the Stage-0 bug class. Mirrors tests/Feature/NoLeakedMarkupTest.
 */
export const LEAK_RE = /<(script|span|time|font|img|table|b>|br\s|a\s|td|tr)|&lt;|&nbsp;|&amp;/i;

export interface PageIssues {
  pageErrors: string[];
  httpFailures: string[];
}

/**
 * Attach BEFORE page.goto(): collects uncaught exceptions and
 * failed/≥400 requests for the lifetime of the page.
 */
export function watchIssues(page: Page): PageIssues {
  const issues: PageIssues = { pageErrors: [], httpFailures: [] };
  page.on('pageerror', (e) => issues.pageErrors.push(e.message));
  page.on('response', (r) => {
    if (r.status() >= 400) {
      issues.httpFailures.push(`${r.status()} ${r.url()}`);
    }
  });
  page.on('requestfailed', (r) =>
    issues.httpFailures.push(`requestfailed ${r.url()} ${r.failure()?.errorText ?? ''}`),
  );
  return issues;
}

export async function leakedMarkup(page: Page): Promise<string | null> {
  const text = await page.evaluate(() => document.body?.innerText ?? '');
  const m = text.match(LEAK_RE);
  if (!m || m.index === undefined) {
    return null;
  }
  const start = Math.max(0, m.index - 40);
  return JSON.stringify(text.slice(start, m.index + m[0].length + 60));
}

/** Assert a page renders clean: no leaked markup, no pageerror, no failed resources. */
export async function expectCleanPage(page: Page, issues: PageIssues, label: string): Promise<void> {
  expect(await leakedMarkup(page), `${label}: leaked markup in innerText`).toBeNull();
  expect(issues.pageErrors, `${label}: uncaught page errors`).toEqual([]);
  expect(issues.httpFailures, `${label}: failed/4xx+ resource requests`).toEqual([]);
}
