<?= $this->extend('layouts/app') ?>
<?php $title = 'Stok Warung'; $canAdj = has_role('cashier'); ?>
<?= $this->section('content') ?>
<div class="page-head anim">
  <div class="seg"><a href="/stock" class="<?= $tab === 'stock' ? 'on' : '' ?>">Stok Saat Ini</a><a href="/stock?tab=moves" class="<?= $tab === 'moves' ? 'on' : '' ?>">Mutasi Stok</a></div>
  <?php if ($tab === 'stock'): ?><div class="input-icon" style="min-width:min(100%,280px)"><i class="bi bi-search"></i><input id="fs" class="form-control" placeholder="Cari menu / SKU"></div><?php endif; ?>
</div>
<?php if ($tab === 'stock'): $val = 0; foreach ($rows as $r) $val += max(0, $r['qty']) * $r['avg_cost']; ?>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt" id="st"><thead><tr><th>Menu</th><th>Kategori</th><th class="text-end">Stok</th><th class="text-end">Min</th><th class="text-end">HPP Rata-rata</th><th class="text-end">Nilai</th><?php if ($canAdj): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php foreach ($rows as $r): $low = $r['qty'] <= $r['min_qty']; ?>
<tr data-s="<?= esc(strtolower($r['name'] . ' ' . $r['sku'])) ?>"><td data-l="Menu"><div class="d-flex align-items-center gap-2 text-start"><?php if ($r['image_url']): ?><img class="thumb" src="<?= esc($r['image_url']) ?>" alt=""><?php else: ?><div class="ph"><?= esc(strtoupper(substr($r['name'], 0, 1))) ?></div><?php endif; ?><div><b><?= esc($r['name']) ?></b><br><small class="text-muted"><?= esc($r['sku']) ?></small></div></div></td>
<td data-l="Kategori"><?= esc($r['category'] ?? '-') ?></td>
<td data-l="Stok" class="text-end"><span class="badge-soft <?= $r['qty'] <= 0 ? 'badge-danger' : ($low ? 'badge-warning' : 'badge-success') ?>"><?= $r['qty'] ?></span></td>
<td data-l="Min" class="text-end"><?= $r['min_qty'] ?></td><td data-l="HPP" class="text-end num"><?= rupiah($r['avg_cost']) ?></td><td data-l="Nilai" class="text-end num"><?= rupiah(max(0, $r['qty']) * $r['avg_cost']) ?></td>
<?php if ($canAdj): ?><td class="act"><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#adjM" data-edit="#adjM" data-product_id="<?= $r['id'] ?>" data-new_qty="<?= $r['qty'] ?>" data-min_qty="<?= $r['min_qty'] ?>" data-title="Opname: <?= esc($r['name']) ?>">Opname</button></td><?php endif; ?></tr>
<?php endforeach; ?><tr><td colspan="5" class="text-end fw-bold">Total nilai persediaan</td><td class="text-end num fw-bold"><?= rupiah($val) ?></td><?php if ($canAdj): ?><td></td><?php endif; ?></tr></tbody></table></div></div>
<?php if ($canAdj): ?>
<div class="modal fade" id="adjM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="/stock/adjust"><?= csrf_field() ?><input type="hidden" name="product_id">
  <div class="modal-header"><h5 class="modal-title">Opname stok</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3"><div class="col-6"><label class="form-label">Stok sebenarnya</label><input name="new_qty" type="number" min="0" class="form-control" required></div><div class="col-6"><label class="form-label">Batas stok menipis</label><input name="min_qty" type="number" min="0" class="form-control" required></div>
  <div class="col-12"><label class="form-label">Catatan</label><input name="note" class="form-control" placeholder="mis. selisih hitung, basi" data-default=""></div></div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?php endif; ?>
<?php else: ?>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>Waktu</th><th>Menu</th><th>Jenis</th><th class="text-end">Qty</th><th class="text-end">Saldo</th><th>Ref / Catatan</th><th>User</th></tr></thead><tbody>
<?php $lbl = ['receipt' => ['Penerimaan', 'success'], 'sale' => ['Penjualan', 'primary'], 'void' => ['Void', 'danger'], 'adjust' => ['Opname', 'info']];
foreach ($moves as $m): [$l, $c] = $lbl[$m['type']] ?? [$m['type'], 'secondary']; ?>
<tr><td data-l="Waktu"><?= tgl($m['created_at']) ?></td><td data-l="Menu"><b><?= esc($m['name']) ?></b></td><td data-l="Jenis"><span class="badge-soft badge-<?= $c ?>"><?= $l ?></span></td>
<td data-l="Qty" class="text-end fw-bold <?= $m['qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['qty'] > 0 ? '+' : '' ?><?= $m['qty'] ?></td><td data-l="Saldo" class="text-end"><?= $m['balance'] ?></td><td data-l="Ref"><?= esc($m['note']) ?></td><td data-l="User"><?= esc($m['user_name']) ?></td></tr>
<?php endforeach; if (! $moves): ?><tr><td colspan="7"><div class="empty">Belum ada mutasi.</div></td></tr><?php endif; ?></tbody></table></div></div>
<?php endif; ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const fs=document.getElementById('fs'); if(fs) fs.addEventListener('input',e=>{const q=e.target.value.toLowerCase(); document.querySelectorAll('#st tbody tr[data-s]').forEach(r=>r.style.display=r.dataset.s.includes(q)?'':'none');});
</script>
<?= $this->endSection() ?>
