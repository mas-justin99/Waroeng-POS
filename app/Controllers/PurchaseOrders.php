<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class PurchaseOrders extends BaseController
{
    public function index()
    {
        $status = $this->request->getGet('status');
        $b = $this->db->table('purchase_orders po')
            ->select('po.*, u.name AS creator, (SELECT COALESCE(SUM(qty),0) FROM purchase_order_items WHERE po_id = po.id) AS q_total, (SELECT COALESCE(SUM(qty_received),0) FROM purchase_order_items WHERE po_id = po.id) AS q_recv')
            ->join('users u', 'u.id = po.created_by', 'left')
            ->where('po.location_id', loc_id())->orderBy('po.id', 'DESC')->limit(200);
        if ($status) $b->where('po.status', $status);
        return view('po/index', ['rows' => $b->get()->getResultArray(), 'status' => $status]);
    }

    public function create()
    {
        $products = $this->db->table('products')->select('id, sku, name, hpp, image_url')->where('is_active', 1)->orderBy('name')->get()->getResultArray();
        foreach ($products as &$p) { $p['id'] = (int) $p['id']; $p['hpp'] = (float) $p['hpp']; }
        return view('po/create', ['products' => $products]);
    }

    public function store()
    {
        $items = [];
        foreach ((array) $this->request->getPost('items') as $it) {
            $pid = (int) ($it['product_id'] ?? 0);
            $qty = (int) ($it['qty'] ?? 0);
            $hpp = max(0, (float) ($it['hpp'] ?? 0));
            if ($pid && $qty > 0) {
                if (isset($items[$pid])) $items[$pid]['qty'] += $qty; else $items[$pid] = ['qty' => $qty, 'hpp' => $hpp];
            }
        }
        if (! $items) return $this->back('/po/create', 'Tambahkan minimal satu item (SKU, HPP, Qty).', 'error')->withInput();

        $loc = loc_row();
        $db = $this->db;
        $db->transBegin();
        try {
            $prods = array_column($db->table('products')->whereIn('id', array_keys($items))->get()->getResultArray(), null, 'id');
            $prefix = 'PO/' . $loc['code'] . '/' . date('ymd') . '/';
            $n = $db->table('purchase_orders')->like('po_number', $prefix, 'after')->countAllResults() + 1;
            $now = date('Y-m-d H:i:s');
            $total = 0;
            foreach ($items as $pid => $i) $total += $i['qty'] * $i['hpp'];
            $db->table('purchase_orders')->insert([
                'po_number' => $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'location_id' => loc_id(), 'status' => 'sent',
                'notes' => trim((string) $this->request->getPost('notes')) ?: null, 'total_hpp' => $total,
                'created_by' => auth_user()['id'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $poId = (int) $db->insertID();
            foreach ($items as $pid => $i) {
                if (! isset($prods[$pid])) continue;
                $db->table('purchase_order_items')->insert([
                    'po_id' => $poId, 'product_id' => $pid, 'sku' => $prods[$pid]['sku'], 'name' => $prods[$pid]['name'],
                    'hpp' => $i['hpp'], 'qty' => $i['qty'], 'qty_received' => 0,
                ]);
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->back('/po/create', 'Gagal menyimpan PO: ' . $e->getMessage(), 'error');
        }
        return $this->back("/po/$poId", 'Pesanan pembelian dikirim ke kasir untuk diterima.');
    }

    public function show(int $id)
    {
        $po = $this->db->table('purchase_orders po')->select('po.*, u.name AS creator')->join('users u', 'u.id = po.created_by', 'left')
            ->where('po.id', $id)->where('po.location_id', loc_id())->get()->getRowArray();
        if (! $po) throw PageNotFoundException::forPageNotFound();
        return view('po/show', [
            'po' => $po,
            'items' => $this->db->table('purchase_order_items')->where('po_id', $id)->get()->getResultArray(),
            'receipts' => $this->db->table('goods_receipts g')->select('g.*, u.name AS receiver')->join('users u', 'u.id = g.received_by', 'left')
                ->where('g.po_id', $id)->orderBy('g.id')->get()->getResultArray(),
        ]);
    }

    public function cancel(int $id)
    {
        $po = $this->db->table('purchase_orders')->where('id', $id)->where('location_id', loc_id())->get()->getRowArray();
        if (! $po) throw PageNotFoundException::forPageNotFound();
        if ($po['status'] !== 'sent') return $this->back("/po/$id", 'PO yang sudah diterima (sebagian) tidak bisa dibatalkan.', 'error');
        $this->db->table('purchase_orders')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->back("/po/$id", 'PO dibatalkan.');
    }
}
