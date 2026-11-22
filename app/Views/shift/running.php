<?= $this->extend('layouts/app') ?>
<?php $title = 'Kasir Dibuka'; use App\Libraries\ShiftService; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><h2><?= esc(ShiftService::label($shift)) ?> <span class="badge-soft badge-success">Sedang buka</span></h2>
<div class="text-muted">Dibuka <?= tgl($shift['opened_at']) ?> oleh <b><?= esc($shift['opener']) ?></b> · <?= esc(loc_row()['name'] ?? '') ?></div></div>
<div class="d-flex gap-2"><a class="btn btn-soft" href="/shifts/<?= $shift['id'] ?>"><i class="bi bi-list-check"></i> Detail</a><a class="btn btn-soft" target="_blank" href="/shifts/<?= $shift['id'] ?>/receipt"><i class="bi bi-receipt"></i> Pratinjau Struk</a><a class="btn btn-primary" href="/pos"><i class="bi bi-shop"></i> Ke Kasir</a></div></div>
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi"><div class="ic"><i class="bi bi-wallet2"></i></div><div class="v num"><?= rupiah($shift['opening_cash']) ?></div><div class="l">Uang modal</div></div></div>
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi k2"><div class="ic"><i class="bi bi-cash-stack"></i></div><div class="v num"><?= rupiah($sum['total']) ?></div><div class="l">Penjualan shift ini</div></div></div>
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi k3"><div class="ic"><i class="bi bi-receipt"></i></div><div class="v num"><?= $sum['trx'] ?></div><div class="l">Transaksi</div></div></div>
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi k4"><div class="ic"><i class="bi bi-cash-coin"></i></div><div class="v num"><?= rupiah($sum['expected_cash']) ?></div><div class="l">Perkiraan kas di laci</div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6 anim"><div class="card-x card-b"><div class="fw-bold mb-2">Penjualan per metode bayar</div>
    <?php foreach ($sum['payments'] as $p): ?><div class="list-row"><span><?= esc($p['name']) ?></span><b class="ms-auto num"><?= rupiah($p['amount']) ?></b></div><?php endforeach; ?>
    <?php if (! $sum['payments']): ?><div class="text-muted">Belum ada transaksi.</div><?php endif; ?></div></div>
  <div class="col-lg-6 anim"><div class="card-x card-b"><div class="fw-bold mb-2">Selesai bertugas?</div>
    <p class="text-muted">Hitung uang di laci, lalu tutup kasir. Rekap shift dan stok akhir akan tersimpan otomatis.</p>
    <a href="/shift/close?s=<?= (int) $shift['id'] ?>" class="btn btn-primary btn-lg w-100"><i class="bi bi-lock"></i> Tutup Kasir</a></div></div>
</div>
<?php if (! empty($others)): ?>
<div class="card-x card-b mt-3 anim"><div class="fw-bold mb-2"><i class="bi bi-people"></i> Kasir lain yang sedang buka</div>
  <?php foreach ($others as $o): ?><div class="list-row"><span><b><?= esc(ShiftService::label($o)) ?></b> · <?= esc($o['opener']) ?> · sejak <?= tgl($o['opened_at'], 'H:i') ?></span>
    <span class="ms-auto d-flex gap-2"><a class="btn btn-sm btn-soft" href="/shifts/<?= $o['id'] ?>">Detail</a><?php if (auth_user()['role'] === 'admin'): ?><a class="btn btn-sm btn-ghost text-danger" href="/shift/close?s=<?= $o['id'] ?>">Tutup</a><?php endif; ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
