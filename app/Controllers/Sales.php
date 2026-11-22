<?php

namespace App\Controllers;

use App\Libraries\SalesService;

class Sales extends BaseController
{
    public function index()
    {
        [$from, $to, $f, $t] = $this->dateRange();
        $status = $this->request->getGet('status');
        $b = $this->db->table('sales s')->select('s.*, u.name AS cashier')->join('users u', 'u.id = s.cashier_id', 'left')
            ->where('s.location_id', loc_id())->where('s.created_at >=', $f)->where('s.created_at <=', $t)
            ->orderBy('s.id', 'DESC')->limit(300);
        if (in_array($status, ['paid', 'void'], true)) $b->where('s.status', $status);
        $rows = $b->get()->getResultArray();
        $sum = 0;
        foreach ($rows as $r) if ($r['status'] === 'paid') $sum += $r['total'];
        return view('sales/index', compact('rows', 'from', 'to', 'status', 'sum'));
    }

    public function show(int $id)
    {
        $sale = $this->db->table('sales s')->select('s.*, u.name AS cashier')->join('users u', 'u.id = s.cashier_id', 'left')
            ->where('s.id', $id)->where('s.location_id', loc_id())->get()->getRowArray();
        if (! $sale) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return view('sales/show', [
            'sale' => $sale,
            'items' => $this->db->table('sale_items')->where('sale_id', $id)->get()->getResultArray(),
            'pays' => $this->db->table('sale_payments')->where('sale_id', $id)->get()->getResultArray(),
        ]);
    }

    public function void(int $id)
    {
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '') return $this->back("/sales/$id", 'Alasan void wajib diisi.', 'error');
        try {
            SalesService::void($id, $reason, (int) auth_user()['id']);
        } catch (\Throwable $e) {
            return $this->back("/sales/$id", $e->getMessage(), 'error');
        }
        return $this->back("/sales/$id", 'Transaksi di-void dan stok dikembalikan.');
    }

    /** Cetak ulang order bar untuk transaksi ini. */
    public function printBar(int $id)
    {
        $sale = $this->db->table('sales')->where('id', $id)->where('location_id', loc_id())->get()->getRowArray();
        if (! $sale || $sale['status'] !== 'paid') return redirect()->to('/sales')->with('error', 'Transaksi tidak ditemukan.');
        $q = [];
        foreach ($this->db->table('sale_items')->where('sale_id', $id)->get()->getResultArray() as $i) $q[(int) $i['product_id']] = (int) $i['qty'];
        $lines = \App\Libraries\PrintService::barLines($q);
        $n = $lines ? \App\Libraries\PrintService::enqueue(loc_id(), 'reprint', $sale['invoice_no'], ['title' => 'ORDER BAR (ULANG)', 'table' => (string) $sale['table_no'], 'ref' => $sale['invoice_no'],
            'cashier' => auth_user()['name'] ?? '', 'order_type' => $sale['order_type'], 'customer' => (string) $sale['customer_name']], $lines) : 0;
        $msg = ! $lines ? 'Tidak ada item bar pada transaksi ini.' : ($n ? 'Order bar dikirim ulang ke printer.' : 'Belum ada printer bar aktif di outlet ini.');
        return redirect()->to('/sales/' . $id)->with($n ? 'success' : 'error', $msg);
    }
}
