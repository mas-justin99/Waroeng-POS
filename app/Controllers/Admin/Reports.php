<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Reports extends BaseController
{
    public function index()
    {
        [$from, $to, $f, $t] = $this->dateRange();
        $type = in_array($this->request->getGet('type'), ['sales', 'products', 'po'], true) ? $this->request->getGet('type') : 'sales';
        $loc = $this->request->getGet('loc') ?: 'all';
        $lw = $loc !== 'all' ? ' AND s.location_id = ' . (int) $loc : '';
        $db = $this->db;

        if ($type === 'sales') {
            $head = ['Tanggal', 'Invoice', 'Lokasi', 'Kasir', 'Tipe', 'Subtotal', 'Diskon', 'Pajak+Service', 'Total', 'HPP', 'Laba Kotor', 'Status'];
            $rows = [];
            foreach ($db->query("SELECT s.*, l.code AS lc, u.name AS cashier FROM sales s JOIN locations l ON l.id = s.location_id LEFT JOIN users u ON u.id = s.cashier_id
                        WHERE s.created_at BETWEEN ? AND ? $lw ORDER BY s.id DESC LIMIT 2000", [$f, $t])->getResultArray() as $r) {
                $rows[] = [$r['created_at'], $r['invoice_no'], $r['lc'], $r['cashier'], order_type_label($r['order_type']), $r['subtotal'], $r['discount'],
                    $r['tax'] + $r['service'], $r['total'], $r['cogs'], $r['subtotal'] - $r['discount'] - $r['cogs'], $r['status']];
            }
        } elseif ($type === 'products') {
            $head = ['SKU', 'Produk', 'Qty Terjual', 'Penjualan', 'HPP', 'Laba Kotor', 'Margin %'];
            $rows = [];
            foreach ($db->query("SELECT i.sku, i.name, SUM(i.qty) AS q, SUM(i.subtotal) AS t, SUM(i.cost * i.qty) AS c FROM sale_items i JOIN sales s ON s.id = i.sale_id
                        WHERE s.status = 'paid' AND s.created_at BETWEEN ? AND ? $lw GROUP BY i.sku, i.name ORDER BY t DESC", [$f, $t])->getResultArray() as $r) {
                $rows[] = [$r['sku'], $r['name'], $r['q'], $r['t'], $r['c'], $r['t'] - $r['c'], $r['t'] > 0 ? round(($r['t'] - $r['c']) / $r['t'] * 100, 1) : 0];
            }
        } else {
            $lp = $loc !== 'all' ? ' AND po.location_id = ' . (int) $loc : '';
            $head = ['Tanggal', 'No. PO', 'Lokasi', 'Dapur (pembuat)', 'Total HPP', 'Qty Pesan', 'Qty Diterima', 'Status'];
            $rows = [];
            foreach ($db->query("SELECT po.*, l.code AS lc, u.name AS creator,
                        (SELECT COALESCE(SUM(qty),0) FROM purchase_order_items WHERE po_id = po.id) AS q1,
                        (SELECT COALESCE(SUM(qty_received),0) FROM purchase_order_items WHERE po_id = po.id) AS q2
                        FROM purchase_orders po JOIN locations l ON l.id = po.location_id LEFT JOIN users u ON u.id = po.created_by
                        WHERE po.created_at BETWEEN ? AND ? $lp ORDER BY po.id DESC", [$f, $t])->getResultArray() as $r) {
                $rows[] = [$r['created_at'], $r['po_number'], $r['lc'], $r['creator'], $r['total_hpp'], $r['q1'], $r['q2'], $r['status']];
            }
        }

        if ($this->request->getGet('export') === 'csv') {
            $out = fopen('php://temp', 'w+');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, $head, ',', '"', '\\');
            foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
            rewind($out);
            return $this->response->download("laporan-$type-$from-$to.csv", stream_get_contents($out));
        }
        return view('admin/reports', compact('type', 'from', 'to', 'loc', 'head', 'rows'));
    }
}
