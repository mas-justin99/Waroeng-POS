<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Categories extends BaseController
{
    public function index()
    {
        $rows = $this->db->query('SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS n FROM categories c ORDER BY c.name')->getResultArray();
        return view('admin/categories', compact('rows'));
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') return $this->back('/admin/categories', 'Nama kategori wajib diisi.', 'error');
        $now = date('Y-m-d H:i:s');
        if ($id) $this->db->table('categories')->where('id', $id)->update(['name' => $name, 'updated_at' => $now]);
        else $this->db->table('categories')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        return $this->back('/admin/categories', 'Kategori disimpan.');
    }

    public function delete(int $id)
    {
        if ($this->db->table('products')->where('category_id', $id)->countAllResults()) {
            return $this->back('/admin/categories', 'Kategori masih dipakai produk.', 'error');
        }
        $this->db->table('categories')->where('id', $id)->delete();
        return $this->back('/admin/categories', 'Kategori dihapus.');
    }
}
