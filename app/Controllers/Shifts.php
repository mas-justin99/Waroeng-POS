<?php

namespace App\Controllers;

use App\Libraries\Inventory;
use App\Libraries\ShiftService;
use CodeIgniter\Exceptions\PageNotFoundException;

/** Buka & tutup kasir (2 shift per hari). */
class Shifts extends BaseController
{
    /** Halaman utama: form buka kasir, atau status shift yang sedang berjalan. */
    public function index()
    {
        $loc   = loc_id();
        $shift = ShiftService::current($loc);
        if ($shift) {
            $shift['opener'] = $this->userName((int) $shift['cashier_id']);
            return view('shift/running', ['shift' => $shift, 'sum' => ShiftService::summary($shift)]);
        }

        $used = array_map('intval', array_column(
            $this->db->table('cash_shifts')->select('shift_no')->where('location_id', $loc)->where('shift_date', date('Y-m-d'))->get()->getResultArray(),
            'shift_no'
        ));
        $products = $this->db->query(
            'SELECT p.id, p.sku, p.name, COALESCE(s.qty,0) AS qty FROM products p
             LEFT JOIN stocks s ON s.product_id = p.id AND s.location_id = ?
             WHERE p.is_active = 1 ORDER BY p.name', [$loc])->getResultArray();
        $last = $this->db->table('cash_shifts')->where('location_id', $loc)->where('status', 'closed')->orderBy('id', 'DESC')->get(1)->getRowArray();
        return view('shift/open', ['used' => $used, 'products' => $products, 'last' => $last]);
    }

    public function open()
    {
        $loc  = loc_id();
        $no   = (int) $this->request->getPost('shift_no');
        $cash = trim((string) $this->request->getPost('opening_cash'));
        $stk  = (array) $this->request->getPost('stock');

        if (! isset(ShiftService::LABELS[$no])) return $this->back('/shift', 'Pilih shift 1 atau shift 2.', 'error')->withInput();
        $digits = preg_replace('/\D/', '', $cash);
        if ($digits === '') return $this->back('/shift', 'Uang modal wajib diisi (isi 0 bila tanpa modal).', 'error')->withInput();
        $cash = (float) $digits;

        $products = $this->db->table('products')->select('id, name')->where('is_active', 1)->get()->getResultArray();
        $count = [];
        foreach ($products as $p) {
            $v = $stk[$p['id']] ?? '';
            if ($v === '' || ! ctype_digit((string) $v)) {
                return $this->back('/shift', 'Stok awal "' . $p['name'] . '" wajib diisi (angka, isi 0 bila kosong).', 'error')->withInput();
            }
            $count[(int) $p['id']] = (int) $v;
        }

        $db = $this->db;
        $db->transBegin();
        try {
            if (ShiftService::current($loc)) throw new \RuntimeException('Masih ada shift yang belum ditutup di lokasi ini.');
            $today = date('Y-m-d');
            if ($db->table('cash_shifts')->where('location_id', $loc)->where('shift_date', $today)->where('shift_no', $no)->countAllResults()) {
                throw new \RuntimeException(ShiftService::LABELS[$no] . ' hari ini sudah pernah dibuka.');
            }
            $now = date('Y-m-d H:i:s');
            $db->table('cash_shifts')->insert([
                'location_id' => $loc, 'shift_no' => $no, 'shift_date' => $today, 'status' => 'open', 'cashier_id' => auth_user()['id'],
                'opening_cash' => $cash, 'opening_note' => trim((string) $this->request->getPost('note')) ?: null, 'opened_at' => $now,
            ]);
            $shiftId = (int) $db->insertID();

            $cur = array_column($db->table('stocks')->select('product_id, qty')->where('location_id', $loc)->get()->getResultArray(), 'qty', 'product_id');
            foreach ($count as $pid => $qty) {
                $sys = (int) ($cur[$pid] ?? 0);
                $db->table('shift_stocks')->insert(['shift_id' => $shiftId, 'product_id' => $pid, 'system_qty' => $sys, 'opening_qty' => $qty]);
                if ($qty !== $sys) { // stok awal hasil hitung kasir menjadi acuan; selisih dicatat sebagai penyesuaian
                    Inventory::move($loc, $pid, $qty - $sys, 'adjust', 'shift', $shiftId, 'Stok awal ' . ShiftService::LABELS[$no], (int) auth_user()['id'], null, true);
                }
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->back('/shift', $e->getMessage(), 'error')->withInput();
        }
        return $this->back('/pos', ShiftService::LABELS[$no] . ' dibuka. Selamat bertugas!');
    }

    public function closeForm()
    {
        $shift = $this->mine();
        if (! $shift) return $this->back('/shift', 'Tidak ada shift yang sedang dibuka.', 'error');
        return view('shift/close', ['shift' => $shift, 'sum' => ShiftService::summary($shift)]);
    }

    public function close()
    {
        $shift = $this->mine();
        if (! $shift) return $this->back('/shift', 'Tidak ada shift yang sedang dibuka.', 'error');
        $cash = trim((string) $this->request->getPost('closing_cash'));
        if ($cash === '' || preg_replace('/\D/', '', $cash) === '') return $this->back('/shift/close', 'Uang kas hasil hitung wajib diisi.', 'error')->withInput();
        $cash = (float) preg_replace('/\D/', '', $cash);

        $db = $this->db;
        $db->transBegin();
        try {
            $locked = $db->query('SELECT * FROM cash_shifts WHERE id = ? FOR UPDATE', [$shift['id']])->getRowArray();
            if (! $locked || $locked['status'] !== 'open') throw new \RuntimeException('Shift sudah ditutup.');
            $sum = ShiftService::summary($locked);
            $db->table('cash_shifts')->where('id', $locked['id'])->update([
                'status' => 'closed', 'closed_by' => auth_user()['id'], 'closed_at' => date('Y-m-d H:i:s'),
                'closing_cash' => $cash, 'expected_cash' => $sum['expected_cash'], 'cash_diff' => $cash - $sum['expected_cash'],
                'sales_total' => $sum['total'], 'trx_count' => $sum['trx'],
                'closing_note' => trim((string) $this->request->getPost('note')) ?: null,
            ]);
            $db->query('UPDATE shift_stocks SET closing_qty = COALESCE((SELECT qty FROM stocks st WHERE st.product_id = shift_stocks.product_id AND st.location_id = ?), 0)
                        WHERE shift_id = ?', [$locked['location_id'], $locked['id']]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->back('/shift/close', $e->getMessage(), 'error');
        }
        return $this->back('/shifts/' . $shift['id'] . '/receipt', 'Kasir ditutup. Berikut struk penutupan shift.');
    }

    public function history()
    {
        [$from, $to] = $this->dateRange();
        $from = $this->request->getGet('from') ?: date('Y-m-d', strtotime('-6 days'));
        $rows = $this->db->table('cash_shifts c')->select('c.*, u.name AS opener, l.code AS loc_code')
            ->join('users u', 'u.id = c.cashier_id', 'left')->join('locations l', 'l.id = c.location_id')
            ->where('c.location_id', loc_id())->where('c.shift_date >=', $from)->where('c.shift_date <=', $to)
            ->orderBy('c.id', 'DESC')->limit(300)->get()->getResultArray();
        return view('shift/history', compact('rows', 'from', 'to'));
    }

    public function show(int $id)
    {
        $shift = $this->db->table('cash_shifts c')->select('c.*, u.name AS opener, cu.name AS closer, l.name AS loc_name')
            ->join('users u', 'u.id = c.cashier_id', 'left')->join('users cu', 'cu.id = c.closed_by', 'left')->join('locations l', 'l.id = c.location_id')
            ->where('c.id', $id)->get()->getRowArray();
        if (! $shift || (auth_user()['role'] !== 'admin' && (int) $shift['location_id'] !== loc_id())) throw PageNotFoundException::forPageNotFound();
        $sales = $this->db->table('sales')->select('id, invoice_no, total, status, created_at')->where('shift_id', $id)->orderBy('id', 'DESC')->limit(200)->get()->getResultArray();
        return view('shift/show', ['shift' => $shift, 'sum' => ShiftService::summary($shift), 'stock' => ShiftService::stockReport($shift), 'sales' => $sales]);
    }

    /** Struk penutupan shift (80 mm). Shift yang masih buka tampil sebagai pratinjau. */
    public function receipt(int $id)
    {
        $shift = $this->db->table('cash_shifts c')->select('c.*, u.name AS opener, cu.name AS closer')
            ->join('users u', 'u.id = c.cashier_id', 'left')->join('users cu', 'cu.id = c.closed_by', 'left')->where('c.id', $id)->get()->getRowArray();
        if (! $shift || (auth_user()['role'] !== 'admin' && (int) $shift['location_id'] !== loc_id())) throw PageNotFoundException::forPageNotFound();
        return view('shift/receipt', [
            'shift' => $shift, 'loc' => loc_row((int) $shift['location_id']), 'sum' => ShiftService::summary($shift),
            'items' => ShiftService::itemsSold($id), 'stock' => ShiftService::stockReport($shift),
        ]);
    }

    /** Shift terbuka di lokasi aktif, hanya boleh ditutup oleh pembukanya atau admin. */
    private function mine(): ?array
    {
        $shift = ShiftService::current(loc_id());
        if (! $shift) return null;
        if (auth_user()['role'] !== 'admin' && (int) $shift['cashier_id'] !== (int) auth_user()['id']) {
            return null;
        }
        $shift['opener'] = $this->userName((int) $shift['cashier_id']);
        return $shift;
    }

    private function userName(int $id): string
    {
        return (string) ($this->db->table('users')->select('name')->where('id', $id)->get()->getRowArray()['name'] ?? '-');
    }
}
