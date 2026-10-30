// Add to Cart then Express Order from the real product page, against a test backend.
const { chromium } = require('playwright');
const SITE = process.env.SITE || 'http://127.0.0.1:3000';
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1300, height: 900 } }); const errs = [], api = [];
  p.on('pageerror', (e) => errs.push(e.message)); p.on('response', (r) => /\/api\/v2\/(carts|guest|order|checkout)/.test(r.url()) && api.push(r.request().method() + ' ' + r.status() + ' ' + r.url().replace(/.*api\/v2/, '')));
  // The repo has no CORS middleware (production adds CORS at the web server); the test harness adds the headers a proxy would.
  await p.route('**/api/v2/**', async (route) => {
    const h = { 'access-control-allow-origin': SITE, 'access-control-allow-headers': '*', 'access-control-allow-methods': '*', 'access-control-allow-credentials': 'true' };
    if (route.request().method() === 'OPTIONS') return route.fulfill({ status: 204, headers: h });
    const r = await route.fetch(); route.fulfill({ response: r, headers: { ...r.headers(), ...h } });
  });
  await p.goto(`${SITE}/product/sample-rice-cooker-jexw`, { waitUntil: 'networkidle' });
  await p.getByRole('button', { name: /Add to Cart/i }).first().click(); await p.waitForTimeout(2500);
  const snap = async (l) => console.log(l, p.url(), '|', (await p.locator('body').innerText()).replace(/\n+/g, ' ').slice(0, 260));
  await snap('after add to cart:');
  if (await p.getByRole('button', { name: /Express Order/i }).count()) { await p.getByRole('button', { name: /Express Order/i }).first().click(); await p.waitForTimeout(3000); }
  await snap('after express order:');
  await p.screenshot({ path: '/tmp/claude-0/shots/sf-express.png' });
  console.log('api calls:', JSON.stringify(api)); console.log('page errors:', JSON.stringify(errs)); await b.close();
})();
