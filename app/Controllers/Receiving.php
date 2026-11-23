<?php

namespace App\Controllers;

use App\Libraries\Inventory;
use CodeIgniter\Exceptions\PageNotFoundException;

class Receiving extends BaseController
{
    public function index()
    {
        $pending = $this->db->table('purchase_orders po')
            ->select('po.*, u.name AS creator, (SELECT COALESCE(SUM(qty),0) FROM purchase_order_items WHERE po_id = po.id) AS q_total, (SELECT COALESCE(SUM(qty_received),0) FROM purchase_order_items WHERE po_id = po.id) AS q_recv')
            ->join('users u', 'u.id = po.created_by', 'left')
            ->where('po.location_id', loc_id())->whereIn('po.status', ['sent', 'partial'])->orderBy('po.id')->get()->getResultArray();
        $history = $this->db->table('goods_receipts g')
            ->select('g.*, po.po_number, u.name AS receiver, (SELECT COALESCE(SUM(qty),0) FROM goods_receipt_items WHERE gr_id = g.id) AS q')
            ->join('purchase_orders po', 'po.id = g.po_id')->join('users u', 'u.id = g.received_by', 'left')
            ->where('g.location_id', loc_id())->orderBy('g.id', 'DESC')->limit(100)->get()->getResultArray();
        return view('receiving/index', compact('pending', 'history'));
    }

    private function po(int $id): array
    {
        $po = $this->db->table('purchase_orders')->where('id', $id)->where('location_id', loc_id())->get()->getRowArray();
        if (! $po) throw PageNotFoundException::forPageNotFound();
        return $po;
    }

    public function form(int $poId)
    {
        $po = $this->po($poId);
        if (! in_array($po['status'], ['sent', 'partial'], true)) return $this->back("/po/$poId", 'PO ini tidak menunggu penerimaan.', 'error');
        return view('receiving/form', ['po' => $po, 'items' => $this->db->table('purchase_order_items')->where('po_id', $poId)->orderBy('id')->get()->getResultArray()]);
    }

    public function store(int $poId)
    {
        $po = $this->po($poId);
        $qtys = (array) $this->request->getPost('qty');
        $db = $this->db;
        $db->transBegin();
        try {
            $po = $db->query('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$poId])->getRowArray();
            if (! in_array($po['status'], ['sent', 'partial'], true)) throw new \RuntimeException('PO ini sudah selesai/dibatalkan.');
            $items = $db->table('purchase_order_items')->where('po_id', $poId)->get()->getResultArray();

            $loc = loc_row();
            $prefix = 'GR/' . $loc['code'] . '/' . date('ymd') . '/';
            $n = $db->table('goods_receipts')->like('gr_number', $prefix, 'after')->countAllResults() + 1;
            $now = date('Y-m-d H:i:s');
            $db->table('goods_receipts')->insert([
                'gr_number' => $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'po_id' => $poId, 'location_id' => loc_id(),
                'notes' => trim((string) $this->request->getPost('notes')) ?: null, 'received_by' => auth_user()['id'], 'created_at' => $now,
            ]);
            $grId = (int) $db->insertID();
            $any = false;
            foreach ($items as $it) {
                $q = (int) ($qtys[$it['id']] ?? 0);
                $remain = (int) $it['qty'] - (int) $it['qty_received'];
                if ($q <= 0) continue;
                if ($q > $remain) throw new \RuntimeException('Qty "' . $it['name'] . '" melebihi sisa pesanan (' . $remain . ').');
                $any = true;
                $db->table('goods_receipt_items')->insert(['gr_id' => $grId, 'po_item_id' => $it['id'], 'product_id' => $it['product_id'], 'qty' => $q, 'hpp' => $it['hpp']]);
                $db->table('purchase_order_items')->where('id', $it['id'])->set('qty_received', 'qty_received + ' . $q, false)->update();
                Inventory::move(loc_id(), (int) $it['product_id'], $q, 'receipt', 'gr', $grId, $po['po_number'], (int) auth_user()['id'], (float) $it['hpp']);
            }
            if (! $any) throw new \RuntimeException('Isi minimal satu qty yang diterima.');

            $left = $db->query('SELECT COALESCE(SUM(qty - qty_received),0) AS r, COALESCE(SUM(qty_received),0) AS got FROM purchase_order_items WHERE po_id = ?', [$poId])->getRowArray();
            $status = (int) $left['r'] === 0 ? 'received' : 'partial';
            $db->table('purchase_orders')->where('id', $poId)->update(['status' => $status, 'updated_at' => $now]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->back("/receiving/receive/$poId", $e->getMessage(), 'error')->withInput();
        }
        return $this->back('/receiving', 'Barang diterima, stok warung bertambah.');
    }

    public function show(int $id)
    {
        $gr = $this->db->table('goods_receipts g')->select('g.*, po.po_number, u.name AS receiver')->join('purchase_orders po', 'po.id = g.po_id')
            ->join('users u', 'u.id = g.received_by', 'left')->where('g.id', $id)->where('g.location_id', loc_id())->get()->getRowArray();
        if (! $gr) throw PageNotFoundException::forPageNotFound();
        $items = $this->db->table('goods_receipt_items i')->select('i.*, p.sku, p.name')->join('products p', 'p.id = i.product_id')->where('i.gr_id', $id)->get()->getResultArray();
        return view('receiving/show', compact('gr', 'items'));
    }

    /** Hapus penerimaan barang: stok dikurangi sebesar qty yang diterima, qty diterima di PO dikembalikan. */
    public function delete(int $id)
    {
        $db = $this->db;
        $short = [];
        $db->transBegin();
        try {
            $gr = $db->query('SELECT * FROM goods_receipts WHERE id = ? AND location_id = ? FOR UPDATE', [$id, loc_id()])->getRowArray();
            if (! $gr) throw new \RuntimeException('Penerimaan tidak ditemukan.');
            $po = $db->query('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$gr['po_id']])->getRowArray();
            $items = $db->table('goods_receipt_items')->where('gr_id', $id)->orderBy('product_id')->get()->getResultArray();
            foreach ($items as $it) {
                $mv = Inventory::move(loc_id(), (int) $it['product_id'], -(int) $it['qty'], 'receipt_void', 'gr', $id, 'Hapus ' . $gr['gr_number'], (int) auth_user()['id'], (float) $it['hpp'], true);
                if ($mv['balance'] < 0) $short[] = $it['product_id'];
                $db->table('purchase_order_items')->where('id', $it['po_item_id'])->set('qty_received', 'GREATEST(qty_received - ' . (int) $it['qty'] . ', 0)', false)->update();
            }
            $db->table('goods_receipt_items')->where('gr_id', $id)->delete();
            $db->table('goods_receipts')->where('id', $id)->delete();
            if ($po && $po['status'] !== 'cancelled') {
                $t = $db->query('SELECT COALESCE(SUM(qty - qty_received),0) AS r, COALESCE(SUM(qty_received),0) AS got FROM purchase_order_items WHERE po_id = ?', [$po['id']])->getRowArray();
                $st = (int) $t['got'] === 0 ? 'sent' : ((int) $t['r'] === 0 ? 'received' : 'partial');
                $db->table('purchase_orders')->where('id', $po['id'])->update(['status' => $st, 'updated_at' => date('Y-m-d H:i:s')]);
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->back('/receiving', $e->getMessage(), 'error');
        }
        $msg = 'Penerimaan ' . $gr['gr_number'] . ' dihapus, stok disesuaikan.' . ($short ? ' Perhatian: ' . count($short) . ' produk stoknya menjadi minus (sudah terpakai/terjual) — cek di Stok Warung.' : '');
        return $this->back('/po/' . $gr['po_id'], $msg, $short ? 'error' : 'success');
    }
}
