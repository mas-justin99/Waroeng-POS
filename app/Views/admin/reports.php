<?= $this->extend('layouts/app') ?>
<?php $title = 'Laporan'; $locs = locations_all(); $q = http_build_query(['type' => $type, 'from' => $from, 'to' => $to, 'loc' => $loc]); ?>
<?= $this->section('content') ?>
<form method="get" class="card-x card-b anim mb-3 d-flex flex-wrap gap-2 align-items-end">
  <div><label class="form-label">Laporan</label><select name="type" class="form-select"><option value="sales" <?= $type === 'sales' ? 'selected' : '' ?>>Penjualan</option><option value="products" <?= $type === 'products' ? 'selected' : '' ?>>Per Produk</option><option value="po" <?= $type === 'po' ? 'selected' : '' ?>>Pesanan Pembelian</option></select></div>
  <div><label class="form-label">Lokasi</label><select name="loc" class="form-select"><option value="all">Semua</option><?php foreach ($locs as $l): ?><option value="<?= $l['id'] ?>" <?= (string) $loc === (string) $l['id'] ? 'selected' : '' ?>><?= esc($l['name']) ?></option><?php endforeach; ?></select></div>
  <div><label class="form-label">Dari</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
  <div><label class="form-label">Sampai</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
  <button class="btn btn-primary"><i class="bi bi-funnel"></i> Tampilkan</button>
  <a class="btn btn-soft ms-auto" href="?<?= $q ?>&export=csv"><i class="bi bi-download"></i> Unduh CSV</a>
</form>
<div class="card-x anim"><div class="table-wrap"><table class="table-x"><thead><tr><?php foreach ($head as $h): ?><th><?= esc($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $i => $c): ?><td class="<?= is_numeric($c) && $i > 1 ? 'text-end num' : '' ?>"><?= is_numeric($c) && abs((float) $c) >= 1000 ? rupiah($c, false) : esc($c) ?></td><?php endforeach; ?></tr>
<?php endforeach; if (! $rows): ?><tr><td colspan="<?= count($head) ?>"><div class="empty"><i class="bi bi-bar-chart"></i>Tidak ada data pada periode ini.</div></td></tr><?php endif; ?></tbody></table></div></div>
<?= $this->endSection() ?>
