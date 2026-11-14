import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const BASE = 'http://127.0.0.1';
const OUT = '/home/ubuntu/e2e-report/shots';
const REPO = '/home/ubuntu/repos/tracker-lp-bits';
const AUTH = '/home/ubuntu/repos/tracker-lp-bits/tests/browser/.auth/state.json';
const DBPW = readFileSync(`${REPO}/.env`, 'utf8').match(/^DB_PASSWORD=(.*)$/m)[1].trim();
const REDIS_PW = readFileSync(`${REPO}/.env`, 'utf8').match(/^REDIS_PASSWORD=(.*)$/m)[1].trim();

const COMBOS = [
  { sheet: 5, theme: 'light', label: 'unshatter-light' },
  { sheet: 5, theme: 'dark', label: 'unshatter-dark' },
  { sheet: 4, theme: 'light', label: 'classic-light' },
  { sheet: 4, theme: 'dark', label: 'classic-dark' },
];

// authed pages (sysop)
const PAGES = [
  ['index', '/index.php'],
  ['torrents', '/torrents.php'],
  ['torrents-p2', '/torrents.php?page=1'],
  ['details', '/details.php?id=31'],
  ['details2', '/details.php?id=13'],
  ['viewsnatches', '/web/viewsnatches?id=31'],
  ['viewpeerlist', '/web/viewpeerlist?id=31'],
  ['forums', '/forums.php'],
  ['viewforum', '/forums?action=viewforum&forumid=1'],
  ['viewtopic', '/forums?action=viewtopic&topicid=1'],
  ['userdetails', '/userdetails.php?id=1'],
  ['userdetails-user', '/userdetails.php?id=10001'],
  ['upload', '/upload.php'],
  ['edit-torrent', '/edit?id=31'],
  ['offers', '/web/offers'],
  ['messages', '/messages.php'],
  ['sendmessage', '/sendmessage?receiver=10001'],
  ['mybonus', '/web/mybonus'],
  ['attendance', '/web/attendance'],
  ['myhr', '/web/myhr'],
  ['topten', '/topten.php'],
  ['staff', '/web/staff'],
  ['faq', '/web/faq'],
  ['rules', '/web/rules'],
  ['log', '/web/log'],
  ['complains', '/web/complains'],
  ['staffpanel', '/web/staffpanel'],
  ['settings', '/web/settings'],
  ['shoutbox-history', '/web/shoutbox_history'],
  ['latestcomments', '/web/latestcomments'],
  ['users', '/web/users'],
  ['uploaders', '/web/uploaders'],
  ['usercp', '/usercp.php'],
  ['invite', '/web/invite?id=1'],
  ['friends', '/web/friends'],
  ['polloverview', '/web/polloverview'],
  ['tags', '/web/tags'],
  ['reports', '/web/reports'],
  ['getrss', '/web/getrss'],
  ['news', '/web/news'],
  ['donate', '/web/donate'],
  ['mysql-stats', '/web/mysql_stats'],
  ['usersearch', '/web/usersearch'],
  ['bonus-log', '/web/bonus-log?uid=1'],
];
const GUEST = [
  ['login', '/login'],
  ['signup', '/signup'],
  ['recover', '/recover'],
];

function mysql(sql) {
  execFileSync('docker', ['exec', '-i', 'nexusphp-mysql', 'mysql', '-uroot', `-p${DBPW}`, 'nexusphp', '-e', sql], { stdio: 'pipe' });
}
function flush() {
  execFileSync('docker', ['exec', 'nexusphp-redis', 'redis-cli', '-a', REDIS_PW, 'FLUSHDB'], { stdio: 'pipe' });
}

const results = [];
const browser = await chromium.launch();

async function settle(page) {
  await page.waitForLoadState('networkidle');
  // trigger lazy Livewire lists by scrolling
  await page.evaluate(async () => {
    await new Promise((res) => {
      let y = 0;
      const step = () => {
        y += window.innerHeight;
        window.scrollTo(0, y);
        if (y < document.body.scrollHeight) setTimeout(step, 120);
        else { window.scrollTo(0, 0); res(); }
      };
      step();
    });
  });
  await page.waitForTimeout(900);
}

for (const combo of COMBOS) {
  mysql(`UPDATE users SET stylesheet=${combo.sheet}, theme='${combo.theme}' WHERE id=1;`);
  flush();
  const ctx = await browser.newContext({ storageState: AUTH, viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  for (const [name, url] of PAGES) {
    const file = `${OUT}/${combo.label}--${name}.jpg`;
    try {
      const resp = await page.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await settle(page);
      await page.screenshot({ path: file, fullPage: true, type: 'jpeg', quality: 70 });
      const status = resp ? resp.status() : 0;
      results.push({ combo: combo.label, page: name, url, status, file });
      console.log(`${combo.label} ${name} -> ${status}`);
    } catch (e) {
      results.push({ combo: combo.label, page: name, url, status: 'ERR', file, error: String(e).slice(0, 200) });
      console.log(`${combo.label} ${name} -> ERR ${e.message?.slice(0, 80)}`);
    }
  }
  await ctx.close();

  // guest pages: anonymous chrome, scheme via prefers-color-scheme
  const gctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: combo.theme });
  const gp = await gctx.newPage();
  for (const [name, url] of GUEST) {
    const file = `${OUT}/${combo.label}--guest-${name}.jpg`;
    try {
      const resp = await gp.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await settle(gp);
      await gp.screenshot({ path: file, fullPage: true, type: 'jpeg', quality: 70 });
      results.push({ combo: combo.label, page: `guest-${name}`, url, status: resp ? resp.status() : 0, file });
      console.log(`${combo.label} guest-${name} -> ${resp ? resp.status() : 0}`);
    } catch (e) {
      results.push({ combo: combo.label, page: `guest-${name}`, url, status: 'ERR', file, error: String(e).slice(0, 200) });
      console.log(`${combo.label} guest-${name} -> ERR ${e.message?.slice(0, 80)}`);
    }
  }
  await gctx.close();
}

// restore user prefs
mysql(`UPDATE users SET stylesheet=5, theme='auto' WHERE id=1;`);
flush();
writeFileSync('/home/ubuntu/e2e-report/matrix-results.json', JSON.stringify(results, null, 2));
await browser.close();
console.log('DONE', results.length);
