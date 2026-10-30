<?= $this->extend('layouts/app') ?>
<?php $title = 'Terima Barang'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/receiving" class="small"><i class="bi bi-arrow-left"></i> Penerimaan</a><h2>Terima <?= esc($po['po_number']) ?> <?= po_badge($po['status']) ?></h2>
<div class="text-muted">Isi qty yang benar-benar diterima. Boleh sebagian — sisanya bisa diterima nanti.</div></div>
<button type="button" class="btn btn-soft" id="all"><i class="bi bi-check-all"></i> Terima Semua Sisa</button></div>
<form method="post" action="/receiving/receive/<?= $po['id'] ?>">
<?= csrf_field() ?>
<div class="card-x anim mb-3"><div class="table-wrap"><table class="table-x"><thead><tr><th>SKU / Menu</th><th class="text-end">HPP</th><th class="text-end">Dipesan</th><th class="text-end">Sudah Diterima</th><th class="text-end">Sisa</th><th class="text-center">Terima Sekarang</th></tr></thead><tbody>
<?php foreach ($items as $i): $rem = $i['qty'] - $i['qty_received']; ?>
<tr><td><b><?= esc($i['sku']) ?></b><br><small class="text-muted"><?= esc($i['name']) ?></small></td><td class="text-end num"><?= rupiah($i['hpp']) ?></td><td class="text-end"><?= $i['qty'] ?></td><td class="text-end"><?= $i['qty_received'] ?></td><td class="text-end fw-bold"><?= $rem ?></td>
<td class="text-center"><?php if ($rem > 0): ?><div class="stepper"><button type="button" data-d="-1">−</button><input name="qty[<?= $i['id'] ?>]" data-max="<?= $rem ?>" value="<?= old('qty.' . $i['id'], $rem) ?>" inputmode="numeric"><button type="button" data-d="1">+</button></div><?php else: ?><span class="badge-soft badge-success">Lengkap</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<div class="card-x card-b anim"><label class="form-label">Catatan penerimaan (mis. selisih, rusak)</label><input name="notes" class="form-control mb-3" maxlength="250" value="<?= esc(old('notes')) ?>">
<button class="btn btn-primary btn-lg w-100"><i class="bi bi-check2-circle"></i> Konfirmasi Penerimaan & Tambah Stok</button></div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.stepper').forEach(s=>{ const i=s.querySelector('input'); const max=+i.dataset.max;
  const set=v=>{ i.value=Math.min(max,Math.max(0,parseInt(v)||0)); };
  s.querySelectorAll('button').forEach(b=>b.addEventListener('click',()=>set((+i.value||0)+ +b.dataset.d))); i.addEventListener('change',()=>set(i.value)); });
document.getElementById('all').addEventListener('click',()=>document.querySelectorAll('.stepper input').forEach(i=>i.value=i.dataset.max));
</script>
<?= $this->endSection() ?>
