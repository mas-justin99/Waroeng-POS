<?= $this->extend('layouts/app') ?>
<?php $title = 'Tutup Kasir'; use App\Libraries\ShiftService; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/shift" class="small"><i class="bi bi-arrow-left"></i> Kembali</a><h2>Tutup <?= esc(ShiftService::LABELS[$shift['shift_no']]) ?></h2>
<div class="text-muted">Dibuka <?= tgl($shift['opened_at']) ?> oleh <?= esc($shift['opener']) ?></div></div></div>
<form method="post" action="/shift/close" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-lg-6 anim"><div class="card-x card-b">
    <div class="fw-bold mb-2">Rekap kas</div>
    <div class="list-row"><span class="text-muted">Uang modal</span><span class="ms-auto num"><?= rupiah($shift['opening_cash']) ?></span></div>
    <div class="list-row"><span class="text-muted">Penjualan tunai</span><span class="ms-auto num"><?= rupiah($sum['cash_sales']) ?></span></div>
    <div class="list-row"><b>Seharusnya ada di laci</b><b class="ms-auto num" id="exp" data-v="<?= $sum['expected_cash'] ?>" style="font-size:1.2rem"><?= rupiah($sum['expected_cash']) ?></b></div>
    <div class="fw-bold mt-4 mb-2">Penjualan non-tunai</div>
    <?php $any = false; foreach ($sum['payments'] as $p): if ($p['type'] === 'cash') continue; $any = true; ?>
      <div class="list-row"><span><?= esc($p['name']) ?></span><span class="ms-auto num"><?= rupiah($p['amount']) ?></span></div><?php endforeach; ?>
    <?php if (! $any): ?><div class="text-muted">Tidak ada.</div><?php endif; ?>
    <div class="list-row mt-2"><b>Total penjualan (<?= $sum['trx'] ?> transaksi)</b><b class="ms-auto num"><?= rupiah($sum['total']) ?></b></div>
  </div></div>
  <div class="col-lg-6 anim"><div class="card-x card-b">
    <div class="form-label">Uang kas hasil hitung di laci <span class="text-danger">*</span></div>
    <div class="money"><span>Rp</span><input id="cc" name="closing_cash" class="form-control form-control-lg" inputmode="numeric" autocomplete="off" placeholder="0" value="<?= esc(old('closing_cash')) ?>" required autofocus></div>
    <div class="mt-3 p-3 rounded-3" id="dbox" style="background:#F6F2EE"><div class="d-flex justify-content-between"><span class="text-muted">Selisih</span><b class="num" id="diff">Rp 0</b></div></div>
    <label class="form-label mt-3">Catatan (mis. alasan selisih)</label>
    <input name="note" class="form-control" maxlength="250" value="<?= esc(old('note')) ?>">
    <div class="small text-muted mt-3"><i class="bi bi-info-circle"></i> Stok akhir semua menu tercatat otomatis saat kasir ditutup. Setelah ditutup, shift tidak bisa dibuka lagi; buka shift berikutnya.</div>
    <button class="btn btn-primary btn-lg w-100 mt-3" id="go"><i class="bi bi-lock"></i> Tutup Kasir</button>
  </div></div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(() => {
  const cc = document.getElementById('cc'), exp = +document.getElementById('exp').dataset.v, out = document.getElementById('diff'), box = document.getElementById('dbox');
  const upd = () => {
    const n = cc.value.replace(/\D/g, ''); cc.value = n ? Number(n).toLocaleString('id-ID') : '';
    if (!n) { out.textContent = '-'; out.className = 'num'; return; }
    const d = Number(n) - exp;
    out.textContent = (d > 0 ? '+ ' : d < 0 ? '- ' : '') + rp(Math.abs(d)) + (d === 0 ? ' (pas)' : d > 0 ? ' (lebih)' : ' (kurang)');
    out.className = 'num ' + (d === 0 ? 'diff-pos' : d > 0 ? 'diff-pos' : 'diff-neg');
  };
  cc.addEventListener('input', upd); upd();
  document.querySelector('form').addEventListener('submit', e => { if (!confirm('Tutup kasir sekarang? Shift tidak dapat dibuka kembali.')) e.preventDefault(); });
})();
</script>
<?= $this->endSection() ?>
