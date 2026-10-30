// Browser smoke test for the Unified Chat UI with a stubbed API (no server, no database).
// Run: NODE_PATH=$(npm root -g) node tests/ui/unified-chat.smoke.js   (needs the `playwright` package)
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path'), assert = require('assert');
const ROOT = path.resolve(__dirname, '../..');
const html = fs.readFileSync(path.join(ROOT, 'resources/views/backend/unified_chat/index.blade.php'), 'utf8')
  .replace(/@extends.*\n/, '').replace(/@section\('content'\)/, '').replace(/@endsection/g, '').replace(/@section\('script'\)[\s\S]*$/, '')
  .replace(/\{\{\s*translate\('([^']*)'\)\s*\}\}/g, '$1').replace(/<link[^>]*>/, '')
  .replace(/\{\{ url\('admin\/unified-chat'\) \}\}/, 'http://x.test/admin/unified-chat').replace(/\{\{ csrf_token\(\) \}\}/, 'tok');
const now = new Date().toISOString();
const ALL = { view_all_chats: true, reply_unified_chat: true, assign_unified_chat: true, manage_chat_accounts: true, create_order_from_chat: true, manage_chat_notes: true, view_chat_analytics: true, manage_chat_settings: true, export_chat_data: true };
const mkBoot = (permissions) => ({ me: { id: 1, name: 'Me' }, permissions, accounts: [{ id: 1, name: 'Shop Page', platform: 'facebook' }], capabilities: { facebook: { text: true, image: true, window_hours: 24 } }, agents: [{ id: 2, name: 'Rina' }], counts: { all: 1, unread: 1, mine: 0, unassigned: 1, open: 1, pending: 0, resolved: 0, archived: 0 }, poll_seconds: 600, realtime: null });
const conv = (o = {}) => ({ id: 5, platform: 'facebook', account: { id: 1, name: 'Shop Page' }, name: '<img src=x onerror=alert(1)>', avatar: null, preview: 'hello <b>', last_message_at: now, cursor: 1, unread: 0, status: 'open', priority: 'normal', assignee: null, user_id: null, tags: [], window_open: true, ...o });

async function page(browser, w, permissions, convOver = {}, detailOver = {}) {
  const p = await browser.newPage({ viewport: { width: w, height: 800 } }); const errors = []; const calls = [];
  p.on('pageerror', (e) => errors.push(e.message)); p.on('dialog', (d) => { errors.push('DIALOG'); d.dismiss(); });
  await p.route('http://x.test/**', (r) => { const u = r.request().url(); calls.push(r.request().method() + ' ' + u); const j = (o) => r.fulfill({ contentType: 'application/json', body: JSON.stringify(o) });
    if (u.includes('/api/bootstrap')) return j(mkBoot(permissions)); if (u.includes('/api/updates')) return j({ now, conversation_ids: [], unread_total: 1 });
    if (/api\/conversations\?/.test(u)) return j({ data: [conv(convOver)], has_more: false });
    if (u.includes('/messages')) return j({ data: [{ id: 1, direction: 'in', type: 'text', body: '<script>window.xss=1</script>hi', status: 'received', at: now, attachments: [] }, { id: 2, direction: 'out', type: 'text', body: 'bad', status: 'failed', error: 'Outside window', at: now, attachments: [] }], has_more: false });
    if (u.includes('/api/conversations/5')) return j({ ...conv(convOver), account_status: 'connected', window_hours: 24, window_expires_at: null, customer_notes: '', phone: null, notes: [], customer: { linked: null, orders: null }, ...detailOver });
    if (u.includes('saved-replies')) return j([]); return j({}); });
  await p.setContent(html, { waitUntil: 'load' });
  await p.addStyleTag({ path: path.join(ROOT, 'public/assets/css/unified-chat.css') });
  await p.evaluate(() => { document.getElementById('uc').dataset.base = 'http://x.test/admin/unified-chat'; });
  await p.addScriptTag({ path: path.join(ROOT, 'public/assets/js/unified-chat.js') });
  await p.waitForSelector('.uc-conv'); await p.click('.uc-conv'); await p.waitForSelector('.uc-msg');
  return { p, errors, calls };
}

(async () => {
  const b = await chromium.launch();
  for (const w of [1400, 820, 390]) {
    const { p, errors } = await page(b, w, ALL);
    const r = await p.evaluate(() => ({ xss: !!window.xss, img: !!document.querySelector('.uc-name img'), overflow: document.documentElement.scrollWidth > innerWidth, retry: [...document.querySelectorAll('.uc-msg a')].some((a) => a.textContent === 'Retry') }));
    assert.deepStrictEqual([r.xss, r.img, r.overflow, r.retry], [false, false, false, true], `${w}px: ${JSON.stringify(r)}`); assert.deepStrictEqual(errors, [], `${w}px js errors`);
    if (w <= 900) { assert.strictEqual(await p.evaluate(() => document.getElementById('uc').dataset.view), 'chat'); await p.click('#uc-back'); assert.strictEqual(await p.evaluate(() => document.getElementById('uc').dataset.view), 'list'); assert.ok(await p.isVisible('.uc-conv')); assert.ok(!(await p.isVisible('#uc-pane'))); }
    console.log(`ok  layout + XSS + retry @${w}px`); await p.close();
  }
  { // permission-aware UI: viewer only
    const { p, calls } = await page(b, 1400, { view_all_chats: true });
    const t = await p.evaluate(() => ({ nav: document.getElementById('uc-sections').textContent, order: document.getElementById('uc-order-btn').hidden, assign: document.getElementById('uc-assign').hidden, note: document.getElementById('uc-note-tab').hidden, send: document.getElementById('uc-send').disabled, text: document.getElementById('uc-text').disabled, status: document.getElementById('uc-status').disabled }));
    assert.strictEqual(t.nav, 'Saved replies', 'only permitted sections are in the menu'); assert.deepStrictEqual([t.order, t.assign, t.note, t.send, t.text, t.status], [true, true, true, true, true, true]);
    assert.ok(!calls.some((c) => c.includes('/api/accounts') || c.includes('/api/analytics')), 'no calls to forbidden endpoints'); console.log('ok  permission-aware menu and controls'); await p.close();
  }
  { // closed window / expired account: composer locked with an explanation
    const { p } = await page(b, 1400, ALL, { window_open: false }, { window_open: false });
    assert.ok(await p.evaluate(() => document.getElementById('uc-send').disabled && /window closed/i.test(document.getElementById('uc-banner').textContent))); console.log('ok  messaging-window warning locks composer'); await p.close();
  }
  { // internal note mode is separate from reply
    const { p, calls } = await page(b, 1400, ALL); await p.click('#uc-note-tab'); await p.fill('#uc-text', 'private'); await p.click('#uc-send'); await p.waitForTimeout(200);
    assert.ok(calls.some((c) => c.startsWith('POST') && c.includes('/notes')) && !calls.some((c) => c.startsWith('POST') && c.endsWith('/messages')), 'a note is never sent as a message'); console.log('ok  internal note is not sent to the customer'); await p.close();
  }
  await b.close(); console.log('all UI smoke checks passed');
})().catch((e) => { console.error('FAIL', e.message); process.exit(1); });
