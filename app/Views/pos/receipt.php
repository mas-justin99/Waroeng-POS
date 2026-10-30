<?php helper('app'); ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($sale['invoice_no']) ?></title>
<link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head><body style="background:#eee">
<div class="receipt">
  <div class="c">
    <img src="<?= base_url('assets/img/logo.png') ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/img/logo.svg') ?>'" alt="">
    <b style="font-size:14px">WAROENG MBOK RENEO</b><br><?= esc($loc['name'] ?? '') ?><br>
    <?php if (! empty($loc['address'])): ?><?= esc($loc['address']) ?><br><?php endif; ?>
    <?php if (! empty($loc['phone'])): ?>Telp. <?= esc($loc['phone']) ?><br><?php endif; ?>
  </div>
  <hr>
  <div class="r"><span>No</span><span><?= esc($sale['invoice_no']) ?></span></div>
  <div class="r"><span>Tanggal</span><span><?= tgl($sale['created_at'], 'd/m/Y H:i') ?></span></div>
  <div class="r"><span>Kasir</span><span><?= esc($sale['cashier'] ?? '-') ?></span></div>
  <div class="r"><span>Tipe</span><span><?= esc(order_type_label($sale['order_type'])) ?><?= $sale['table_no'] ? ' / ' . esc($sale['table_no']) : '' ?></span></div>
  <?php if ($sale['customer_name']): ?><div class="r"><span>Pelanggan</span><span><?= esc($sale['customer_name']) ?></span></div><?php endif; ?>
  <hr>
  <?php foreach ($items as $i): ?>
    <div><?= esc($i['name']) ?></div>
    <div class="r"><span><?= $i['qty'] ?> x <?= rupiah($i['price'], false) ?></span><span><?= rupiah($i['subtotal'], false) ?></span></div>
  <?php endforeach; ?>
  <hr>
  <div class="r"><span>Subtotal</span><span><?= rupiah($sale['subtotal'], false) ?></span></div>
  <?php if ($sale['discount'] > 0): ?><div class="r"><span>Diskon</span><span>-<?= rupiah($sale['discount'], false) ?></span></div><?php endif; ?>
  <?php if ($sale['service'] > 0): ?><div class="r"><span>Service</span><span><?= rupiah($sale['service'], false) ?></span></div><?php endif; ?>
  <?php if ($sale['tax'] > 0): ?><div class="r"><span>Pajak</span><span><?= rupiah($sale['tax'], false) ?></span></div><?php endif; ?>
  <div class="r" style="font-weight:700;font-size:14px"><span>TOTAL</span><span><?= rupiah($sale['total'], false) ?></span></div>
  <hr>
  <?php foreach ($pays as $p): ?><div class="r"><span><?= esc($p['method_name']) ?></span><span><?= rupiah($p['amount'], false) ?></span></div><?php endforeach; ?>
  <?php if ($sale['change_amount'] > 0): ?><div class="r"><span>Kembalian</span><span><?= rupiah($sale['change_amount'], false) ?></span></div><?php endif; ?>
  <?php if ($sale['status'] === 'void'): ?><hr><div class="c"><b>*** VOID ***</b></div><?php endif; ?>
  <hr>
  <div class="c"><?= esc($loc['receipt_footer'] ?? 'Terima kasih') ?></div>
</div>
<div class="no-print text-center pb-4"><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button> <a class="btn btn-ghost" href="javascript:window.close()">Tutup</a></div>
<?php if (service('request')->getGet('print')): ?><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),400))</script><?php endif; ?>
</body></html>
