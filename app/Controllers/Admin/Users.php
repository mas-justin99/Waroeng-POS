<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Users extends BaseController
{
    public function index()
    {
        $rows = $this->db->table('users u')->select('u.*, l.name AS loc')->join('locations l', 'l.id = u.location_id', 'left')->orderBy('u.role')->orderBy('u.name')->get()->getResultArray();
        $cut = date('Y-m-d H:i:s', time() - idle_minutes() * 60);
        foreach ($rows as &$r) $r['online'] = $r['session_token'] && $r['last_seen'] && $r['last_seen'] > $cut;
        return view('admin/users', ['rows' => $rows, 'locations' => locations_all()]);
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');
        $username = strtolower(trim((string) $this->request->getPost('username')));
        $name = trim((string) $this->request->getPost('name'));
        $role = (string) $this->request->getPost('role');
        $pass = (string) $this->request->getPost('password');
        $locId = (int) $this->request->getPost('location_id') ?: null;
        if (! preg_match('/^[a-z0-9._-]{3,30}$/', $username)) return $this->back('/admin/users', 'Username 3-30 karakter (huruf kecil, angka, titik, strip).', 'error');
        if ($name === '' || ! in_array($role, ['admin', 'cashier', 'kitchen'], true)) return $this->back('/admin/users', 'Nama dan role wajib diisi.', 'error');
        if ($role !== 'admin' && ! $locId) return $this->back('/admin/users', 'Kasir/Dapur wajib punya lokasi.', 'error');
        if ($this->db->table('users')->where('username', $username)->where('id !=', $id)->countAllResults()) return $this->back('/admin/users', 'Username sudah dipakai.', 'error');
        if (($id === 0 || $pass !== '') && strlen($pass) < 6) return $this->back('/admin/users', 'Password minimal 6 karakter.', 'error');

        $data = ['name' => $name, 'username' => $username, 'role' => $role, 'location_id' => $role === 'admin' ? null : $locId, 'updated_at' => date('Y-m-d H:i:s')];
        if ($pass !== '') $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        if ($id) {
            if ($pass !== '') $data['session_token'] = null; // ganti password = paksa logout
            $this->db->table('users')->where('id', $id)->update($data);
        } else {
            $this->db->table('users')->insert($data + ['is_active' => 1, 'created_at' => $data['updated_at']]);
        }
        return $this->back('/admin/users', 'Pengguna disimpan.');
    }

    public function toggle(int $id)
    {
        if ($id === (int) auth_user()['id']) return $this->back('/admin/users', 'Tidak bisa menonaktifkan akun sendiri.', 'error');
        $this->db->query('UPDATE users SET is_active = 1 - is_active, session_token = NULL WHERE id = ?', [$id]);
        return $this->back('/admin/users', 'Status pengguna diubah.');
    }

    public function forceLogout(int $id)
    {
        $this->db->table('users')->where('id', $id)->update(['session_token' => null, 'last_seen' => null]);
        return $this->back('/admin/users', 'Pengguna dipaksa logout. Perangkat itu akan keluar pada permintaan berikutnya.');
    }
}
