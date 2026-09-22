import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { AUTH_STATE, BROWSER_USER } from '../fixtures/auth';
import { watchIssues } from '../helpers';

const REPO_ROOT = join(__dirname, '..', '..', '..');
const SHOTS = join(__dirname, '..', 'test-results', 'notifications');
const PM_COUNT = 25;

/**
 * REL-01: the notification panel end to end. Seeds a deterministic
 * unread backlog straight into the dev database (the compose stack is
 * a prerequisite for the whole suite), then drives the real UI:
 * server-authoritative badge, 20-item pages behind "Show more",
 * valid item URLs, and watermark-aware "mark all read" — a PM that
 * arrives while the panel is open must stay unread.
 */
test.use({ storageState: AUTH_STATE });

function mysql(sql: string): void {
  execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'mysql', 'mysql', '-unexusphp', '-pnexusphp', 'nexusphp', '-e', sql],
    { cwd: REPO_ROOT, stdio: 'pipe' },
  );
}

test.beforeAll(() => {
  mkdirSync(SHOTS, { recursive: true });

  // Re-run safe: drop rows from a previous spec run first, then pin
  // every channel cursor at its source maximum so only the fixtures
  // inserted below count as unread (auto-seed never kicks in because
  // the cursor rows exist).
  mysql(`
    DELETE FROM messages WHERE sender=10005 AND receiver=1 AND subject LIKE 'REL-01 E2E%';
    DELETE FROM comments WHERE user=10005 AND text LIKE 'rel01 e2e%';
    DELETE FROM posts WHERE userid=10005 AND body LIKE 'rel01 e2e%';
    DELETE FROM shoutbox WHERE userid=10005 AND text LIKE '%rel01 e2e%';
    UPDATE torrents SET visible=1, banned=0 WHERE id=1;
    UPDATE forums SET minclassread=0 WHERE id=1;
    INSERT IGNORE INTO notification_cursors (user_id, channel, last_id) VALUES
      (1,'pm',0),(1,'shout',0),(1,'comment',0),(1,'topic_reply',0),(1,'staff',0);
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM messages) WHERE user_id=1 AND channel='pm';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM shoutbox) WHERE user_id=1 AND channel='shout';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM comments) WHERE user_id=1 AND channel='comment';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM posts) WHERE user_id=1 AND channel='topic_reply';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM staffmessages) WHERE user_id=1 AND channel='staff';
  `);

  const pms = Array.from(
    { length: PM_COUNT },
    (_, i) => `(10005,1,NOW(),'REL-01 E2E ${i + 1}','seeded by notifications.spec.ts',1,1)`,
  ).join(',');
  mysql(`
    INSERT INTO messages (sender,receiver,added,subject,msg,unread,location) VALUES ${pms};
    INSERT INTO comments (user,torrent,added,text)
      SELECT 10005, id, NOW(), 'rel01 e2e comment' FROM torrents WHERE id=1 AND owner=1 LIMIT 1;
    INSERT INTO posts (topicid,userid,added,body)
      SELECT 1, 10005, NOW(), 'rel01 e2e reply' FROM topics WHERE id=1 AND userid=1 LIMIT 1;
    INSERT INTO shoutbox (userid,date,text,type)
      VALUES (10005, UNIX_TIMESTAMP(), '@${BROWSER_USER} rel01 e2e mention', 0);
  `);
});

test.afterAll(() => {
  // Don't leak fixture rows into sibling specs: the shoutbox iframe
  // renders mentions with a class whose contrast is tracked by axe, and
  // details.php shows seeded comments.
  mysql(`
    DELETE FROM messages WHERE sender=10005 AND receiver=1 AND (subject LIKE 'REL-01 E2E%' OR subject='REL-01 late PM');
    DELETE FROM comments WHERE user=10005 AND text LIKE 'rel01 e2e%';
    DELETE FROM posts WHERE userid=10005 AND body LIKE 'rel01 e2e%';
    DELETE FROM shoutbox WHERE userid=10005 AND text LIKE '%rel01 e2e%';
  `);
});

test('badge, pagination, links and watermark-aware mark-all-read', async ({
  page,
}) => {
  const issues = watchIssues(page);
  // Fresh client: no delivered cursors — init seeds them quietly.
  await page.addInitScript(() => {
    Object.keys(localStorage)
      .filter((k) => k.startsWith('toast_last_'))
      .forEach((k) => localStorage.removeItem(k));
  });

  const api = async (url: string) => {
    const res = await page.request.get(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const body = await res.json();
    return body.data;
  };

  // Ground truth: the same endpoint the panel consumes.
  const feed = await api('/notifications');
  const total: number = feed.counts.total;
  const apiUrls: string[] = [];
  {
    let offset = 0;
    for (;;) {
      const d = await api(`/notifications?offset=${offset}`);
      apiUrls.push(...d.items.map((i: { url?: string }) => i.url ?? '#'));
      if (!d.has_more || d.items.length === 0) break;
      offset += d.items.length;
    }
  }
  expect(total).toBeGreaterThanOrEqual(PM_COUNT);
  expect(apiUrls.length).toBe(total);

  await page.goto('/index.php', { waitUntil: 'networkidle' });

  const badge = page.locator('#nx-notif-badge');
  await expect(badge).toBeVisible();
  await expect(badge).toHaveText(total > 99 ? '99+' : String(total));
  await page.locator('.nx-notif').screenshot({ path: join(SHOTS, '01-badge.png') });

  await page.locator('#nx-notif-bell').click();
  const panel = page.locator('#nx-notif-panel');
  const items = panel.locator('.nx-notif-item');
  await expect(panel).toBeVisible();
  await expect(items).toHaveCount(Math.min(total, 20));
  await page.screenshot({ path: join(SHOTS, '02-panel-first-page.png') });

  while (await panel.locator('.nx-notif-more button').isVisible()) {
    await panel.locator('.nx-notif-more button').click();
    await page.waitForResponse((r) => r.url().includes('notifications?offset='));
  }
  await expect(items).toHaveCount(total);
  const domUrls = await items.evaluateAll((els) =>
    els.map((el) => (el as HTMLAnchorElement).getAttribute('href')),
  );
  expect(domUrls).toEqual(apiUrls);
  await page.screenshot({ path: join(SHOTS, '03-panel-all-items.png') });

  // A PM arriving after the panel snapshot must survive mark-all-read.
  mysql(
    `INSERT INTO messages (sender,receiver,added,subject,msg,unread,location)
     VALUES (10005,1,NOW(),'REL-01 late PM','arrived while the panel was open',1,1)`,
  );
  await panel.getByRole('button', { name: /mark all read/i }).click();
  await expect(badge).toBeHidden();
  await expect(panel.locator('.nx-notif-empty')).toBeVisible();
  await page.screenshot({ path: join(SHOTS, '04-marked-all-read.png') });

  await page.reload({ waitUntil: 'networkidle' });
  await expect(badge).toHaveText('1');
  await page.locator('#nx-notif-bell').click();
  await expect(items).toHaveCount(1);
  await expect(items.first()).toContainText('REL-01 late PM');
  await page.screenshot({ path: join(SHOTS, '05-late-pm-still-unread.png') });

  expect(issues.pageErrors, 'uncaught page errors').toEqual([]);
  expect(
    issues.httpFailures.filter((f) => /notifications|ajax\.php|shoutbox_sse/.test(f)),
    'notification endpoint failures',
  ).toEqual([]);
});
