<?= $this->extend('layouts/app') ?>
<?php $title = 'Detail Penerimaan'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/receiving" class="small"><i class="bi bi-arrow-left"></i> Penerimaan</a><h2><?= esc($gr['gr_number']) ?></h2><div class="text-muted">PO <?= esc($gr['po_number']) ?> · <?= tgl($gr['created_at']) ?> · diterima <?= esc($gr['receiver']) ?></div></div></div>
<?php if ($gr['notes']): ?><div class="alert-x ok anim"><i class="bi bi-chat-left-text"></i><span><?= esc($gr['notes']) ?></span></div><?php endif; ?>
<div class="card-x anim"><div class="table-wrap"><table class="table-x"><thead><tr><th>SKU</th><th>Menu</th><th class="text-end">HPP</th><th class="text-end">Qty Diterima</th><th class="text-end">Nilai</th></tr></thead><tbody>
<?php $t = 0; foreach ($items as $i): $t += $i['qty'] * $i['hpp']; ?><tr><td><b><?= esc($i['sku']) ?></b></td><td><?= esc($i['name']) ?></td><td class="text-end num"><?= rupiah($i['hpp']) ?></td><td class="text-end"><?= $i['qty'] ?></td><td class="text-end num"><?= rupiah($i['qty'] * $i['hpp']) ?></td></tr><?php endforeach; ?>
<tr><td colspan="4" class="text-end fw-bold">Total nilai</td><td class="text-end num fw-bold"><?= rupiah($t) ?></td></tr></tbody></table></div></div>
<?= $this->endSection() ?>
