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

function tinker(php: string): string {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'php', 'php', 'artisan', 'tinker', `--execute=${php}`],
    { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'inherit'] },
  );
}

function appDbName(): string {
  // The database the app actually uses — the mysql container's own
  // MYSQL_DATABASE env can be stale (frozen at `up` time), while CI
  // installs into nexusphp_e2e_testing rather than the .env default.
  const out = tinker('echo DB::connection()->getDatabaseName();');
  const name = (out.trim().split('\n').pop() ?? '').trim();
  if (!/^[A-Za-z0-9_]+$/.test(name)) {
    throw new Error(`could not resolve app database name: ${JSON.stringify(out)}`);
  }
  return name;
}

let db = '';

function mysql(sql: string): string {
  return execFileSync(
    'docker',
    [
      'compose', 'exec', '-T', 'mysql', 'sh', '-c',
      'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --batch --skip-column-names "$1"',
      'sh', db,
    ],
    { cwd: REPO_ROOT, input: sql, encoding: 'utf8', stdio: ['pipe', 'pipe', 'inherit'] },
  );
}

function mysqlId(sql: string): number {
  const out = mysql(sql).trim();
  const id = Number(out);
  if (!Number.isInteger(id) || id <= 0) {
    throw new Error(`expected a positive id, got: ${JSON.stringify(out)}`);
  }
  return id;
}

let uid = 0;
let senderId = 0;

test.beforeAll(() => {
  mkdirSync(SHOTS, { recursive: true });
  db = appDbName();

  // The logged-in account is created by the harness (locally id=1, in CI
  // whatever user:reset_id_auto_increment yields) — resolve it by name.
  uid = mysqlId(
    `SELECT id FROM users WHERE username='${BROWSER_USER}' LIMIT 1`,
  );
  // messages.sender / comments.user / posts.userid have FKs into users —
  // and the feed deliberately skips rows authored by the recipient — so
  // a real second account is required. The factory keeps the insert
  // schema-proof across environments.
  senderId =
    Number(
      mysql(`SELECT id FROM users WHERE username='rel01_e2e_sender' LIMIT 1`).trim(),
    ) ||
    Number(
      tinker(
        "echo \\App\\Models\\User::factory()->create(['username' => 'rel01_e2e_sender'])->id;",
      )
        .trim()
        .split('\n')
        .pop() ?? 0,
    );
  if (!Number.isInteger(senderId) || senderId <= 0) {
    throw new Error('could not create the notification sender user');
  }

  // Re-run safe: drop rows from a previous spec run first, then pin
  // every channel cursor at its source maximum so only the fixtures
  // inserted below count as unread (auto-seed never kicks in because
  // the cursor rows exist).
  mysql(`
    DELETE FROM messages WHERE sender=${senderId} AND receiver=${uid} AND subject LIKE 'REL-01 E2E%';
    DELETE FROM comments WHERE user=${senderId} AND text LIKE 'rel01 e2e%';
    DELETE FROM posts WHERE userid=${senderId} AND body LIKE 'rel01 e2e%';
    DELETE FROM shoutbox WHERE userid=${senderId} AND text LIKE '%rel01 e2e%';
    UPDATE torrents SET visible=1, banned=0 WHERE owner=${uid};
    UPDATE forums f JOIN topics t ON t.forumid=f.id AND t.userid=${uid}
      SET f.minclassread=0;
    INSERT IGNORE INTO notification_cursors (user_id, channel, last_id) VALUES
      (${uid},'pm',0),(${uid},'shout',0),(${uid},'comment',0),(${uid},'topic_reply',0),(${uid},'staff',0);
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM messages) WHERE user_id=${uid} AND channel='pm';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM shoutbox) WHERE user_id=${uid} AND channel='shout';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM comments) WHERE user_id=${uid} AND channel='comment';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM posts) WHERE user_id=${uid} AND channel='topic_reply';
    UPDATE notification_cursors SET last_id=(SELECT COALESCE(MAX(id),0) FROM staffmessages) WHERE user_id=${uid} AND channel='staff';
  `);

  const pms = Array.from(
    { length: PM_COUNT },
    (_, i) => `(${senderId},${uid},NOW(),'REL-01 E2E ${i + 1}','seeded by notifications.spec.ts',1,1)`,
  ).join(',');
  mysql(`
    INSERT INTO messages (sender,receiver,added,subject,msg,unread,location) VALUES ${pms};
    INSERT INTO comments (user,torrent,added,text)
      SELECT ${senderId}, id, NOW(), 'rel01 e2e comment' FROM torrents WHERE owner=${uid} LIMIT 1;
    INSERT INTO posts (topicid,userid,added,body)
      SELECT id, ${senderId}, NOW(), 'rel01 e2e reply' FROM topics WHERE userid=${uid} LIMIT 1;
    INSERT INTO shoutbox (userid,date,text,type)
      VALUES (${senderId}, UNIX_TIMESTAMP(), '@${BROWSER_USER} rel01 e2e mention', 0);
  `);
});

test.afterAll(() => {
  // Don't leak fixture rows into sibling specs: the shoutbox iframe
  // renders mentions with a class whose contrast is tracked by axe, and
  // details.php shows seeded comments.
  mysql(`
    DELETE FROM messages WHERE sender=${senderId} AND receiver=${uid} AND (subject LIKE 'REL-01 E2E%' OR subject='REL-01 late PM');
    DELETE FROM comments WHERE user=${senderId} AND text LIKE 'rel01 e2e%';
    DELETE FROM posts WHERE userid=${senderId} AND body LIKE 'rel01 e2e%';
    DELETE FROM shoutbox WHERE userid=${senderId} AND text LIKE '%rel01 e2e%';
    DELETE FROM users WHERE id=${senderId} AND username='rel01_e2e_sender';
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
    // force: the button node is removed and re-appended after each fetch —
    // actionability's stability check can otherwise never settle.
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('notifications?offset=')),
      panel.locator('.nx-notif-more button').click({ force: true }),
    ]);
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
     VALUES (${senderId},${uid},NOW(),'REL-01 late PM','arrived while the panel was open',1,1)`,
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
