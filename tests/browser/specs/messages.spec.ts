import { AxeBuilder } from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { AUTH_STATE, BROWSER_USER } from '../fixtures/auth';

const REPO_ROOT = join(__dirname, '..', '..', '..');
const SENDER_NAME = 'pmquick_e2e_sender';
const SUBJECT_PREFIX = 'PM QUICK E2E';

test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

function tinker(php: string): string {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'php', 'php', 'artisan', 'tinker', `--execute=${php}`],
    { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'inherit'] },
  );
}

function appDbName(): string {
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
  const id = Number(mysql(sql).trim());
  if (!Number.isInteger(id) || id <= 0) {
    throw new Error(`expected a positive id, got: ${id}`);
  }

  return id;
}

let userId = 0;
let senderId = 0;
let originalId = 0;
let originalSubject = '';

test.beforeAll(() => {
  db = appDbName();
  userId = mysqlId(`SELECT id FROM users WHERE username='${BROWSER_USER}' LIMIT 1`);

  const oldSender = Number(mysql(`SELECT id FROM users WHERE username='${SENDER_NAME}' LIMIT 1`).trim());
  if (oldSender > 0) {
    mysql(`DELETE FROM messages WHERE sender=${oldSender} OR receiver=${oldSender}`);
    mysql(`DELETE FROM users WHERE id=${oldSender} AND username='${SENDER_NAME}'`);
  }
  mysql(
    `DELETE FROM messages WHERE receiver=${userId} AND ` +
      `(subject LIKE '${SUBJECT_PREFIX}%' OR subject LIKE 'Re: ${SUBJECT_PREFIX}%')`,
  );

  senderId = Number(
    tinker(`echo \\App\\Models\\User::factory()->create(['username' => '${SENDER_NAME}'])->id;`)
      .trim()
      .split('\n')
      .pop() ?? 0,
  );
  if (!Number.isInteger(senderId) || senderId <= 0) {
    throw new Error('could not create the quick reply sender user');
  }

  originalSubject = `${SUBJECT_PREFIX} ${Date.now()}`;
  originalId = mysqlId(
    `INSERT INTO messages (sender,receiver,added,subject,msg,unread,location) ` +
      `VALUES (${senderId},${userId},NOW(),'${originalSubject}','Original quick reply message',1,1); ` +
      'SELECT LAST_INSERT_ID();',
  );
});

test.afterAll(() => {
  if (userId > 0 && senderId > 0) {
    mysql(
      `DELETE FROM messages WHERE (sender=${senderId} AND receiver=${userId}) ` +
        `OR (sender=${userId} AND receiver=${senderId})`,
    );
    mysql(`DELETE FROM users WHERE id=${senderId} AND username='${SENDER_NAME}'`);
  }
  if (userId > 0) {
    mysql(`UPDATE users SET stylesheet=5 WHERE id=${userId}`);
  }
});

const packs = [
  { name: 'Classic', id: 4 },
  { name: 'Unshatter', id: 5 },
];
const themes = ['light', 'dark'];

for (const pack of packs) {
  for (const theme of themes) {
    for (const view of ['inbox', 'received message']) {
      test(`${view} passes axe in ${pack.name} ${theme}`, async ({ page }) => {
        mysql(`UPDATE users SET stylesheet=${pack.id} WHERE id=${userId}`);
        const path = view === 'inbox'
          ? '/web/messages'
          : `/web/messages?action=viewmessage&id=${originalId}`;
        await page.goto(path, { waitUntil: 'networkidle' });
        await page.evaluate((value) => {
          document.documentElement.dataset.theme = value;
        }, theme);

        const results = await new AxeBuilder({ page })
          .withTags(['wcag2a', 'wcag2aa'])
          .include(['.nx-msgview', '.nx-quickreply', '.nx-mail'])
          .exclude(['.nx-mail .User_Name', '.nx-mail .VeteranUser_Name'])
          .analyze();
        const violations = results.violations.map((violation) => ({
          id: violation.id,
          nodes: violation.nodes.map((node) => ({
            target: node.target,
            html: node.html,
            failureSummary: node.failureSummary,
          })),
        }));
        expect(violations).toEqual([]);

        if (view === 'received message') {
          const replyColors = await page.locator('.nx-msgview__actions a.nx-btn--primary').evaluate((element) => {
            const styles = getComputedStyle(element);
            return { color: styles.color, background: styles.backgroundColor };
          });
          expect(replyColors.color).not.toBe(replyColors.background);

          const actions = await new AxeBuilder({ page })
            .include('.nx-msgview__actions')
            .withRules(['color-contrast'])
            .analyze();
          expect(actions.violations.map((violation) => violation.id)).toEqual([]);
        }
      });
    }
  }
}

test('inbox and received message have no mobile overflow', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  mysql('UPDATE users SET stylesheet=4 WHERE id='+userId);

  for (const path of ['/web/messages', `/web/messages?action=viewmessage&id=${originalId}`]) {
    await page.goto(path, { waitUntil: 'networkidle' });
    await page.evaluate(() => {
      document.documentElement.dataset.theme = 'light';
    });
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    expect(overflow, `${path}: horizontal overflow`).toBeLessThanOrEqual(1);
  }

  await page.goto(`/web/messages?action=viewmessage&id=${originalId}`, { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'light';
  });
  await expect(page.locator('.nx-msgsplit__list')).toBeHidden();
  await expect(page.locator('.nx-msgview__back')).toBeVisible();
  await expect(page.locator('.nx-msgview__back')).toHaveAttribute('href', '/web/messages');
});

test('quick reply returns to the original message and stores a Re subject', async ({ page }) => {
  mysql('UPDATE users SET stylesheet=4 WHERE id='+userId);
  const path = `/web/messages?action=viewmessage&id=${originalId}`;
  await page.goto(path, { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'light';
  });
  await page.locator('.nx-quickreply textarea[name="body"]').fill('Browser quick reply');
  await Promise.all([
    page.waitForURL((url) => `${url.pathname}${url.search}` === path),
    page.locator('.nx-quickreply button[type="submit"]').click(),
  ]);

  expect(
    mysql(
      `SELECT subject FROM messages WHERE sender=${userId} AND receiver=${senderId} ` +
        `AND subject='Re: ${originalSubject}' LIMIT 1`,
    ).trim(),
  ).toBe(`Re: ${originalSubject}`);
});
