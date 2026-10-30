<?php

namespace App\Controllers;

use App\Libraries\Inventory;

class Stock extends BaseController
{
    public function index()
    {
        $loc = loc_id();
        $rows = $this->db->query(
            'SELECT p.id, p.sku, p.name, p.image_url, c.name AS category, COALESCE(s.qty,0) AS qty,
                    COALESCE(s.avg_cost,0) AS avg_cost, COALESCE(s.min_qty,5) AS min_qty
             FROM products p
             LEFT JOIN stocks s ON s.product_id = p.id AND s.location_id = ?
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 ORDER BY p.name', [$loc])->getResultArray();
        $moves = [];
        if ($this->request->getGet('tab') === 'moves') {
            $moves = $this->db->table('stock_movements m')->select('m.*, p.sku, p.name, u.name AS user_name')
                ->join('products p', 'p.id = m.product_id')->join('users u', 'u.id = m.user_id', 'left')
                ->where('m.location_id', $loc)->orderBy('m.id', 'DESC')->limit(150)->get()->getResultArray();
        }
        return view('stock/index', ['rows' => $rows, 'moves' => $moves, 'tab' => $this->request->getGet('tab') === 'moves' ? 'moves' : 'stock']);
    }

    public function adjust()
    {
        $pid = (int) $this->request->getPost('product_id');
        $new = (int) $this->request->getPost('new_qty');
        $min = (int) $this->request->getPost('min_qty');
        $note = trim((string) $this->request->getPost('note')) ?: 'Stok opname';
        $db = $this->db;
        $db->transBegin();
        try {
            $cur = $db->query('SELECT qty FROM stocks WHERE location_id = ? AND product_id = ? FOR UPDATE', [loc_id(), $pid])->getRowArray();
            $delta = $new - (int) ($cur['qty'] ?? 0);
            if ($delta !== 0) Inventory::move(loc_id(), $pid, $delta, 'adjust', null, null, $note, (int) auth_user()['id'], null, true);
            $db->table('stocks')->where('location_id', loc_id())->where('product_id', $pid)->update(['min_qty' => max(0, $min)]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->back('/stock', $e->getMessage(), 'error');
        }
        return $this->back('/stock', 'Stok diperbarui.');
    }
}
