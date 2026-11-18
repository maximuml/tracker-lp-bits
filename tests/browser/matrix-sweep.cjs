// Full pre-release page matrix: all public pages x 4 theme combos.
// Run from tests/browser/: node matrix-sweep.cjs
const { chromium } = require('./node_modules/playwright-core');
const { execFileSync } = require('node:child_process');
const { readFileSync, writeFileSync } = require('node:fs');

const BASE = 'http://127.0.0.1';
const OUT = '/home/ubuntu/e2e-report/shots';
const REPO = '/home/ubuntu/repos/tracker-lp-bits';
const AUTH = `${__dirname}/.auth/state.json`;
const ENV = readFileSync(`${REPO}/.env`, 'utf8');
const DBPW = ENV.match(/^DB_PASSWORD=(.*)$/m)[1].trim();
const REDIS_PW = ENV.match(/^REDIS_PASSWORD=(.*)$/m)[1].trim();

const COMBOS = [
  { sheet: 5, theme: 'light', label: 'unshatter-light' },
  { sheet: 5, theme: 'dark', label: 'unshatter-dark' },
  { sheet: 4, theme: 'light', label: 'classic-light' },
  { sheet: 4, theme: 'dark', label: 'classic-dark' },
];

const PAGES = [
  ['index', '/index.php'],
  ['torrents', '/torrents.php'],
  ['torrents-p2', '/torrents.php?page=1'],
  ['details', '/details.php?id=31'],
  ['details2', '/details.php?id=13'],
  ['details3', '/details.php?id=36'],
  ['viewsnatches', '/viewsnatches.php?id=31'],
  ['viewpeerlist', '/viewpeerlist.php?id=35'],
  ['forums', '/forums.php'],
  ['viewforum', '/forums.php?action=viewforum&forumid=1'],
  ['viewtopic', '/forums.php?action=viewtopic&topicid=1'],
  ['viewtopic2', '/forums.php?action=viewtopic&topicid=2'],
  ['forumsearch', '/forums.php?action=search'],
  ['forumsearch-results', '/forums.php?action=search&keywords=E2E'],
  ['post', '/forums.php?action=post&topicid=2'],
  ['userdetails', '/userdetails.php?id=1'],
  ['userdetails-user', '/userdetails.php?id=10001'],
  ['upload', '/upload.php'],
  ['edit-torrent', '/edit.php?id=31'],
  ['offers', '/web/offers'],
  ['offer-detail', '/web/offers?id=7&off_details=1'],
  ['offer-add', '/web/offers?action=new'],
  ['messages', '/messages.php'],
  ['messages-sent', '/messages.php?action=viewmailbox&box=-1'],
  ['viewmessage', '/viewmessage.php?id=86'],
  ['sendmessage', '/sendmessage.php?receiver=10001'],
  ['mybonus', '/mybonus.php'],
  ['bonus-log', '/web/bonus-log?uid=1'],
  ['attendance', '/attendance.php'],
  ['myhr', '/web/myhr'],
  ['topten', '/topten.php'],
  ['topten-torrents', '/topten.php?type=2'],
  ['staff', '/staff.php'],
  ['faq', '/faq.php'],
  ['rules', '/rules.php'],
  ['log', '/web/log'],
  ['complains', '/web/complains'],
  ['staffpanel', '/web/staffpanel'],
  ['settings', '/web/settings'],
  ['settings-main', '/web/settings?action=mainsettings'],
  ['shoutbox-history', '/web/shoutbox_history'],
  ['latestcomments', '/web/latestcomments'],
  ['users', '/web/users'],
  ['uploaders', '/web/uploaders'],
  ['usercp', '/usercp.php'],
  ['usercp-personal', '/usercp.php?action=personal'],
  ['usercp-tracker', '/usercp.php?action=tracker'],
  ['usercp-forum', '/usercp.php?action=forum'],
  ['usercp-security', '/usercp.php?action=security'],
  ['invite', '/web/invite?id=1'],
  ['friends', '/web/friends'],
  ['polloverview', '/web/polloverview'],
  ['makepoll', '/web/makepoll'],
  ['tags', '/web/tags'],
  ['reports', '/web/reports'],
  ['getrss', '/getrss.php'],
  ['news', '/web/news'],
  ['donate', '/donate.php'],
  ['mysql-stats', '/web/mysql_stats'],
  ['usersearch', '/usersearch.php'],
  ['contactstaff', '/web/contactstaff'],
  // staff single-page tools
  ['staff-masspm', '/staffmess.php'],
  ['staff-massmail', '/massmail.php'],
  ['staff-maxlogin', '/maxlogin.php'],
  ['staff-bitbucket', '/bitbucketlog.php'],
  ['staff-location', '/location.php'],
  ['staff-warned', '/warned.php'],
  ['staff-ipcheck', '/ipcheck.php'],
  ['staff-cheaters', '/cheaters.php'],
  ['staff-stats', '/stats.php'],
  ['staff-testip', '/testip.php'],
  ['staff-unco', '/unco.php'],
  ['staff-adduser', '/adduser.php'],
  ['staff-reset', '/reset.php'],
  ['staff-bans', '/bans.php'],
  ['staff-docleanup', '/docleanup.php'],
  ['staff-forummanage', '/forummanage.php'],
  ['staff-faqmanage', '/faqmanage.php'],
  ['staff-modrules', '/modrules.php'],
  ['staff-catmanage', '/catmanage.php'],
  ['staff-fields', '/fields.php'],
  ['staff-clearcache', '/clearcache.php'],
  ['aboutnexus', '/aboutnexus.php'],
];
const GUEST = [
  ['login', '/login'],
  ['signup', '/signup'],
  ['recover', '/recover'],
  ['complains', '/web/complains'],
];

function mysql(sql) {
  execFileSync('docker', ['exec', '-i', 'nexusphp-mysql', 'mysql', '-uroot', `-p${DBPW}`, 'nexusphp', '-e', sql], { stdio: 'pipe' });
}
function flush() {
  execFileSync('docker', ['exec', 'nexusphp-redis', 'redis-cli', '-a', REDIS_PW, 'FLUSHDB'], { stdio: 'pipe' });
}

const results = [];
(async () => {
const browser = await chromium.connectOverCDP('http://localhost:29229');

async function settle(page) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.evaluate(async () => {
    await new Promise((res) => {
      let y = 0;
      const step = () => {
        y += window.innerHeight;
        window.scrollTo(0, y);
        if (y < document.body.scrollHeight) setTimeout(step, 100);
        else { window.scrollTo(0, 0); res(); }
      };
      step();
    });
  }).catch(() => {});
  await page.waitForTimeout(800);
}

async function visit(page, combo, name, url) {
  const file = `${OUT}/${combo.label}--${name}.jpg`;
  const jsErr = [];
  const http4xx = [];
  const onErr = (e) => jsErr.push(String(e.message).slice(0, 160));
  const onResp = (r) => { if (r.status() >= 400) http4xx.push(`${r.status()} ${r.url().slice(0, 120)}`); };
  page.on('pageerror', onErr);
  page.on('response', onResp);
  try {
    const resp = await page.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await settle(page);
    const leak = await page.evaluate(() => {
      const t = document.body?.innerText ?? '';
      const m = t.match(/&lt;|<(script|font|table|b>|br\s|a\s)[a-z ]*/i);
      return m ? t.slice(Math.max(0, m.index - 40), m.index + 100) : null;
    }).catch(() => null);
    await page.screenshot({ path: file, fullPage: true, type: 'jpeg', quality: 70 });
    const status = resp ? resp.status() : 0;
    results.push({ combo: combo.label, page: name, url, status, file, jsErr, http4xx, leak });
    console.log(`${combo.label} ${name} -> ${status}${jsErr.length ? ' JSERR' : ''}${http4xx.length ? ' HTTP4xx:' + http4xx.length : ''}${leak ? ' LEAK' : ''}`);
  } catch (e) {
    try { await page.screenshot({ path: file, fullPage: true, type: 'jpeg', quality: 70 }); } catch {}
    results.push({ combo: combo.label, page: name, url, status: 'ERR', file, jsErr, http4xx, error: String(e).slice(0, 200) });
    console.log(`${combo.label} ${name} -> ERR ${String(e.message || e).slice(0, 80)}`);
  }
  page.off('pageerror', onErr);
  page.off('response', onResp);
}

for (const combo of COMBOS) {
  mysql(`UPDATE users SET stylesheet=${combo.sheet}, theme='${combo.theme}' WHERE id=1;`);
  flush();
  const ctx = await browser.newContext({ storageState: AUTH, viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  for (const [name, url] of PAGES) {
    await visit(page, combo, name, url);
    if (results.filter(r => r.status === 429).length) { flush(); }
  }
  await ctx.close();

  const gctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: combo.theme });
  const gp = await gctx.newPage();
  for (const [name, url] of GUEST) {
    await visit(gp, combo, `guest-${name}`, url);
  }
  // mobile guest header (guest-header fix coverage)
  const mctx = await browser.newContext({ viewport: { width: 390, height: 844 }, colorScheme: combo.theme, isMobile: true, hasTouch: true });
  const mp = await mctx.newPage();
  await visit(mp, combo, 'guest-mobile-login', '/login');
  await mctx.close();
  await gctx.close();
}

mysql(`UPDATE users SET stylesheet=5, theme='auto' WHERE id=1;`);
flush();
writeFileSync('/home/ubuntu/e2e-report/matrix-results.json', JSON.stringify(results, null, 2));
console.log('DONE', results.length);
process.exit(0);
})().catch(e => { console.error('FATAL', e); process.exit(1); });
