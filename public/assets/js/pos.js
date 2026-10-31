/* Logika kasir: keranjang, diskon, split payment, checkout */
(function () {
  const P = window.POS, $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
  const state = { cart: {}, cat: 0, q: '', otype: 'dine_in', dtype: 'nominal', dval: 0, pays: [] };
  const byId = Object.fromEntries(P.products.map(p => [p.id, p]));
  const icon = { cash: 'bi-cash-coin', qris: 'bi-qr-code', card: 'bi-credit-card', ewallet: 'bi-wallet2', transfer: 'bi-bank', online: 'bi-bicycle', dp: 'bi-bookmark-star', other: 'bi-ticket-perforated' };
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const modal = id => bootstrap.Modal.getOrCreateInstance($(id));

  /* ---------- hitung ---------- */
  function totals() {
    let sub = 0, qty = 0;
    for (const [id, q] of Object.entries(state.cart)) { sub += byId[id].price * q; qty += q; }
    let disc = state.dtype === 'percent' ? Math.round(sub * Math.min(state.dval, 100) / 100) : state.dval;
    disc = Math.min(disc, sub);
    const base = sub - disc, svc = Math.round(base * P.service / 100), tax = Math.round((base + svc) * P.tax / 100);
    return { sub, qty, disc, svc, tax, total: base + svc + tax };
  }
  const tween = (el, to) => {
    const from = parseFloat(el.dataset.v || 0); el.dataset.v = to;
    if (!window.gsap) { el.textContent = rp(to); return; }
    const o = { v: from }; gsap.to(o, { v: to, duration: .35, ease: 'power1.out', onUpdate: () => el.textContent = rp(o.v) });
  };

  /* ---------- produk ---------- */
  function renderGrid() {
    const q = state.q.toLowerCase();
    const list = P.products.filter(p => (!state.cat || p.category_id === state.cat) && (!q || p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)));
    $('#grid').innerHTML = list.length ? list.map(p => {
      const inCart = state.cart[p.id] || 0, left = p.stock - inCart;
      const cls = (p.stock <= 0 ? 'out' : p.stock <= 5 ? 'low' : '') + (inCart ? ' in-cart' : '');
      return `<button class="pcard ${cls}" data-id="${p.id}">
        <span class="qty-b">${inCart}</span><span class="st">${p.stock <= 0 ? 'Habis' : 'Stok ' + left}</span>
        <div class="im">${p.image_url ? `<img loading="lazy" src="${esc(p.image_url)}" alt="">` : esc(p.name.charAt(0))}</div>
        <div class="tx"><div class="nm">${esc(p.name)}</div><div class="pr num">${rp(p.price)}</div></div></button>`;
    }).join('') : '<div class="empty" style="grid-column:1/-1"><i class="bi bi-search"></i>Menu tidak ditemukan</div>';
  }

  /* ---------- keranjang ---------- */
  function renderCart(pulseId) {
    const ids = Object.keys(state.cart);
    $('#items').innerHTML = ids.length ? ids.map(id => {
      const p = byId[id], q = state.cart[id];
      return `<div class="ci" data-id="${id}"><div class="n"><b>${esc(p.name)}</b><small class="num">${rp(p.price)}</small></div>
        <div class="stepper"><button data-a="dec">−</button><input value="${q}" inputmode="numeric" data-a="set"><button data-a="inc">+</button></div>
        <b class="num" style="min-width:78px;text-align:right">${rp(p.price * q)}</b></div>`;
    }).join('') : '<div class="empty"><i class="bi bi-bag"></i>Belum ada pesanan.<br>Ketuk menu untuk menambah.</div>';
    const t = totals();
    tween($('#s-sub'), t.sub); tween($('#s-total'), t.total); tween($('#barTotal'), t.total);
    $('#s-disc').textContent = '- ' + rp(t.disc); $('#s-svc').textContent = rp(t.svc); $('#s-tax').textContent = rp(t.tax);
    $('#r-svc').style.display = P.service ? '' : 'none'; $('#r-tax').style.display = P.tax ? '' : 'none';
    $('#barQty').textContent = t.qty; $('#payBtn').disabled = !t.qty; $('#cartBar').style.display = '';
    renderGrid();
    if (pulseId && window.gsap) {
      const el = $(`.ci[data-id="${pulseId}"]`); if (el) gsap.fromTo(el, { backgroundColor: '#FDECEC' }, { backgroundColor: 'rgba(253,237,228,0)', duration: .8 });
      gsap.fromTo('#barQty', { scale: 1.5 }, { scale: 1, duration: .4, ease: 'back.out(3)' });
    }
  }
  function add(id, d = 1) {
    const p = byId[id]; if (!p) return;
    const n = (state.cart[id] || 0) + d;
    if (n <= 0) delete state.cart[id];
    else if (n > p.stock) { Toast.show(`Stok ${p.name} hanya ${p.stock}`, 'err'); return; }
    else state.cart[id] = n;
    renderCart(id);
  }
  function setQty(id, n) {
    n = Math.max(0, parseInt(n) || 0); const p = byId[id];
    if (n > p.stock) { n = p.stock; Toast.show(`Stok ${p.name} hanya ${p.stock}`, 'err'); }
    if (n <= 0) delete state.cart[id]; else state.cart[id] = n; renderCart(id);
  }

  $('#grid').addEventListener('click', e => { const c = e.target.closest('.pcard'); if (c) { add(+c.dataset.id); if (window.gsap) gsap.fromTo(c, { scale: .94 }, { scale: 1, duration: .35, ease: 'back.out(3)' }); } });
  $('#items').addEventListener('click', e => { const b = e.target.closest('button[data-a]'); if (!b) return; const id = +b.closest('.ci').dataset.id; add(id, b.dataset.a === 'inc' ? 1 : -1); });
  $('#items').addEventListener('change', e => { if (e.target.dataset.a === 'set') setQty(+e.target.closest('.ci').dataset.id, e.target.value); });
  $('#items').addEventListener('focusin', e => { if (e.target.dataset.a === 'set') e.target.select(); });
  $('#q').addEventListener('input', e => { state.q = e.target.value; renderGrid(); });
  $('#cats').addEventListener('click', e => { const b = e.target.closest('.cat'); if (!b) return; $$('.cat').forEach(x => x.classList.remove('on')); b.classList.add('on'); state.cat = +b.dataset.c; renderGrid(); });
  $('#otype').addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; $$('#otype button').forEach(x => x.classList.remove('on')); b.classList.add('on'); state.otype = b.dataset.v; });
  $('#clear').addEventListener('click', async () => {
    if (!Object.keys(state.cart).length) return;
    const r = window.Swal ? await Swal.fire({ title: 'Kosongkan pesanan?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, kosongkan', cancelButtonText: 'Batal', confirmButtonColor: '#E32F37', reverseButtons: true }) : { isConfirmed: confirm('Kosongkan pesanan?') };
    if (r.isConfirmed) { state.cart = {}; renderCart(); }
  });

  /* ---------- bottom sheet (HP/tablet portrait) ---------- */
  const cart = $('#cart'), scrim = $('#scrim');
  const openCart = o => { cart.classList.toggle('open', o); scrim.classList.toggle('on', o); };
  $('#cartBar').addEventListener('click', () => openCart(true));
  $('#closeCart').addEventListener('click', () => openCart(false)); scrim.addEventListener('click', () => openCart(false));

  /* ---------- diskon ---------- */
  $('#discBtn').addEventListener('click', e => { e.preventDefault(); $('#dval').value = state.dval || ''; modal('#discModal').show(); });
  $('#dtype').addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; $$('#dtype button').forEach(x => x.classList.remove('on')); b.classList.add('on'); state.dtype = b.dataset.v; });
  $('#dok').addEventListener('click', () => { state.dval = Math.max(0, parseFloat($('#dval').value) || 0); renderCart(); });

  /* ---------- pembayaran ---------- */
  $('#pg').innerHTML = P.methods.map(m => `<button class="pm" data-id="${m.id}"><i class="bi ${icon[m.type] || icon.other}"></i>${esc(m.name)}</button>`).join('');
  const mById = Object.fromEntries(P.methods.map(m => [m.id, m]));
  const paid = () => state.pays.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);

  function renderPay() {
    const t = totals(), pd = paid(), rem = t.total - pd;
    $('#p-total').textContent = rp(t.total); $('#p-paid').textContent = rp(pd);
    $('#p-lbl').textContent = rem > 0 ? 'Kurang' : 'Kembalian'; $('#p-rem').textContent = rp(Math.abs(rem));
    $('#p-rem').style.color = rem > 0 ? '#FCA5A5' : '#86EFAC';
    $$('#pg .pm').forEach(b => b.classList.toggle('used', state.pays.some(p => p.method_id == b.dataset.id)));
    $('#lines').innerHTML = state.pays.map((p, i) => {
      const m = mById[p.method_id];
      return `<div class="payline" data-i="${i}"><div class="nm"><i class="bi ${icon[m.type] || icon.other} text-primary"></i>${esc(m.name)}</div><button class="x" data-a="rm"><i class="bi bi-x-circle-fill"></i></button>
        <input class="form-control" data-a="amt" type="text" inputmode="numeric" value="${p.amount ? Number(p.amount).toLocaleString('id-ID') : ''}" placeholder="0">
        ${m.type === 'dp' ? `<div class="small text-muted" style="grid-column:1/-1">Reservasi <b>${esc(p.rname || '')}</b> · DP ${rp(p.max || 0)}${p.max > p.amount ? ' · <span class="text-warning">sisa ' + rp(p.max - p.amount) + ' tidak terpotong</span>' : ''}</div>` : m.type !== 'cash' ? `<input class="form-control" data-a="ref" style="text-align:left;font-weight:500" placeholder="No. ref / approval (opsional)" value="${esc(p.reference || '')}">` : '<span></span>'}</div>`;
    }).join('');
    const hasCash = state.pays.some(p => mById[p.method_id].type === 'cash');
    const q = [];
    if (hasCash) { q.push(['Uang pas', t.total]); [10000, 20000, 50000, 100000].forEach(v => { const n = Math.ceil(t.total / v) * v; if (n >= t.total && !q.some(x => x[1] === n)) q.push([rp(n, 0), n]); }); }
    $('#quick').innerHTML = q.slice(0, 5).map(([l, v]) => `<button data-v="${v}">${l}</button>`).join('');
    const cashOver = Math.max(0, pd - t.total) <= state.pays.filter(p => mById[p.method_id].type === 'cash').reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
    $('#confirmPay').disabled = !(pd >= t.total && t.total > 0 && cashOver);
    $('#payErr').classList.add('d-none');
  }
  $('#payBtn').addEventListener('click', () => {
    state.pays = []; const cash = P.methods.find(m => m.type === 'cash');
    if (cash) state.pays.push({ method_id: cash.id, amount: totals().total, reference: '' });
    renderPay(); modal('#payModal').show();
  });
  $('#pg').addEventListener('click', e => {
    const b = e.target.closest('.pm'); if (!b) return; const id = +b.dataset.id, m = mById[id];
    if (m.type === 'dp') { openDp(id); return; }
    const rem = Math.max(0, totals().total - paid());
    const ex = state.pays.find(p => p.method_id === id);
    if (ex && m.type !== 'cash') { if (!rem) return; ex.amount = (parseFloat(ex.amount) || 0) + rem; }
    else if (!ex) {
      // jika hanya ada satu baris tunai default & user pilih metode lain → ganti jadi pembayaran penuh metode itu
      if (state.pays.length === 1 && mById[state.pays[0].method_id].type === 'cash' && parseFloat(state.pays[0].amount) === totals().total && m.type !== 'cash') state.pays = [];
      state.pays.push({ method_id: id, amount: state.pays.length ? Math.max(0, totals().total - paid()) : totals().total, reference: '' });
    }
    renderPay();
  });
  $('#lines').addEventListener('input', e => {
    const i = +e.target.closest('.payline').dataset.i;
    if (e.target.dataset.a === 'amt') { let n = parseInt(e.target.value.replace(/\D/g, '')) || 0; if (state.pays[i].max) n = Math.min(n, state.pays[i].max); state.pays[i].amount = n; e.target.value = n ? n.toLocaleString('id-ID') : ''; updSum(); }
    if (e.target.dataset.a === 'ref') state.pays[i].reference = e.target.value;
  });
  /* ---------- DP reservasi ---------- */
  function openDp(methodId) {
    const taken = state.pays.filter(p => p.reservation_id).map(p => p.reservation_id);
    const list = (P.dps || []).filter(r => !taken.includes(+r.id));
    if (!list.length) { Toast.show('Tidak ada reservasi ber-DP yang belum terpakai.', 'err'); return; }
    $('#dpList').innerHTML = list.map(r => `<button type="button" class="dp-item" data-id="${r.id}"><div><b>${esc(r.name)}</b><div class="small text-muted">${esc(String(r.reserved_at).slice(5, 16).replace('-', '/'))} · ${r.pax} org${r.table_no ? ' · Meja ' + esc(r.table_no) : ''}</div></div><span class="num fw-bold">${rp(+r.amount)}</span></button>`).join('');
    $('#dpList').dataset.m = methodId; modal('#dpModal').show();
  }
  $('#dpList').addEventListener('click', e => {
    const b = e.target.closest('.dp-item'); if (!b) return;
    const r = P.dps.find(x => x.id == b.dataset.id), mid = +$('#dpList').dataset.m;
    if (state.pays.length === 1 && mById[state.pays[0].method_id].type === 'cash' && parseFloat(state.pays[0].amount) === totals().total) state.pays = [];
    const rem = Math.max(0, totals().total - paid());
    state.pays.push({ method_id: mid, amount: Math.min(+r.amount, rem), max: +r.amount, reservation_id: +r.id, rname: r.name, reference: '' });
    modal('#dpModal').hide(); renderPay();
    const left = Math.max(0, totals().total - paid()); if (left > 0) Toast.show('DP dipotong. Sisa tagihan ' + rp(left) + ' — pilih metode lain.', 'ok');
  });
  function updSum() { // update ringkasan tanpa render ulang (menjaga fokus input)
    const t = totals(), pd = paid(), rem = t.total - pd;
    $('#p-paid').textContent = rp(pd); $('#p-lbl').textContent = rem > 0 ? 'Kurang' : 'Kembalian'; $('#p-rem').textContent = rp(Math.abs(rem)); $('#p-rem').style.color = rem > 0 ? '#FCA5A5' : '#86EFAC';
    const cashSum = state.pays.filter(p => mById[p.method_id].type === 'cash').reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
    $('#confirmPay').disabled = !(pd >= t.total && t.total > 0 && Math.max(0, pd - t.total) <= cashSum);
  }
  $('#lines').addEventListener('click', e => { if (e.target.closest('[data-a=rm]')) { state.pays.splice(+e.target.closest('.payline').dataset.i, 1); renderPay(); } });
  $('#quick').addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; const c = state.pays.find(p => mById[p.method_id].type === 'cash'); if (c) { const others = state.pays.filter(p => p !== c).reduce((s, p) => s + (+p.amount || 0), 0); c.amount = Math.max(0, +b.dataset.v - others); renderPay(); } });

  $('#confirmPay').addEventListener('click', async () => {
    const btn = $('#confirmPay'); btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses…';
    try {
      const r = await api('/pos/checkout', {
        items: Object.entries(state.cart).map(([id, qty]) => ({ id: +id, qty })), order_type: state.otype, table_no: $('#table').value, customer: $('#cust').value,
        discount_type: state.dtype, discount_value: state.dval, payments: state.pays.map(p => ({ method_id: p.method_id, amount: +p.amount, reference: p.reference, reservation_id: p.reservation_id || null }))
      });
      // kurangi stok lokal
      for (const [id, q] of Object.entries(state.cart)) byId[id].stock -= q;
      modal('#payModal').hide();
      $('#ok-inv').textContent = r.invoice_no; $('#ok-change').textContent = rp(r.change); $('#ok-print').href = '/pos/receipt/' + r.id + '?print=1';
      openCart(false); modal('#okModal').show();
      if (window.gsap) { gsap.to('.ok-ring circle', { strokeDashoffset: 0, duration: .7, ease: 'power2.out' }); gsap.to('.ok-ring path', { strokeDashoffset: 0, duration: .5, delay: .55, ease: 'power2.out' }); }
      else $$('.ok-ring circle,.ok-ring path').forEach(el => el.style.strokeDashoffset = 0);
    } catch (err) { const e = $('#payErr'); e.textContent = err.message; e.classList.remove('d-none'); Toast.show(err.message, 'err'); }
    finally { btn.innerHTML = '<i class="bi bi-check2-circle"></i> Selesaikan Transaksi'; updSum(); }
  });
  $('#ok-new').addEventListener('click', () => {
    state.cart = {}; state.dval = 0; state.pays = []; $('#table').value = ''; $('#cust').value = '';
    if (window.gsap) gsap.set('.ok-ring circle,.ok-ring path', { strokeDashoffset: (i, el) => el.tagName === 'circle' ? 283 : 60 });
    else $$('.ok-ring circle,.ok-ring path').forEach(el => el.style.strokeDashoffset = el.tagName === 'circle' ? 283 : 60);
    modal('#okModal').hide(); renderCart();
  });

  renderCart();
})();
