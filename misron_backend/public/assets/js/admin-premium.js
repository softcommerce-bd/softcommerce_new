/*
 * Admin "Premium" skin: tiny, dependency-free helpers.
 *  1. light/dark switch (saved in this browser only)
 *  2. staggered entrance of cards on first paint
 *  3. count-up on dashboard numbers
 * Every step is wrapped so a failure can never break an admin page. Motion is skipped for "reduce motion".
 */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function safe(fn) { try { fn(); } catch (e) { /* never break the page */ } }

  // 1. theme switch -------------------------------------------------------------------------------------------
  safe(function () {
    var root = document.documentElement;
    function set(mode) {
      root.setAttribute('data-ui-theme', mode);
      try { localStorage.setItem('ui-theme', mode); } catch (e) {}
      var b = document.querySelector('.ui-theme-toggle');
      if (b) { b.setAttribute('aria-label', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'); b.innerHTML = mode === 'dark' ? SUN : MOON; }
    }
    var MOON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
    var SUN = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
    // sit beside the language / notification buttons, vertically centred like them
    var anchor = document.querySelector('#lang-change');
    anchor = anchor ? anchor.closest('.aiz-topbar-item') : null;
    if (!anchor) { var items = document.querySelectorAll('.aiz-topbar .aiz-topbar-item'); anchor = items.length ? items[items.length - 1] : null; }
    if (!anchor || !anchor.parentNode) return;
    var wrap = document.createElement('div');
    wrap.className = 'aiz-topbar-item mr-3 d-flex align-items-center';
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'ui-theme-toggle';
    btn.addEventListener('click', function () { set(root.getAttribute('data-ui-theme') === 'dark' ? 'light' : 'dark'); });
    wrap.appendChild(btn);
    anchor.parentNode.insertBefore(wrap, anchor);
    set(root.getAttribute('data-ui-theme') === 'dark' ? 'dark' : 'light');
  });

  // 2. staggered reveal ---------------------------------------------------------------------------------------
  safe(function () {
    if (reduce) return;
    var items = document.querySelectorAll('.aiz-main-content .dashboard-box, .aiz-main-content .dashboard-stack-box, .aiz-main-content .dashboard-single-box, .aiz-main-content .dashboard-wide-box, .aiz-main-content .dashboard-box-full, .aiz-main-content .card');
    var n = 0;
    for (var i = 0; i < items.length && n < 16; i++) {
      var r = items[i].getBoundingClientRect();
      if (r.top > window.innerHeight) continue; // only what is on screen at load: no work for below-the-fold content
      items[i].style.setProperty('--ui-i', n++);
      items[i].classList.add('ui-reveal');
    }
  });

  // 2b. slow-device guard: if the first frames are slow, drop the glass blur (look stays, cost goes) -----------------
  safe(function () {
    var root = document.documentElement, saved = null;
    try { saved = localStorage.getItem('ui-lite'); } catch (e) {}
    if (saved === '1') { root.classList.add('ui-lite'); return; }
    if (saved === '0' || !window.requestAnimationFrame) return;
    var last = 0, slow = 0, frames = 0;
    function tick(ts) {
      if (last) { frames++; if (ts - last > 34) slow++; }
      last = ts;
      if (frames < 40) return requestAnimationFrame(tick);
      var lite = slow > 12;
      if (lite) root.classList.add('ui-lite');
      try { localStorage.setItem('ui-lite', lite ? '1' : '0'); } catch (e) {}
    }
    setTimeout(function () { requestAnimationFrame(tick); }, 900); // after the entrance animation, so we measure normal use
  });

  // 3. count-up -----------------------------------------------------------------------------------------------
  safe(function () {
    if (reduce) return;
    var nodes = document.querySelectorAll('.executive-price, .dashboard-box h1, .dashboard-box .fs-30, .card h3.fs-20');
    nodes.forEach(function (el) {
      if (el.children.length) return;
      var text = el.textContent.trim();
      var m = text.match(/^([^\d-]*)(-?[\d,]*\.?\d+)(.*)$/);
      if (!m) return;
      var target = parseFloat(m[2].replace(/,/g, ''));
      if (!isFinite(target) || Math.abs(target) < 2 || Math.abs(target) > 1e12) return;
      var decimals = (m[2].split('.')[1] || '').length;
      var grouped = m[2].indexOf(',') !== -1;
      var start = null, dur = 750;
      function fmt(v) {
        var s = v.toFixed(decimals);
        return grouped ? Number(s).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) : s;
      }
      function step(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = m[1] + fmt(target * eased) + m[3];
        if (p < 1) requestAnimationFrame(step); else el.textContent = text; // always end on the exact server value
      }
      requestAnimationFrame(step);
    });
  });
})();
