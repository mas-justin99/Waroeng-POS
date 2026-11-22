<?= $this->extend('layouts/app') ?>
<?php $title = 'Buka Kasir'; use App\Libraries\ShiftService; $L = ShiftService::LABELS;
$defReg = null; for ($i = 1; $i <= $regCount; $i++) { if (! isset($busy[$i])) { $defReg = $i; break; } }
$defReg = (int) old('register_no', $defReg ?? 1);
$used = $usedMap[$defReg] ?? []; $firstFree = null; foreach ([1, 2] as $n) { if (! in_array($n, $used, true)) { $firstFree = $n; break; } }
$allBusy = $defReg === null || count($busy) >= $regCount; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><h2>Buka Kasir</h2>
<div class="text-muted">Isi uang modal dan hitung stok awal sebelum mulai berjualan. <?= esc(loc_row()['name'] ?? '') ?> · <?= tgl(date('Y-m-d'), 'd M Y') ?></div></div></div>

<?php if (! empty($open)): ?>
<div class="card-x card-b mb-3 anim"><div class="fw-bold mb-2"><i class="bi bi-people"></i> Kasir yang sedang buka di outlet ini</div>
  <?php foreach ($open as $o): ?><div class="list-row"><span><b><?= esc(ShiftService::label($o)) ?></b> · <?= esc($o['opener']) ?> · sejak <?= tgl($o['opened_at'], 'H:i') ?></span>
    <span class="ms-auto d-flex gap-2"><a class="btn btn-sm btn-soft" href="/shifts/<?= $o['id'] ?>">Detail</a><?php if (auth_user()['role'] === 'admin'): ?><a class="btn btn-sm btn-ghost text-danger" href="/shift/close?s=<?= $o['id'] ?>">Tutup</a><?php endif; ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" action="/shift/open" id="openForm" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-lg-4 anim">
    <div class="card-x card-b">
      <div class="form-label">1. Pilih kasir</div>
      <div class="shift-opts mb-3" id="regs">
        <?php for ($i = 1; $i <= $regCount; $i++): $dis = isset($busy[$i]); ?>
          <label class="shift-opt <?= $dis ? 'dis' : '' ?>">
            <input type="radio" name="register_no" value="<?= $i ?>" <?= $i === $defReg && ! $dis ? 'checked' : '' ?> <?= $dis ? 'disabled' : '' ?> required>
            <span class="so-n"><?= $i ?></span>
            <span><b>Kasir <?= $i ?></b><small><?= $dis ? 'Dipakai ' . esc($busy[$i]) : 'Tersedia' ?></small></span>
          </label>
        <?php endfor; ?>
      </div>
      <div class="form-label">2. Pilih shift</div>
      <div class="shift-opts" id="shifts" data-used="<?= esc(json_encode($usedMap), 'attr') ?>">
        <?php foreach ($L as $n => $label): $dis = in_array($n, $used, true); $sel = (int) old('shift_no', $firstFree) === $n && ! $dis; ?>
          <label class="shift-opt <?= $dis ? 'dis' : '' ?>">
            <input type="radio" name="shift_no" value="<?= $n ?>" <?= $sel ? 'checked' : '' ?> <?= $dis ? 'disabled' : '' ?> required>
            <span class="so-n"><?= $n ?></span>
            <span><b><?= esc($label) ?></b><small><?= $dis ? 'Sudah dipakai hari ini' : ($n === 1 ? 'Pagi – siang' : 'Siang – malam') ?></small></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if ($allBusy): ?><div class="alert-x err mt-3 mb-0"><i class="bi bi-exclamation-circle-fill"></i><span>Semua kasir (1 - <?= $regCount ?>) sedang dipakai. Tunggu salah satu menutup kasirnya.</span></div>
      <?php elseif ($firstFree === null): ?><div class="alert-x err mt-3 mb-0" id="noShift"><i class="bi bi-exclamation-circle-fill"></i><span>Kedua shift untuk kasir ini hari ini sudah dipakai. Pilih kasir lain atau buka lagi besok.</span></div><?php endif; ?>

      <div class="form-label mt-4">3. Uang modal (kas awal laci) <span class="text-danger">*</span></div>
      <div class="money"><span>Rp</span><input id="cash" name="opening_cash" class="form-control form-control-lg" inputmode="numeric" autocomplete="off" placeholder="0" value="<?= esc(old('opening_cash')) ?>" required></div>
      <div class="small text-muted mt-1">Hitung uang fisik di laci. Isi 0 bila tanpa modal.</div>
      <?php if (! empty($last)): ?><div class="small text-muted mt-2">Kas akhir shift terakhir: <b><?= rupiah($last['closing_cash']) ?></b></div><?php endif; ?>

      <label class="form-label mt-4">Catatan (opsional)</label>
      <input name="note" class="form-control" maxlength="250" value="<?= esc(old('note')) ?>" placeholder="mis. serah terima dari kasir pagi">
    </div>
  </div>

  <div class="col-lg-8 anim">
    <?php if ($needCount): ?>
    <div class="card-x">
      <div class="card-h"><span>4. Hitung stok awal <span class="text-danger">*</span></span>
        <button type="button" class="btn btn-sm btn-ghost" id="reset"><i class="bi bi-arrow-counterclockwise"></i> Samakan dengan sistem</button></div>
      <div class="px-3 pt-3"><div class="input-icon"><i class="bi bi-search"></i><input id="sq" class="form-control" placeholder="Cari menu / SKU…" autocomplete="off"></div>
        <div class="small text-muted mt-2">Hitung barang yang ada di warung. Angka sudah terisi sesuai catatan sistem — ubah bila hasil hitung berbeda. Selisih akan dicatat sebagai penyesuaian stok.</div></div>
      <div class="sa-list" id="sl">
        <?php foreach ($products as $p): $old = old('stock.' . $p['id']); ?>
          <div class="sa-row" data-s="<?= esc(strtolower($p['name'] . ' ' . $p['sku'])) ?>" data-sys="<?= (int) $p['qty'] ?>">
            <div class="sa-n"><b><?= esc($p['name']) ?></b><small><?= esc($p['sku']) ?> · sistem: <?= (int) $p['qty'] ?></small></div>
            <span class="sa-diff"></span>
            <div class="stepper"><button type="button" data-d="-1">−</button><input name="stock[<?= $p['id'] ?>]" value="<?= $old !== null && $old !== '' ? esc($old) : (int) $p['qty'] ?>" inputmode="numeric" required><button type="button" data-d="1">+</button></div>
          </div>
        <?php endforeach; ?>
        <?php if (! $products): ?><div class="empty"><i class="bi bi-box-seam"></i>Belum ada produk aktif.</div><?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="card-x card-b"><div class="fw-bold mb-1"><i class="bi bi-box-seam"></i> Stok awal</div><div class="text-muted">Kasir lain sudah menghitung stok awal dan sedang berjualan, jadi stok outlet dipakai bersama. Anda tidak perlu menghitung ulang — cukup isi uang modal laci Anda.</div></div>
    <?php endif; ?>
    <button class="btn btn-primary btn-lg w-100 mt-3" id="go" <?= $firstFree === null || $allBusy ? 'disabled' : '' ?>><i class="bi bi-unlock"></i> Buka Kasir</button>
  </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
  const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
  const cash = $('#cash');
  const fmt = () => { const n = cash.value.replace(/\D/g, ''); cash.value = n ? Number(n).toLocaleString('id-ID') : ''; };
  cash.addEventListener('input', fmt); fmt();

  // shift yang sudah dipakai hari ini ditentukan per kasir
  const used = JSON.parse($('#shifts').dataset.used || '{}');
  const syncShifts = () => {
    const r = ($('#regs input:checked') || {}).value, u = (used[r] || []).map(Number); let first = null;
    $$('#shifts input').forEach(i => { const d = u.includes(+i.value); i.disabled = d; i.closest('.shift-opt').classList.toggle('dis', d); if (!d && first === null) first = i; });
    const cur = $('#shifts input:checked'); if ((!cur || cur.disabled) && first) first.checked = true;
    const none = first === null; $('#go').disabled = none; const w = $('#noShift'); if (w) w.style.display = none ? '' : 'none';
  };
  $$('#regs input').forEach(i => i.addEventListener('change', syncShifts));

  const refresh = row => {
    const i = row.querySelector('input'), sys = +row.dataset.sys, v = i.value === '' ? null : parseInt(i.value);
    const d = row.querySelector('.sa-diff');
    if (v === null || v === sys) { d.textContent = ''; d.className = 'sa-diff'; row.classList.remove('chg'); }
    else { const x = v - sys; d.textContent = (x > 0 ? '+' : '') + x; d.className = 'sa-diff ' + (x > 0 ? 'up' : 'down'); row.classList.add('chg'); }
  };
  $$('.sa-row').forEach(row => {
    const i = row.querySelector('input');
    i.addEventListener('input', () => { i.value = i.value.replace(/\D/g, ''); refresh(row); });
    i.addEventListener('focus', () => i.select());
    row.querySelectorAll('button').forEach(b => b.addEventListener('click', () => { i.value = Math.max(0, (parseInt(i.value) || 0) + +b.dataset.d); refresh(row); }));
    refresh(row);
  });
  if ($('#sq')) $('#sq').addEventListener('input', e => { const q = e.target.value.toLowerCase().trim(); $$('.sa-row').forEach(r => r.style.display = !q || r.dataset.s.includes(q) ? '' : 'none'); });
  if ($('#reset')) $('#reset').addEventListener('click', () => $$('.sa-row').forEach(r => { r.querySelector('input').value = r.dataset.sys; refresh(r); }));
  $('#openForm').addEventListener('submit', e => {
    const n = $$('.sa-row.chg').length;
    if (n && !confirm(n + ' item stok awal berbeda dari catatan sistem. Stok sistem akan disesuaikan dengan hasil hitung Anda. Lanjutkan?')) e.preventDefault();
  });
})();
</script>
<?= $this->endSection() ?>
