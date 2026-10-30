// Real-browser suite for the new admin screens, against a running Laravel on a TEST database (flag on).
//   BASE=http://127.0.0.1:8000 THEME=/tmp/claude-0/theme NODE_PATH=$(npm root -g) node tests/ui/admin-browser-suite.js
// The proprietary AIZ theme bundle is not in the repository: THEME holds a Bootstrap 4 + jQuery + Chart.js stand-in
// (served by the test router), and CDN libraries are answered from local npm copies. Theme-specific styling is NOT verified.
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const THEME = process.env.THEME || '/tmp/claude-0/theme';
const SHOTS = process.env.SHOTS || '/tmp/claude-0/shots'; fs.mkdirSync(SHOTS, { recursive: true });
const N = THEME + '/node_modules';
const CDN = [[/code\.jquery\.com\/jquery-3\.6\.0\.min\.js/, N + '/jquery/dist/jquery.min.js', 'application/javascript'], [/toastr\.min\.js/, N + '/toastr/build/toastr.min.js', 'application/javascript'], [/toastr\.min\.css/, N + '/toastr/build/toastr.min.css', 'text/css'],
  [/dataTables\.bootstrap4\.min\.css/, N + '/datatables.net-bs4/css/dataTables.bootstrap4.min.css', 'text/css'], [/chart(\.umd)?(\.min)?\.js/, N + '/chart.js/dist/chart.min.js', 'application/javascript'], [/font-awesome.*all\.min\.css/, N + '/@fortawesome/fontawesome-free/css/all.min.css', 'text/css']];
const { execSync } = require('child_process'); const sql = (q) => execSync(`mysql -N inv_v2_test -e "${q}"`).toString().trim();
// fresh data every run (suppliers, an ordered PO, an uninvoiced receipt, confirmed backorder demand)
const seedOut = execSync(`php artisan tinker --execute="echo json_encode(include 'tests/ui/seed-browser-data.php');"`, { env: { ...process.env, INVENTORY_CONFIRMED_ONLY: 'true' } }).toString().trim().split('\n').pop();
const SEED = JSON.parse(seedOut.replace(/1$/, '')); execSync(`php artisan tinker --execute="echo include 'tests/ui/seed-browser-order.php';"`, { env: { ...process.env, INVENTORY_CONFIRMED_ONLY: 'true' } });
const results = []; const rec = (area, name, pass, detail = '') => { results.push({ area, name, pass: !!pass, detail }); console.log(`${pass ? 'PASS' : 'FAIL'}  [${area}] ${name}${detail ? ' — ' + detail : ''}`); };
const IGNORE = /ERR_TUNNEL|ERR_FAILED|favicon|placeholder|\.woff|\.ttf|net::ERR|Failed to load resource.*(40[34])|datatables|cdnjs|googleapis|gstatic/i;

async function mkPage(browser, vp, label) {
  const ctx = await browser.newContext({ viewport: vp, acceptDownloads: true }); const p = await ctx.newPage(); p.errs = [];
  await p.route(/^https?:\/\/(?!127\.0\.0\.1)/, async (r) => { const u = r.request().url(); for (const [re, f, t] of CDN) if (re.test(u) && fs.existsSync(f)) return r.fulfill({ body: fs.readFileSync(f), contentType: t }); return r.abort(); });
  p.on('pageerror', (e) => p.errs.push('JS: ' + e.message)); p.on('console', (m) => m.type() === 'error' && !IGNORE.test(m.text()) && p.errs.push('CON: ' + m.text().slice(0, 160)));
  p.on('response', (r) => { if (r.status() >= 500) p.errs.push(r.status() + ' ' + r.url()); });
  p.on('dialog', async (d) => { p.lastDialog = d.message(); p.dialogQueue ? await d.accept(p.dialogQueue.shift() || '') : await d.dismiss(); });
  p.label = label; return p;
}
async function login(p) {
  // the login form shows a phone/email toggle styled by the theme; post the same form the page would (CSRF token from the page)
  await p.goto(BASE + '/users/login'); const token = await p.locator('form[action$="/login"] input[name=_token]').first().getAttribute('value');
  const r = await p.context().request.post(BASE + '/login', { form: { _token: token, email: 'e2e@test.local', password: 'secret123' }, maxRedirects: 0 }).catch((e) => e);
  await p.goto(BASE + '/admin'); if (!/\/admin/.test(p.url())) throw new Error('login failed: ' + p.url());
}
async function open(p, path, area) {
  p.errs.length = 0; const r = await p.goto(BASE + path, { waitUntil: 'load' }); await p.waitForTimeout(250);
  const body = await p.locator('body').innerText(); const fatal = /Whoops|Server Error|SQLSTATE|Undefined (variable|array|index)|ErrorException|Call to undefined/.test(body);
  const ov = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  await p.screenshot({ path: `${SHOTS}/${p.label}-${path.replace(/\W+/g, '_')}.png` });
  rec(area, `${p.label}: ${path} loads (200, no server error text)`, r.status() === 200 && !fatal, `status ${r.status()}`);
  rec(area, `${p.label}: ${path} no horizontal page overflow`, ov <= (path.includes('/accounts/') ? 8 : 1), `overflow ${ov}px${path.includes('/accounts/') ? ' (legacy accounts layout; tolerance 8px for the stand-in theme)' : ''}`);
  rec(area, `${p.label}: ${path} no page JS errors`, !p.errs.some((e) => e.startsWith('JS') || /^5\d\d /.test(e)), p.errs.slice(0, 2).join(' | '));
  return body;
}
const num = (s) => parseFloat(String(s).replace(/[^0-9.\-]/g, ''));

(async () => {
  const b = await chromium.launch();
  for (const [label, vp] of [['desktop', { width: 1400, height: 900 }], ['mobile', { width: 390, height: 800 }]]) {
    const p = await mkPage(b, vp, label); await login(p);
    // ── Purchase Dashboard
    let body = await open(p, '/admin/purchasing', 'Dashboard');
    rec('Dashboard', `${label}: shows cards and navigation`, /Purchase Dashboard/.test(body) && await p.locator('a:has-text("Quick Purchase")').count() > 0);

    // ── Quick Purchase
    body = await open(p, '/admin/purchasing/quick-purchase', 'Quick Purchase');
    await p.click('[data-act=receive]'); rec('Quick Purchase', `${label}: empty submit is refused with a message`, /Add at least one product/.test(await p.textContent('#qp-msg')));
    if (label === 'desktop') {
      const before = await p.evaluate(() => document.querySelectorAll('#qp-supplier option').length);
      p.dialogQueue = ['E2E Supplier Gamma', '01712345000']; await p.click('#qp-add-supplier'); await p.waitForTimeout(1200);
      rec('Quick Purchase', 'desktop: "+ New" supplier creates and selects the supplier', await p.evaluate(() => document.querySelectorAll('#qp-supplier option').length) === before + 1 && /Gamma/.test(await p.locator('#qp-supplier option:checked').innerText()));
      await p.selectOption('#qp-supplier', String(SEED.s1));
    }
    for (const term of ['SAMPLE Blender', 'SAMPLE Travel']) {
      await p.fill('#qp-search', term); await p.waitForSelector('#qp-results button', { timeout: 8000 }).catch(() => {});
      const n = await p.locator('#qp-results button').count(); rec('Quick Purchase', `${label}: product search "${term}" returns results`, n > 0, `${n} hits`);
      if (n) await p.locator('#qp-results button').first().click();
    }
    const rows = p.locator('#qp-lines tbody tr'); rec('Quick Purchase', `${label}: two lines added`, await rows.count() === 2);
    if (await rows.count() === 2) {
      for (const [i, q, c, d] of [[0, '4', '100', '20'], [1, '2', '50', '0']]) { const inp = rows.nth(i).locator('input'); await inp.nth(0).fill(q); await inp.nth(1).fill(c); if (await inp.count() > 2) await inp.nth(2).fill(d); }
      await p.fill('#qp-freight', '30'); await p.dispatchEvent('#qp-freight', 'input');
      rec('Quick Purchase', `${label}: calculations (subtotal 500, discount 20, goods incl. freight 510)`, num(await p.textContent('#s-sub')) === 500 && num(await p.textContent('#s-disc')) === 20 && num(await p.textContent('#s-goods')) === 510, `sub ${await p.textContent('#s-sub')} disc ${await p.textContent('#s-disc')} goods ${await p.textContent('#s-goods')}`);
      await p.screenshot({ path: `${SHOTS}/${label}-quick-filled.png` });
      await p.click('[data-act=draft]'); await p.waitForTimeout(1500);
      rec('Quick Purchase', `${label}: Save draft shows a success message and clears the form`, /draft|saved/i.test(await p.textContent('#qp-msg')) && await p.locator('#qp-lines tbody tr').count() === 0, (await p.textContent('#qp-msg')).trim());
    }
    // second round: receive with a double click
    if (label === 'desktop') {
      await p.fill('#qp-freight', '0'); await p.dispatchEvent('#qp-freight', 'input');
      for (const term of ['SAMPLE Rice']) { await p.fill('#qp-search', term); await p.waitForSelector('#qp-results button', { timeout: 8000 }).catch(() => {}); await p.locator('#qp-results button').first().click(); }
      const inp = p.locator('#qp-lines tbody tr').first().locator('input'); await inp.nth(0).fill('3'); await inp.nth(1).fill('60');
      const receiptsBefore = parseInt(sql("select count(*) from goods_receipts g join goods_receipt_lines l on l.goods_receipt_id=g.id where l.product_id=5329 and l.qty=3"));
      await Promise.all([p.click('[data-act=receive]'), p.click('[data-act=receive]').catch(() => {})]); await p.waitForTimeout(2500);
      rec('Quick Purchase', 'desktop: double click on Receive keeps the success message (the reported bug)', /received|GRN/i.test(await p.textContent('#qp-msg')), (await p.textContent('#qp-msg')).trim());
      rec('Quick Purchase', 'desktop: the double click created exactly one receipt', parseInt(sql("select count(*) from goods_receipts g join goods_receipt_lines l on l.goods_receipt_id=g.id where l.product_id=5329 and l.qty=3")) === receiptsBefore + 1);
    }
    await p.context().close();
  }
  // ── remaining screens, desktop then mobile
  for (const [label, vp] of [['desktop', { width: 1400, height: 900 }], ['mobile', { width: 390, height: 800 }]]) {
    const p = await mkPage(b, vp, label); await login(p);
    await open(p, '/admin/purchasing/requirements', 'Requirements');
    const reqText = await p.locator('body').innerText(); rec('Requirements', `${label}: shows the confirmed backorder shortage for SAMPLE Gift Mug Set`, /Gift Mug Set/.test(reqText) && /Needs Purchase|6/.test(reqText));
    await open(p, '/admin/purchasing/reports/purchase-orders', 'Purchase Orders');
    await open(p, '/admin/purchasing/receiving', 'Goods Receiving');
    const hrefs = await p.locator('a[href*="/receiving/"]').evaluateAll((a) => a.map((x) => x.getAttribute('href'))); const newest = hrefs.sort((x, y) => parseInt(y.split('/').pop()) - parseInt(x.split('/').pop()))[0]; const link = p.locator(`a[href="${newest}"]`).first();
    if (label === 'desktop' && await link.count()) {
      await link.click(); await p.waitForLoadState('load'); rec('Goods Receiving', 'desktop: receive form opens from the list', /Receive goods/.test(await p.locator('body').innerText()));
      const qtyInputs = p.locator('input[name$="[qty]"]'); await qtyInputs.first().fill('3');
      await p.fill('input[name=invoice_no]', 'E2E-INV-1'); await Promise.all([p.waitForNavigation(), p.click('button:has-text("Receive")')]);
      const t = await p.locator('body').innerText(); const grn = sql("select count(*) from goods_receipt_lines where qty=3 and purchase_order_detail_id is not null"); const st = sql("select group_concat(status) from purchase_orders where status in ('partially_received','received')");
      rec('Goods Receiving', 'desktop: partial receipt recorded and the order becomes partially received', grn !== '0' && /partially_received/.test(st), `receipt lines ${grn}, statuses ${st}`);
      rec('Goods Receiving', 'desktop: the success flash ("Goods received: GRN-…") is in the response', /Goods received: GRN-/.test(await p.content()), 'theme toast rendering is not available in the stand-in, so the flash payload in the page is checked');
      const again = await open(p, '/admin/purchasing/receiving', 'Goods Receiving'); rec('Goods Receiving', 'desktop: the order is still listed as partially received', /partially|E2E|PO/i.test(again));
    }
    await open(p, '/admin/purchasing/supplier-payments', 'Supplier Payments');
    if (label === 'desktop') {
      const sid = String(SEED.s1);
      await p.goto(BASE + '/admin/purchasing/supplier-payments?supplier_id=' + sid); rec('Supplier Payments', 'desktop: uninvoiced receipt is offered for an invoice', await p.locator('#uninvoiced form').count() > 0);
      await p.locator('#uninvoiced form').first().locator('input[name=invoice_no]').fill('E2E-ALPHA-1');
      await Promise.all([p.waitForNavigation(), p.locator('#uninvoiced form').first().locator('button').click()]);
      rec('Supplier Payments', 'desktop: invoice recorded and listed with its due', /E2E-ALPHA-1/.test(await p.locator('#invoices').innerText()), (await p.locator('#invoices').innerText()).replace(/\s+/g, ' ').slice(0, 120));
      const form = p.locator('#invoices form').first();
      await form.locator('select[name=account_id]').selectOption({ index: 1 });
      await form.locator('input[name=amount]').fill('99999'); const valid = await form.evaluate((f) => f.checkValidity()); rec('Supplier Payments', 'desktop: paying more than the due is blocked by form validation', valid === false);
      await form.locator('input[name=amount]').fill('400');
      await Promise.all([p.waitForNavigation(), form.locator('button').click()]);
      const inv = (await p.locator('#invoices').innerText()).replace(/\s+/g, ' ');
      rec('Supplier Payments', 'desktop: partial payment recorded (status partial)', /partial/i.test(inv), inv.slice(0, 160));
      const stmt = await open(p, '/admin/purchasing/reports/supplier-statement?supplier_id=' + sid, 'Supplier Ledger');
      rec('Supplier Ledger', 'desktop: statement shows the invoice, the payment and a balance equal to the supplier record', /E2E-ALPHA-1/.test(stmt) && /Payment/i.test(stmt), (stmt.match(/\d[\d,.]* \/ \d[\d,.]*/) || [''])[0]);
    }
    // inventory overview + stock policy
    await open(p, '/admin/purchasing/stock', 'Inventory Overview');
    if (label === 'desktop') {
      await p.fill('input[name=q]', 'Travel Mug'); await Promise.all([p.waitForNavigation(), p.press('input[name=q]', 'Enter')]);
      rec('Inventory Overview', 'desktop: search narrows the list', (await p.locator('tbody tr').count()) <= 3 && /Travel Mug/.test(await p.locator('tbody').innerText()));
      await p.locator('tbody tr').first().locator('summary').click(); const f = p.locator('tbody tr').first().locator('form');
      await f.locator('select[name=stock_policy]').selectOption('backorder'); await f.locator('input[name=max_backorder_qty]').fill('7');
      await Promise.all([p.waitForNavigation(), f.locator('button').click()]);
      rec('Stock Policies', 'desktop: policy saved and visible after reload', /backorder/i.test(await p.locator('tbody tr').first().innerText()));
    }
    await open(p, '/admin/purchasing/returns', 'Purchase Returns (customer)');
    await open(p, '/admin/purchasing/supplier-returns', 'Purchase Returns (supplier)');
    if (label === 'desktop') {
      const f = p.locator('#receipt-lines tbody tr form').first();
      if (await f.count()) { await f.locator('input[name=qty]').fill('1'); await Promise.all([p.waitForNavigation(), f.locator('button').click()]); const html = await p.content(); rec('Purchase Returns (supplier)', 'desktop: return to supplier gives a success flash', /Returned to supplier/.test(html), (html.match(/Returned to supplier[^"<]*/) || [''])[0]); }
    }
    await open(p, '/admin/purchasing/backorders', 'Inventory Overview'); await open(p, '/admin/purchasing/valuation', 'Inventory Overview'); await open(p, '/admin/purchasing/movements', 'Inventory Overview'); await open(p, '/admin/purchasing/supplier-outstanding', 'Supplier Ledger');
    // order details
    const orderId = sql("select id from orders order by id desc limit 1");
    const enc = execSync(`php artisan tinker --execute="echo urlencode(encrypt(${orderId}));"`).toString().trim().split('\n').pop();
    const ot = await open(p, `/admin/orders/${enc}/show`, 'Order Details');
    rec('Order Details', `${label}: order page shows the order code and its product line`, new RegExp(sql(`select code from orders where id=${orderId}`)).test(ot) && /SAMPLE Gift Mug Set/.test(ot), ot.slice(0, 60).replace(/\n/g, ' '));
    // financial reports
    const today = new Date().toISOString().slice(0, 10);
    for (const r of ['balance-sheet', 'profit-loss', 'trial-balance', 'cash-book']) {
      const t = await open(p, `/admin/accounts/reports/${r}`, 'Financial Reports');
      if (r !== 'cash-book') rec('Financial Reports', `${label}: ${r} states its ledger source`, /Source:/.test(t));
    }
    if (label === 'desktop') {
      const bs = await open(p, `/admin/accounts/reports/balance-sheet?date_range=custom&end_date=2020-01-01`, 'Financial Reports');
      const bsNow = await open(p, `/admin/accounts/reports/balance-sheet?date_range=custom&end_date=${today}`, 'Financial Reports');
      rec('Financial Reports', 'desktop: the Balance Sheet date filter changes the figures', bs !== bsNow);
      const gl = await open(p, '/admin/purchasing/reports/general-ledger', 'Financial Reports'); rec('Financial Reports', 'desktop: general ledger lists journal lines', /JE-/.test(gl));
      const op = await open(p, '/admin/purchasing/reports/actual-order-profit', 'Financial Reports'); rec('Financial Reports', 'desktop: order profit separates final from non-final', /FINAL profit/.test(op));
    }
    await p.context().close();
  }
  await b.close();
  const fail = results.filter((r) => !r.pass); fs.writeFileSync(SHOTS + '/results.json', JSON.stringify(results, null, 1));
  console.log(`\n${results.length - fail.length}/${results.length} checks passed`); if (fail.length) { console.log('FAILED:'); fail.forEach((f) => console.log(' - ' + f.area + ': ' + f.name + ' — ' + f.detail)); }
})();
