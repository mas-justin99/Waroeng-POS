<?= $this->extend('layouts/app') ?>
<?php $title = 'Detail Transaksi'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/sales" class="small"><i class="bi bi-arrow-left"></i> Riwayat</a><h2><?= esc($sale['invoice_no']) ?> <?= po_badge($sale['status']) ?></h2></div>
  <div class="d-flex gap-2"><a class="btn btn-soft" target="_blank" href="/pos/receipt/<?= $sale['id'] ?>?print=1"><i class="bi bi-printer"></i> Cetak Struk</a>
  <?php if ($sale['status'] === 'paid' && auth_user()['role'] === 'admin'): ?><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidM"><i class="bi bi-x-octagon"></i> Void</button><?php endif; ?></div></div>
<div class="row g-3">
  <div class="col-lg-8 anim"><div class="card-x"><div class="table-wrap"><table class="table-x"><thead><tr><th>Item</th><th class="text-end">Harga</th><th class="text-end">Qty</th><th class="text-end">Subtotal</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><b><?= esc($i['name']) ?></b><br><small class="text-muted"><?= esc($i['sku']) ?></small></td><td class="text-end num"><?= rupiah($i['price']) ?></td><td class="text-end"><?= $i['qty'] ?></td><td class="text-end num fw-bold"><?= rupiah($i['subtotal']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-4 anim"><div class="card-x card-b">
    <div class="list-row"><span class="text-muted">Waktu</span><span class="ms-auto"><?= tgl($sale['created_at']) ?></span></div>
    <div class="list-row"><span class="text-muted">Kasir</span><span class="ms-auto"><?= esc($sale['cashier']) ?></span></div>
    <div class="list-row"><span class="text-muted">Tipe</span><span class="ms-auto"><?= esc(order_type_label($sale['order_type'])) ?><?= $sale['table_no'] ? ' · ' . esc($sale['table_no']) : '' ?></span></div>
    <?php if ($sale['customer_name']): ?><div class="list-row"><span class="text-muted">Pelanggan</span><span class="ms-auto"><?= esc($sale['customer_name']) ?></span></div><?php endif; ?>
    <div class="list-row"><span class="text-muted">Subtotal</span><span class="ms-auto num"><?= rupiah($sale['subtotal']) ?></span></div>
    <div class="list-row"><span class="text-muted">Diskon</span><span class="ms-auto num">- <?= rupiah($sale['discount']) ?></span></div>
    <div class="list-row"><span class="text-muted">Service + Pajak</span><span class="ms-auto num"><?= rupiah($sale['service'] + $sale['tax']) ?></span></div>
    <div class="list-row"><b>Total</b><b class="ms-auto num" style="font-size:1.2rem"><?= rupiah($sale['total']) ?></b></div>
    <div class="mt-3 mb-1 fw-bold">Pembayaran</div>
    <?php foreach ($pays as $p): ?><div class="list-row"><span><?= esc($p['method_name']) ?><?= $p['reference'] ? ' <small class="text-muted">(' . esc($p['reference']) . ')</small>' : '' ?></span><span class="ms-auto num"><?= rupiah($p['amount']) ?></span></div><?php endforeach; ?>
    <?php if ($sale['change_amount'] > 0): ?><div class="list-row"><span class="text-muted">Kembalian</span><span class="ms-auto num"><?= rupiah($sale['change_amount']) ?></span></div><?php endif; ?>
    <?php if ($sale['status'] === 'void'): ?><div class="alert-x err mt-3 mb-0"><i class="bi bi-x-octagon-fill"></i><span>Void: <?= esc($sale['void_reason']) ?></span></div><?php endif; ?>
  </div></div>
</div>
<?php if ($sale['status'] === 'paid' && auth_user()['role'] === 'admin'): ?>
<div class="modal fade" id="voidM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="/sales/<?= $sale['id'] ?>/void"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">Void transaksi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p class="text-muted">Stok item akan dikembalikan. Tindakan ini tidak bisa dibatalkan.</p><label class="form-label">Alasan</label><input name="reason" class="form-control" required></div>
  <div class="modal-footer"><button class="btn btn-danger w-100">Void Transaksi</button></div></form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>
