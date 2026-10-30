<?php

namespace App\Controllers;

use App\Libraries\SalesService;
use App\Libraries\ShiftService;

class Pos extends BaseController
{
    public function index()
    {
        $loc = loc_id();
        $shift = ShiftService::current($loc);
        if (! $shift) {
            return redirect()->to('/shift')->with('error', 'Buka kasir dulu: isi uang modal dan hitung stok awal.');
        }
        $products = $this->db->query(
            'SELECT p.id, p.sku, p.name, p.price, p.image_url, p.category_id, COALESCE(s.qty,0) AS stock
             FROM products p LEFT JOIN stocks s ON s.product_id = p.id AND s.location_id = ?
             WHERE p.is_active = 1 ORDER BY p.name', [$loc])->getResultArray();
        foreach ($products as &$p) {
            $p['id'] = (int) $p['id']; $p['price'] = (float) $p['price']; $p['stock'] = (int) $p['stock'];
            $p['category_id'] = (int) $p['category_id'];
        }
        $l = loc_row();
        return view('pos/index', [
            'shift' => $shift,
            'products' => $products,
            'categories' => $this->db->table('categories')->orderBy('name')->get()->getResultArray(),
            'methods' => $this->db->table('payment_methods')->where('is_active', 1)->orderBy('sort')->get()->getResultArray(),
            'tax' => (float) ($l['tax_percent'] ?? 0), 'service' => (float) ($l['service_percent'] ?? 0),
        ]);
    }

    public function checkout()
    {
        $in = $this->request->getJSON(true) ?? [];
        try {
            $r = SalesService::checkout($in, loc_id(), (int) auth_user()['id']);
            return $this->response->setJSON(['ok' => true] + $r);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function receipt(int $id)
    {
        $sale = $this->db->table('sales s')->select('s.*, u.name AS cashier')->join('users u', 'u.id = s.cashier_id', 'left')
            ->where('s.id', $id)->get()->getRowArray();
        if (! $sale || (auth_user()['role'] !== 'admin' && (int) $sale['location_id'] !== loc_id())) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return view('pos/receipt', [
            'sale' => $sale, 'loc' => loc_row((int) $sale['location_id']),
            'items' => $this->db->table('sale_items')->where('sale_id', $id)->get()->getResultArray(),
            'pays' => $this->db->table('sale_payments')->where('sale_id', $id)->get()->getResultArray(),
        ]);
    }
}
