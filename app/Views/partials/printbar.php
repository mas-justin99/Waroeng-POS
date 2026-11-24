<?php /** Tombol cetak struk: browser, RawBT (Bluetooth klasik), Web Bluetooth (BLE). Dipakai di semua halaman struk. $extra = tombol tambahan (HTML). */ ?>
<div class="no-print pbar" data-printbar>
  <div class="pbar-row">
    <button class="btn btn-primary" data-p="rawbt"><i class="bi bi-bluetooth"></i> Cetak Bluetooth (RawBT)</button>
    <button class="btn btn-ghost" data-p="bt"><i class="bi bi-broadcast"></i> Bluetooth Langsung</button>
    <button class="btn btn-ghost" data-p="print"><i class="bi bi-printer"></i> Cetak Biasa</button>
  </div>
  <div class="pbar-row small">
    <label>Lebar kertas <select data-pcols class="form-select form-select-sm d-inline-block" style="width:auto"><option value="32">58 mm</option><option value="48">80 mm</option></select></label>
    <?= $extra ?? '' ?>
  </div>
  <div class="small text-center" data-pstatus style="min-height:1.2em"></div>
  <details class="small text-muted pbar-help"><summary>Printer Bluetooth tidak terbaca?</summary>
    <div>1) Pasangkan (pair) printer RPP02N di Pengaturan Bluetooth HP dulu. 2) Pasang aplikasi <b>RawBT</b> (Play Store), buka sekali, pilih printer Bluetooth Anda di dalamnya. 3) Tekan <b>Cetak Bluetooth (RawBT)</b> di sini. Tombol <i>Bluetooth Langsung</i> hanya untuk printer berjenis BLE. <b>Printer WiFi/LAN:</b> di RawBT pilih jenis printer <i>Ethernet/WiFi</i>, isi IP printer (port 9100); tombol RawBT di sini akan mencetak ke sana, termasuk saat internet mati selama WiFi lokal menyala.</div></details>
</div>
<script src="<?= base_url('assets/js/escpos.js') ?>?v=2"></script>
