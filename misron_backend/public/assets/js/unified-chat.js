/* Unified Chat admin UI. All server text is inserted with textContent / DOM nodes, never innerHTML. */
(() => {
  'use strict';
  const root = document.getElementById('uc');
  if (!root) return;
  const base = root.dataset.base, csrf = root.dataset.csrf;
  const $ = (s) => root.querySelector(s);
  const modal = document.getElementById('uc-modal');
  const PLAT = { facebook: 'f', instagram: 'IG', whatsapp: 'W', tiktok: 'T' };
  const FOLDERS = [['all', 'All conversations'], ['unread', 'Unread'], ['mine', 'Assigned to me'], ['unassigned', 'Unassigned'], ['open', 'Open'], ['pending', 'Pending'], ['resolved', 'Resolved'], ['archived', 'Archived']];
  let boot, folder = 'all', convs = [], hasMore = false, cur = null, msgs = [], earlier = false, mode = 'reply', lastPoll = null, pollTimer = null, sending = false;

  const h = (tag, attrs, ...kids) => {
    const el = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => { if (v === false || v == null) return; if (k === 'class') el.className = v; else if (k.startsWith('on')) el.addEventListener(k.slice(2), v); else el.setAttribute(k, v === true ? '' : v); });
    kids.flat().forEach((c) => { if (c != null && c !== false) el.append(c.nodeType ? c : document.createTextNode(String(c))); });
    return el;
  };
  const clear = (el) => { while (el.firstChild) el.removeChild(el.firstChild); return el; };

  async function api(path, opt = {}) {
    const o = { method: opt.method || 'GET', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
    if (opt.body instanceof FormData) o.body = opt.body; else if (opt.body) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(opt.body); }
    const r = await fetch(base + '/api/' + path, o);
    let data = null; try { data = await r.json(); } catch (e) { /* empty */ }
    if (!r.ok) { const first = data && data.errors ? Object.values(data.errors)[0][0] : null; throw new Error((data && data.message && !first ? data.message : first) || ('Request failed (' + r.status + ')')); }
    return data;
  }
  const qs = (o) => Object.entries(o).filter(([, v]) => v !== '' && v != null).map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v)).join('&');
  const ago = (iso) => { if (!iso) return ''; const d = new Date(iso), s = (Date.now() - d) / 1000; if (s < 60) return 'now'; if (s < 3600) return Math.floor(s / 60) + 'm'; if (s < 86400) return Math.floor(s / 3600) + 'h'; return d.toLocaleDateString(); };
  const dur = (s) => s == null ? '—' : s < 3600 ? Math.round(s / 60) + ' min' : (s / 3600).toFixed(1) + ' h';
  const toast = (msg, bad) => { const b = $('#uc-banner'); b.textContent = msg; b.className = 'uc-banner' + (bad ? ' err' : ''); b.hidden = false; setTimeout(() => { if (b.textContent === msg) b.hidden = true; }, 6000); };
  const store = { get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set(k, v) { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch (e) { /* private mode */ } } };
  const avatar = (c) => h('span', { class: 'uc-av', 'aria-hidden': 'true' }, c.avatar && /^https:\/\//.test(c.avatar) ? h('img', { src: c.avatar, alt: '', referrerpolicy: 'no-referrer', loading: 'lazy' }) : (c.name || '?').trim().charAt(0).toUpperCase(), h('span', { class: 'uc-plat p-' + c.platform }, PLAT[c.platform] || '?'));

  // ── bootstrap / navigation ──────────────────────────────────────────────
  async function init() {
    boot = await api('bootstrap');
    renderNav(); fillFilters();
    await loadConvs(true);
    startUpdates();
    const want = new URLSearchParams(location.search).get('c'); if (want) openConv(+want);
  }
  function renderNav() {
    const nav = clear($('#uc-folders'));
    FOLDERS.forEach(([k, label]) => nav.append(h('button', { class: 'uc-nav-i' + (k === folder && !root.dataset.page ? ' on' : ''), onclick: () => { showInbox(); folder = k; renderNav(); loadConvs(true); root.dataset.view = 'list'; root.dataset.nav = ''; } }, label, boot.counts[k] ? h('span', { class: 'uc-count' }, boot.counts[k]) : null)));
    const sec = clear($('#uc-sections')), p = boot.permissions;
    const add = (label, fn) => sec.append(h('button', { class: 'uc-nav-i', onclick: () => { fn(); root.dataset.nav = ''; } }, label));
    if (p.manage_chat_accounts) add('Connected accounts', pageAccounts);
    if (p.assign_unified_chat) add('Team assignment', pageTeam);
    add('Saved replies', pageReplies);
    if (p.view_chat_analytics) add('Analytics', pageAnalytics);
    if (p.manage_chat_settings || p.manage_chat_automation) add('Settings', pageSettings);
    const nb = document.getElementById('uc-nav-unread'); if (nb) { nb.textContent = boot.counts.unread; nb.classList.toggle('d-none', !boot.counts.unread); }
  }
  function fillFilters() {
    const a = $('#f-account'), s = $('#f-assignee');
    boot.accounts.forEach((x) => a.append(h('option', { value: x.id }, x.name + ' (' + x.platform + ')')));
    boot.agents.forEach((x) => s.append(h('option', { value: x.id }, x.name)));
    if (!boot.permissions.assign_unified_chat) s.hidden = true;
    const q = $('#uc-quick'); api('saved-replies').then((r) => { window.__replies = r; r.forEach((x) => q.append(h('option', { value: x.id }, '[' + x.lang + '] ' + x.title))); }).catch(() => {});
  }
  function showInbox() { root.dataset.page = ''; root.removeAttribute('data-page'); $('#uc-page').hidden = true; }
  function page(title, ...kids) { root.dataset.page = 'on'; const p = clear($('#uc-page')); p.hidden = false; p.append(h('h4', {}, title), ...kids); renderNav(); return p; }

  // ── conversation list ───────────────────────────────────────────────────
  const filters = () => ({ folder, q: $('#uc-search').value.trim(), platform: $('#f-platform').value, account_id: $('#f-account').value, assignee: $('#f-assignee').value, priority: $('#f-priority').value, from: $('#f-from').value, to: $('#f-to').value });
  async function loadConvs(reset) {
    const f = filters(); if (!reset && convs.length) { const l = convs[convs.length - 1]; f.before_at = l.cursor; f.before_id = l.id; }
    try { const r = await api('conversations?' + qs(f)); convs = reset ? r.data : convs.concat(r.data); hasMore = r.has_more; renderConvs(); } catch (e) { toast(e.message, true); }
  }
  function renderConvs() {
    const ul = clear($('#uc-convs'));
    if (!convs.length) ul.append(h('li', { class: 'uc-empty', style: 'padding:30px' }, 'No conversations'));
    convs.forEach((c) => ul.append(h('li', { class: 'uc-conv', role: 'option', tabindex: '0', 'aria-selected': cur && cur.id === c.id ? 'true' : 'false', 'data-id': c.id, onclick: () => openConv(c.id), onkeydown: (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openConv(c.id); } } },
      avatar(c),
      h('div', { class: 'uc-conv-main' },
        h('div', { class: 'uc-row' }, h('span', { class: 'uc-name' }, c.name), h('small', { class: 'uc-meta' }, ago(c.last_message_at))),
        h('div', { class: 'uc-row' }, h('span', { class: 'uc-prev' }, c.preview || ''), c.unread ? h('span', { class: 'uc-badge uc-unread', 'aria-label': c.unread + ' unread' }, c.unread) : null),
        h('div', { class: 'uc-meta' }, h('span', {}, c.account.name), h('span', { class: 'uc-badge' }, c.status), c.priority === 'high' || c.priority === 'urgent' ? h('span', { class: 'uc-badge uc-' + c.priority }, c.priority) : null, c.assignee ? h('span', {}, '→ ' + c.assignee.name) : null)))));
    $('#uc-convs-more').hidden = !hasMore;
  }

  // ── conversation pane ───────────────────────────────────────────────────
  async function openConv(id) {
    showInbox();
    try { cur = await api('conversations/' + id); } catch (e) { toast(e.message, true); return; }
    root.dataset.view = 'chat'; $('#uc-empty').hidden = true; $('#uc-pane').hidden = false;
    $('#uc-title').textContent = cur.name; $('#uc-sub').textContent = cur.platform + ' · ' + cur.account.name;
    $('#uc-status').value = cur.status; $('#uc-status').disabled = !boot.permissions.reply_unified_chat;
    const as = $('#uc-assign'); as.hidden = !boot.permissions.assign_unified_chat;
    clear(as).append(h('option', { value: '' }, 'Unassigned'), ...boot.agents.map((a) => h('option', { value: a.id }, a.name))); as.value = cur.assignee ? cur.assignee.id : '';
    $('#uc-order-btn').hidden = !boot.permissions.create_order_from_chat;
    $('#uc-note-tab').hidden = !boot.permissions.manage_chat_notes;
    $('#uc-file').disabled = false; $('#uc-attach-label').hidden = !(boot.capabilities[cur.platform] || {}).image;
    setMode('reply'); $('#uc-text').value = store.get('uc-draft-' + cur.id) || ''; autosize();
    banner(); renderInfo(); renderNotes();
    msgs = []; await loadMsgs(); renderConvs();
    if (cur.unread) { api('conversations/' + cur.id + '/read', { method: 'POST' }).then(() => { cur.unread = 0; const c = convs.find((x) => x.id === cur.id); if (c) c.unread = 0; boot.counts.unread = Math.max(0, boot.counts.unread - 1); renderConvs(); renderNav(); }); }
    $('#uc-text').focus({ preventScroll: true });
  }
  function banner() {
    const b = $('#uc-banner'); b.hidden = true; const msgs_ = [];
    if (cur.account_status !== 'connected') msgs_.push('This account is ' + cur.account_status + '. ' + (cur.account_error || 'Reconnect it in Connected accounts.'));
    else if (!cur.window_open) msgs_.push('Messaging window closed (' + cur.window_hours + 'h since the customer last wrote). Only an approved template message is allowed by ' + cur.platform + '.');
    else if (cur.window_expires_at) { const left = (new Date(cur.window_expires_at) - Date.now()) / 36e5; if (left < 4) msgs_.push('Reply window closes in about ' + Math.max(1, Math.round(left)) + 'h.'); }
    if (msgs_.length) { b.textContent = msgs_[0]; b.className = 'uc-banner' + (cur.account_status !== 'connected' ? ' err' : ''); b.hidden = false;
      if (cur.platform === 'whatsapp' && !cur.window_open && cur.account_status === 'connected' && boot.permissions.reply_unified_chat) b.append(' ', h('button', { class: 'uc-btn', type: 'button', onclick: templatePicker }, 'Send approved template')); }
    const can = boot.permissions.reply_unified_chat && cur.account_status === 'connected' && cur.window_open;
    $('#uc-text').disabled = mode === 'reply' && !can; $('#uc-send').disabled = mode === 'reply' && !can;
    $('#uc-text').placeholder = !boot.permissions.reply_unified_chat ? 'You can view but not reply' : (!cur.window_open ? 'Messaging window closed' : 'Type a message');
  }
  async function loadMsgs(before) {
    const r = await api('conversations/' + cur.id + '/messages' + (before ? '?before=' + before : ''));
    msgs = before ? r.data.concat(msgs) : r.data; earlier = r.has_more; renderMsgs(!before);
  }
  function msgNode(m) {
    const out = m.direction === 'out';
    const atts = m.attachments.map((a) => a.type === 'image' ? h('img', { src: base + '/attachments/' + m.id + '/' + a.index, alt: a.name || 'image', loading: 'lazy' }) : h('a', { href: base + '/attachments/' + m.id + '/' + a.index, target: '_blank', rel: 'noopener' }, '📄 ' + (a.name || a.type)));
    const status = out ? (m.status === 'failed' ? null : m.status) : null;
    return h('div', { class: 'uc-msg' + (out ? ' out' : ''), 'data-id': m.id }, atts, m.body, !m.body && !atts.length ? '[' + m.type + ']' : null,
      h('small', {}, new Date(m.at).toLocaleString(), m.auto ? ' · auto' : '', status ? ' · ' + status : '',
        m.status === 'failed' ? h('span', { class: 'err' }, ' · Not sent: ' + (m.error || 'error') + ' ') : null,
        m.status === 'failed' && boot.permissions.reply_unified_chat ? h('a', { href: '#', onclick: async (e) => { e.preventDefault(); try { await api('conversations/' + cur.id + '/messages/' + m.id + '/retry', { method: 'POST' }); refreshMsgs(); } catch (x) { toast(x.message, true); } } }, 'Retry') : null));
  }
  function renderMsgs(scroll) {
    const box = $('#uc-msgs'), atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    const e = $('#uc-earlier'); clear(box).append(e); e.hidden = !earlier;
    msgs.forEach((m) => box.append(msgNode(m)));
    if (scroll || atBottom) box.scrollTop = box.scrollHeight;
  }
  async function refreshMsgs() {
    if (!cur) return; const last = msgs.length ? msgs[msgs.length - 1].id : 0;
    const r = await api('conversations/' + cur.id + '/messages'); // newest page replaces: also refreshes statuses
    const known = new Map(msgs.map((m) => [m.id, m])); r.data.forEach((m) => known.set(m.id, m));
    msgs = [...known.values()].sort((a, b) => a.id - b.id); renderMsgs(false);
    if (msgs.length && msgs[msgs.length - 1].id !== last) { const box = $('#uc-msgs'); box.scrollTop = box.scrollHeight; api('conversations/' + cur.id + '/read', { method: 'POST' }).catch(() => {}); }
  }
  $('#uc-earlier').addEventListener('click', () => msgs.length && loadMsgs(msgs[0].id));

  // composer
  function setMode(m) { mode = m; $('#uc-composer').classList.toggle('note', m === 'note'); root.querySelectorAll('.uc-tabs [role=tab]').forEach((b) => b.setAttribute('aria-selected', b.dataset.mode === m)); $('#uc-send').textContent = m === 'note' ? 'Add note' : 'Send'; $('#uc-attach-label').style.visibility = m === 'note' ? 'hidden' : 'visible'; if (cur) banner(); if (m === 'note') { $('#uc-text').disabled = false; $('#uc-send').disabled = false; } }
  root.querySelectorAll('.uc-tabs [role=tab]').forEach((b) => b.addEventListener('click', () => setMode(b.dataset.mode)));
  const autosize = () => { const t = $('#uc-text'); t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 140) + 'px'; };
  $('#uc-text').addEventListener('input', () => { autosize(); if (cur && mode === 'reply') store.set('uc-draft-' + cur.id, $('#uc-text').value); });
  $('#uc-text').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); $('#uc-composer').requestSubmit(); } });
  $('#uc-file').addEventListener('change', (e) => { $('#uc-file-name').textContent = e.target.files[0] ? e.target.files[0].name : ''; });
  $('#uc-quick').addEventListener('change', (e) => { const r = (window.__replies || []).find((x) => x.id == e.target.value); if (r) { $('#uc-text').value = r.body; autosize(); $('#uc-text').focus(); } e.target.value = ''; });
  $('#uc-composer').addEventListener('submit', async (e) => {
    e.preventDefault(); if (sending || !cur) return;
    const text = $('#uc-text').value.trim(), file = $('#uc-file').files[0];
    if (!text && !file) return;
    sending = true; $('#uc-send').disabled = true; let failure = null;
    try {
      if (mode === 'note') { await api('conversations/' + cur.id + '/notes', { method: 'POST', body: { body: text } }); cur = await api('conversations/' + cur.id); renderNotes(); }
      else {
        const fd = new FormData(); fd.append('body', text); fd.append('client_ref', 'w' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8)); if (file) fd.append('attachment', file);
        const m = await api('conversations/' + cur.id + '/messages', { method: 'POST', body: fd }); msgs.push(m); renderMsgs(true);
        store.set('uc-draft-' + cur.id, ''); $('#uc-file').value = ''; $('#uc-file-name').textContent = '';
      }
      $('#uc-text').value = ''; autosize();
    } catch (x) { failure = x.message; }
    finally { sending = false; banner(); if (mode === 'note') $('#uc-send').disabled = false; if (failure) toast(failure, true); } // banner() resets the strip, so the error is shown after it
  });
  function renderNotes() {
    const ul = clear($('#uc-notes')); ul.hidden = !(cur.notes && cur.notes.length);
    (cur.notes || []).forEach((n) => ul.append(h('li', {}, h('strong', {}, n.author + ': '), n.body, ' ', h('small', {}, ago(n.created_at)), n.user_id === boot.me.id ? h('a', { href: '#', onclick: async (e) => { e.preventDefault(); await api('conversations/' + cur.id + '/notes/' + n.id, { method: 'DELETE' }); cur = await api('conversations/' + cur.id); renderNotes(); } }, ' delete') : null)));
  }

  // header actions
  $('#uc-status').addEventListener('change', async (e) => { try { await api('conversations/' + cur.id, { method: 'POST', body: { status: e.target.value } }); toast('Status updated'); loadConvs(true); } catch (x) { toast(x.message, true); e.target.value = cur.status; } });
  $('#uc-assign').addEventListener('change', async (e) => { try { await api('conversations/' + cur.id + '/assign', { method: 'POST', body: { user_id: e.target.value || null } }); toast('Assignment updated'); loadConvs(true); } catch (x) { toast(x.message, true); e.target.value = cur.assignee ? cur.assignee.id : ''; } });
  $('#uc-back').addEventListener('click', () => { root.dataset.view = 'list'; });
  $('#uc-open-nav').addEventListener('click', () => { root.dataset.nav = root.dataset.nav === 'on' ? '' : 'on'; });
  $('#uc-info-btn').addEventListener('click', () => { root.dataset.info = 'on'; });
  $('#uc-info-close').addEventListener('click', () => { root.dataset.info = ''; });
  $('#uc-filter-toggle').addEventListener('click', (e) => { const f = $('#uc-filters'); f.hidden = !f.hidden; e.target.setAttribute('aria-expanded', !f.hidden); });
  $('#uc-convs-more').addEventListener('click', () => loadConvs(false));
  let t; const deb = () => { clearTimeout(t); t = setTimeout(() => loadConvs(true), 300); };
  ['#uc-search', '#f-platform', '#f-account', '#f-assignee', '#f-priority', '#f-from', '#f-to'].forEach((s) => $(s).addEventListener('input', deb));

  // ── customer panel ──────────────────────────────────────────────────────
  function renderInfo() {
    const b = clear($('#uc-info-body')), c = cur, cu = c.customer;
    b.append(h('div', { style: 'text-align:center' }, avatar(c), h('h5', { style: 'margin:8px 0 0' }, c.name), h('small', {}, c.platform + (c.phone ? ' · ' + c.phone : ''))));
    b.append(h('h4', {}, 'Customer'));
    if (cu.linked) {
      b.append(h('dl', {}, h('dt', {}, 'Name'), h('dd', {}, cu.linked.name), h('dt', {}, 'Phone'), h('dd', {}, cu.linked.phone || '—'), h('dt', {}, 'Email'), h('dd', {}, cu.linked.email || '—')));
      if (boot.permissions.reply_unified_chat) b.append(h('button', { class: 'uc-btn', onclick: () => linkCustomer(null) }, 'Unlink'));
      if (cu.orders) {
        b.append(h('h4', {}, 'Orders'), h('dl', {}, h('dt', {}, 'Total'), h('dd', {}, cu.orders.count), h('dt', {}, 'Pending'), h('dd', {}, cu.orders.pending), h('dt', {}, 'Lifetime'), h('dd', {}, cu.orders.lifetime_value)));
        cu.orders.recent.forEach((o) => b.append(h('div', { class: 'uc-meta' }, '#' + o.code + ' · ' + o.total + ' · ' + o.delivery + ' · ' + o.payment)));
      }
    } else {
      b.append(h('p', { class: 'uc-meta' }, 'Not linked to a store customer.'));
      if (boot.permissions.reply_unified_chat) b.append(h('button', { class: 'uc-btn', onclick: customerSearch }, 'Link customer'));
    }
    b.append(h('h4', {}, 'Priority'));
    const pr = h('select', { 'aria-label': 'Priority' }, ...['low', 'normal', 'high', 'urgent'].map((p) => h('option', { value: p }, p))); pr.value = c.priority; pr.disabled = !boot.permissions.reply_unified_chat;
    pr.addEventListener('change', () => api('conversations/' + c.id, { method: 'POST', body: { priority: pr.value } }).then(() => loadConvs(true)).catch((x) => toast(x.message, true)));
    b.append(pr, h('h4', {}, 'Tags'));
    const tg = h('input', { value: (c.tags || []).join(', '), placeholder: 'vip, wholesale', 'aria-label': 'Tags', disabled: !boot.permissions.reply_unified_chat });
    tg.addEventListener('change', () => api('conversations/' + c.id, { method: 'POST', body: { tags: tg.value.split(',').map((x) => x.trim()).filter(Boolean).slice(0, 10) } }).catch((x) => toast(x.message, true)));
    b.append(tg, h('h4', {}, 'Customer notes'));
    const nt = h('textarea', { rows: 3, 'aria-label': 'Customer notes', disabled: !boot.permissions.reply_unified_chat }); nt.value = c.customer_notes || '';
    nt.addEventListener('change', () => api('conversations/' + c.id, { method: 'POST', body: { customer_notes: nt.value } }).catch((x) => toast(x.message, true)));
    b.append(nt);
  }
  async function linkCustomer(userId, create) { try { await api('conversations/' + cur.id + '/customer', { method: 'POST', body: { user_id: userId, create } }); cur = await api('conversations/' + cur.id); renderInfo(); closeModal(); } catch (x) { toast(x.message, true); } }
  function customerSearch() {
    const res = h('div'), inp = h('input', { type: 'search', placeholder: 'Name, phone or email (3+ characters)', 'aria-label': 'Search customers' });
    let tm; inp.addEventListener('input', () => { clearTimeout(tm); tm = setTimeout(async () => { const r = await api('customers?q=' + encodeURIComponent(inp.value)).catch(() => []); clear(res).append(...r.map((u) => h('button', { class: 'uc-hit', onclick: () => linkCustomer(u.id) }, u.name + ' · ' + (u.phone || u.email || '')))); }, 300); });
    const nm = h('input', { placeholder: 'Name' }), ph = h('input', { placeholder: 'Phone' });
    openModal('Link customer', h('div', { class: 'uc-form' }, inp, res, h('h4', {}, 'Or create a new customer'), nm, ph, h('button', { class: 'uc-btn uc-primary', onclick: () => linkCustomer(null, { name: nm.value, phone: ph.value }) }, 'Create and link')));
    inp.focus();
  }

  // ── modal ───────────────────────────────────────────────────────────────
  let opener = null;
  function openModal(title, ...kids) { opener = document.activeElement; document.getElementById('uc-modal-title').textContent = title; clear(document.getElementById('uc-modal-body')).append(...kids); modal.hidden = false; }
  function closeModal() { modal.hidden = true; if (opener) opener.focus(); }
  document.getElementById('uc-modal-close').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

  // ── create order ────────────────────────────────────────────────────────
  $('#uc-order-btn').addEventListener('click', async () => {
    const key = 'o' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); // one key per opened form: double submits return the same order
    const lines = [], districts = await api('districts').catch(() => []);
    const f = { name: h('input', { value: (cur.customer.linked && cur.customer.linked.name) || cur.name }), phone: h('input', { value: (cur.customer.linked && cur.customer.linked.phone) || cur.phone || '', inputmode: 'tel' }), email: h('input', { type: 'email' }), address: h('textarea', { rows: 2 }), city: h('select', {}, h('option', { value: '' }, 'Choose district'), ...districts.map((d) => h('option', { value: d.id }, d.name))), discount: h('input', { type: 'number', min: 0, step: '0.01', value: 0 }), note: h('input') };
    const tbody = h('tbody'), res = h('div'), err = h('div', { class: 'err', role: 'alert' }), sub = h('strong');
    const draw = () => { clear(tbody); let tot = 0; lines.forEach((l, i) => { tot += l.price * l.qty; tbody.append(h('tr', {}, h('td', {}, l.name + (l.variant ? ' (' + l.variant + ')' : '')), h('td', {}, l.price), h('td', {}, h('input', { type: 'number', min: 1, max: l.stock, value: l.qty, style: 'width:70px', 'aria-label': 'Quantity', onchange: (e) => { l.qty = Math.max(1, Math.min(l.stock, +e.target.value || 1)); draw(); } })), h('td', {}, (l.price * l.qty).toFixed(2)), h('td', {}, h('button', { type: 'button', class: 'uc-btn', 'aria-label': 'Remove', onclick: () => { lines.splice(i, 1); draw(); } }, '✕')))); }); sub.textContent = 'Items: ' + tot.toFixed(2) + ' (delivery charge is added by the store on save)'; };
    const ps = h('input', { type: 'search', placeholder: 'Search products', 'aria-label': 'Search products' }); let tm;
    ps.addEventListener('input', () => { clearTimeout(tm); tm = setTimeout(async () => { if (ps.value.length < 2) return; const r = await api('products?q=' + encodeURIComponent(ps.value)).catch(() => []); clear(res).append(...r.flatMap((p) => (p.variants.length ? p.variants : [{ variant: '', quantity: p.stock.quantity, sale_price: p.price.sale_min }]).map((v) => h('button', { type: 'button', class: 'uc-hit', disabled: v.quantity < 1, onclick: () => { const ex = lines.find((l) => l.id === p.id && l.variant === v.variant); if (ex) ex.qty = Math.min(v.quantity, ex.qty + 1); else lines.push({ id: p.id, name: p.name, variant: v.variant, price: v.sale_price, qty: 1, stock: v.quantity }); draw(); } }, p.name + (v.variant ? ' — ' + v.variant : '') + ' · ' + v.sale_price + ' · stock ' + v.quantity)))); }, 300); });
    const submit = h('button', { type: 'button', class: 'uc-btn uc-primary' }, 'Create order');
    submit.addEventListener('click', async () => {
      if (submit.disabled) return; submit.disabled = true; err.textContent = '';
      try {
        const r = await api('conversations/' + cur.id + '/orders', { method: 'POST', body: { idempotency_key: key, items: lines.map((l) => ({ product_id: l.id, variant: l.variant, quantity: l.qty })), customer: { name: f.name.value, phone: f.phone.value, email: f.email.value, address: f.address.value, city_id: f.city.value }, discount: f.discount.value, note: f.note.value } });
        closeModal(); toast('Order created (total ' + r.grand_total + ')'); cur = await api('conversations/' + cur.id); renderInfo();
      } catch (x) { err.textContent = x.message; submit.disabled = false; }
    });
    const lab = (t, el) => h('label', {}, t, el);
    openModal('Create order', h('div', { class: 'uc-form' }, ps, res, h('table', { class: 'uc-table uc-lines' }, h('thead', {}, h('tr', {}, ...['Product', 'Price', 'Qty', 'Total', ''].map((x) => h('th', {}, x)))), tbody), sub,
      lab('Customer name', f.name), lab('Phone', f.phone), lab('Email (optional)', f.email), lab('Delivery address', f.address), lab('District', f.city), lab('Discount (taka, limited by your role)', f.discount), lab('Order note', f.note), err, submit));
    draw(); ps.focus();
  });

  // ── WhatsApp approved templates (answering an existing customer; may be billed by Meta) ──
  async function templatePicker() {
    const body = h('div', { class: 'uc-form' }, h('p', {}, 'Loading templates…'));
    openModal('Send an approved WhatsApp template', body);
    try {
      const r = await api('conversations/' + cur.id + '/templates');
      const sel = h('select', { 'aria-label': 'Template' }, ...r.data.map((t, i) => h('option', { value: i, disabled: !t.supported }, t.name + ' (' + t.language + ', ' + t.category + ')' + (t.supported ? '' : ' — not supported here'))));
      const prev = h('div', { class: 'uc-card' }), fields = h('div', { class: 'uc-form' }), err = h('div', { class: 'err', role: 'alert' });
      let inputs = [];
      const draw = () => { const t = r.data[sel.value]; if (!t) return; inputs = []; clear(fields); for (let i = 1; i <= t.params; i++) { const inp = h('input', { 'aria-label': 'Value ' + i, placeholder: 'Value for {{' + i + '}}' }); inputs.push(inp); fields.append(inp); inp.addEventListener('input', paint); } paint(); };
      const paint = () => { const t = r.data[sel.value]; prev.textContent = t ? t.body.replace(/\{\{(\d+)\}\}/g, (m, n) => (inputs[n - 1] && inputs[n - 1].value) || m) : ''; };
      sel.addEventListener('change', draw);
      const send = h('button', { class: 'uc-btn uc-primary', type: 'button' }, 'Send template'); const ref = 't' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
      send.addEventListener('click', async () => { if (send.disabled) return; const t = r.data[sel.value]; if (!t || !t.supported) return; send.disabled = true; err.textContent = '';
        try { const m = await api('conversations/' + cur.id + '/template-messages', { method: 'POST', body: { name: t.name, language: t.language, params: inputs.map((i) => i.value), client_ref: ref } }); msgs.push(m); renderMsgs(true); closeModal(); } catch (x) { err.textContent = x.message; send.disabled = false; } });
      clear(body).append(h('p', { class: 'uc-meta' }, r.billing_note), r.data.length ? sel : h('p', {}, 'No approved customer-service templates were found for this number.'), fields, prev, err, send); if (r.data.length) draw();
    } catch (x) { clear(body).append(h('p', { class: 'err' }, x.message)); }
  }

  // ── pages ───────────────────────────────────────────────────────────────
  async function pageAccounts() {
    const p = page('Connected accounts', h('p', {}, 'Loading…'));
    try {
      const d = await api('accounts');
      const conn = h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px' }, ...Object.entries(d.platforms).map(([k, v]) => h('button', { class: 'uc-btn', disabled: !v.available, title: v.note || '', onclick: async () => { try { const r = await api('accounts/connect/' + k, { method: 'POST' }); location.href = r.redirect; } catch (x) { toast(x.message, true); } } }, '+ Connect ' + k)));
      const rows = d.accounts.map((a) => {
        const staff = h('button', { class: 'uc-btn', onclick: () => staffModal(a, d.staff) }, 'Staff (' + a.staff_ids.length + ')');
        return h('tr', {}, h('td', {}, h('strong', {}, a.name), h('br'), h('small', {}, a.platform + ' · ' + a.external_id)), h('td', {}, h('span', { class: 'uc-st ' + a.status }, a.status)),
          h('td', {}, h('small', {}, 'Webhook: ' + (a.last_webhook_at ? ago(a.last_webhook_at) : 'never'), h('br'), 'Message: ' + (a.last_message_at ? ago(a.last_message_at) : 'never'), h('br'), 'API OK: ' + (a.last_api_ok_at ? ago(a.last_api_ok_at) : 'never'))),
          h('td', {}, a.health.issues.length ? h('ul', { style: 'padding-left:16px;margin:0' }, ...a.health.issues.map((i) => h('li', {}, i))) : 'Healthy'),
          h('td', {}, h('label', {}, h('input', { type: 'checkbox', checked: a.inbox_enabled, onchange: (e) => api('accounts/' + a.id, { method: 'POST', body: { inbox_enabled: e.target.checked } }).catch((x) => toast(x.message, true)) }), ' Inbox'), h('br'), staff, ' ',
            a.status !== 'disabled' ? h('button', { class: 'uc-btn', onclick: () => verifyAccount(a) }, 'Verify') : null, ' ',
            a.status === 'expired' || a.status === 'error' ? h('button', { class: 'uc-btn', onclick: () => api('accounts/connect/' + a.platform, { method: 'POST' }).then((r) => { location.href = r.redirect; }).catch((x) => toast(x.message, true)) }, 'Reconnect') : null, ' ',
            a.status !== 'disabled' ? h('button', { class: 'uc-btn', onclick: async () => { if (confirm('Disconnect ' + a.name + '? History is kept; sending and receiving stop.')) { await api('accounts/' + a.id, { method: 'DELETE' }); pageAccounts(); } } }, 'Disconnect') : null));
      });
      clear(p).append(h('h4', {}, 'Connected accounts'), h('p', { class: 'uc-meta' }, 'Mode: ' + d.mode + (d.mode === 'gateway' && !d.gateway_ready ? ' — gateway not configured (see deployment guide)' : '')), conn,
        h('div', { style: 'overflow:auto' }, h('table', { class: 'uc-table' }, h('thead', {}, h('tr', {}, ...['Account', 'Status', 'Activity', 'Health', 'Actions'].map((x) => h('th', {}, x)))), h('tbody', {}, ...rows))));
    } catch (x) { toast(x.message, true); }
  }
  async function verifyAccount(a) {
    openModal('Checking ' + a.name + '…', h('p', {}, 'Contacting the provider (read-only)…'));
    try {
      const r = await api('accounts/' + a.id + '/verify', { method: 'POST' });
      openModal('Connection check: ' + a.name, h('ul', { style: 'list-style:none;padding:0' }, ...r.checks.map((c) => h('li', { style: 'margin:6px 0' }, (c.ok ? '✅ ' : '❌ '), h('strong', {}, c.name), ': ', c.detail))), h('button', { class: 'uc-btn', onclick: () => { closeModal(); pageAccounts(); } }, 'Done'));
    } catch (x) { openModal('Check failed', h('p', { class: 'err' }, x.message)); }
  }
  function staffModal(a, staff) {
    const sel = new Set(a.staff_ids), list = staff.map((s) => h('label', { style: 'display:flex;gap:8px;margin:4px 0' }, h('input', { type: 'checkbox', checked: sel.has(s.id), onchange: (e) => e.target.checked ? sel.add(s.id) : sel.delete(s.id) }), s.name));
    openModal('Staff access: ' + a.name, h('p', { class: 'uc-meta' }, 'Only staff whose role has Unified Chat access are listed. Removing someone un-assigns their conversations on this account.'), ...list, h('button', { class: 'uc-btn uc-primary', onclick: async () => { try { await api('accounts/' + a.id, { method: 'POST', body: { staff_ids: [...sel] } }); closeModal(); pageAccounts(); } catch (x) { toast(x.message, true); } } }, 'Save'));
  }
  function pageTeam() {
    const p = page('Team assignment', h('p', { class: 'uc-meta' }, 'Assign from any conversation header. Unassigned conversations:'));
    p.append(h('button', { class: 'uc-btn', onclick: () => { showInbox(); folder = 'unassigned'; renderNav(); loadConvs(true); } }, 'Show unassigned (' + boot.counts.unassigned + ')'));
    if (boot.permissions.manage_chat_accounts) p.append(h('p', {}, 'Per-account staff access is managed under Connected accounts.'));
  }
  async function pageReplies() {
    const p = page('Saved replies'), manage = boot.permissions.manage_chat_settings || boot.permissions.manage_chat_automation;
    const list = h('div'); const draw = async () => { const r = await api('saved-replies'); window.__replies = r; clear(list).append(...r.map((x) => h('div', { class: 'uc-card', style: 'margin-bottom:8px' }, h('strong', {}, '[' + x.lang + '] ' + x.title), h('div', { style: 'white-space:pre-wrap' }, x.body), manage ? h('button', { class: 'uc-btn', onclick: async () => { await api('saved-replies/' + x.id, { method: 'DELETE' }); draw(); } }, 'Delete') : null))); };
    if (manage) { const t = h('input', { placeholder: 'Title' }), b = h('textarea', { rows: 3, placeholder: 'Reply text' }), l = h('select', {}, h('option', { value: 'bn' }, 'Bangla'), h('option', { value: 'en' }, 'English')); p.append(h('div', { class: 'uc-form', style: 'max-width:520px;margin-bottom:12px' }, t, b, l, h('button', { class: 'uc-btn uc-primary', onclick: async () => { try { await api('saved-replies', { method: 'POST', body: { title: t.value, body: b.value, lang: l.value } }); t.value = b.value = ''; draw(); } catch (x) { toast(x.message, true); } } }, 'Add reply'))); }
    p.append(list); draw();
  }
  async function pageSettings() {
    const p = page('Settings & automation', h('p', {}, 'Loading…'));
    try {
      const s = await api('settings'), f = {};
      const mk = (id, el) => (f[id] = el);
      mk('gOn', h('input', { type: 'checkbox', checked: s.greeting.enabled })); mk('gTx', h('textarea', { rows: 2 })); f.gTx.value = s.greeting.text || '';
      mk('aOn', h('input', { type: 'checkbox', checked: s.away.enabled })); mk('aTx', h('textarea', { rows: 2 })); f.aTx.value = s.away.text || '';
      mk('st', h('input', { type: 'time', value: s.hours.start })); mk('en', h('input', { type: 'time', value: s.hours.end })); mk('tz', h('input', { value: s.hours.tz }));
      mk('days', h('input', { value: s.hours.days.join(','), 'aria-label': 'Open days, 0=Sunday' })); mk('ac', h('input', { type: 'number', min: 0, value: s.auto_close_days }));
      const lab = (t, el) => h('label', {}, t, el);
      clear(p).append(h('h4', {}, 'Settings & automation'), h('p', { class: 'uc-meta' }, 'Automatic replies are only sent to a customer who just wrote to you, once per 12 hours per conversation, and obey each platform’s rules. There is no bulk messaging.'),
        h('div', { class: 'uc-form', style: 'max-width:560px' }, lab('Business-hours greeting', f.gOn), lab('Greeting text', f.gTx), lab('Outside-hours reply', f.aOn), lab('Outside-hours text', f.aTx), lab('Opens', f.st), lab('Closes', f.en), lab('Time zone (e.g. Asia/Dhaka)', f.tz), lab('Open days (0=Sun … 6=Sat)', f.days), lab('Auto-resolve idle conversations after (days, 0 = never)', f.ac),
          h('button', { class: 'uc-btn uc-primary', onclick: async () => { try { await api('settings', { method: 'POST', body: { greeting: { enabled: f.gOn.checked, text: f.gTx.value }, away: { enabled: f.aOn.checked, text: f.aTx.value }, hours: { start: f.st.value, end: f.en.value, tz: f.tz.value, days: f.days.value.split(',').map((x) => +x.trim()).filter((x) => x >= 0 && x <= 6) }, auto_close_days: +f.ac.value || 0, unanswered_alert_minutes: s.unanswered_alert_minutes || 0 } }); toast('Saved'); } catch (x) { toast(x.message, true); } } }, 'Save')));
    } catch (x) { toast(x.message, true); }
  }
  async function pageAnalytics() {
    const p = page('Analytics'), box = h('div'), range = h('select', {}, ...[['today', 'Today'], ['yesterday', 'Yesterday'], ['7d', 'Last 7 days'], ['30d', 'Last 30 days'], ['custom', 'Custom']].map(([v, l]) => h('option', { value: v }, l)));
    range.value = '7d'; const from = h('input', { type: 'date' }), to = h('input', { type: 'date' });
    const plat = h('select', {}, h('option', { value: '' }, 'All platforms'), ...['facebook', 'instagram', 'whatsapp', 'tiktok'].map((x) => h('option', { value: x }, x)));
    const acc = h('select', {}, h('option', { value: '' }, 'All accounts'), ...boot.accounts.map((a) => h('option', { value: a.id }, a.name)));
    const stf = h('select', {}, h('option', { value: '' }, 'All staff'), ...boot.agents.map((a) => h('option', { value: a.id }, a.name)));
    const q = () => qs({ range: range.value, from: from.value, to: to.value, platform: plat.value, account_id: acc.value, staff_id: stf.value });
    const load = async () => {
      try {
        const d = await api('analytics?' + q()), t = d.totals, cards = [['Conversations', t.conversations], ['New', t.new], ['Active', t.active], ['Unread', t.unread], ['Unassigned', t.unassigned], ['Resolved', t.resolved], ['Incoming msgs', t.incoming], ['Outgoing msgs', t.outgoing], ['Avg first response', dur(t.avg_first_response_seconds)], ['Avg resolution', dur(t.avg_resolution_seconds)], ['Chat orders', t.chat_orders], ['Chat revenue', t.chat_revenue]];
        clear(box).append(h('div', { class: 'uc-cards' }, ...cards.map(([l, v]) => h('div', { class: 'uc-card' }, h('small', {}, l), h('b', {}, v)))),
          h('h5', {}, 'By platform'), h('div', { class: 'uc-meta' }, Object.entries(d.by_platform).map(([k, v]) => k + ': ' + v).join(' · ') || '—'),
          h('h5', {}, 'By account'), h('div', { class: 'uc-meta' }, d.by_account.map((a) => a.name + ' (' + a.platform + '): ' + a.total).join(' · ') || '—'),
          h('h5', {}, 'Agents'), h('table', { class: 'uc-table' }, h('thead', {}, h('tr', {}, ...['Agent', 'Assigned', 'Resolved', 'Replies'].map((x) => h('th', {}, x)))), h('tbody', {}, ...d.agents.map((a) => h('tr', {}, h('td', {}, a.name), h('td', {}, a.assigned), h('td', {}, a.resolved), h('td', {}, a.replies))))),
          boot.permissions.export_chat_data ? h('p', {}, h('a', { class: 'uc-btn', href: base + '/export?' + q() }, 'Export CSV')) : null);
      } catch (x) { toast(x.message, true); }
    };
    [range, from, to, plat, acc, stf].forEach((e) => e.addEventListener('change', load));
    p.append(h('div', { style: 'display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px' }, range, from, to, plat, acc, stf), box); load();
  }

  // ── live updates: WebSocket (if Echo is present) else authorized polling ─
  function startUpdates() {
    if (window.Echo && boot.realtime) boot.realtime.channels.forEach((id) => window.Echo.private('chat.account.' + id).listen('.chat.updated', onSignal));
    const tick = async () => {
      if (document.hidden) return;
      try { const r = await api('updates' + (lastPoll ? '?since=' + encodeURIComponent(lastPoll) : '')); lastPoll = r.now; if (r.conversation_ids.length) onSignal({ conversation_id: cur ? (r.conversation_ids.includes(cur.id) ? cur.id : null) : null }); boot.counts.unread = r.unread_total; const nb = document.getElementById('uc-nav-unread'); if (nb) { nb.textContent = r.unread_total; nb.classList.toggle('d-none', !r.unread_total); } } catch (e) { /* offline: try next tick */ }
    };
    pollTimer = setInterval(tick, (boot.poll_seconds || 5) * 1000); tick();
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
  }
  let sig; function onSignal(e) { clearTimeout(sig); sig = setTimeout(async () => { if (!root.dataset.page) await loadConvs(true); if (cur && e.conversation_id === cur.id) refreshMsgs().catch(() => {}); }, 400); }

  init().catch((e) => { clear($('#uc-convs')).append(h('li', { class: 'uc-empty', style: 'padding:30px' }, e.message)); });
})();
