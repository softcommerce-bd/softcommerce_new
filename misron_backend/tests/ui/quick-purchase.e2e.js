// Drives Quick Purchase in a real browser: search, add, price, Receive (double click), then checks the DOM message.
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1300, height: 900 } }); const errs = [];
  p.on('pageerror', (e) => errs.push(e.message));
  await p.goto(BASE + '/users/login'); await p.fill('form[action$="/login"] input[name=email]', 'e2e@test.local'); await p.fill('form[action$="/login"] input[name=password]', 'secret123');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/login"] button[type=submit]')]);
  await p.goto(BASE + '/admin/purchasing/quick-purchase');
  // empty submit shows a validation message, not a crash
  await p.click('[data-act=receive]'); console.log('empty submit ->', (await p.textContent('#qp-msg')).trim());
  await p.fill('#qp-search', 'a'); await p.waitForSelector('#qp-results button', { timeout: 8000 }).catch(() => {});
  const n = await p.locator('#qp-results button').count(); console.log('search hits:', n);
  if (n) {
    await p.locator('#qp-results button').first().click();
    await p.locator('#qp-lines tbody tr').first().waitFor();
    const inputs = p.locator('#qp-lines tbody tr').first().locator('input');
    console.log('line inputs:', await inputs.count());
    await inputs.nth(0).fill('3'); await inputs.nth(1).fill('55');
    await p.screenshot({ path: '/tmp/claude-0/shots/qp-filled.png' });
    await Promise.all([p.click('[data-act=receive]'), p.click('[data-act=receive]').catch(() => {})]);
    await p.waitForTimeout(2500);
    console.log('after receive ->', (await p.textContent('#qp-msg')).trim());
  }
  console.log('page errors:', JSON.stringify(errs)); await b.close();
})();
