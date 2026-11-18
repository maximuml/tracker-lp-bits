const { chromium } = require('./node_modules/playwright-core');
const { execFileSync } = require('node:child_process');
const { readFileSync, mkdirSync } = require('node:fs');
const ENV = readFileSync('/home/ubuntu/repos/tracker-lp-bits/.env', 'utf8');
const DBPW = ENV.match(/^DB_PASSWORD=(.*)$/m)[1].trim();
const OUT = '/home/ubuntu/rankshots';
const COMBOS = [
  { sheet: 5, theme: 'light', label: 'unshatter-light' },
  { sheet: 5, theme: 'dark', label: 'unshatter-dark' },
  { sheet: 4, theme: 'light', label: 'classic-light' },
  { sheet: 4, theme: 'dark', label: 'classic-dark' },
];
const PAGES = [
  ['userdetails-staff', '/userdetails.php?id=1'],
  ['index-shoutbox', '/'],
  ['userdetails-ultimate', '/userdetails.php?id=10001'],
  ['shout-history', '/web/shoutbox_history'],
];
const sql = (q) => execFileSync('docker', ['exec', '-i', 'nexusphp-mysql', 'mysql', '-uroot', '-p' + DBPW, 'nexusphp', '-e', q]);
(async () => {
  mkdirSync(OUT, { recursive: true });
  const b = await chromium.connectOverCDP('http://localhost:29229');
  for (const c of COMBOS) {
    sql(`UPDATE users SET stylesheet=${c.sheet},theme='${c.theme}' WHERE id=1;`);
    const ctx = await b.newContext({ storageState: '.auth/state.json', viewport: { width: 1440, height: 1100 } });
    const p = await ctx.newPage();
    for (const [name, url] of PAGES) {
      await p.goto('http://127.0.0.1' + url, { waitUntil: 'networkidle' });
      await p.waitForTimeout(400);
      await p.screenshot({ path: `${OUT}/${c.label}--${name}.png`, fullPage: true });
      console.log(c.label, name, 'done');
    }
    await ctx.close();
  }
  sql("UPDATE users SET stylesheet=5,theme='auto' WHERE id=1;");
  sql("UPDATE users SET class=1 WHERE id=10001;");
  await b.close();
})();
