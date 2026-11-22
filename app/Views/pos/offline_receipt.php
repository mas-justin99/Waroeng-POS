<?php helper('app'); /* Cangkang struk offline: kosong sampai JS mengisi dari antrean di perangkat. Tidak memuat data apa pun dari server. */ ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Struk</title>
<link href="<?= base_url('assets/css/app.css') ?>?v=6" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head><body style="background:#eee">
<div class="receipt" id="rc"><div class="c">Memuat struk…</div></div>
<?= view('partials/printbar', ['extra' => '<a class="btn btn-sm btn-ghost" href="javascript:window.close()">Tutup</a>']) ?>
<script src="<?= base_url('assets/js/offline.js') ?>?v=1"></script>
<script src="<?= base_url('assets/js/offline-receipt.js') ?>?v=1"></script>
</body></html>
