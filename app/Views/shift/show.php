<?= $this->extend('layouts/app') ?>
<?php $title = 'Rekap Shift'; use App\Libraries\ShiftService; $open = $shift['status'] === 'open'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/shifts" class="small"><i class="bi bi-arrow-left"></i> Riwayat Shift</a>
<h2><?= esc(ShiftService::LABELS[$shift['shift_no']]) ?> · <?= tgl($shift['shift_date'], 'd M Y') ?> <?= $open ? '<span class="badge-soft badge-success">Sedang buka</span>' : '<span class="badge-soft badge-secondary">Ditutup</span>' ?></h2>
<div class="text-muted"><?= esc($shift['loc_name']) ?> · Dibuka <?= tgl($shift['opened_at']) ?> oleh <?= esc($shift['opener']) ?><?= $shift['closed_at'] ? ' · Ditutup ' . tgl($shift['closed_at']) . ' oleh ' . esc($shift['closer']) : '' ?></div></div>
<div class="d-flex gap-2 no-print"><a class="btn btn-soft" target="_blank" href="/shifts/<?= $shift['id'] ?>/receipt"><i class="bi bi-receipt"></i> Struk Penutupan</a><?php if ($open): ?><a class="btn btn-primary" href="/shift/close"><i class="bi bi-lock"></i> Tutup Kasir</a><?php endif; ?></div></div>

<div class="row g-3 mb-3">
  <div class="col-lg-5 anim"><div class="card-x card-b">
    <div class="fw-bold mb-2">Kas</div>
    <div class="list-row"><span class="text-muted">Uang modal</span><span class="ms-auto num"><?= rupiah($shift['opening_cash']) ?></span></div>
    <div class="list-row"><span class="text-muted">Penjualan tunai</span><span class="ms-auto num"><?= rupiah($sum['cash_sales']) ?></span></div>
    <div class="list-row"><span class="text-muted">Seharusnya di laci</span><span class="ms-auto num"><?= rupiah($shift['expected_cash'] ?? $sum['expected_cash']) ?></span></div>
    <?php if (! $open): $d = (float) $shift['cash_diff']; ?>
      <div class="list-row"><span class="text-muted">Kas hasil hitung</span><span class="ms-auto num"><?= rupiah($shift['closing_cash']) ?></span></div>
      <div class="list-row"><b>Selisih</b><b class="ms-auto num <?= $d < 0 ? 'diff-neg' : 'diff-pos' ?>"><?= $d > 0 ? '+ ' : ($d < 0 ? '- ' : '') ?><?= rupiah(abs($d)) ?><?= $d == 0 ? ' (pas)' : '' ?></b></div>
      <?php if ($shift['closing_note']): ?><div class="small text-muted mt-2">Catatan: <?= esc($shift['closing_note']) ?></div><?php endif; ?>
    <?php endif; ?>
    <?php if ($shift['opening_note']): ?><div class="small text-muted mt-2">Catatan buka: <?= esc($shift['opening_note']) ?></div><?php endif; ?>
  </div></div>
  <div class="col-lg-7 anim"><div class="card-x card-b">
    <div class="fw-bold mb-2">Penjualan — <?= $sum['trx'] ?> transaksi · <span class="num"><?= rupiah($sum['total']) ?></span></div>
    <?php foreach ($sum['payments'] as $p): ?><div class="list-row"><span><?= esc($p['name']) ?></span><span class="ms-auto num"><?= rupiah($p['amount']) ?></span></div><?php endforeach; ?>
    <?php if (! $sum['payments']): ?><div class="text-muted">Belum ada transaksi.</div><?php endif; ?>
    <?php if ($sum['discount'] > 0): ?><div class="list-row"><span class="text-muted">Total diskon</span><span class="ms-auto num"><?= rupiah($sum['discount']) ?></span></div><?php endif; ?>
    <?php if ($sum['void_count']): ?><div class="list-row"><span class="text-danger">Void (<?= $sum['void_count'] ?>)</span><span class="ms-auto num text-danger"><?= rupiah($sum['void_total']) ?></span></div><?php endif; ?>
  </div></div>
</div>

<div class="card-x anim mb-3"><div class="card-h"><span>Stok shift</span><small class="text-muted fw-normal">awal = hasil hitung kasir</small></div>
<div class="table-wrap mt-2"><table class="table-x"><thead><tr><th>Menu</th><th class="text-end">Sistem saat buka</th><th class="text-end">Stok awal</th><th class="text-end">Masuk</th><th class="text-end">Terjual</th><th class="text-end">Penyesuaian</th><th class="text-end"><?= $open ? 'Stok sekarang' : 'Stok akhir' ?></th></tr></thead><tbody>
<?php foreach ($stock as $r): $dd = $r['diff_open']; ?>
<tr><td><b><?= esc($r['name']) ?></b><br><small class="text-muted"><?= esc($r['sku']) ?></small></td>
<td class="text-end text-muted"><?= $r['system_qty'] ?></td>
<td class="text-end fw-bold"><?= $r['opening_qty'] ?><?php if ($dd): ?> <small class="<?= $dd > 0 ? 'diff-pos' : 'diff-neg' ?>">(<?= $dd > 0 ? '+' : '' ?><?= $dd ?>)</small><?php endif; ?></td>
<td class="text-end"><?= $r['in'] ?: '-' ?></td><td class="text-end"><?= $r['sold'] ?: '-' ?></td><td class="text-end"><?= $r['adjust'] ?: '-' ?></td>
<td class="text-end fw-bold"><?= $r['end'] ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>

<div class="card-x anim no-print"><div class="card-h"><span>Transaksi di shift ini</span></div>
<div class="table-wrap mt-2"><table class="table-x"><thead><tr><th>Invoice</th><th>Waktu</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ($sales as $s): ?><tr><td><a href="/sales/<?= $s['id'] ?>"><b><?= esc($s['invoice_no']) ?></b></a></td><td><?= tgl($s['created_at']) ?></td><td class="text-end num"><?= rupiah($s['total']) ?></td><td><?= po_badge($s['status']) ?></td></tr><?php endforeach; ?>
<?php if (! $sales): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada transaksi.</td></tr><?php endif; ?></tbody></table></div></div>
<?= $this->endSection() ?>
