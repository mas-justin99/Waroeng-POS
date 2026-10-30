<?= $this->extend('layouts/app') ?>
<?php $title = 'Metode Pembayaran'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div class="text-muted">Aktifkan metode yang dipakai kasir. Pembayaran bisa dipecah (split) beberapa metode.</div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mM" data-new="#mM" data-title="Metode baru"><i class="bi bi-plus-lg"></i> Tambah Metode</button></div>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>Nama</th><th>Tipe</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-l="Nama"><b><?= esc($r['name']) ?></b> <small class="text-muted"><?= esc($r['code']) ?></small></td><td data-l="Tipe"><?= esc($types[$r['type']] ?? $r['type']) ?></td>
<td data-l="Status"><?= $r['is_active'] ? '<span class="badge-soft badge-success">Aktif</span>' : '<span class="badge-soft badge-secondary">Nonaktif</span>' ?></td>
<td class="act text-end"><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#mM" data-edit="#mM" data-id="<?= $r['id'] ?>" data-name="<?= esc($r['name']) ?>" data-type="<?= $r['type'] ?>" data-title="Ubah metode">Ubah</button>
<form class="d-inline" method="post" action="/admin/payment-methods/<?= $r['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="modal fade" id="mM" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><form class="modal-content" method="post" action="/admin/payment-methods/save"><?= csrf_field() ?><input type="hidden" name="id">
<div class="modal-header"><h5 class="modal-title">Metode</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
<label class="form-label">Tipe</label><select name="type" class="form-select" data-default="other"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
<div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?= $this->endSection() ?>
