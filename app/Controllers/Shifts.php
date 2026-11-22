<?php

namespace App\Controllers;

use App\Libraries\Inventory;
use App\Libraries\ShiftService;
use CodeIgniter\Exceptions\PageNotFoundException;

/** Buka & tutup kasir (2 shift per hari). */
class Shifts extends BaseController
{
    /** Halaman utama: form buka kasir, atau status shift milik kasir ini. */
    public function index()
    {
        $loc   = loc_id();
        $uid   = (int) auth_user()['id'];
        $open  = ShiftService::openList($loc);
        $shift = ShiftService::current($loc, $uid);
        if ($shift) {
            $shift['opener'] = $this->userName((int) $shift['cashier_id']);
            $others = array_values(array_filter($open, fn ($o) => (int) $o['id'] !== (int) $shift['id']));
            return view('shift/running', ['shift' => $shift, 'sum' => ShiftService::summary($shift), 'others' => $others]);
        }

        $regCount = max(1, (int) (loc_row()['register_count'] ?? 2));
        $usedMap = [];
        foreach ($this->db->table('cash_shifts')->select('register_no, shift_no')->where('location_id', $loc)->where('shift_date', date('Y-m-d'))->get()->getResultArray() as $r) {
            $usedMap[(int) $r['register_no']][] = (int) $r['shift_no'];
        }
        $busy = [];
        foreach ($open as $o) $busy[(int) $o['register_no']] = $o['opener'];
        $products = $this->db->query(
            'SELECT p.id, p.sku, p.name, COALESCE(s.qty,0) AS qty FROM products p
             LEFT JOIN stocks s ON s.product_id = p.id AND s.location_id = ?
             WHERE p.is_active = 1 ORDER BY p.name', [$loc])->getResultArray();
        $last = $this->db->table('cash_shifts')->where('location_id', $loc)->where('status', 'closed')->orderBy('id', 'DESC')->get(1)->getRowArray();
        return view('shift/open', ['usedMap' => $usedMap, 'busy' => $busy, 'regCount' => $regCount, 'needCount' => ! $open,
            'open' => $open, 'products' => $products, 'last' => $last]);
    }

    public function open()
    {
        $loc  = loc_id();
        $no   = (int) $this->request->getPost('shift_no');
        $reg  = (int) $this->request->getPost('register_no') ?: 1;
        $cash = trim((string) $this->request->getPost('opening_cash'));
        $stk  = (array) $this->request->getPost('stock');
        $regCount = max(1, (int) (loc_row()['register_count'] ?? 2));

        if (! isset(ShiftService::LABELS[$no])) return $this->back('/shift', 'Pilih shift 1 atau shift 2.', 'error')->withInput();
        if ($reg < 1 || $reg > $regCount) return $this->back('/shift', 'Pilih kasir yang tersedia (Kasir 1 - ' . $regCount . ').', 'error')->withInput();
        $digits = preg_replace('/\D/', '', $cash);
        if ($digits === '') return $this->back('/shift', 'Uang modal wajib diisi (isi 0 bila tanpa modal).', 'error')->withInput();
        $cash = (float) $digits;

        $db = $this->db;
        $db->transBegin();
        try {
            $uid = (int) auth_user()['id'];
            if (ShiftService::current($loc, $uid)) throw new \RuntimeException('Anda masih punya shift yang belum ditutup.');
            $open = ShiftService::openList($loc);
            foreach ($open as $o) {
                if ((int) $o['register_no'] === $reg) throw new \RuntimeException('Kasir ' . $reg . ' sedang dipakai oleh ' . $o['opener'] . '. Pilih kasir lain atau minta shiftnya ditutup dulu.');
            }
            $today = date('Y-m-d');
            if ($db->table('cash_shifts')->where('location_id', $loc)->where('shift_date', $today)->where('shift_no', $no)->where('register_no', $reg)->countAllResults()) {
                throw new \RuntimeException(ShiftService::LABELS[$no] . ' · Kasir ' . $reg . ' hari ini sudah pernah dibuka.');
            }

            // Stok awal dihitung oleh kasir yang membuka pertama. Kasir berikutnya memakai stok outlet yang sedang berjalan.
            $products = $db->table('products')->select('id, name')->where('is_active', 1)->get()->getResultArray();
            $count = [];
            if (! $open) {
                foreach ($products as $p) {
                    $v = $stk[$p['id']] ?? '';
                    if ($v === '' || ! ctype_digit((string) $v)) {
                        throw new \RuntimeException('Stok awal "' . $p['name'] . '" wajib diisi (angka, isi 0 bila kosong).');
                    }
                    $count[(int) $p['id']] = (int) $v;
                }
            }

            $now = date('Y-m-d H:i:s');
            $db->table('cash_shifts')->insert([
                'location_id' => $loc, 'shift_no' => $no, 'register_no' => $reg, 'shift_date' => $today, 'status' => 'open', 'cashier_id' => $uid,
                'opening_cash' => $cash, 'opening_note' => trim((string) $this->request->getPost('note')) ?: null, 'opened_at' => $now,
            ]);
            $shiftId = (int) $db->insertID();

            $cur = array_column($db->table('stocks')->select('product_id, qty')->where('location_id', $loc)->get()->getResultArray(), 'qty', 'product_id');
            foreach ($products as $p) {
                $pid = (int) $p['id'];
                $sys = (int) ($cur[$pid] ?? 0);
                $qty = $count[$pid] ?? $sys; // kasir kedua dst: tanpa hitung ulang (angka = stok berjalan)
                $db->table('shift_stocks')->insert(['shift_id' => $shiftId, 'product_id' => $pid, 'system_qty' => $sys, 'opening_qty' => $qty]);
                if ($qty !== $sys) { // stok awal hasil hitung kasir menjadi acuan; selisih dicatat sebagai penyesuaian
                    Inventory::move($loc, $pid, $qty - $sys, 'adjust', 'shift', $shiftId, 'Stok awal ' . ShiftService::LABELS[$no] . ' Kasir ' . $reg, $uid, null, true);
                }
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->back('/shift', $e->getMessage(), 'error')->withInput();
        }
        return $this->back('/pos', ShiftService::LABELS[$no] . ' · Kasir ' . $reg . ' dibuka. Selamat bertugas!');
    }

    public function closeForm()
    {
        $shift = $this->mine();
        if (! $shift) return $this->back('/shift', 'Tidak ada shift yang sedang dibuka.', 'error');
        return view('shift/close', ['shift' => $shift, 'sum' => ShiftService::summary($shift),
            'openBills' => $this->db->table('table_bills')->where('location_id', $shift['location_id'])->where('status', 'open')->countAllResults()]);
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
        return $this->back('/shifts/' . $shift['id'] . '/receipt', ShiftService::label($shift) . ' ditutup. Berikut struk penutupan shift.');
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

    /** Struk penutupan shift (58 mm). Shift yang masih buka tampil sebagai pratinjau. */
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

    /** Shift yang akan ditutup: milik kasir sendiri. Admin boleh menutup shift kasir mana pun lewat parameter s (id shift). */
    private function mine(): ?array
    {
        $u = auth_user();
        $loc = loc_id();
        $sid = (int) ($this->request->getVar('s') ?? 0);
        if ($u['role'] === 'admin') {
            $shift = $sid
                ? ($this->db->table('cash_shifts')->where('id', $sid)->where('location_id', $loc)->where('status', 'open')->get()->getRowArray() ?: null)
                : (ShiftService::current($loc, (int) $u['id']) ?: ShiftService::current($loc));
        } else {
            $shift = ShiftService::current($loc, (int) $u['id']);
        }
        if (! $shift) return null;
        $shift['opener'] = $this->userName((int) $shift['cashier_id']);
        return $shift;
    }

    private function userName(int $id): string
    {
        return (string) ($this->db->table('users')->select('name')->where('id', $id)->get()->getRowArray()['name'] ?? '-');
    }
}
