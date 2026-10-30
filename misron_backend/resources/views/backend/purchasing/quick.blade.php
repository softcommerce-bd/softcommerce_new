@extends('backend.layouts.app')
@section('content')
<style>
    .qp-results{position:absolute;z-index:50;background:#fff;border:1px solid #dee2e6;border-radius:6px;max-height:340px;overflow:auto;width:100%;box-shadow:0 6px 18px rgba(0,0,0,.12)}
    .qp-results button{display:flex;gap:10px;align-items:center;width:100%;text-align:left;border:0;background:#fff;padding:6px 10px;border-bottom:1px solid #f1f1f1}
    .qp-results button.on,.qp-results button:hover{background:#eef4ff}
    .qp-results img{width:34px;height:34px;object-fit:cover;border-radius:4px}
    .qp-sum{position:sticky;top:70px}
    #qp-lines input{min-width:70px}
    @media(max-width:767px){.qp-sum{position:static}}
</style>
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Quick Purchase') }}</h1></div>
<div class="row">
 <div class="col-lg-8">
  <div class="card mb-3"><div class="card-body">
    <div class="form-row">
        <div class="form-group col-md-6"><label for="qp-supplier">{{ translate('Supplier') }}</label>
            <div class="input-group"><select id="qp-supplier" class="form-control">@foreach ($suppliers as $s)<option value="{{ $s->id }}" data-due="{{ $s->due_amount }}" data-adv="{{ $s->opening_balance }}">{{ $s->business_name }}</option>@endforeach</select>
            <div class="input-group-append"><button type="button" class="btn btn-outline-primary" id="qp-add-supplier">+ {{ translate('New') }}</button></div></div>
            <small class="text-muted" id="qp-supplier-info"></small></div>
        <div class="form-group col-md-6"><label for="qp-note">{{ translate('Note') }}</label><input id="qp-note" class="form-control" maxlength="500"></div>
    </div>
    <div class="form-group position-relative mb-0"><label for="qp-search">{{ translate('Add product (name, SKU, barcode, ID or variation)') }}</label>
        <input id="qp-search" class="form-control form-control-lg" autocomplete="off" placeholder="{{ translate('Type or scan, then Enter') }}" autofocus>
        <div id="qp-results" class="qp-results" hidden role="listbox"></div></div>
  </div></div>
  <div class="card mb-3"><div class="table-responsive"><table class="table table-sm mb-0" id="qp-lines">
    <thead><tr><th>{{ translate('Product') }}</th><th style="width:90px">{{ translate('Qty') }}</th><th style="width:120px">{{ translate('Buying price') }}</th><th style="width:100px">{{ translate('Discount') }}</th><th class="text-right" style="width:110px">{{ translate('Line total') }}</th><th></th></tr></thead>
    <tbody></tbody></table></div><div class="card-footer text-muted small" id="qp-empty">{{ translate('Search above to add products. Stock, pending demand and the last buying price show in the list.') }}</div></div>
  <div class="card"><div class="card-header h6 mb-0">{{ translate('Bulk purchase (CSV)') }}</div><div class="card-body">
    <a href="{{ route('purchasing.bulk.template') }}">{{ translate('Download template') }}</a>
    <div class="form-inline mt-2"><input type="file" id="bulk-file" accept=".csv,text/csv" class="form-control-file mr-2"><button class="btn btn-sm btn-outline-secondary mr-2" id="bulk-preview" type="button">{{ translate('Validate') }}</button><button class="btn btn-sm btn-primary" id="bulk-commit" type="button" disabled>{{ translate('Create purchase order') }}</button></div>
    <div id="bulk-out" class="small mt-2"></div></div></div>
 </div>
 <div class="col-lg-4"><div class="card qp-sum"><div class="card-body">
    <h6>{{ translate('Summary') }}</h6>
    <dl class="row mb-2"><dt class="col-6">{{ translate('Subtotal') }}</dt><dd class="col-6 text-right" id="s-sub">0.00</dd><dt class="col-6">{{ translate('Discount') }}</dt><dd class="col-6 text-right" id="s-disc">0.00</dd></dl>
    <div class="form-row"><div class="form-group col-6"><label class="small">{{ translate('Transport') }}</label><input id="qp-freight" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0"></div>
        <div class="form-group col-6"><label class="small">{{ translate('Other costs') }}</label><input id="qp-other" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0"></div></div>
    <div class="form-group" id="qp-cost-acc-wrap" hidden><label class="small">{{ translate('Extra costs paid from') }}</label><select id="qp-cost-acc" class="form-control form-control-sm"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->current_balance }})</option>@endforeach</select>
        <small class="text-muted">{{ translate('Capitalised into the cost of the goods, not an expense.') }}</small></div>
    <dl class="row mb-2"><dt class="col-6">{{ translate('Goods total') }}</dt><dd class="col-6 text-right h5" id="s-goods">0.00</dd></dl>
    @can('make_supplier_payment')
    <div class="form-group"><label class="small">{{ translate('Paid now') }}</label><input id="qp-paid" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0"></div>
    <div class="form-group"><label class="small">{{ translate('Pay from') }}</label><select id="qp-pay-acc" class="form-control form-control-sm"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->current_balance }})</option>@endforeach</select></div>
    @endcan
    <dl class="row mb-2"><dt class="col-6">{{ translate('Due') }}</dt><dd class="col-6 text-right" id="s-due">0.00</dd></dl>
    <div class="form-group"><label class="small">{{ translate('Supplier invoice no. (optional)') }}</label><input id="qp-invoice" class="form-control form-control-sm" maxlength="80"></div>
    <div id="qp-msg" role="alert" class="small mb-2"></div>
    <div class="d-grid" style="display:grid;gap:6px">
        <button class="btn btn-outline-secondary" data-act="draft" type="button">{{ translate('Save draft') }}</button>
        <button class="btn btn-outline-primary" data-act="order" type="button">{{ translate('Create purchase order') }}</button>
        <button class="btn btn-primary" data-act="receive" type="button">{{ translate('Receive products') }}</button>
        @can('make_supplier_payment')<button class="btn btn-success" data-act="receive_pay" type="button">{{ translate('Receive & pay') }}</button>@endcan
    </div>
    <small class="text-muted d-block mt-2">{{ translate('Order, receive, invoice and pay are separate records; a repeat click never doubles them.') }}</small>
 </div></div></div>
</div>
@endsection
@section('script')
<script>
(function () {
  var H = {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}, lines = [], opKey = uuid(), hits = [], active = -1, done = false, busy = false;
  var $ = function (s) { return document.querySelector(s); };
  function uuid() { return (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2)); }
  function cents(v) { v = parseFloat(v); return isNaN(v) ? 0 : Math.round(v * 100); }
  function fmt(c) { return (c / 100).toFixed(2); }
  function el(tag, attrs, text) { var e = document.createElement(tag); for (var k in (attrs || {})) e.setAttribute(k, attrs[k]); if (text != null) e.textContent = text; return e; }
  function api(url, opt) { return fetch(url, opt).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Request failed'); return j; }); }); }
  function say(msg, bad) { var m = $('#qp-msg'); m.textContent = msg; m.className = 'small mb-2 ' + (bad ? 'text-danger' : 'text-success'); }

  function supplierInfo() { var o = $('#qp-supplier').selectedOptions[0]; $('#qp-supplier-info').textContent = o ? ('{{ translate('Outstanding') }}: ' + o.dataset.due + ' · {{ translate('Advance held') }}: ' + o.dataset.adv) : ''; }
  $('#qp-supplier').addEventListener('change', supplierInfo); supplierInfo();

  function render() {
    var tb = $('#qp-lines tbody'); while (tb.firstChild) tb.removeChild(tb.firstChild);
    var sub = 0, disc = 0;
    lines.forEach(function (l, i) {
      var line = Math.max(0, cents(l.qty * l.cost) - cents(l.discount)); sub += cents(l.qty * l.cost); disc += cents(l.discount);
      var tr = el('tr'); var td = el('td'); td.appendChild(el('strong', {}, l.name)); td.appendChild(el('div', {class: 'small text-muted'}, (l.variant ? l.variant + ' · ' : '') + 'physical ' + l.on_hand + ' · available ' + l.available + (l.demand ? ' · demand ' + l.demand : '') + (l.last_cost ? ' · last ' + l.last_cost : ''))); tr.appendChild(td);
      [['qty', 1, '1'], ['cost', 0, '0.0001'], ['discount', 0, '0.01']].forEach(function (f) {
        var c = el('td'), inp = el('input', {type: 'number', min: f[1], step: f[2], class: 'form-control form-control-sm', 'aria-label': f[0]}); inp.value = l[f[0]];
        inp.addEventListener('input', function () { l[f[0]] = f[0] === 'qty' ? Math.max(1, parseInt(inp.value || '1', 10)) : Math.max(0, parseFloat(inp.value || '0')); totals(); }); c.appendChild(inp); tr.appendChild(c); });
      tr.appendChild(el('td', {class: 'text-right', id: 'lt' + i}, fmt(line)));
      var rm = el('button', {type: 'button', class: 'btn btn-sm btn-link text-danger', 'aria-label': 'Remove'}, '✕'); rm.addEventListener('click', function () { lines.splice(i, 1); render(); }); var rc = el('td'); rc.appendChild(rm); tr.appendChild(rc);
      tb.appendChild(tr);
    });
    $('#qp-empty').hidden = lines.length > 0; totals();
  }
  function totals() {
    var sub = 0, disc = 0; lines.forEach(function (l, i) { sub += cents(l.qty * l.cost); disc += cents(l.discount); var c = document.getElementById('lt' + i); if (c) c.textContent = fmt(Math.max(0, cents(l.qty * l.cost) - cents(l.discount))); });
    var extra = cents($('#qp-freight').value) + cents($('#qp-other').value), goods = sub - disc;
    $('#s-sub').textContent = fmt(sub); $('#s-disc').textContent = fmt(disc); $('#s-goods').textContent = fmt(goods + extra);
    $('#qp-cost-acc-wrap').hidden = extra <= 0; var paid = $('#qp-paid') ? cents($('#qp-paid').value) : 0; $('#s-due').textContent = fmt(goods + extra - paid);
  }
  ['#qp-freight', '#qp-other', '#qp-paid'].forEach(function (s) { var e = $(s); if (e) e.addEventListener('input', totals); });

  function add(p) {
    var dup = lines.filter(function (l) { return l.product_id === p.product_id && l.variant === p.variant; })[0];
    if (dup) { dup.qty += 1; say('{{ translate('Already in the list: quantity increased') }}'); }
    else { done = false; lines.push({product_id: p.product_id, variant: p.variant || '', name: p.name, qty: Math.max(1, p.shortage || 1), cost: parseFloat(p.last_cost || p.estimated_cost || 0), discount: 0, on_hand: p.on_hand, available: p.available, demand: p.demand, last_cost: p.last_cost}); }
    $('#qp-results').hidden = true; $('#qp-search').value = ''; $('#qp-search').focus(); render();
  }
  var timer; $('#qp-search').addEventListener('input', function () { clearTimeout(timer); var q = this.value.trim(); if (!q) { $('#qp-results').hidden = true; return; } timer = setTimeout(function () { api('{{ route('purchasing.products.search') }}?q=' + encodeURIComponent(q), {headers: H}).then(showHits).catch(function () {}); }, 180); });
  function showHits(rows) {
    hits = rows; active = rows.length ? 0 : -1; var box = $('#qp-results'); while (box.firstChild) box.removeChild(box.firstChild);
    rows.forEach(function (p, i) { var b = el('button', {type: 'button', role: 'option', class: i === 0 ? 'on' : ''}); if (p.thumb) b.appendChild(el('img', {src: p.thumb, alt: ''})); var d = el('div'); d.appendChild(el('div', {}, p.name + (p.variant ? ' — ' + p.variant : ''))); d.appendChild(el('small', {class: 'text-muted'}, (p.sku ? p.sku + ' · ' : '') + 'physical ' + p.on_hand + ' · available ' + p.available + ' · demand ' + p.demand + (p.last_cost ? ' · last ' + p.last_cost : ''))); b.appendChild(d); b.addEventListener('click', function () { add(p); }); box.appendChild(b); });
    box.hidden = !rows.length;
  }
  $('#qp-search').addEventListener('keydown', function (e) {
    var btns = $('#qp-results').children;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (!btns.length) return; btns[active] && btns[active].classList.remove('on'); active = (active + (e.key === 'ArrowDown' ? 1 : -1) + btns.length) % btns.length; btns[active].classList.add('on'); btns[active].scrollIntoView({block: 'nearest'}); }
    else if (e.key === 'Enter') { e.preventDefault(); var q = this.value.trim(); if (hits[active]) add(hits[active]); else if (q) api('{{ route('purchasing.products.search') }}?q=' + encodeURIComponent(q), {headers: H}).then(function (r) { if (r.length === 1) add(r[0]); else showHits(r); }); } /* a scanner types the code then Enter */
    else if (e.key === 'Escape') { $('#qp-results').hidden = true; }
  });

  $('#qp-add-supplier').addEventListener('click', function () {
    var name = prompt('{{ translate('New supplier name') }}'); if (!name) return; var mobile = prompt('{{ translate('Mobile (optional)') }}') || '';
    api('{{ route('purchasing.suppliers.quick') }}', {method: 'POST', headers: Object.assign({'Content-Type': 'application/json'}, H), body: JSON.stringify({business_name: name, mobile: mobile})}).then(function (s) { var o = el('option', {value: s.id, 'data-due': '0', 'data-adv': '0'}, s.business_name); $('#qp-supplier').appendChild(o); o.selected = true; supplierInfo(); say('{{ translate('Supplier added; your list is untouched.') }}'); }).catch(function (e) { say(e.message, true); });
  });

  document.querySelectorAll('[data-act]').forEach(function (b) { b.addEventListener('click', function () {
    if (busy) return;
    if (!lines.length) { if (!done) say('{{ translate('Add at least one product.') }}', true); return; } // after a successful save a repeat click keeps the success message
    busy = true;
    var act = b.dataset.act, all = document.querySelectorAll('[data-act]'); all.forEach(function (x) { x.disabled = true; });
    var body = {op_key: opKey, action: act, supplier_id: +$('#qp-supplier').value, note: $('#qp-note').value, invoice_no: $('#qp-invoice').value, freight: $('#qp-freight').value || 0, other_cost: $('#qp-other').value || 0, cost_account_id: $('#qp-cost-acc').value || null,
      paid_amount: $('#qp-paid') ? $('#qp-paid').value : 0, pay_account_id: $('#qp-pay-acc') ? ($('#qp-pay-acc').value || null) : null,
      lines: lines.map(function (l) { return {product_id: l.product_id, variant: l.variant, qty: l.qty, unit_cost: l.cost, discount: l.discount}; })};
    api('{{ route('purchasing.quick.submit') }}', {method: 'POST', headers: Object.assign({'Content-Type': 'application/json'}, H), body: JSON.stringify(body)})
      .then(function (r) { say(r.message); lines = []; opKey = uuid(); done = true; render(); })
      .catch(function (e) { say(e.message, true); })   // the same op_key stays, so pressing again after a timeout cannot double-post
      .then(function () { busy = false; all.forEach(function (x) { x.disabled = false; }); });
  }); });

  var bulkOK = false;
  function bulkForm() { var f = new FormData(); f.append('supplier_id', $('#qp-supplier').value); f.append('file', $('#bulk-file').files[0]); return f; }
  $('#bulk-preview').addEventListener('click', function () {
    if (!$('#bulk-file').files[0]) return; var out = $('#bulk-out'); while (out.firstChild) out.removeChild(out.firstChild);
    api('{{ route('purchasing.bulk.preview') }}', {method: 'POST', headers: H, body: bulkForm()}).then(function (r) {
      out.appendChild(el('div', {}, r.lines.length + ' {{ translate('valid lines') }} · {{ translate('total') }} ' + r.total)); r.problems.forEach(function (p) { out.appendChild(el('div', {class: 'text-danger'}, p)); });
      bulkOK = r.problems.length === 0 && r.lines.length > 0; $('#bulk-commit').disabled = !bulkOK; }).catch(function (e) { out.appendChild(el('div', {class: 'text-danger'}, e.message)); });
  });
  $('#bulk-commit').addEventListener('click', function () { if (!bulkOK) return; var f = bulkForm(); f.append('status', 'draft'); this.disabled = true;
    api('{{ route('purchasing.bulk.commit') }}', {method: 'POST', headers: H, body: f}).then(function (r) { $('#bulk-out').appendChild(el('div', {class: 'text-success'}, r.message)); }).catch(function (e) { $('#bulk-out').appendChild(el('div', {class: 'text-danger'}, e.message)); }); });
  render();
})();
</script>
@endsection
