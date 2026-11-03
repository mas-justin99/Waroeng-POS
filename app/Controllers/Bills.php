<?php

namespace App\Controllers;

use App\Libraries\BillService;
use RuntimeException;

/** Bill per meja: daftar meja terbuka, simpan dari kasir, cetak bill sementara, batalkan. */
class Bills extends BaseController
{
    private function find(int $id): ?array
    {
        return $this->db->table('table_bills')->where('id', $id)->where('location_id', loc_id())->get()->getRowArray() ?: null;
    }

    private function summary(array $b, array $loc): array
    {
        $lines = BillService::lines((int) $b['id']);
        $t = BillService::totals($lines, $b, $loc);
        return ['id' => (int) $b['id'], 'table_no' => $b['table_no'], 'customer' => $b['customer_name'], 'order_type' => $b['order_type'],
            'opened_at' => $b['opened_at'], 'updated_at' => $b['updated_at'], 'qty' => $t['qty'], 'total' => $t['total'], 'lines' => $lines, 'totals' => $t,
            'discount_type' => $b['discount_type'], 'discount_value' => (float) $b['discount_value']];
    }

    private function openBills(): array
    {
        $loc = loc_row();
        $rows = $this->db->table('table_bills')->where('location_id', loc_id())->where('status', 'open')->orderBy('opened_at')->get()->getResultArray();
        return array_map(fn ($b) => $this->summary($b, $loc), $rows);
    }

    public function index()
    {
        return view('bills/index', ['bills' => $this->openBills()]);
    }

    public function list()
    {
        return $this->response->setJSON(['ok' => true, 'bills' => array_map(fn ($b) => array_diff_key($b, ['lines' => 1]), $this->openBills())]);
    }

    public function show(int $id)
    {
        $b = $this->find($id);
        if (! $b || $b['status'] !== 'open') return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Bill tidak ditemukan atau sudah ditutup.']);
        return $this->response->setJSON(['ok' => true, 'bill' => $this->summary($b, loc_row())]);
    }

    public function save()
    {
        try {
            $r = BillService::save($this->request->getJSON(true) ?? [], loc_id(), (int) auth_user()['id']);
            return $this->response->setJSON(['ok' => true] + $r);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function print(int $id)
    {
        $b = $this->find($id);
        if (! $b) return redirect()->to('/bills')->with('error', 'Bill tidak ditemukan.');
        return view('bills/print', ['bill' => $this->summary($b, loc_row()), 'status' => $b['status'], 'loc' => loc_row(), 'cashier' => auth_user()['name'] ?? '']);
    }

    public function cancel(int $id)
    {
        $b = $this->find($id);
        if (! $b || $b['status'] !== 'open') return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Bill tidak ditemukan atau sudah ditutup.']);
        $now = date('Y-m-d H:i:s');
        $this->db->table('table_bills')->where('id', $id)->update(['status' => 'cancelled', 'closed_at' => $now, 'updated_at' => $now]);
        return $this->response->setJSON(['ok' => true]);
    }
}
