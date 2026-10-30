<?php
$u = auth_user();
$menu = [
  ['Penjualan', [
    ['/pos', 'bi-shop', 'Kasir (POS)', ['cashier'], 'Kasir'],
    ['/shift', 'bi-unlock', 'Buka / Tutup Kasir', ['cashier'], 'Shift'],
    ['/reservations', 'bi-calendar-check', 'Reservasi', ['cashier', 'kitchen', 'admin'], 'Reservasi'],
    ['/sales', 'bi-receipt', 'Riwayat Penjualan', ['cashier'], 'Riwayat'],
    ['/shifts', 'bi-clock-history', 'Riwayat Shift', ['cashier'], 'Riwayat Shift'],
  ]],
  ['Inventori', [
    ['/po', 'bi-clipboard2-check', 'Pesanan Pembelian', ['cashier', 'kitchen'], 'PO'],
    ['/receiving', 'bi-box-seam', 'Penerimaan Pembelian', ['cashier'], 'Terima'],
    ['/stock', 'bi-boxes', 'Stok Warung', ['cashier', 'kitchen'], 'Stok'],
  ]],
  ['Backoffice', [
    ['/admin', 'bi-speedometer2', 'Dashboard', [], 'Dashboard'],
    ['/admin/products', 'bi-egg-fried', 'Produk', [], 'Produk'],
    ['/admin/categories', 'bi-tags', 'Kategori', []],
    ['/admin/reports', 'bi-graph-up-arrow', 'Laporan', [], 'Laporan'],
    ['/admin/locations', 'bi-geo-alt', 'Lokasi Resto', []],
    ['/admin/users', 'bi-people', 'Pengguna', []],
    ['/admin/payment-methods', 'bi-credit-card-2-front', 'Metode Bayar', []],
  ]],
];
$path = '/' . trim(service('request')->getUri()->getPath(), '/');
$isActive = function ($href) use ($path) {
    if ($href === '/admin') return $path === '/admin';
    return $path === $href || str_starts_with($path, $href . '/');
};
$visible = [];
foreach ($menu as [$g, $items]) {
    $vis = array_filter($items, fn ($i) => has_role(...$i[3]) && ($g !== 'Backoffice' || $u['role'] === 'admin'));
    if ($vis) $visible[$g] = $vis;
}
