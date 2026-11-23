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
        $past = $this->db->query("SELECT b.id, b.table_no, b.customer_name, b.status, b.closed_at, b.opened_at, b.sale_id, s.invoice_no, s.status AS sale_status
            FROM table_bills b LEFT JOIN sales s ON s.id = b.sale_id WHERE b.location_id = ? AND b.status <> 'open' ORDER BY b.id DESC LIMIT 40", [loc_id()])->getResultArray();
        return view('bills/index', ['bills' => $this->openBills(), 'past' => $past]);
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
        $printed = array_filter(\App\Libraries\BillService::printedMap($id));
        \App\Libraries\PrintService::sendDelta(loc_id(), 'Meja ' . $b['table_no'], ['table' => $b['table_no'], 'ref' => 'Bill dibatalkan', 'cashier' => auth_user()['name'] ?? '', 'order_type' => $b['order_type']],
            array_map(fn ($q) => -$q, $printed));
        $this->db->table('table_bills')->where('id', $id)->update(['status' => 'cancelled', 'closed_at' => $now, 'updated_at' => $now]);
        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Hapus bill permanen. Bill terbuka ikut dibatalkan (tiket BATAL ke bar).
     * Bill yang sudah dibayar: transaksinya harus di-void (dihapus) dulu agar stok & kas kembali benar.
     */
    public function delete(int $id)
    {
        $b = $this->find($id);
        if (! $b) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Bill tidak ditemukan.']);
        if ($b['status'] === 'paid' && $b['sale_id']) {
            $s = $this->db->table('sales')->where('id', $b['sale_id'])->get()->getRowArray();
            if ($s && $s['status'] === 'paid') {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Bill ini sudah dibayar (transaksi ' . $s['invoice_no'] . '). Void transaksinya dulu di Riwayat Penjualan, baru bill bisa dihapus.', 'sale_id' => (int) $s['id']]);
            }
        }
        if ($b['status'] === 'open') {
            $printed = array_filter(BillService::printedMap($id));
            \App\Libraries\PrintService::sendDelta(loc_id(), 'Meja ' . $b['table_no'], ['table' => $b['table_no'], 'ref' => 'Bill dihapus', 'cashier' => auth_user()['name'] ?? '', 'order_type' => $b['order_type']],
                array_map(fn ($q) => -$q, $printed));
        }
        $this->db->transBegin();
        $this->db->table('table_bill_items')->where('bill_id', $id)->delete();
        $this->db->table('table_bills')->where('id', $id)->delete();
        if (! $this->db->transStatus()) { $this->db->transRollback(); return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => 'Gagal menghapus bill.']); }
        $this->db->transCommit();
        return $this->response->setJSON(['ok' => true]);
    }
}
