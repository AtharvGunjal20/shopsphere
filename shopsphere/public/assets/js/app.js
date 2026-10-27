(function () {
  'use strict';
  var SS = window.SS || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function url(p) { return (SS.base || '') + '/' + String(p).replace(/^\//, ''); }

  function api(path, data, method) {
    var opt = { method: method || 'POST', headers: { 'X-CSRF-Token': SS.csrf, 'X-Requested-With': 'fetch', 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (opt.method === 'POST') { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(data || {}); }
    return fetch(url(path), opt).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; }).then(function (j) { j._status = r.status; return j; }); })
      .catch(function () { return { ok: false, error: 'Network error. Please check your connection.' }; });
  }
  window.SSapi = api;

  function toast(msg, type) {
    var box = $('#toasts'); if (!box || !window.bootstrap) return alert(msg);
    var el = document.createElement('div');
    el.className = 'toast align-items-center border-0 text-bg-' + (type || 'dark'); el.setAttribute('role', 'status');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    el.querySelector('.toast-body').textContent = msg; box.appendChild(el);
    var t = new bootstrap.Toast(el, { delay: 3500 }); t.show(); el.addEventListener('hidden.bs.toast', function () { el.remove(); });
  }
  window.SStoast = toast;

  function confirmDialog(text) {
    return new Promise(function (res) {
      var m = $('#confirmModal'); if (!m || !window.bootstrap) return res(window.confirm(text));
      $('#confirmText').textContent = text; var modal = bootstrap.Modal.getOrCreateInstance(m); var ok = $('#confirmOk'), done = false;
      function yes() { done = true; modal.hide(); res(true); }
      ok.onclick = yes; m.addEventListener('hidden.bs.modal', function once() { m.removeEventListener('hidden.bs.modal', once); if (!done) res(false); });
      modal.show();
    });
  }
  window.SSconfirm = confirmDialog;

  function setCount(id, n) { var el = document.getElementById(id); if (!el) return; el.textContent = n; el.hidden = !n; }

  // image fallback
  document.addEventListener('error', function (e) { var t = e.target; if (t && t.tagName === 'IMG' && t.hasAttribute('data-fallback') && !t.dataset.failed) { t.dataset.failed = '1'; t.src = SS.placeholder; } }, true);

  // confirm dialogs on forms
  document.addEventListener('submit', function (e) {
    var f = e.target; if (f.matches && f.matches('form[data-confirm]') && !f.dataset.ok) {
      e.preventDefault(); confirmDialog(f.getAttribute('data-confirm')).then(function (y) { if (y) { f.dataset.ok = '1'; f.submit(); } });
    }
  });

  // ---- live search suggestions
  var si = $('#searchInput'), sb = $('#suggestBox'), st, sel = -1;
  function hideSug() { if (sb) { sb.hidden = true; sb.innerHTML = ''; sel = -1; } }
  function recent() { try { return JSON.parse(localStorage.getItem('ss_recent') || '[]'); } catch (e) { return []; } }
  function saveRecent(q) { try { var r = recent().filter(function (x) { return x !== q; }); r.unshift(q); localStorage.setItem('ss_recent', JSON.stringify(r.slice(0, 6))); } catch (e) {} }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  if (si && sb) {
    si.addEventListener('focus', function () { if (!si.value.trim()) { var r = recent(); if (r.length) { sb.innerHTML = '<div class="sg-h">Recent searches</div>' + r.map(function (q) { return '<a href="' + url('search?q=' + encodeURIComponent(q)) + '"><i class="bi bi-clock-history"></i>' + esc(q) + '</a>'; }).join(''); sb.hidden = false; } } });
    si.addEventListener('input', function () {
      clearTimeout(st); var q = si.value.trim(); if (q.length < 2) return hideSug();
      st = setTimeout(function () {
        api('api/suggest?q=' + encodeURIComponent(q), null, 'GET').then(function (r) {
          if (si.value.trim() !== q) return; var h = '';
          (r.categories || []).forEach(function (c) { h += '<a href="' + c.url + '"><i class="bi bi-grid"></i> in <strong>' + esc(c.name) + '</strong></a>'; });
          (r.products || []).forEach(function (p) { h += '<a href="' + p.url + '"><img src="' + p.image + '" alt=""><span class="flex-grow-1">' + esc(p.name) + '<br><small class="text-muted">' + esc(p.brand) + '</small></span><strong>' + esc(p.price) + '</strong></a>'; });
          if (!h) h = '<div class="p-3 text-muted small">No suggestions. Press Enter to search.</div>';
          sb.innerHTML = h; sb.hidden = false; sel = -1;
        });
      }, 200);
    });
    si.addEventListener('keydown', function (e) {
      var items = $$('a', sb); if (sb.hidden || !items.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length; items.forEach(function (a, i) { a.classList.toggle('sel', i === sel); }); }
      else if (e.key === 'Enter' && sel > -1) { e.preventDefault(); location.href = items[sel].href; }
      else if (e.key === 'Escape') hideSug();
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.search-form')) hideSug(); });
    si.form.addEventListener('submit', function () { var q = si.value.trim(); if (q) saveRecent(q); });
  }

  // ---- PIN code
  function pinCheck(pin, cb) { return api('api/pin', { pin: pin }).then(cb); }
  var pf = $('#pinForm');
  if (pf) pf.addEventListener('submit', function (e) {
    e.preventDefault(); var pin = $('#pinInput').value.trim();
    pinCheck(pin, function (r) {
      var out = $('#pinResult');
      if (!r.ok) { out.className = 'small mt-2 text-danger'; out.textContent = r.error; return; }
      out.className = 'small mt-2 text-success'; out.textContent = 'Standard: ' + r.standard + ' · Express: ' + r.express + ' (estimates)';
      var l = $('#pinLabel'); if (l) l.textContent = r.pin; $$('.pin-text').forEach(function (x) { x.textContent = r.pin; });
    });
  });
  var pp = $('#pdpPin');
  if (pp) pp.addEventListener('submit', function (e) {
    e.preventDefault(); var inp = $('input', pp); var out = $('#pdpEst');
    pinCheck(inp.value.trim(), function (r) { if (!r.ok) { out.className = 'small text-danger'; out.textContent = r.error; } else { out.className = 'small'; out.innerHTML = 'Estimated delivery <strong>' + esc(r.standard) + '</strong> (standard) or <strong>' + esc(r.express) + '</strong> (express). Estimate from configured rules, not a live courier promise.'; var l = $('#pinLabel'); if (l) l.textContent = r.pin; } });
  });

  // ---- wishlist / compare / quick add (delegated)
  document.addEventListener('click', function (e) {
    var w = e.target.closest('[data-wish]');
    if (w) { e.preventDefault(); api('api/wishlist/toggle', { product_id: +w.dataset.wish }).then(function (r) {
      if (r.login) { location.href = url('login'); return; } if (!r.ok) return toast(r.error, 'danger');
      $$('[data-wish="' + w.dataset.wish + '"]').forEach(function (b) { b.classList.toggle('active', r.wished); b.setAttribute('aria-pressed', r.wished); var i = $('i', b); if (i) i.className = 'bi ' + (r.wished ? 'bi-heart-fill' : 'bi-heart') + (r.wished && b.classList.contains('pc-wish-inline') ? ' text-danger' : ''); });
      setCount('wishCount', r.count); toast(r.message, 'success'); if (location.pathname.slice(-9) === '/wishlist' && !r.wished) setTimeout(function () { location.reload(); }, 400);
    }); return; }
    var c = e.target.closest('[data-compare]');
    if (c) { e.preventDefault(); api('api/compare/toggle', { product_id: +c.dataset.compare }).then(function (r) {
      if (!r.ok) return toast(r.error, 'warning');
      $$('[data-compare="' + c.dataset.compare + '"]').forEach(function (b) { b.classList.toggle('active', r.comparing); b.setAttribute('aria-pressed', r.comparing); });
      setCount('cmpCount', r.count); toast(r.message + (r.comparing && r.count > 1 ? ' — open Compare in the header' : ''), 'dark');
    }); return; }
    var q = e.target.closest('[data-quick-add]');
    if (q) { e.preventDefault(); q.classList.add('loading'); api('api/cart/add', { product_id: +q.dataset.quickAdd, qty: 1 }).then(function (r) {
      q.classList.remove('loading');
      if (r.needs_options) { toast('Please choose options first', 'warning'); location.href = q.dataset.href; return; }
      if (!r.ok) return toast(r.error, 'danger'); setCount('cartCount', r.summary.count); toast(r.message, 'success');
    }); return; }
    var m = e.target.closest('[data-wish-move]');
    if (m) { api('api/wishlist/move', { product_id: +m.dataset.wishMove }).then(function (r) { if (!r.ok) return toast(r.error, 'danger'); toast(r.message, 'success'); setTimeout(function () { location.reload(); }, 500); }); }
  });

  // ---- product page
  var bf = $('#buyForm');
  if (bf) {
    $$('.thumb').forEach(function (t) { t.addEventListener('click', function () { $('#mainImg').src = t.dataset.src; $$('.thumb').forEach(function (x) { x.classList.remove('active'); }); t.classList.add('active'); }); });
    var qty = $('#qty');
    $$('[data-qty-step]').forEach(function (b) { b.addEventListener('click', function () { var v = (+qty.value || 1) + (+b.dataset.qtyStep); qty.value = Math.max(+qty.min, Math.min(+qty.max, v)); }); });
    function collect() { var o = {}, ok = true; $$('input[type=radio][data-group]', bf).forEach(function (r) { if (r.checked) o[r.dataset.group] = r.value; });
      var groups = {}; $$('input[type=radio][data-group]', bf).forEach(function (r) { groups[r.dataset.group] = 1; });
      Object.keys(groups).forEach(function (g) { if (!o[g]) { ok = false; toast('Please select ' + g.toLowerCase(), 'warning'); } });
      return ok ? { product_id: +bf.dataset.product, qty: Math.max(1, Math.min(+qty.max, +qty.value || 1)), options: o } : null; }
    function add(buy) { var d = collect(); if (!d) return; api('api/cart/add', d).then(function (r) { if (!r.ok) return toast(r.error, 'danger'); setCount('cartCount', r.summary.count); if (buy) location.href = url('checkout'); else toast(r.message, 'success'); }); }
    var a = $('#addToCartBtn'), b = $('#buyNowBtn'); if (a) a.addEventListener('click', function () { add(false); }); if (b) b.addEventListener('click', function () { add(true); });
  }

  // ---- cart page
  function cartRefreshTotals() { location.reload(); }
  $$('.cart-line').forEach(function (line) {
    var id = +line.dataset.line, inp = $('[data-cart-qty]', line);
    function upd(v) { api('api/cart/update', { item_id: id, qty: v }).then(function (r) { if (!r.ok) return toast(r.error, 'danger'); if (r.message) toast(r.message, 'warning'); setTimeout(cartRefreshTotals, r.message ? 900 : 0); }); }
    $$('[data-cart-step]', line).forEach(function (b) { b.addEventListener('click', function () { var v = (+inp.value || 1) + (+b.dataset.cartStep); if (v < 1) return; upd(Math.min(v, +inp.max)); }); });
    inp.addEventListener('change', function () { upd(Math.max(1, Math.min(+inp.max, +inp.value || 1))); });
    $('[data-cart-remove]', line).addEventListener('click', function () { confirmDialog('Remove this item from your cart?').then(function (y) { if (y) api('api/cart/remove', { item_id: id }).then(cartRefreshTotals); }); });
  });
  var cf = $('#couponForm');
  if (cf) {
    cf.addEventListener('submit', function (e) { e.preventDefault(); var i = $('#couponCode'); if (!i) return; api('api/cart/coupon', { code: i.value }).then(function (r) { if (!r.ok) return toast(r.error, 'danger'); toast(r.message, 'success'); setTimeout(cartRefreshTotals, 500); }); });
    $$('[data-coupon]').forEach(function (b) { b.addEventListener('click', function () { var i = $('#couponCode'); i.value = b.dataset.coupon; cf.requestSubmit(); }); });
    var rm = $('#couponRemove'); if (rm) rm.addEventListener('click', function () { api('api/cart/coupon', { remove: 1 }).then(cartRefreshTotals); });
  }

  // ---- newsletter
  var nf = $('#newsletterForm');
  if (nf) nf.addEventListener('submit', function (e) { e.preventDefault(); var i = $('input', nf), m = $('#newsletterMsg');
    api('api/newsletter', { email: i.value }).then(function (r) { m.className = 'small mt-1 ' + (r.ok ? 'text-success' : 'text-warning'); m.textContent = r.ok ? r.message : r.error; if (r.ok) i.value = ''; }); });

  // ---- return modal
  var rm2 = $('#retModal');
  if (rm2) rm2.addEventListener('show.bs.modal', function (e) { var b = e.relatedTarget; if (b) { $('#retItem').value = b.dataset.item; $('#retName').textContent = b.dataset.name; } });

  // ---- Razorpay
  function loadRzp() { return new Promise(function (res, rej) { if (window.Razorpay) return res(); var s = document.createElement('script'); s.src = 'https://checkout.razorpay.com/v1/checkout.js'; s.onload = res; s.onerror = function () { rej(new Error('Could not load Razorpay checkout')); }; document.head.appendChild(s); }); }
  function openRazorpay(params, redirect, failedRedirect) {
    return loadRzp().then(function () {
      var rzp = new Razorpay(Object.assign({}, params, {
        handler: function (resp) {
          api('api/payment/verify', resp).then(function (r) { if (r.ok) location.href = r.redirect || redirect; else { toast(r.error || 'Payment verification failed', 'danger'); setTimeout(function () { location.href = failedRedirect || redirect; }, 1800); } });
        },
        modal: { ondismiss: function () { api('api/payment/failed', { razorpay_order_id: params.order_id, reason: 'Checkout closed by customer' }); location.href = failedRedirect || redirect; } }
      }));
      rzp.on('payment.failed', function (r) { api('api/payment/failed', { razorpay_order_id: params.order_id, reason: (r.error && r.error.description) || 'Payment failed' }); toast((r.error && r.error.description) || 'Payment failed. You can retry.', 'danger'); });
      rzp.open();
    });
  }
  var retry = $('#retryPay');
  if (retry) retry.addEventListener('click', function () { api('api/payment/start', { order_no: retry.dataset.order }).then(function (r) { if (!r.ok) return toast(r.error, 'danger'); openRazorpay(r.razorpay, r.redirect, location.href).catch(function (e) { toast(e.message, 'danger'); }); }); });

  // ---- checkout
  var co = $('#checkoutForm');
  if (co) {
    var newAddr = $('#newAddr');
    function sel(n) { var el = co.querySelector('input[name=' + n + ']:checked'); return el ? el.value : ''; }
    function currentPin() { var a = co.querySelector('input[name=address_id]:checked'); if (a && a.value !== '0') return a.dataset.pin || ''; var p = $('#addr_pincode'); return p ? p.value.trim() : ''; }
    function quote() {
      api('api/checkout/quote', { shipping_method: sel('shipping_method'), payment_method: sel('payment_method'), pin: currentPin() }).then(function (r) {
        if (!r.ok) return; var t = r.totals;
        $$('[data-q]').forEach(function (el) { var k = el.dataset.q; if (k === 'cod_fee') { el.textContent = t.cod_fee || ''; $('#rowCod').hidden = !t.cod_fee; } else if (t[k] !== undefined) el.textContent = t[k]; });
        if (r.estimates.standard) { $('#estStandard').textContent = r.estimates.standard; $('#estExpress').textContent = r.estimates.express; }
      });
    }
    $$('input[name=address_id]', co).forEach(function (r) { r.addEventListener('change', function () { newAddr.hidden = r.value !== '0'; quote(); }); });
    $$('input[name=shipping_method],input[name=payment_method]', co).forEach(function (r) { r.addEventListener('change', quote); });
    var ap = $('#addr_pincode'); if (ap) ap.addEventListener('change', quote);
    quote();
    var btn = $('#placeOrder'), err = $('#coError');
    btn.addEventListener('click', function () {
      err.classList.add('d-none'); var data = {};
      $$('input,select,textarea', co).forEach(function (el) { if (!el.name) return; if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; } else if (el.type === 'checkbox') { if (el.checked) data[el.name] = el.value; } else data[el.name] = el.value; });
      if (!data.payment_method) { err.textContent = 'Please choose a payment method.'; err.classList.remove('d-none'); return; }
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Placing order…';
      api('checkout/place', data).then(function (r) {
        if (!r.ok) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-lock-fill"></i> Place order'; err.textContent = r.error; err.classList.remove('d-none'); err.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (r.login) location.href = url('login'); return; }
        if (r.razorpay) { openRazorpay(r.razorpay, r.redirect, r.failed_redirect).catch(function (e) { toast(e.message, 'danger'); location.href = r.failed_redirect; }); }
        else location.href = r.redirect;
      });
    });
  }
})();
