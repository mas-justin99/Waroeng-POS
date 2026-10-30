<?php

namespace App\Libraries;

use RuntimeException;

class SalesService
{
    /**
     * @param array $in {items:[{id,qty}], order_type, table_no, customer, note,
     *                   discount_type:'percent'|'nominal', discount_value, payments:[{method_id,amount,reference}]}
     * @return array{id:int, invoice_no:string, total:float, change:float}
     */
    public static function checkout(array $in, int $locId, int $userId): array
    {
        $db = db_connect();
        $loc = $db->table('locations')->where('id', $locId)->get()->getRowArray();
        if (! $loc) throw new RuntimeException('Lokasi tidak valid.');

        $items = [];
        foreach ($in['items'] ?? [] as $it) {
            $q = (int) ($it['qty'] ?? 0);
            if ($q > 0) $items[(int) $it['id']] = ($items[(int) $it['id']] ?? 0) + $q;
        }
        if (! $items) throw new RuntimeException('Keranjang kosong.');

        $shift = ShiftService::current($locId);
        if (! $shift) throw new RuntimeException('Kasir belum dibuka. Buka kasir dulu (isi modal awal & stok awal).');

        $db->transBegin();
        try {
            $prods = $db->table('products')->whereIn('id', array_keys($items))->where('is_active', 1)->get()->getResultArray();
            $prods = array_column($prods, null, 'id');
            $lines = [];
            $subtotal = 0;
            ksort($items); // urutan lock konsisten, hindari deadlock
            foreach ($items as $pid => $qty) {
                if (! isset($prods[$pid])) throw new RuntimeException('Ada produk yang sudah tidak aktif.');
                $p = $prods[$pid];
                $lines[] = ['p' => $p, 'qty' => $qty, 'sub' => $p['price'] * $qty];
                $subtotal += $p['price'] * $qty;
            }

            $dv = max(0, (float) ($in['discount_value'] ?? 0));
            $discount = ($in['discount_type'] ?? 'nominal') === 'percent' ? round($subtotal * min($dv, 100) / 100) : $dv;
            $discount = min($discount, $subtotal);
            $base = $subtotal - $discount;
            $service = round($base * (float) $loc['service_percent'] / 100);
            $tax = round(($base + $service) * (float) $loc['tax_percent'] / 100);
            $total = $base + $service + $tax;

            // Pembayaran
            $methods = array_column($db->table('payment_methods')->where('is_active', 1)->get()->getResultArray(), null, 'id');
            $pays = [];
            $tender = 0;
            $cashTender = 0;
            foreach ($in['payments'] ?? [] as $pm) {
                $amt = (float) ($pm['amount'] ?? 0);
                $m = $methods[(int) ($pm['method_id'] ?? 0)] ?? null;
                if ($amt <= 0 || ! $m) continue;
                $pays[] = ['m' => $m, 'amount' => $amt, 'ref' => trim((string) ($pm['reference'] ?? '')) ?: null];
                $tender += $amt;
                if ($m['type'] === 'cash') $cashTender += $amt;
            }
            if (! $pays) throw new RuntimeException('Pilih metode pembayaran.');
            if ($tender + 0.001 < $total) throw new RuntimeException('Pembayaran kurang ' . rupiah($total - $tender) . '.');
            $change = $tender - $total;
            if ($change > $cashTender + 0.001) throw new RuntimeException('Kelebihan bayar hanya boleh pada pembayaran tunai.');

            // Buat nomor invoice
            $prefix = 'INV/' . $loc['code'] . '/' . date('ymd') . '/';
            $n = $db->table('sales')->like('invoice_no', $prefix, 'after')->countAllResults() + 1;
            $invoice = $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);

            $now = date('Y-m-d H:i:s');
            $db->table('sales')->insert([
                'invoice_no' => $invoice, 'location_id' => $locId, 'cashier_id' => $userId, 'shift_id' => $shift['id'],
                'order_type' => in_array($in['order_type'] ?? '', ['dine_in', 'take_away', 'delivery'], true) ? $in['order_type'] : 'dine_in',
                'table_no' => trim((string) ($in['table_no'] ?? '')) ?: null,
                'customer_name' => trim((string) ($in['customer'] ?? '')) ?: null,
                'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'service' => $service,
                'total' => $total, 'paid' => $tender, 'change_amount' => $change, 'cogs' => 0, 'status' => 'paid',
                'note' => trim((string) ($in['note'] ?? '')) ?: null, 'created_at' => $now,
            ]);
            $saleId = (int) $db->insertID();

            $cogs = 0;
            foreach ($lines as $l) {
                $mv = Inventory::move($locId, (int) $l['p']['id'], -$l['qty'], 'sale', 'sale', $saleId, $invoice, $userId);
                $cost = $mv['avg_cost'];
                $cogs += $cost * $l['qty'];
                $db->table('sale_items')->insert([
                    'sale_id' => $saleId, 'product_id' => $l['p']['id'], 'sku' => $l['p']['sku'], 'name' => $l['p']['name'],
                    'qty' => $l['qty'], 'price' => $l['p']['price'], 'cost' => $cost, 'subtotal' => $l['sub'],
                ]);
            }
            $db->table('sales')->where('id', $saleId)->update(['cogs' => round($cogs, 2)]);

            // Kembalian dipotong dari baris tunai agar sum(pembayaran) = total
            $rem = $change;
            foreach ($pays as $p) {
                $amt = $p['amount'];
                if ($p['m']['type'] === 'cash' && $rem > 0) {
                    $cut = min($rem, $amt);
                    $amt -= $cut;
                    $rem -= $cut;
                }
                if ($amt <= 0) continue;
                $db->table('sale_payments')->insert([
                    'sale_id' => $saleId, 'method_id' => $p['m']['id'], 'method_name' => $p['m']['name'],
                    'amount' => $amt, 'reference' => $p['ref'],
                ]);
            }

            if (! $db->transStatus()) throw new RuntimeException('Gagal menyimpan transaksi.');
            $db->transCommit();
            return ['id' => $saleId, 'invoice_no' => $invoice, 'total' => $total, 'change' => $change];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e instanceof RuntimeException ? $e : new RuntimeException('Gagal menyimpan transaksi: ' . $e->getMessage());
        }
    }

    public static function void(int $saleId, string $reason, int $userId): void
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $s = $db->query('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$saleId])->getRowArray();
            if (! $s || $s['status'] !== 'paid') throw new RuntimeException('Transaksi tidak bisa di-void.');
            foreach ($db->table('sale_items')->where('sale_id', $saleId)->get()->getResultArray() as $it) {
                Inventory::move((int) $s['location_id'], (int) $it['product_id'], (int) $it['qty'], 'void', 'sale', $saleId,
                    'Void ' . $s['invoice_no'], $userId, (float) $it['cost'], true);
            }
            $db->table('sales')->where('id', $saleId)->update(['status' => 'void', 'void_reason' => $reason]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
