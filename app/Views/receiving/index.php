<?= $this->extend('layouts/app') ?>
<?php $title = 'Penerimaan Pembelian'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><h2>Menunggu Diterima <span class="badge-soft badge-warning"><?= count($pending) ?></span></h2><div class="text-muted">Cocokkan kiriman dari dapur, lalu terima agar masuk stok warung.</div></div></div>
<div class="row g-3 mb-4">
<?php foreach ($pending as $r): $pct = $r['q_total'] ? round($r['q_recv'] / $r['q_total'] * 100) : 0; ?>
  <div class="col-md-6 col-xl-4 anim"><div class="card-x card-b h-100">
    <div class="d-flex justify-content-between align-items-start"><div><b><?= esc($r['po_number']) ?></b><br><small class="text-muted"><?= tgl($r['created_at']) ?> · <?= esc($r['creator']) ?></small></div><?= po_badge($r['status']) ?></div>
    <div class="progress-x my-3"><i style="width:<?= $pct ?>%"></i></div>
    <div class="d-flex justify-content-between text-muted small mb-3"><span><?= $r['q_recv'] ?>/<?= $r['q_total'] ?> porsi</span><span>HPP <?= rupiah($r['total_hpp']) ?></span></div>
    <a class="btn btn-primary w-100" href="/receiving/receive/<?= $r['id'] ?>"><i class="bi bi-box-arrow-in-down"></i> Terima Barang</a>
  </div></div>
<?php endforeach; if (! $pending): ?><div class="col-12"><div class="card-x empty"><i class="bi bi-check2-circle"></i>Semua kiriman dapur sudah diterima.</div></div><?php endif; ?>
</div>
<h5 class="mb-2 anim">Riwayat Penerimaan</h5>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>No. Penerimaan</th><th>PO</th><th>Waktu</th><th>Penerima</th><th class="text-end">Porsi</th><th></th></tr></thead><tbody>
<?php foreach ($history as $g): ?><tr><td data-l="No."><b><?= esc($g['gr_number']) ?></b></td><td data-l="PO"><?= esc($g['po_number']) ?></td><td data-l="Waktu"><?= tgl($g['created_at']) ?></td><td data-l="Penerima"><?= esc($g['receiver']) ?></td><td data-l="Porsi" class="text-end"><?= $g['q'] ?></td><td class="act"><a class="btn btn-sm btn-ghost" href="/receiving/<?= $g['id'] ?>">Detail</a></td></tr>
<?php endforeach; if (! $history): ?><tr><td colspan="6"><div class="empty">Belum ada penerimaan.</div></td></tr><?php endif; ?></tbody></table></div></div>
<?= $this->endSection() ?>
