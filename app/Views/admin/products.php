<?= $this->extend('layouts/app') ?>
<?php $title = 'Produk'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><form class="input-icon" style="min-width:min(100%,320px)" method="get"><i class="bi bi-search"></i><input name="q" class="form-control" placeholder="Cari nama / SKU" value="<?= esc($q) ?>"></form>
<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pM" data-new="#pM" data-title="Produk baru"><i class="bi bi-plus-lg"></i> Tambah Produk</button></div>
<?php if (! $cloud): ?><div class="alert-x ok anim"><i class="bi bi-info-circle"></i><span>Cloudinary belum diatur — gambar disimpan lokal (hanya untuk pengembangan). Isi <code>CLOUDINARY_URL</code> untuk produksi.</span></div><?php endif; ?>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>Produk</th><th>Kategori</th><th class="text-end">Harga Jual</th><th class="text-end">HPP Std</th><th class="text-end">Margin</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $mg = $r['price'] > 0 ? round(($r['price'] - $r['hpp']) / $r['price'] * 100) : 0; ?>
<tr><td data-l="Produk"><div class="d-flex align-items-center gap-2 text-start"><?php if ($r['image_url']): ?><img class="thumb" src="<?= esc($r['image_url']) ?>" alt=""><?php else: ?><div class="ph"><?= esc(strtoupper(substr($r['name'], 0, 1))) ?></div><?php endif; ?><div><b><?= esc($r['name']) ?></b><br><small class="text-muted"><?= esc($r['sku']) ?></small></div></div></td>
<td data-l="Kategori"><?= esc($r['category'] ?? '-') ?></td><td data-l="Harga" class="text-end num"><?= rupiah($r['price']) ?></td><td data-l="HPP" class="text-end num"><?= rupiah($r['hpp']) ?></td><td data-l="Margin" class="text-end"><?= $mg ?>%</td>
<td data-l="Status"><?= $r['is_active'] ? '<span class="badge-soft badge-success">Aktif</span>' : '<span class="badge-soft badge-secondary">Nonaktif</span>' ?></td>
<td class="act"><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#pM" data-edit="#pM" data-id="<?= $r['id'] ?>" data-sku="<?= esc($r['sku']) ?>" data-name="<?= esc($r['name']) ?>" data-category_id="<?= $r['category_id'] ?>" data-price="<?= (float) $r['price'] ?>" data-hpp="<?= (float) $r['hpp'] ?>" data-title="Ubah produk">Ubah</button>
<form class="d-inline" method="post" action="/admin/products/<?= $r['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr>
<?php endforeach; if (! $rows): ?><tr><td colspan="7"><div class="empty"><i class="bi bi-egg-fried"></i>Belum ada produk.</div></td></tr><?php endif; ?></tbody></table></div></div>
<div class="modal fade" id="pM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="/admin/products/save" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title">Produk</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-5"><label class="form-label">SKU</label><input name="sku" class="form-control text-uppercase" required></div>
    <div class="col-7"><label class="form-label">Kategori</label><select name="category_id" class="form-select"><option value="">—</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Nama menu</label><input name="name" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Harga jual</label><input name="price" type="number" min="0" step="any" class="form-control" required data-default="0"></div>
    <div class="col-6"><label class="form-label">HPP standar</label><input name="hpp" type="number" min="0" step="any" class="form-control" required data-default="0"></div>
    <div class="col-12"><label class="form-label">Foto (opsional, diunggah ke Cloudinary)</label><input name="image" type="file" accept="image/*" class="form-control"></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?= $this->endSection() ?>
