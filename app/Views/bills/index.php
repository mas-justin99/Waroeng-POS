<?= $this->extend('layouts/app') ?>
<?php $title = 'Bill Meja'; ?>
<?= $this->section('content') ?>
<div class="page-head anim">
  <div><h5 class="m-0">Bill Meja</h5><div class="text-muted small"><?= count($bills) ?> meja terbuka · belum dibayar</div></div>
  <a href="/pos" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Pesanan Baru</a>
</div>
<?php if (! $bills): ?><div class="card-x anim"><div class="empty"><i class="bi bi-receipt-cutoff"></i>Belum ada bill meja yang terbuka.<br>Di halaman Kasir, isi nomor meja lalu tekan <b>Simpan ke Meja</b>.</div></div><?php endif; ?>
<div class="bill-grid">
<?php foreach ($bills as $b): ?>
  <div class="card-x card-b bill anim" data-id="<?= $b['id'] ?>">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div><div class="bill-tbl">Meja <?= esc($b['table_no']) ?></div><div class="text-muted small"><?= $b['customer'] ? esc($b['customer']) . ' · ' : '' ?>sejak <?= tgl($b['opened_at'], 'H:i') ?></div></div>
      <div class="text-end"><div class="fw-bold num"><?= rupiah($b['total']) ?></div><div class="text-muted small"><?= $b['qty'] ?> item</div></div>
    </div>
    <ul class="bill-items">
      <?php foreach (array_slice($b['lines'], 0, 4) as $l): ?><li><span><?= $l['qty'] ?>× <?= esc($l['name']) ?></span><span class="num"><?= rupiah($l['sub'], false) ?></span></li><?php endforeach; ?>
      <?php if (count($b['lines']) > 4): ?><li class="text-muted">+ <?= count($b['lines']) - 4 ?> menu lain…</li><?php endif; ?>
    </ul>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-primary btn-sm flex-grow-1" href="/pos?bill=<?= $b['id'] ?>"><i class="bi bi-wallet2"></i> Tambah / Bayar</a>
      <a class="btn btn-ghost btn-sm" href="/bills/<?= $b['id'] ?>/print"><i class="bi bi-printer"></i> Bill</a>
      <button class="btn btn-ghost btn-sm text-danger" data-cancel="<?= $b['id'] ?>" data-tbl="<?= esc($b['table_no'], 'attr') ?>"><i class="bi bi-x-circle"></i></button>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.addEventListener('click', async e => {
  const b = e.target.closest('[data-cancel]'); if (!b) return;
  if (!confirm('Batalkan bill meja ' + b.dataset.tbl + '? Pesanan di bill ini akan dihapus.')) return;
  try { await api('/bills/' + b.dataset.cancel + '/cancel', {}); b.closest('.bill').remove(); Toast.show('Bill dibatalkan.'); } catch (err) { Toast.show(err.message, 'err'); }
});
</script>
<?= $this->endSection() ?>
