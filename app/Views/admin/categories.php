<?= $this->extend('layouts/app') ?>
<?php $title = 'Kategori'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cM" data-new="#cM" data-title="Kategori baru"><i class="bi bi-plus-lg"></i> Tambah Kategori</button></div>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>Nama</th><th class="text-end">Jumlah Produk</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-l="Nama"><b><?= esc($r['name']) ?></b></td><td data-l="Produk" class="text-end"><?= $r['n'] ?></td><td class="act text-end">
<button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#cM" data-edit="#cM" data-id="<?= $r['id'] ?>" data-name="<?= esc($r['name']) ?>" data-title="Ubah kategori">Ubah</button>
<form class="d-inline" method="post" action="/admin/categories/<?= $r['id'] ?>/delete" data-confirm="Hapus kategori ini?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost text-danger">Hapus</button></form></td></tr>
<?php endforeach; if (! $rows): ?><tr><td colspan="3"><div class="empty">Belum ada kategori.</div></td></tr><?php endif; ?></tbody></table></div></div>
<div class="modal fade" id="cM" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><form class="modal-content" method="post" action="/admin/categories/save"><?= csrf_field() ?><input type="hidden" name="id">
<div class="modal-header"><h5 class="modal-title">Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
<div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?= $this->endSection() ?>
