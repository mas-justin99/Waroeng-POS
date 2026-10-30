<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Data contoh (opsional): stok awal + transaksi 14 hari terakhir agar dashboard tidak kosong.
 *   php spark db:seed DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;
        mt_srand(2026);
        $locs = $db->table('locations')->get()->getResultArray();
        $prods = $db->table('products')->get()->getResultArray();
        $pm = $db->table('payment_methods')->where('is_active', 1)->get()->getResultArray();
        $weights = ['CASH' => 40, 'QRIS' => 30, 'DEBIT' => 6, 'GOPAY' => 8, 'OVO' => 5, 'DANA' => 4, 'TRANSFER' => 3, 'GOFOOD' => 4];
        $pool = [];
        foreach ($pm as $m) {
            for ($i = 0; $i < ($weights[$m['code']] ?? 0); $i++) $pool[] = $m;
        }
        $users = $db->table('users')->where('role', 'cashier')->get()->getResultArray();
        $cashier = [];
        foreach ($users as $u) $cashier[$u['location_id']] = $u['id'];

        foreach ($locs as $loc) {
            $lid = (int) $loc['id'];
            // stok awal
            foreach ($prods as $p) {
                $exists = $db->table('stocks')->where('location_id', $lid)->where('product_id', $p['id'])->countAllResults();
                if ($exists) continue;
                $q = mt_rand(0, 9) === 0 ? mt_rand(0, 4) : mt_rand(12, 45);
                $db->table('stocks')->insert(['location_id' => $lid, 'product_id' => $p['id'], 'qty' => $q, 'avg_cost' => $p['hpp'],
                    'min_qty' => 5, 'updated_at' => date('Y-m-d H:i:s')]);
            }
            for ($d = 13; $d >= 0; $d--) {
                $n = mt_rand(8, 26) + ($lid === 1 ? 6 : 0);
                for ($k = 0; $k < $n; $k++) {
                    $ts = date('Y-m-d', strtotime("-$d days")) . ' ' . sprintf('%02d:%02d:%02d', mt_rand(8, 21), mt_rand(0, 59), mt_rand(0, 59));
                    if (strtotime($ts) > time()) continue;
                    $lines = [];
                    $sub = $cogs = 0;
                    foreach ((array) array_rand($prods, mt_rand(1, 4)) as $i) {
                        $p = $prods[$i]; $q = mt_rand(1, 3);
                        $lines[] = [$p, $q];
                        $sub += $p['price'] * $q; $cogs += $p['hpp'] * $q;
                    }
                    $tax = round($sub * $loc['tax_percent'] / 100);
                    $total = $sub + $tax;
                    $inv = 'INV/' . $loc['code'] . '/' . date('ymd', strtotime($ts)) . '/' . sprintf('D%04d', $k + 1);
                    if ($db->table('sales')->where('invoice_no', $inv)->countAllResults()) continue;
                    $m = $pool[array_rand($pool)];
                    $paid = $m['type'] === 'cash' ? ceil($total / 5000) * 5000 : $total;
                    $db->table('sales')->insert([
                        'invoice_no' => $inv, 'location_id' => $lid, 'cashier_id' => $cashier[$lid] ?? null,
                        'order_type' => ['dine_in', 'dine_in', 'take_away', 'delivery'][mt_rand(0, 3)], 'table_no' => (string) mt_rand(1, 12),
                        'subtotal' => $sub, 'discount' => 0, 'tax' => $tax, 'service' => 0, 'total' => $total, 'paid' => $paid,
                        'change_amount' => $paid - $total, 'cogs' => $cogs, 'status' => 'paid', 'created_at' => $ts,
                    ]);
                    $sid = (int) $db->insertID();
                    foreach ($lines as [$p, $q]) {
                        $db->table('sale_items')->insert(['sale_id' => $sid, 'product_id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'],
                            'qty' => $q, 'price' => $p['price'], 'cost' => $p['hpp'], 'subtotal' => $p['price'] * $q]);
                    }
                    $db->table('sale_payments')->insert(['sale_id' => $sid, 'method_id' => $m['id'], 'method_name' => $m['name'], 'amount' => $total]);
                }
            }
        }
    }
}
