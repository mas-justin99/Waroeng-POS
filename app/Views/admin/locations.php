<?= $this->extend('layouts/app') ?>
<?php $title = 'Lokasi Resto'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div class="text-muted">Setiap lokasi punya stok, PO, kasir, dan pajak sendiri.</div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#lM" data-new="#lM" data-title="Lokasi baru"><i class="bi bi-plus-lg"></i> Tambah Lokasi</button></div>
<div class="row g-3">
<?php foreach ($rows as $r): ?><div class="col-md-6 col-xl-4 anim"><div class="card-x card-b h-100 <?= $r['is_active'] ? '' : 'opacity-50' ?>">
  <div class="d-flex justify-content-between"><div class="ph"><i class="bi bi-shop"></i></div><span class="badge-soft badge-primary"><?= esc($r['code']) ?></span></div>
  <h5 class="mt-3 mb-1"><?= esc($r['name']) ?></h5><div class="text-muted small mb-3"><?= esc($r['address'] ?: '-') ?><?= $r['phone'] ? ' · ' . esc($r['phone']) : '' ?></div>
  <div class="d-flex gap-3 small mb-3"><span>Pajak <b><?= (float) $r['tax_percent'] ?>%</b></span><span>Service <b><?= (float) $r['service_percent'] ?>%</b></span></div>
  <div class="d-flex gap-2"><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#lM" data-edit="#lM" data-id="<?= $r['id'] ?>" data-code="<?= esc($r['code']) ?>" data-name="<?= esc($r['name']) ?>" data-address="<?= esc($r['address']) ?>" data-phone="<?= esc($r['phone']) ?>" data-tax_percent="<?= (float) $r['tax_percent'] ?>" data-service_percent="<?= (float) $r['service_percent'] ?>" data-receipt_footer="<?= esc($r['receipt_footer']) ?>" data-title="Ubah lokasi">Ubah</button>
  <form method="post" action="/admin/locations/<?= $r['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></div></div></div><?php endforeach; ?>
</div>
<div class="modal fade" id="lM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="/admin/locations/save"><?= csrf_field() ?><input type="hidden" name="id">
<div class="modal-header"><h5 class="modal-title">Lokasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-4"><label class="form-label">Kode</label><input name="code" class="form-control text-uppercase" maxlength="10" required></div><div class="col-8"><label class="form-label">Nama outlet</label><input name="name" class="form-control" required></div>
<div class="col-12"><label class="form-label">Alamat</label><input name="address" class="form-control"></div><div class="col-12"><label class="form-label">Telepon</label><input name="phone" class="form-control"></div>
<div class="col-6"><label class="form-label">Pajak (%)</label><input name="tax_percent" type="number" step="0.01" min="0" class="form-control" data-default="0"></div><div class="col-6"><label class="form-label">Service (%)</label><input name="service_percent" type="number" step="0.01" min="0" class="form-control" data-default="0"></div>
<div class="col-12"><label class="form-label">Footer struk</label><input name="receipt_footer" class="form-control" data-default="Terima kasih"></div></div></div>
<div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?= $this->endSection() ?>
