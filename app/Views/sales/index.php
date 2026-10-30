<?= $this->extend('layouts/app') ?>
<?php $title = 'Riwayat Penjualan'; ?>
<?= $this->section('content') ?>
<form class="card-x card-b anim mb-3 d-flex flex-wrap gap-2 align-items-end" method="get">
  <div><label class="form-label">Dari</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
  <div><label class="form-label">Sampai</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
  <div><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua</option><option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Lunas</option><option value="void" <?= $status === 'void' ? 'selected' : '' ?>>Void</option></select></div>
  <button class="btn btn-primary"><i class="bi bi-funnel"></i> Tampilkan</button>
  <div class="ms-auto text-end"><div class="text-muted small">Total penjualan (lunas)</div><b style="font-size:1.3rem"><?= rupiah($sum) ?></b></div>
</form>
<div class="card-x anim"><div class="table-wrap rtw">
<table class="table-x rt"><thead><tr><th>Waktu</th><th>Invoice</th><th>Kasir</th><th>Tipe</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td data-l="Waktu"><?= tgl($r['created_at']) ?></td><td data-l="Invoice"><b><?= esc($r['invoice_no']) ?></b></td><td data-l="Kasir"><?= esc($r['cashier']) ?></td>
<td data-l="Tipe"><?= esc(order_type_label($r['order_type'])) ?><?= $r['table_no'] ? ' · ' . esc($r['table_no']) : '' ?></td>
<td data-l="Total" class="text-end num fw-bold"><?= rupiah($r['total']) ?></td><td data-l="Status"><?= po_badge($r['status']) ?></td>
<td class="act"><a class="btn btn-sm btn-ghost" href="/sales/<?= $r['id'] ?>">Detail</a></td></tr>
<?php endforeach; if (! $rows): ?><tr><td colspan="7"><div class="empty"><i class="bi bi-receipt"></i>Tidak ada transaksi pada periode ini.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
