<?php helper('app'); use App\Libraries\ShiftService; $open = $shift['status'] === 'open';
$cashCount = $open ? null : (float) $shift['closing_cash']; $exp = $open ? $sum['expected_cash'] : (float) $shift['expected_cash']; $diff = $open ? null : (float) $shift['cash_diff'];
$left = array_values(array_filter($stock, fn ($r) => $r['end'] > 0)); $empty = array_values(array_filter($stock, fn ($r) => $r['end'] <= 0)); ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Penutupan <?= esc(ShiftService::LABELS[$shift['shift_no']]) ?> <?= esc($shift['shift_date']) ?></title>
<link href="<?= base_url('assets/css/app.css') ?>?v=5" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head><body style="background:#eee">
<div class="receipt">
  <div class="c">
    <img src="<?= base_url('assets/img/logo.png') ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/img/logo.svg') ?>'" alt="">
    <b style="font-size:13px">WAROENG MBOK RENEO</b><br><?= esc($loc['name'] ?? '') ?><br>
    <b>STRUK PENUTUPAN KASIR</b><?php if ($open): ?><br>** PRATINJAU - SHIFT BELUM DITUTUP **<?php endif; ?>
  </div>
  <hr>
  <div class="r"><span>Shift</span><span><?= esc(ShiftService::LABELS[$shift['shift_no']]) ?></span></div>
  <div class="r"><span>Tanggal</span><span><?= tgl($shift['shift_date'], 'd/m/Y') ?></span></div>
  <div class="r"><span>Dibuka</span><span><?= tgl($shift['opened_at'], 'H:i') ?> <?= esc($shift['opener'] ?? '') ?></span></div>
  <div class="r"><span>Ditutup</span><span><?= $shift['closed_at'] ? tgl($shift['closed_at'], 'H:i') . ' ' . esc($shift['closer'] ?? '') : '-' ?></span></div>
  <hr>
  <div class="c"><b>PENJUALAN</b></div>
  <div class="r"><span>Jumlah transaksi</span><span><?= $sum['trx'] ?></span></div>
  <div class="r"><span>Total porsi terjual</span><span><?= $sum['qty_sold'] ?></span></div>
  <div class="r"><span>Subtotal</span><span><?= rupiah($sum['subtotal'], false) ?></span></div>
  <?php if ($sum['discount'] > 0): ?><div class="r"><span>Diskon</span><span>-<?= rupiah($sum['discount'], false) ?></span></div><?php endif; ?>
  <?php if ($sum['service'] > 0): ?><div class="r"><span>Service</span><span><?= rupiah($sum['service'], false) ?></span></div><?php endif; ?>
  <?php if ($sum['tax'] > 0): ?><div class="r"><span>Pajak</span><span><?= rupiah($sum['tax'], false) ?></span></div><?php endif; ?>
  <div class="r" style="font-weight:700;font-size:13px"><span>OMSET</span><span><?= rupiah($sum['total'], false) ?></span></div>
  <?php if ($sum['void_count']): ?><div class="r"><span>Void (<?= $sum['void_count'] ?>)</span><span><?= rupiah($sum['void_total'], false) ?></span></div><?php endif; ?>
  <hr>
  <div class="c"><b>METODE PEMBAYARAN</b></div>
  <?php foreach ($sum['payments'] as $p): ?><div class="r"><span><?= esc($p['name']) ?></span><span><?= rupiah($p['amount'], false) ?></span></div><?php endforeach; ?>
  <?php if (! $sum['payments']): ?><div class="c">- belum ada -</div><?php endif; ?>
  <hr>
  <div class="c"><b>UANG KASIR</b></div>
  <div class="r"><span>Modal awal</span><span><?= rupiah($shift['opening_cash'], false) ?></span></div>
  <div class="r"><span>Penjualan tunai</span><span><?= rupiah($sum['cash_sales'], false) ?></span></div>
  <div class="r"><span>Seharusnya di laci</span><span><?= rupiah($exp, false) ?></span></div>
  <?php if (! $open): ?>
    <div class="r"><span>Uang hasil hitung</span><span><?= rupiah($cashCount, false) ?></span></div>
    <div class="r" style="font-weight:700"><span>SELISIH</span><span><?= $diff > 0 ? '+' : ($diff < 0 ? '-' : '') ?><?= rupiah(abs($diff), false) ?><?= $diff == 0 ? ' (pas)' : ($diff > 0 ? ' (lebih)' : ' (kurang)') ?></span></div>
    <?php if ($shift['closing_note']): ?><div>Cat: <?= esc($shift['closing_note']) ?></div><?php endif; ?>
  <?php endif; ?>
  <hr>
  <div class="c"><b>MENU TERJUAL</b></div>
  <?php foreach ($items as $i): ?><div><?= esc($i['name']) ?></div><div class="r"><span><?= $i['qty'] ?> porsi</span><span><?= rupiah($i['total'], false) ?></span></div><?php endforeach; ?>
  <?php if (! $items): ?><div class="c">- belum ada -</div><?php endif; ?>
  <hr>
  <div class="c"><b>SISA STOK<?= $open ? ' (SAAT INI)' : ' AKHIR' ?></b></div>
  <?php foreach ($left as $r): ?><div class="r"><span><?= esc($r['name']) ?></span><span><?= $r['end'] ?></span></div><?php endforeach; ?>
  <?php if ($empty): ?><div style="margin-top:4px">Habis: <?= esc(implode(', ', array_column($empty, 'name'))) ?></div><?php endif; ?>
  <?php if (! $stock): ?><div class="c">-</div><?php endif; ?>
  <hr>
  <div class="r"><span>Stok awal shift (total)</span><span><?= array_sum(array_column($stock, 'opening_qty')) ?></span></div>
  <div class="r"><span>Stok masuk</span><span><?= array_sum(array_column($stock, 'in')) ?></span></div>
  <div class="r"><span>Stok akhir (total)</span><span><?= array_sum(array_column($stock, 'end')) ?></span></div>
  <hr>
  <div class="r" style="margin-top:22px"><span class="c" style="flex:1;border-top:1px solid #000;padding-top:3px">Kasir</span><span style="width:16px"></span><span class="c" style="flex:1;border-top:1px solid #000;padding-top:3px">Pemilik/Manajer</span></div>
  <div class="c" style="margin-top:8px">Dicetak <?= date('d/m/Y H:i') ?></div>
</div>
<div class="no-print text-center pb-4"><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Struk</button>
  <a class="btn btn-ghost" href="/shifts/<?= $shift['id'] ?>">Rekap Lengkap</a> <a class="btn btn-ghost" href="/shift">Selesai</a></div>
<?php if (service('request')->getGet('print')): ?><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),400))</script><?php endif; ?>
</body></html>
