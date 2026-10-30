// Real-browser run of the admin screens against a running Laravel (BASE) on a TEST database.
// Run: BASE=http://127.0.0.1:8000 NODE_PATH=$(npm root -g) node tests/ui/admin-screens.e2e.js
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const OUT = process.env.OUT || '/tmp/claude-0/shots';
const fs = require('fs'); fs.mkdirSync(OUT, { recursive: true });
const PAGES = (process.env.PAGES || '/admin/purchasing,/admin/purchasing/quick-purchase,/admin/purchasing/requirements,/admin/purchasing/receiving,/admin/purchasing/stock,/admin/purchasing/returns,/admin/purchasing/backorders,/admin/purchasing/valuation,/admin/purchasing/supplier-outstanding,/admin/purchasing/movements,/admin/purchase/index,/admin/purchase/create,/admin/all_orders').split(',');
(async () => {
  const b = await chromium.launch(); const results = [];
  for (const [label, vp] of [['desktop', { width: 1400, height: 900 }], ['mobile', { width: 390, height: 800 }]]) {
    const ctx = await b.newContext({ viewport: vp }); const p = await ctx.newPage();
    const errs = []; p.on('pageerror', (e) => errs.push('JS ' + e.message)); p.on('console', (m) => m.type() === 'error' && errs.push('CON ' + m.text()));
    p.on('response', (r) => r.status() >= 400 && !/\.(png|jpg|ico|woff2?|map)/.test(r.url()) && errs.push(r.status() + ' ' + r.url()));
    await p.goto(BASE + '/users/login'); await p.fill('form[action$="/login"] input[name=email]', 'e2e@test.local'); await p.fill('form[action$="/login"] input[name=password]', 'secret123');
    await Promise.all([p.waitForNavigation(), p.click('form[action$="/login"] button[type=submit]')]);
    for (const path of PAGES) {
      errs.length = 0; const resp = await p.goto(BASE + path, { waitUntil: 'networkidle' }).catch((e) => ({ status: () => 'ERR ' + e.message }));
      const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth).catch(() => -1);
      const title = await p.title(); const h = (await p.locator('h1,h2,.h3,.h4').first().innerText().catch(() => '')).slice(0, 50);
      const body = await p.locator('body').innerText().catch(() => ''); const fatal = /Whoops|Server Error|SQLSTATE|Undefined (variable|array)|ErrorException/.test(body);
      await p.screenshot({ path: `${OUT}/${label}-${path.replace(/\W+/g, '_')}.png`, fullPage: false });
      results.push({ label, path, status: resp.status(), overflow, fatal, h, errs: [...errs] });
    }
    await ctx.close();
  }
  await b.close();
  for (const r of results) console.log(`${r.label.padEnd(8)} ${String(r.status).padEnd(4)} ov=${String(r.overflow).padEnd(4)} ${r.fatal ? 'FATAL ' : ''}${r.path}  [${r.h}] ${r.errs.length ? JSON.stringify(r.errs.slice(0, 3)) : ''}`);
})();
