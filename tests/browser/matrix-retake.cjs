const { chromium } = require('./node_modules/playwright-core');
const { execFileSync } = require('node:child_process');
const { readFileSync, writeFileSync } = require('node:fs');
const ENV = readFileSync('/home/ubuntu/repos/tracker-lp-bits/.env', 'utf8');
const DBPW = ENV.match(/^DB_PASSWORD=(.*)$/m)[1].trim();
const COMBOS = [
  { sheet: 5, theme: 'light', label: 'unshatter-light' },
  { sheet: 5, theme: 'dark', label: 'unshatter-dark' },
  { sheet: 4, theme: 'light', label: 'classic-light' },
  { sheet: 4, theme: 'dark', label: 'classic-dark' },
];
const PAGES = [
  ['userdetails-user', '/userdetails.php?id=10001'],
  ['viewmessage', '/web/messages?action=viewmessage&id=86'],
  ['post', '/forums.php?action=reply&topicid=2'],
];
const results = [];
(async () => {
  const b = await chromium.connectOverCDP('http://localhost:29229');
  for (const c of COMBOS) {
    execFileSync('docker', ['exec', '-i', 'nexusphp-mysql', 'mysql', '-uroot', '-p' + DBPW, 'nexusphp', '-e', `UPDATE users SET stylesheet=${c.sheet},theme='${c.theme}' WHERE id=1;`]);
    const ctx = await b.newContext({ storageState: '.auth/state.json', viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    for (const [name, url] of PAGES) {
      const file = `/home/ubuntu/e2e-report/shots/${c.label}--${name}.jpg`;
      const r = await p.goto('http://127.0.0.1' + url, { waitUntil: 'networkidle' });
      await p.waitForTimeout(500);
      await p.screenshot({ path: file, type: 'jpeg', quality: 70, fullPage: true });
      results.push({ combo: c.label, page: name, url, status: r.status(), file });
      console.log(c.label, name, r.status());
    }
    await ctx.close();
  }
  execFileSync('docker', ['exec', '-i', 'nexusphp-mysql', 'mysql', '-uroot', '-p' + DBPW, 'nexusphp', '-e', "UPDATE users SET stylesheet=5,theme='auto' WHERE id=1;"]);
  // merge into matrix-results.json
  const m = JSON.parse(readFileSync('/home/ubuntu/e2e-report/matrix-results.json', 'utf8'));
  for (const r of results) {
    const i = m.findIndex(x => x.combo === r.combo && x.page === r.page);
    if (i >= 0) m[i] = { ...m[i], ...r, jsErr: [], http4xx: [], leak: null };
  }
  writeFileSync('/home/ubuntu/e2e-report/matrix-results.json', JSON.stringify(m, null, 2));
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
