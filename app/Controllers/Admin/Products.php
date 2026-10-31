<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Cloud;

class Products extends BaseController
{
    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));
        $b = $this->db->table('products p')->select('p.*, c.name AS category')->join('categories c', 'c.id = p.category_id', 'left')->orderBy('p.name');
        if ($q !== '') $b->groupStart()->like('p.name', $q, 'both', null, true)->orLike('p.sku', $q, 'both', null, true)->groupEnd();
        return view('admin/products', [
            'rows' => $b->get()->getResultArray(), 'q' => $q,
            'categories' => $this->db->table('categories')->orderBy('name')->get()->getResultArray(),
            'cloud' => Cloud::configured(),
        ]);
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');
        $sku = strtoupper(trim((string) $this->request->getPost('sku')));
        $name = trim((string) $this->request->getPost('name'));
        if ($sku === '' || $name === '') return $this->back('/admin/products', 'SKU dan nama wajib diisi.', 'error');
        $dupe = $this->db->table('products')->where('sku', $sku)->where('id !=', $id)->countAllResults();
        if ($dupe) return $this->back('/admin/products', "SKU $sku sudah dipakai.", 'error');

        $data = [
            'sku' => $sku, 'name' => $name, 'category_id' => (int) $this->request->getPost('category_id') ?: null,
            'price' => max(0, (float) $this->request->getPost('price')), 'hpp' => max(0, (float) $this->request->getPost('hpp')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $file = $this->request->getFile('image');
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) || $file->getSize() > 12 * 1024 * 1024) {
                return $this->back('/admin/products', 'Gambar harus JPG/PNG/WEBP maksimal 12MB.', 'error');
            }
            [$url, $err] = Cloud::upload($file->getTempName(), 'waroeng-mbok-reneo', 800);
            if ($err) return $this->back('/admin/products', $err, 'error');
            $data['image_url'] = $url;
        }
        if ($id) {
            $this->db->table('products')->where('id', $id)->update($data);
        } else {
            $data += ['is_active' => 1, 'created_at' => $data['updated_at']];
            $this->db->table('products')->insert($data);
        }
        return $this->back('/admin/products', 'Produk disimpan.');
    }

    public function toggle(int $id)
    {
        $this->db->query('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$id]);
        return $this->back('/admin/products', 'Status produk diubah.');
    }
}
