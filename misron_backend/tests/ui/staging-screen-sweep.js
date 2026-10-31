// READ-ONLY sweep (GET only) of every purchasing / supplier / inventory / returns / order / accounting screen against a STAGING database,
// desktop and mobile. It does not submit any form, so it cannot change the figures the accountant is reviewing.
//   BASE=http://127.0.0.1:8000 STAGING_USER=... STAGING_PASS=... THEME=/tmp/claude-0/theme node tests/ui/staging-screen-sweep.js
const { chromium } = require('playwright'); const fs = require('fs'); const { execSync, execFileSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000', THEME = process.env.THEME || '/tmp/claude-0/theme', DB = process.env.STAGING_DB || 'sc_staging';
const SHOTS = process.env.SHOTS || '/tmp/claude-0/shots-staging'; fs.mkdirSync(SHOTS, { recursive: true });
const N = THEME + '/node_modules';
const CDN = [[/code\.jquery\.com\/jquery-3\.6\.0\.min\.js/, N + '/jquery/dist/jquery.min.js', 'application/javascript'], [/toastr\.min\.js/, N + '/toastr/build/toastr.min.js', 'application/javascript'], [/toastr\.min\.css/, N + '/toastr/build/toastr.min.css', 'text/css'], [/chart(\.umd)?(\.min)?\.js/, N + '/chart.js/dist/chart.min.js', 'application/javascript']];
const sql = (q) => execFileSync('mysql', ['-N', DB, '-e', q]).toString().trim();
const firstOrder = sql('select id from orders where delivery_status in ("confirmed","pending") order by id desc limit 1') || sql('select id from orders order by id desc limit 1');
const enc = (id) => execFileSync('php', ['artisan', 'tinker', '--execute=echo urlencode(encrypt(' + id + '));'], { env: process.env }).toString().trim().split('\n').pop();
const firstSupplier = sql('select id from suppliers order by id limit 1'), firstProduct = sql('select id from products order by id limit 1');
const SCREENS = [
  ['Purchase Dashboard', '/admin/purchasing'], ['Quick Purchase', '/admin/purchasing/quick-purchase'], ['Purchase Requirements', '/admin/purchasing/requirements'],
  ['Purchase Orders', '/admin/purchasing/reports/purchase-orders'], ['Goods Receiving', '/admin/purchasing/receiving'], ['Supplier Payments (new)', '/admin/purchasing/supplier-payments?supplier_id=' + firstSupplier],
  ['Supplier Outstanding', '/admin/purchasing/supplier-outstanding'], ['Supplier Ledger/Statement', '/admin/purchasing/reports/supplier-statement?supplier_id=' + firstSupplier],
  ['Inventory Overview / Stock Policies', '/admin/purchasing/stock'], ['Stock Movements', '/admin/purchasing/movements'], ['Backorders', '/admin/purchasing/backorders'], ['Inventory Valuation', '/admin/purchasing/valuation'],
  ['Customer Returns (receive)', '/admin/purchasing/returns'], ['Return to Supplier', '/admin/purchasing/supplier-returns'], ['Reconciliation', '/admin/purchasing/reports/reconciliation'],
  ['Order Profit', '/admin/purchasing/reports/actual-order-profit'], ['General Ledger (journal)', '/admin/purchasing/reports/general-ledger'], ['Journal Trial Balance', '/admin/purchasing/reports/trial-balance'],
  ['Legacy Purchase list', '/purchase/index'], ['Legacy Purchase create', '/purchase/create'], ['Legacy Supplier Payments', '/admin/accounts/supplier-payments'], ['Legacy Supplier Payment create', '/admin/accounts/supplier-payments/create'],
  ['Customer Payments', '/admin/accounts/customer-payments'], ['Accounts dashboard', '/admin/accounts'], ['Balance Sheet', '/admin/accounts/reports/balance-sheet'], ['Balance Sheet (past date)', '/admin/accounts/reports/balance-sheet?date_range=custom&end_date=2026-09-14'],
  ['Profit & Loss', '/admin/accounts/reports/profit-loss?date_range=this_year'], ['Trial Balance', '/admin/accounts/reports/trial-balance'], ['Cash Book', '/admin/accounts/reports/cash-book'],
  ['Courier Settlement', '/courier-settlement/index'], ['Stock Adjustments', '/admin/stock-adjustments'], ['Drafts', '/draft/index'], ['All Orders', '/admin/all_orders'], ['Order Details', '/admin/orders/' + enc(firstOrder) + '/show'],
];
const results = [];
(async () => {
  const b = await chromium.launch();
  for (const [label, vp] of [['desktop', { width: 1400, height: 900 }], ['mobile', { width: 390, height: 800 }]]) {
    const ctx = await b.newContext({ viewport: vp }); const p = await ctx.newPage(); const errs = [];
    await p.route(/^https?:\/\/(?!127\.0\.0\.1)/, async (r) => { const u = r.request().url(); for (const [re, f, t] of CDN) if (re.test(u) && fs.existsSync(f)) return r.fulfill({ body: fs.readFileSync(f), contentType: t }); return r.abort(); });
    p.on('pageerror', (e) => errs.push(e.message)); p.on('response', (r) => r.status() >= 500 && errs.push(r.status() + ' ' + r.url()));
    await p.goto(BASE + '/users/login'); const token = await p.locator('form[action$="/login"] input[name=_token]').first().getAttribute('value');
    await ctx.request.post(BASE + '/login', { form: { _token: token, email: process.env.STAGING_USER, password: process.env.STAGING_PASS }, maxRedirects: 0 }).catch(() => {});
    await p.goto(BASE + '/admin'); if (!/\/admin/.test(p.url())) throw new Error('staging login failed');
    for (const [name, path] of SCREENS) {
      errs.length = 0; const t0 = Date.now(); const r = await p.goto(BASE + path, { waitUntil: 'load' }).catch((e) => ({ status: () => 0 })); await p.waitForTimeout(150);
      const body = await p.locator('body').innerText().catch(() => ''); const fatal = /Whoops|Server Error|SQLSTATE|Undefined (variable|array|index)|ErrorException|Call to undefined|Something went wrong/.test(body);
      const ov = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth).catch(() => -1); const ms = Date.now() - t0;
      await p.screenshot({ path: `${SHOTS}/${label}-${name.replace(/\W+/g, '_')}.png` });
      const pass = r.status() === 200 && !fatal && errs.length === 0 && ov <= (path.includes('/accounts') ? 8 : 1);
      results.push({ label, name, path: path.replace(/\/orders\/.*\/show/, '/orders/<encrypted>/show'), status: r.status(), fatal, overflow: ov, errors: errs.slice(0, 2), ms, pass });
      console.log(`${pass ? 'PASS' : 'FAIL'}  ${label.padEnd(7)} ${String(r.status()).padEnd(4)} ${String(ms).padStart(5)}ms ov=${ov}  ${name}${errs.length ? ' — ' + errs[0].slice(0, 100) : ''}`);
    }
    await ctx.close();
  }
  await b.close(); fs.writeFileSync(SHOTS + '/staging-sweep-results.json', JSON.stringify(results, null, 1));
  console.log(`\n${results.filter((r) => r.pass).length}/${results.length} screen checks passed`);
})();
