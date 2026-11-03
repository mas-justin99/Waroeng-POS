<?php helper('app'); $t = $bill['totals']; $open = $status === 'open'; ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bill Meja <?= esc($bill['table_no']) ?></title>
<link href="<?= base_url('assets/css/app.css') ?>?v=6" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head><body style="background:#eee">
<div class="receipt">
  <div class="c">
    <img src="<?= base_url('assets/img/logo.png') ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/img/logo.svg') ?>'" alt="">
    <b style="font-size:13px">WAROENG MBOK RENEO</b><br><?= esc($loc['name'] ?? '') ?><br>
    <?php if (! empty($loc['address'])): ?><?= esc($loc['address']) ?><br><?php endif; ?>
    <?php if (! empty($loc['phone'])): ?>Telp. <?= esc($loc['phone']) ?><br><?php endif; ?>
  </div>
  <hr>
  <div class="c"><b>BILL SEMENTARA</b><br><?= $open ? '** BELUM DIBAYAR **' : ($status === 'paid' ? '(sudah dibayar)' : '(dibatalkan)') ?></div>
  <hr>
  <div class="r" style="font-weight:700;font-size:13px"><span>MEJA</span><span><?= esc($bill['table_no']) ?></span></div>
  <div class="r"><span>Dibuka</span><span><?= tgl($bill['opened_at'], 'd/m/Y H:i') ?></span></div>
  <div class="r"><span>Dicetak</span><span><?= date('d/m/Y H:i') ?></span></div>
  <div class="r"><span>Kasir</span><span><?= esc($cashier) ?></span></div>
  <div class="r"><span>Tipe</span><span><?= esc(order_type_label($bill['order_type'])) ?></span></div>
  <?php if ($bill['customer']): ?><div class="r"><span>Pelanggan</span><span><?= esc($bill['customer']) ?></span></div><?php endif; ?>
  <hr>
  <?php foreach ($bill['lines'] as $i): ?>
    <div><?= esc($i['name']) ?></div>
    <div class="r"><span><?= $i['qty'] ?> x <?= rupiah($i['price'], false) ?></span><span><?= rupiah($i['sub'], false) ?></span></div>
  <?php endforeach; ?>
  <hr>
  <div class="r"><span>Subtotal</span><span><?= rupiah($t['subtotal'], false) ?></span></div>
  <?php if ($t['discount'] > 0): ?><div class="r"><span>Diskon</span><span>-<?= rupiah($t['discount'], false) ?></span></div><?php endif; ?>
  <?php if ($t['service'] > 0): ?><div class="r"><span>Service</span><span><?= rupiah($t['service'], false) ?></span></div><?php endif; ?>
  <?php if ($t['tax'] > 0): ?><div class="r"><span>Pajak</span><span><?= rupiah($t['tax'], false) ?></span></div><?php endif; ?>
  <div class="r" style="font-weight:700;font-size:13px"><span>TOTAL</span><span><?= rupiah($t['total'], false) ?></span></div>
  <hr>
  <div class="c">Silakan lakukan pembayaran di kasir.<br>Terima kasih</div>
</div>
<?= view('partials/printbar', ['extra' => '<a class="btn btn-sm btn-ghost" href="/bills">Daftar Bill</a>' . ($open ? ' <a class="btn btn-sm btn-ghost" href="/pos?bill=' . (int) $bill['id'] . '">Buka di Kasir</a>' : '')]) ?>
</body></html>
