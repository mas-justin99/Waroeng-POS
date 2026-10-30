<?= $this->extend('layouts/app') ?>
<?php $title = 'Detail PO'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/po" class="small"><i class="bi bi-arrow-left"></i> Pesanan Pembelian</a><h2><?= esc($po['po_number']) ?> <?= po_badge($po['status']) ?></h2>
<div class="text-muted">Dibuat <?= tgl($po['created_at']) ?> oleh <?= esc($po['creator']) ?></div></div>
<div class="d-flex gap-2">
<?php if (in_array($po['status'], ['sent', 'partial'], true) && in_array(auth_user()['role'], ['cashier', 'admin'], true)): ?><a class="btn btn-primary" href="/receiving/receive/<?= $po['id'] ?>"><i class="bi bi-box-arrow-in-down"></i> Terima Barang</a><?php endif; ?>
<?php if ($po['status'] === 'sent' && in_array(auth_user()['role'], ['kitchen', 'admin'], true)): ?>
<form method="post" action="/po/<?= $po['id'] ?>/cancel" data-confirm="Batalkan PO ini?"><?= csrf_field() ?><button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Batalkan</button></form><?php endif; ?></div></div>
<?php if ($po['notes']): ?><div class="alert-x ok anim"><i class="bi bi-chat-left-text"></i><span><?= esc($po['notes']) ?></span></div><?php endif; ?>
<div class="card-x anim mb-3"><div class="table-wrap"><table class="table-x"><thead><tr><th>SKU</th><th>Menu</th><th class="text-end">HPP</th><th class="text-end">Dipesan</th><th class="text-end">Diterima</th><th class="text-end">Subtotal HPP</th></tr></thead><tbody>
<?php foreach ($items as $i): ?><tr><td><b><?= esc($i['sku']) ?></b></td><td><?= esc($i['name']) ?></td><td class="text-end num"><?= rupiah($i['hpp']) ?></td><td class="text-end"><?= $i['qty'] ?></td>
<td class="text-end"><span class="badge-soft <?= $i['qty_received'] >= $i['qty'] ? 'badge-success' : 'badge-warning' ?>"><?= $i['qty_received'] ?></span></td><td class="text-end num fw-bold"><?= rupiah($i['hpp'] * $i['qty']) ?></td></tr><?php endforeach; ?>
<tr><td colspan="5" class="text-end fw-bold">Total HPP</td><td class="text-end num fw-bold" style="font-size:1.1rem"><?= rupiah($po['total_hpp']) ?></td></tr></tbody></table></div></div>
<?php if ($receipts): ?><div class="card-x card-b anim"><b>Riwayat Penerimaan</b>
<?php foreach ($receipts as $g): ?><div class="list-row"><i class="bi bi-box-seam text-primary"></i><div><a href="/receiving/<?= $g['id'] ?>"><b><?= esc($g['gr_number']) ?></b></a><br><small class="text-muted"><?= tgl($g['created_at']) ?> · <?= esc($g['receiver']) ?></small></div></div><?php endforeach; ?></div><?php endif; ?>
<?= $this->endSection() ?>
