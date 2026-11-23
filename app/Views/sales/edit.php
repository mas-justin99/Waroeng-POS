<?= $this->extend('layouts/app') ?>
<?php $title = 'Ubah Transaksi'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/sales/<?= $sale['id'] ?>" class="small"><i class="bi bi-arrow-left"></i> Detail transaksi</a><h2>Ubah <?= esc($sale['invoice_no']) ?></h2>
<div class="text-muted small">Stok ikut menyesuaikan: qty dikurangi → stok kembali, qty ditambah → stok berkurang. Selisih uang dihitung otomatis.</div></div></div>
<div class="row g-3">
 <div class="col-lg-7 anim"><div class="card-x card-b">
  <div id="lines"></div>
  <div class="input-group mt-3"><select id="addSel" class="form-select"><option value="">+ Tambah menu…</option>
   <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?> (<?= esc($p['sku']) ?>) · stok <?= $p['stock'] ?></option><?php endforeach; ?></select><button class="btn btn-soft" id="addBtn" type="button">Tambah</button></div>
 </div></div>
 <div class="col-lg-5 anim"><div class="card-x card-b">
  <div class="list-row"><span class="text-muted">Subtotal</span><span class="ms-auto num" id="sSub"></span></div>
  <div class="list-row"><span class="text-muted">Diskon (Rp)</span><input id="disc" type="number" min="0" class="form-control form-control-sm ms-auto" style="max-width:130px;text-align:right" value="<?= (float) $sale['discount'] ?>"></div>
  <div class="list-row"><span class="text-muted">Service + Pajak</span><span class="ms-auto num" id="sTax"></span></div>
  <div class="list-row"><b>Total baru</b><b class="ms-auto num" style="font-size:1.2rem" id="sTot"></b></div>
  <div class="list-row"><span class="text-muted">Total sebelumnya</span><span class="ms-auto num"><?= rupiah($sale['total']) ?></span></div>
  <div id="diffBox" class="alert-x ok mt-3"><i class="bi bi-check-circle"></i><span id="diffTxt">Tidak ada selisih.</span></div>
  <div id="payBox" class="d-none">
   <label class="form-label small">Metode pembayaran tambahan</label>
   <select id="pm" class="form-select mb-2"><?php foreach ($methods as $m): ?><option value="<?= $m['id'] ?>"><?= esc($m['name']) ?></option><?php endforeach; ?></select>
   <label class="form-label small">Uang diterima</label><input id="pamt" type="number" min="0" class="form-control mb-1"><div class="small text-muted" id="chg"></div>
  </div>
  <div id="refBox" class="d-none"><label class="form-label small">Dikembalikan lewat</label>
   <select id="rm" class="form-select"><?php foreach ($methods as $m): ?><option value="<?= $m['id'] ?>" <?= $m['type'] === 'cash' ? 'selected' : '' ?>><?= esc($m['name']) ?></option><?php endforeach; ?></select></div>
  <label class="form-label small mt-3">Alasan perubahan</label><input id="reason" class="form-control" maxlength="200" placeholder="mis. pelanggan tambah 1 es teh">
  <div id="err" class="alert-x err d-none mt-3"><i class="bi bi-exclamation-triangle"></i><span></span></div>
  <button id="save" class="btn btn-primary w-100 mt-3"><i class="bi bi-check2-circle"></i> Simpan Perubahan</button>
 </div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const LINES = <?= json_encode($lines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>, PRODS = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const OLD_TOTAL = <?= (float) $sale['total'] ?>, SP = <?= $sp ?>, TP = <?= $tp ?>;
const st = {}; LINES.forEach(l => st[l.id] = { id: l.id, name: l.name, price: l.price, old: l.qty, qty: l.qty, stock: l.stock });
const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID'), $ = s => document.querySelector(s);
function calc() {
  const sub = Object.values(st).reduce((s, l) => s + l.price * l.qty, 0), disc = Math.min(Math.max(0, +$('#disc').value || 0), sub), base = sub - disc;
  const svc = Math.round(base * SP / 100), tax = Math.round((base + svc) * TP / 100), total = base + svc + tax;
  return { sub, disc, svc, tax, total, diff: total - OLD_TOTAL };
}
function draw() {
  $('#lines').innerHTML = Object.values(st).map(l => {
    const d = l.qty - l.old, note = d > 0 ? `<span class="text-danger small">stok −${d}</span>` : d < 0 ? `<span class="text-success small">stok +${-d}</span>` : '';
    return `<div class="list-row"><div class="flex-grow-1"><b>${l.name.replace(/</g,'&lt;')}</b><br><small class="text-muted">${rp(l.price)} · sebelumnya ${l.old}</small> ${note}</div>
      <div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-ghost" data-m="${l.id}" type="button">−</button><b style="min-width:24px;text-align:center">${l.qty}</b><button class="btn btn-sm btn-ghost" data-p="${l.id}" type="button">+</button></div>
      <div class="num fw-bold" style="min-width:90px;text-align:right">${rp(l.price * l.qty)}</div></div>`;
  }).join('') || '<div class="text-muted">Tidak ada item.</div>';
  const c = calc(); $('#sSub').textContent = rp(c.sub); $('#sTax').textContent = rp(c.svc + c.tax); $('#sTot').textContent = rp(c.total);
  const box = $('#diffBox'), t = $('#diffTxt'); $('#payBox').classList.toggle('d-none', c.diff <= 0); $('#refBox').classList.toggle('d-none', c.diff >= 0);
  box.className = 'alert-x mt-3 ' + (c.diff > 0 ? 'warn' : c.diff < 0 ? 'ok' : 'ok');
  t.innerHTML = c.diff > 0 ? `<b>Kekurangan ${rp(c.diff)}</b> — terima pembayaran tambahan.` : c.diff < 0 ? `<b>Kelebihan ${rp(-c.diff)}</b> — kembalikan ke pelanggan.` : 'Tidak ada selisih.';
  if (c.diff > 0 && !$('#pamt').dataset.t) $('#pamt').value = c.diff;
  const ch = (+$('#pamt').value || 0) - c.diff; $('#chg').textContent = c.diff > 0 ? (ch >= 0 ? 'Kembalian ' + rp(ch) : 'Masih kurang ' + rp(-ch)) : '';
}
document.addEventListener('click', e => {
  const m = e.target.closest('[data-m]'), p = e.target.closest('[data-p]');
  if (m) { const l = st[m.dataset.m]; l.qty = Math.max(0, l.qty - 1); if (!l.qty && !l.old) delete st[l.id]; draw(); }
  if (p) { const l = st[p.dataset.p]; if (l.qty + 1 - l.old > l.stock) { Toast.show('Stok ' + l.name + ' tidak cukup', 'err'); return; } l.qty++; draw(); }
});
$('#addBtn').onclick = () => { const id = +$('#addSel').value; if (!id) return; const p = PRODS.find(x => x.id === id); if (st[id]) { st[id].qty++; } else { if (p.stock < 1) { Toast.show('Stok habis', 'err'); return; } st[id] = { id, name: p.name, price: p.price, old: 0, qty: 1, stock: p.stock }; } $('#addSel').value = ''; draw(); };
$('#disc').oninput = draw; $('#pamt').oninput = e => { e.target.dataset.t = 1; draw(); };
$('#save').onclick = async () => {
  const c = calc(), e = $('#err'); e.classList.add('d-none');
  const body = { items: Object.values(st).filter(l => l.qty > 0).map(l => ({ id: l.id, qty: l.qty })), discount: c.disc, reason: $('#reason').value,
    payments: c.diff > 0 ? [{ method_id: +$('#pm').value, amount: +$('#pamt').value || 0 }] : [], refund_method_id: +$('#rm').value };
  if (c.diff < 0 && !confirm('Kembalikan ' + rp(-c.diff) + ' ke pelanggan?')) return;
  $('#save').disabled = true;
  try { await api('/sales/<?= $sale['id'] ?>/edit', body); location.href = '/sales/<?= $sale['id'] ?>'; }
  catch (err) { e.querySelector('span').textContent = err.message; e.classList.remove('d-none'); $('#save').disabled = false; }
};
draw();
</script>
<?= $this->endSection() ?>
