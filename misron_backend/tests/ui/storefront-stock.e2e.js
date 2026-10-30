// Storefront product page vs Laravel availability: buttons must match can_order.
const { chromium } = require('playwright');
const SITE = process.env.SITE || 'http://127.0.0.1:3000';
(async () => {
  const b = await chromium.launch();
  for (const [slug, expectOrder] of [['sample-rice-cooker-jexw', true], ['sample-sold-out-lamp-6umi', false], ['sample-limited-edition-watch-tkks', true]]) {
    for (const vp of [{ width: 1300, height: 900 }, { width: 390, height: 800 }]) {
      const p = await b.newPage({ viewport: vp }); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
      await p.goto(`${SITE}/product/${slug}`, { waitUntil: 'networkidle' }).catch(() => {});
      const btns = await p.locator('button').evaluateAll((els) => els.map((e) => ({ t: e.innerText.trim().slice(0, 30), d: e.disabled })).filter((x) => /cart|buy|order|stock|কার্ট|কিনুন|অর্ডার|স্টক/i.test(x.t)));
      const overflow = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      console.log(slug, vp.width, 'expectOrder=' + expectOrder, JSON.stringify(btns), 'overflow=' + overflow, errs.length ? 'ERR ' + errs[0] : '');
      if (vp.width === 1300) await p.screenshot({ path: `/tmp/claude-0/shots/sf-${slug}.png` });
      await p.close();
    }
  }
  await b.close();
})();
