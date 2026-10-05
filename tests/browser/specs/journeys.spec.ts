import { expect, test, type Browser } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { join } from 'node:path';
import { AUTH_STATE } from '../fixtures/auth';
import { expectCleanPage, watchIssues } from '../helpers';
import { makeTorrent, percentEncode, readTorrent } from '../torrent';

const REPO_ROOT = join(__dirname, '..', '..', '..');

/**
 * Key user journeys end to end through the real UI and tracker:
 * upload a .torrent → download it back → announce as a BitTorrent
 * client and see the seeder via scrape; and a private message from
 * sysop that the recipient reads in their own inbox.
 */
test.use({ storageState: AUTH_STATE });
test.describe.configure({ mode: 'serial' });

function tinker(php: string): string {
  const out = execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'php', 'php', 'artisan', 'tinker', `--execute=${php}`],
    { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'inherit'] },
  );
  return (out.trim().split('\n').pop() ?? '').trim();
}

test('upload → download → announce as a client → scrape sees the seeder', async ({ page }) => {
  const issues = watchIssues(page);
  const name = `e2e-journey-${Date.now()}`;

  await page.goto('/upload', { waitUntil: 'networkidle' });
  const announceUrl = await page.inputValue('#announce-url');
  await page.setInputFiles('#torrent', {
    name: `${name}.torrent`,
    mimeType: 'application/x-bittorrent',
    buffer: makeTorrent(name, announceUrl),
  });
  await page.fill('#name', name);
  await page.fill('#descr', 'Uploaded by the browser journey spec.');
  const category = await page.$eval('#browsecat', (s: HTMLSelectElement) =>
    [...s.options].map((o) => o.value).find((v) => v !== '0') ?? '');
  expect(category, 'a seeded category to upload into').not.toBe('');
  await page.selectOption('#browsecat', category);

  await Promise.all([
    page.waitForURL(/\/details(\.php)?\?id=\d+&uploaded=1/),
    page.click('#compose [type="submit"]'),
  ]);
  const torrentId = new URL(page.url()).searchParams.get('id');
  await expect(page.locator('body')).toContainText(name);
  await expectCleanPage(page, issues, 'details after upload');

  // The tracker rewrites the file (passkey in announce, private flag) —
  // read both back from the user's own download, as a client would.
  const dl = await page.request.get(`/download.php?id=${torrentId}`);
  expect(dl.status()).toBe(200);
  const torrent = readTorrent(await dl.body());
  expect(torrent.announce).toMatch(/passkey=[0-9a-f]{32}/);

  const infoHash = percentEncode(torrent.infoHash);
  const peerId = percentEncode(Buffer.from(`-qB4520-${randomBytes(6).toString('hex')}`));
  const client = { 'User-Agent': 'qBittorrent/4.5.2' };
  // Keep passkey and path but hit the stack under test: settings specs
  // may point basic.announce_url at a placeholder public host.
  const announcePath = (() => {
    const u = new URL(torrent.announce);
    return `${u.pathname}${u.search}`;
  })();
  const sep = announcePath.includes('?') ? '&' : '?';

  const announce = await page.request.get(
    `${announcePath}${sep}info_hash=${infoHash}&peer_id=${peerId}` +
      '&port=51413&uploaded=0&downloaded=0&left=0&event=started&compact=1&numwant=50',
    { headers: client },
  );
  const body = (await announce.body()).toString('latin1');
  expect(announce.status()).toBe(200);
  expect(body, `announce must not fail: ${body}`).not.toContain('failure reason');
  expect(body, `announce must not warn: ${body}`).not.toContain('warning message');
  expect(body).toMatch(/8:intervali\d+e/);

  const scrapeUrl = announcePath.replace(/announce(\.php)?/, 'scrape$1');
  const scrape = await page.request.get(`${scrapeUrl}${sep}info_hash=${infoHash}`, { headers: client });
  const scraped = (await scrape.body()).toString('latin1');
  expect(scraped, `the announcing seeder is counted: ${scraped}`).toContain('8:completei1e');
});

async function loginAs(browser: Browser, username: string, password: string) {
  const context = await browser.newContext({ storageState: undefined });
  const page = await context.newPage();
  await page.goto('/login', { waitUntil: 'networkidle' });
  await page.fill('#login-form input[name="username"]', username);
  await page.fill('#login-form input[name="password"]', password);
  await Promise.all([
    page.waitForURL(/\/index(\.php)?$/),
    page.click('#login-form [type="submit"]'),
  ]);
  return { context, page };
}

test('private message: sysop sends, recipient reads it in their inbox', async ({ page, browser }) => {
  // `pw` prefix: CI's post-run cleanup deletes these accounts.
  const recipient = 'pwe2e_pm';
  const password = 'E2eJourney2026';
  const recipientId = tinker(
    `$u = App\\Models\\User::query()->where('username', '${recipient}')->first()` +
      ` ?? App\\Models\\User::factory()->create(['username' => '${recipient}', 'email' => '${recipient}@example.com', 'class' => 1]);` +
      ` $u->passhash = App\\Support\\PasswordHasher::hash('${password}');` +
      ` $u->passhash_algo = App\\Support\\PasswordHasher::ALGO_ARGON2ID; $u->auth_version++; $u->save(); echo $u->id;`,
  );
  expect(recipientId).toMatch(/^\d+$/);

  const subject = `e2e journey ${Date.now()}`;
  const text = `Hello from the journey spec ${randomBytes(4).toString('hex')}`;
  const issues = watchIssues(page);
  await page.goto(`/sendmessage?receiver=${recipientId}`, { waitUntil: 'networkidle' });
  await page.fill('#compose input[name="subject"]', subject);
  await page.fill('#compose textarea[name="body"]', text);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.click('#compose input[type="submit"]'),
  ]);
  expect(issues.pageErrors).toEqual([]);

  const { context, page: inbox } = await loginAs(browser, recipient, password);
  try {
    const inboxIssues = watchIssues(inbox);
    await inbox.goto('/messages', { waitUntil: 'networkidle' });
    const link = inbox.getByRole('link', { name: subject });
    await expect(link).toBeVisible();
    await link.click();
    await inbox.waitForLoadState('networkidle');
    await expect(inbox.locator('body')).toContainText(text);
    await expectCleanPage(inbox, inboxIssues, 'recipient reads PM');
  } finally {
    await context.close();
  }
});
