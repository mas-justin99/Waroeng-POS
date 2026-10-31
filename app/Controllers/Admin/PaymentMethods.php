<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class PaymentMethods extends BaseController
{
    public const TYPES = ['cash' => 'Tunai', 'qris' => 'QRIS', 'card' => 'Kartu', 'ewallet' => 'E-Wallet', 'transfer' => 'Transfer', 'online' => 'Platform Online', 'dp' => 'DP Reservasi', 'other' => 'Lainnya'];

    public function index()
    {
        return view('admin/payment_methods', ['rows' => $this->db->table('payment_methods')->orderBy('sort')->get()->getResultArray(), 'types' => self::TYPES]);
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');
        $name = trim((string) $this->request->getPost('name'));
        $type = (string) $this->request->getPost('type');
        if ($name === '' || ! isset(self::TYPES[$type])) return $this->back('/admin/payment-methods', 'Nama dan tipe wajib diisi.', 'error');
        if ($id) {
            $this->db->table('payment_methods')->where('id', $id)->update(['name' => $name, 'type' => $type]);
        } else {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 20)) . random_int(10, 99);
            $max = (int) $this->db->query('SELECT COALESCE(MAX(sort),0) AS m FROM payment_methods')->getRowArray()['m'];
            $this->db->table('payment_methods')->insert(['code' => $code, 'name' => $name, 'type' => $type, 'sort' => $max + 1, 'is_active' => 1]);
        }
        return $this->back('/admin/payment-methods', 'Metode pembayaran disimpan.');
    }

    public function toggle(int $id)
    {
        $m = $this->db->table('payment_methods')->where('id', $id)->get()->getRowArray();
        if ($m && $m['type'] === 'cash' && $m['is_active']) {
            $others = $this->db->table('payment_methods')->where('type', 'cash')->where('is_active', 1)->where('id !=', $id)->countAllResults();
            if (! $others) return $this->back('/admin/payment-methods', 'Minimal satu metode tunai harus aktif.', 'error');
        }
        $this->db->query('UPDATE payment_methods SET is_active = 1 - is_active WHERE id = ?', [$id]);
        return $this->back('/admin/payment-methods', 'Status metode diubah.');
    }
}
