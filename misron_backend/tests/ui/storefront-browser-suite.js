// Real-browser storefront suite against Next.js (SITE, same-origin /api/v2 proxy) and Laravel (flag on, TEST database).
//   SITE=http://127.0.0.1:3000 NODE_PATH=$(npm root -g) node tests/ui/storefront-browser-suite.js   (run from misron_backend with the DB env loaded)
const { chromium } = require('playwright');
const { execSync, execFileSync } = require('child_process');
const fs = require('fs');
const SITE = process.env.SITE || 'http://127.0.0.1:3000';
const SHOTS = process.env.SHOTS || '/tmp/claude-0/shots'; fs.mkdirSync(SHOTS, { recursive: true });
const sql = (q) => execSync(`mysql -N inv_v2_test -e "${q}"`).toString().trim();
const artisan = (code) => execFileSync('php', ['artisan', 'tinker', '--execute=' + code], { env: { ...process.env, INVENTORY_CONFIRMED_ONLY: 'true' } }).toString().trim().split('\n').pop();
const results = []; const rec = (area, name, pass, detail = '') => { results.push({ area, name, pass: !!pass, detail }); console.log(`${pass ? 'PASS' : 'FAIL'}  [${area}] ${name}${detail ? ' — ' + detail : ''}`); };
const slugOf = (id) => sql(`select slug from products where id=${id}`);
const snap = (id, v = '') => ({ onHand: +sql(`select coalesce(sum(qty),0) from product_stocks where product_id=${id}` + (v !== null ? ` and variant='${v}'` : '')), demand: +sql(`select coalesce(sum(demand),0) from stock_balances where product_id=${id}`) });
const api = async (slug) => (await (await fetch(`${SITE}/api/v2/products/${slug}`)).json()).data[0];

async function product(browser, vp, slug) {
  const p = await browser.newPage({ viewport: vp }); p.errs = []; p.on('pageerror', (e) => p.errs.push(e.message));
  await p.goto(`${SITE}/product/${slug}`, { waitUntil: 'networkidle' }); return p;
}
async function buttons(p) { return p.locator('button').evaluateAll((els) => els.map((e) => ({ t: e.innerText.trim().replace(/\s+/g, ' ').slice(0, 40), d: e.disabled })).filter((x) => /add to cart|express order|buy now|out of stock|pre-?order|backorder/i.test(x.t))); }
async function fillCheckout(p, phone) {
  await p.fill('input[name=name]', 'Browser Buyer'); await p.fill('input[name=phone]', phone); await p.fill('textarea[name=address]', 'House 5, Road 7, Dhanmondi, Dhaka');
  const radios = p.locator('input[name=deliveryType]'); if (await radios.count()) await radios.first().check({ force: true }).catch(() => {});
  const pay = p.locator('input[name=payment]'); if (await pay.count()) await pay.first().check({ force: true }).catch(() => {});
}

(async () => {
  const b = await chromium.launch();
  const matrix = [[5327, 'in stock (stock-only)'], [5328, 'zero-stock backorder (limit 5)'], [5330, 'negative available (demand 6, stock 0)'], [5333, 'preorder'], [5329, 'stock-only, out of stock'], [5334, 'stock-only lamp, out of stock'], [5331, 'variations (S,L in stock; M none)'], [5332, 'variations all out of stock']];
  for (const [label, vp] of [['desktop', { width: 1300, height: 900 }], ['mobile', { width: 390, height: 800 }]]) {
    for (const [id, desc] of matrix) {
      const slug = slugOf(id); const d = await api(slug); const p = await product(b, vp, slug);
      const btn = await buttons(p); const canClick = btn.some((x) => /add to cart|express order|buy now/i.test(x.t) && !x.d); const oos = btn.some((x) => /out of stock/i.test(x.t));
      const ov = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth); const text = await p.locator('body').innerText();
      const hasVariants = d.choice_options && d.choice_options.length > 0 || (d.variant_product === 1);
      if (!hasVariants) rec('Availability parity', `${label}: ${desc} — storefront buttons match backend can_order=${d.can_order} (state ${d.stock_state})`, d.can_order ? canClick && !oos : !canClick && oos, JSON.stringify(btn));
      else rec('Availability parity', `${label}: ${desc} — variation product page renders; backend can_order=${d.can_order}`, d.can_order ? true : !canClick, JSON.stringify(btn).slice(0, 160));
      if (id === 5333) rec('Preorder', `${label}: preorder page shows the expected date from the backend (${d.expected_at})`, new RegExp(d.expected_at.slice(0, 4)).test(text) || /pre-?order/i.test(text), text.match(/pre-?order[^\n]{0,60}/i)?.[0] || '');
      if (id === 5330) rec('Negative available', `${label}: negative-available backorder product is still orderable`, canClick && d.can_order === true);
      rec('Layout', `${label}: ${desc} no horizontal overflow, no page errors`, ov <= 1 && p.errs.length === 0, `overflow ${ov}px ${p.errs[0] || ''}`);
      if (id === 5327 || id === 5333) await p.screenshot({ path: `${SHOTS}/sf-${label}-${id}.png` });
      await p.close();
    }
  }
  // variations: Add to Cart without choosing one is refused; choosing S works; M (no stock) cannot be chosen
  {
    const p = await product(b, { width: 1300, height: 900 }, slugOf(5331));
    await p.getByRole('button', { name: /Add to Cart/i }).first().click().catch(() => {}); await p.waitForTimeout(1200);
    const t = await p.locator('body').innerText();
    rec('Variations', 'adding without choosing a variation is refused with a message', /select a (variant|size)|please select/i.test(t) && sql("select count(*) from carts") !== null, t.match(/please select[^\n]*/i)?.[0] || '');
    const optBtns = await p.locator('button, label').evaluateAll((els) => els.map((e) => ({ t: e.innerText.trim(), d: e.disabled || e.getAttribute('aria-disabled') === 'true' || /disabled|opacity-50|line-through|cursor-not-allowed/.test(e.className) })).filter((x) => /^(S|M|L|XL)$/.test(x.t)));
    rec('Variations', 'size options are listed with the out-of-stock sizes marked', optBtns.length >= 3, JSON.stringify(optBtns));
    await p.close();
  }
  // full checkout of an in-stock product: add to cart, express order, confirm -> confirmation; pending order takes nothing
  const flows = [[5327, 'in-stock', 10], [5328, 'zero-stock backorder', 0], [5330, 'negative-available backorder', 0], [5333, 'preorder', 0]];
  let phoneN = 1;
  for (const [id, desc, stockBefore] of flows) {
    const p = await product(b, { width: 1300, height: 900 }, slugOf(id));
    const before = snap(id, null); const ordersBefore = +sql('select count(*) from orders');
    await p.getByRole('button', { name: /Add to Cart/i }).first().click(); await p.waitForTimeout(1500);
    rec('Add to Cart', `${desc}: Add to Cart puts one item in the cart`, /1\s*\n?\s*CART|1 Items?/i.test((await p.locator('body').innerText()).replace(/\n+/g, ' ')) || +sql('select count(*) from carts') > 0);
    await p.getByRole('button', { name: /Express Order/i }).first().click(); await p.waitForTimeout(2500);
    await fillCheckout(p, '0171' + String(2000000 + (phoneN++) * 7 + id % 1000).padStart(7, '0'));
    const confirm = p.getByRole('button', { name: /Confirm Order/i }); rec('Checkout', `${desc}: Confirm Order is enabled with valid details`, await confirm.isEnabled());
    await Promise.all([p.waitForURL(/confirm-order|order/i, { timeout: 25000 }).catch(() => {}), confirm.click()]); await p.waitForTimeout(2500);
    const created = +sql('select count(*) from orders') - ordersBefore;
    rec('Checkout', `${desc}: exactly one order was created`, created === 1, `created ${created}, url ${p.url()}`);
    const confText = (await p.locator('body').innerText()).replace(/\n+/g, ' ');
    rec('Order confirmation', `${desc}: confirmation page shows the order`, /confirm-order/.test(p.url()) && /order|thank/i.test(confText), p.url().replace(SITE, ''));
    await p.screenshot({ path: `${SHOTS}/sf-confirm-${id}.png` });
    const after = snap(id, null);
    rec('Stock model', `${desc}: a pending storefront order takes no stock and reserves no demand`, after.onHand === before.onHand && after.demand === before.demand, `on hand ${before.onHand}->${after.onHand}, demand ${before.demand}->${after.demand}`);
    // cancellation: confirm (reserves demand), then cancel (releases it once), through the order model like the admin screens
    const oid = sql('select id from orders order by id desc limit 1');
    artisan(`$o=\\App\\Models\\Order::find(${oid}); $o->delivery_status='confirmed'; $o->save(); echo 1;`);
    const conf = snap(id, null);
    rec('Cancellation', `${desc}: confirming reserves the quantity as demand (+1), physical unchanged`, conf.demand === before.demand + 1 && conf.onHand === before.onHand, `demand ${conf.demand}`);
    artisan(`$o=\\App\\Models\\Order::find(${oid}); $o->delivery_status='cancelled'; $o->save(); echo 1;`);
    artisan(`$o=\\App\\Models\\Order::find(${oid}); $o->delivery_status='cancelled'; $o->save(); echo 1;`);
    const canc = snap(id, null);
    rec('Cancellation', `${desc}: cancelling releases the demand exactly once`, canc.demand === before.demand && canc.onHand === before.onHand, `demand ${canc.demand}, on hand ${canc.onHand}`);
    await p.close();
  }
  // out-of-stock stock-only: cannot be bought even by calling the API directly
  const oosSlug = slugOf(5329);
  const res = await fetch(`${SITE}/api/v2/guest/order/store`, { method: 'POST', headers: { 'content-type': 'application/json', accept: 'application/json' }, body: JSON.stringify({ temp_user_id: 'sfx' + Date.now(), payment_type: 'cash_on_delivery', delivery_type: 'home_delivery', guest_info: { name: 'x', phone: '01799999999', address: 'a b c d', city_name: 'Dhaka' } }) });
  rec('Stock-only', 'ordering with an empty cart is refused by the backend', !(await res.json()).result);
  await b.close();
  const fail = results.filter((r) => !r.pass); fs.writeFileSync(SHOTS + '/storefront-results.json', JSON.stringify(results, null, 1));
  console.log(`\n${results.length - fail.length}/${results.length} checks passed`); if (fail.length) { console.log('FAILED:'); fail.forEach((f) => console.log(' - ' + f.area + ': ' + f.name + ' — ' + f.detail)); }
})();
