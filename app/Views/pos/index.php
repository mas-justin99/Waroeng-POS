<?= $this->extend('layouts/app') ?>
<?php $title = 'Kasir'; $bodyClass = 'pos-page'; ?>
<?= $this->section('content') ?>
<div class="pos">
  <section class="pos-left">
    <div class="pos-tools">
      <div class="input-icon"><i class="bi bi-search"></i><input id="q" class="form-control" type="search" placeholder="Cari menu atau SKU…" autocomplete="off"></div>
      <div class="cats" id="cats">
        <button class="cat on" data-c="0">Semua</button>
        <?php foreach ($categories as $c): ?><button class="cat" data-c="<?= $c['id'] ?>"><?= esc($c['name']) ?></button><?php endforeach; ?>
      </div>
    </div>
    <div class="grid" id="grid"></div>
  </section>

  <aside class="cart" id="cart">
    <div class="cart-h">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <b style="font-size:1.05rem"><i class="bi bi-bag-heart text-primary"></i> Pesanan</b>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-ghost" id="clear"><i class="bi bi-trash3"></i></button>
          <button class="btn btn-sm btn-ghost cart-close" id="closeCart"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>
      <div class="seg w-100 mb-2" id="otype">
        <button class="on flex-fill justify-content-center" data-v="dine_in"><i class="bi bi-cup-hot"></i> Makan</button>
        <button class="flex-fill justify-content-center" data-v="take_away"><i class="bi bi-bag"></i> Bawa</button>
        <button class="flex-fill justify-content-center" data-v="delivery"><i class="bi bi-bicycle"></i> Antar</button>
      </div>
      <div class="row g-2"><div class="col-4"><input id="table" class="form-control" placeholder="Meja" inputmode="text"></div><div class="col-8"><input id="cust" class="form-control" placeholder="Nama pelanggan (opsional)"></div></div>
    </div>
    <div class="cart-items" id="items"></div>
    <div class="cart-f">
      <div class="sum-r"><span>Subtotal</span><span class="num" id="s-sub">Rp 0</span></div>
      <div class="sum-r"><span>Diskon <a href="#" id="discBtn" class="ms-1 small">ubah</a></span><span class="num text-danger" id="s-disc">- Rp 0</span></div>
      <div class="sum-r" id="r-svc"><span>Service (<?= rtrim(rtrim(number_format($service, 2), '0'), '.') ?>%)</span><span class="num" id="s-svc">Rp 0</span></div>
      <div class="sum-r" id="r-tax"><span>Pajak (<?= rtrim(rtrim(number_format($tax, 2), '0'), '.') ?>%)</span><span class="num" id="s-tax">Rp 0</span></div>
      <div class="sum-r t"><span>Total</span><span class="num" id="s-total">Rp 0</span></div>
      <button class="btn btn-primary btn-lg w-100 mt-3" id="payBtn" disabled><i class="bi bi-wallet2"></i> Bayar</button>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>
  <button class="cart-bar" id="cartBar"><span><span class="c" id="barQty">0</span>Lihat pesanan</span><span class="num" id="barTotal">Rp 0</span></button>
</div>

<!-- Diskon -->
<div class="modal fade" id="discModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Diskon</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="seg w-100 mb-3" id="dtype"><button class="on flex-fill justify-content-center" data-v="nominal">Rp</button><button class="flex-fill justify-content-center" data-v="percent">%</button></div>
    <input id="dval" class="form-control form-control-lg text-center" type="number" inputmode="decimal" min="0" placeholder="0">
  </div>
  <div class="modal-footer"><button class="btn btn-primary w-100" id="dok" data-bs-dismiss="modal">Terapkan</button></div>
</div></div></div>

<!-- Pembayaran -->
<div class="modal fade" id="payModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-wallet2 text-primary"></i> Pembayaran</h5><button class="btn-close" data-bs-dismiss="modal" id="payClose"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="form-label">Pilih metode (bisa lebih dari satu / split)</div>
        <div class="paygrid" id="pg"></div>
      </div>
      <div class="col-lg-6">
        <div class="pay-sum mb-3">
          <div class="big"><span>Total Tagihan</span><span class="num" id="p-total">Rp 0</span></div>
          <span>Dibayar</span><b class="num" id="p-paid">Rp 0</b>
          <span id="p-lbl">Kurang</span><b class="num" id="p-rem">Rp 0</b>
        </div>
        <div id="lines"></div>
        <div class="quick" id="quick"></div>
      </div>
    </div>
    <div id="payErr" class="alert-x err d-none mt-2"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary btn-lg w-100" id="confirmPay" disabled><i class="bi bi-check2-circle"></i> Selesaikan Transaksi</button></div>
</div></div></div>

<!-- Sukses -->
<div class="modal fade" id="okModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content text-center">
  <div class="modal-body py-4">
    <svg class="ok-ring" viewBox="0 0 100 100"><circle cx="50" cy="50" r="45"/><path d="M30 52l14 14 27-30"/></svg>
    <h5 class="mb-1">Transaksi Berhasil</h5>
    <div class="text-muted small" id="ok-inv"></div>
    <div class="mt-3 text-muted">Kembalian</div><div class="fw-bold" style="font-size:1.8rem" id="ok-change">Rp 0</div>
  </div>
  <div class="modal-footer flex-column gap-2">
    <a class="btn btn-soft w-100" id="ok-print" target="_blank"><i class="bi bi-printer"></i> Cetak Struk</a>
    <button class="btn btn-primary w-100" id="ok-new"><i class="bi bi-plus-lg"></i> Transaksi Baru</button>
  </div>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
window.POS = {
  products: <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  methods: <?= json_encode($methods, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  tax: <?= $tax ?>, service: <?= $service ?>
};
</script>
<script src="<?= base_url('assets/js/pos.js') ?>?v=1"></script>
<?= $this->endSection() ?>
