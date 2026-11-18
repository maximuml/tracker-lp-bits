const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.connectOverCDP('http://localhost:29229');
    const ctx = await browser.newContext({ storageState: '.auth/state.json', viewport: { width: 1400, height: 1100 } });
    const page = await ctx.newPage();
    const shots = [
        ['/web/index', 'index-classstats'],
        ['/web/settings?action=authoritysettings', 'settings-authority'],
        ['/web/settings?action=attachmentsettings', 'settings-attachment'],
        ['/web/userdetails?id=1', 'userdetails'],
    ];
    for (const [url, name] of shots) {
        await page.goto('http://127.0.0.1' + url, { waitUntil: 'networkidle' });
        await page.screenshot({ path: `/home/ubuntu/shots/tierpurge-${name}.png`, fullPage: false });
        console.log('shot', name);
    }
    // verify select has no retired options
    await page.goto('http://127.0.0.1/web/settings?action=authoritysettings', { waitUntil: 'networkidle' });
    const opts = await page.$$eval('select[name="log"] option', els => els.map(e => e.textContent.trim() + '=' + e.value));
    console.log('log select options:', JSON.stringify(opts));
    await ctx.close();
})();
