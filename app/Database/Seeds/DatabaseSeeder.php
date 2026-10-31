<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $db = $this->db;

        if ($db->table('locations')->countAllResults() === 0) {
            $db->table('locations')->insertBatch([
                ['code' => 'PST', 'name' => 'Waroeng Mbok Reneo - Pusat', 'address' => 'Alamat outlet pusat', 'phone' => '',
                 'tax_percent' => 10, 'service_percent' => 0, 'receipt_footer' => 'Matur nuwun, sampun dhahar! Ditunggu kunjungan berikutnya.',
                 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'CB2', 'name' => 'Waroeng Mbok Reneo - Cabang 2', 'address' => 'Alamat cabang 2', 'phone' => '',
                 'tax_percent' => 10, 'service_percent' => 0, 'receipt_footer' => 'Matur nuwun, sampun dhahar! Ditunggu kunjungan berikutnya.',
                 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if ($db->table('users')->countAllResults() === 0) {
            $mk = fn ($name, $user, $pass, $role, $loc) => ['name' => $name, 'username' => $user,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => $role, 'location_id' => $loc,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now];
            $db->table('users')->insertBatch([
                $mk('Owner', 'admin', 'admin123', 'admin', null),
                $mk('Kasir Pusat', 'kasir', 'kasir123', 'cashier', 1),
                $mk('Dapur Pusat', 'dapur', 'dapur123', 'kitchen', 1),
                $mk('Kasir Cabang 2', 'kasir2', 'kasir123', 'cashier', 2),
                $mk('Dapur Cabang 2', 'dapur2', 'dapur123', 'kitchen', 2),
            ]);
        }

        if ($db->table('payment_methods')->countAllResults() === 0) {
            $m = [
                ['CASH', 'Tunai', 'cash'], ['QRIS', 'QRIS', 'qris'], ['DEBIT', 'Kartu Debit', 'card'],
                ['CREDIT', 'Kartu Kredit', 'card'], ['TRANSFER', 'Transfer Bank', 'transfer'],
                ['GOPAY', 'GoPay', 'ewallet'], ['OVO', 'OVO', 'ewallet'], ['DANA', 'DANA', 'ewallet'],
                ['SHOPEEPAY', 'ShopeePay', 'ewallet'], ['LINKAJA', 'LinkAja', 'ewallet'],
                ['GOFOOD', 'GoFood', 'online'], ['GRABFOOD', 'GrabFood', 'online'], ['SHOPEEFOOD', 'ShopeeFood', 'online'],
                ['DP', 'DP Reservasi', 'dp'], ['VOUCHER', 'Voucher', 'other'], ['OTHER', 'Lainnya', 'other'],
            ];
            foreach ($m as $i => [$c, $n, $t]) {
                $db->table('payment_methods')->insert(['code' => $c, 'name' => $n, 'type' => $t, 'sort' => $i, 'is_active' => 1]);
            }
        }

        if ($db->table('categories')->countAllResults() === 0) {
            foreach (['Makanan Berat', 'Lauk & Sayur', 'Minuman', 'Camilan'] as $c) {
                $db->table('categories')->insert(['name' => $c, 'created_at' => $now, 'updated_at' => $now]);
            }
            $p = [
                ['NSG-001', 'Nasi Goreng Mbok Reneo', 1, 18000, 9000], ['NPC-001', 'Nasi Pecel Komplit', 1, 15000, 7500],
                ['SOT-001', 'Soto Ayam Kampung', 1, 17000, 8500], ['RWN-001', 'Rawon Daging', 1, 25000, 13000],
                ['AYG-001', 'Ayam Goreng Kremes', 2, 16000, 8500], ['TMP-001', 'Tempe Mendoan (3 pcs)', 2, 6000, 2500],
                ['TLR-001', 'Telur Dadar', 2, 5000, 2200], ['ETH-001', 'Es Teh Manis', 3, 4000, 1000],
                ['WDG-001', 'Wedang Jahe', 3, 6000, 1800], ['ESJ-001', 'Es Jeruk', 3, 6000, 2000],
                ['PSG-001', 'Pisang Goreng', 4, 8000, 3500], ['KRP-001', 'Kerupuk Udang', 4, 3000, 1200],
            ];
            foreach ($p as [$sku, $n, $c, $price, $hpp]) {
                $db->table('products')->insert(['sku' => $sku, 'name' => $n, 'category_id' => $c, 'price' => $price,
                    'hpp' => $hpp, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}
