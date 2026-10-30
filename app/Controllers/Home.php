<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $u = auth_user();
        return redirect()->to(match ($u['role']) {
            'admin' => '/admin', 'cashier' => '/pos', default => '/po',
        });
    }

    public function switchLocation()
    {
        if (has_role('admin') && auth_user()['role'] === 'admin') {
            $id = (int) $this->request->getPost('location_id');
            if ($this->db->table('locations')->where('id', $id)->countAllResults()) {
                session()->set('loc_id', $id);
            }
        }
        $back = $this->request->getPost('back') ?: '/';
        // hanya path internal
        if (! str_starts_with($back, '/') || str_starts_with($back, '//')) $back = '/';
        return redirect()->to($back);
    }
}
