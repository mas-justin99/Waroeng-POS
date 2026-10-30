<?= $this->extend('layouts/app') ?>
<?php $title = 'Pesanan Pembelian'; ?>
<?= $this->section('content') ?>
<div class="page-head anim">
  <div class="seg">
    <?php foreach ([''=>'Semua','sent'=>'Menunggu','partial'=>'Sebagian','received'=>'Selesai','cancelled'=>'Batal'] as $k=>$l): ?>
      <a href="/po<?= $k ? '?status=' . $k : '' ?>" class="<?= ($status ?? '') === $k ? 'on' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (has_role('kitchen')): ?><a class="btn btn-primary" href="/po/create"><i class="bi bi-plus-lg"></i> Buat Pesanan Pembelian</a><?php endif; ?>
</div>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>No. PO</th><th>Tanggal</th><th>Dibuat oleh</th><th class="text-end">Total HPP</th><th style="min-width:140px">Diterima</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $pct = $r['q_total'] ? round($r['q_recv'] / $r['q_total'] * 100) : 0; ?>
<tr><td data-l="No. PO"><b><?= esc($r['po_number']) ?></b></td><td data-l="Tanggal"><?= tgl($r['created_at']) ?></td><td data-l="Dibuat oleh"><?= esc($r['creator']) ?></td>
<td data-l="Total HPP" class="text-end num"><?= rupiah($r['total_hpp']) ?></td>
<td data-l="Diterima"><div class="progress-x"><i style="width:<?= $pct ?>%"></i></div><small class="text-muted"><?= $r['q_recv'] ?>/<?= $r['q_total'] ?> porsi</small></td>
<td data-l="Status"><?= po_badge($r['status']) ?></td>
<td class="act"><a class="btn btn-sm btn-ghost" href="/po/<?= $r['id'] ?>">Detail</a>
<?php if (has_role('cashier') && in_array($r['status'], ['sent', 'partial'], true) && auth_user()['role'] !== 'kitchen'): ?> <a class="btn btn-sm btn-primary" href="/receiving/receive/<?= $r['id'] ?>">Terima</a><?php endif; ?></td></tr>
<?php endforeach; if (! $rows): ?><tr><td colspan="7"><div class="empty"><i class="bi bi-clipboard2"></i>Belum ada pesanan pembelian.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
