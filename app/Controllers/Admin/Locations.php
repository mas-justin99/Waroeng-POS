<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Locations extends BaseController
{
    public function index()
    {
        return view('admin/locations', ['rows' => locations_all(false)]);
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->request->getPost('code')));
        $name = trim((string) $this->request->getPost('name'));
        if ($code === '' || $name === '') return $this->back('/admin/locations', 'Kode dan nama lokasi wajib diisi.', 'error');
        if ($this->db->table('locations')->where('code', $code)->where('id !=', $id)->countAllResults()) {
            return $this->back('/admin/locations', "Kode $code sudah dipakai.", 'error');
        }
        $data = [
            'code' => substr($code, 0, 10), 'name' => $name, 'address' => trim((string) $this->request->getPost('address')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'tax_percent' => min(100, max(0, (float) $this->request->getPost('tax_percent'))),
            'service_percent' => min(100, max(0, (float) $this->request->getPost('service_percent'))),
            'receipt_footer' => trim((string) $this->request->getPost('receipt_footer')), 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id) $this->db->table('locations')->where('id', $id)->update($data);
        else $this->db->table('locations')->insert($data + ['is_active' => 1, 'created_at' => $data['updated_at']]);
        return $this->back('/admin/locations', 'Lokasi disimpan.');
    }

    public function toggle(int $id)
    {
        $this->db->query('UPDATE locations SET is_active = 1 - is_active WHERE id = ?', [$id]);
        return $this->back('/admin/locations', 'Status lokasi diubah.');
    }
}
