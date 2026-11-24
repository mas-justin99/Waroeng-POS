<?php

namespace App\Libraries;

use RuntimeException;

/** Bill per meja: simpan / gabung / hitung estimasi total. */
class BillService
{
    /** @return array<int,array{id:int,sku:string,name:string,price:float,qty:int,sub:float}> */
    public static function lines(int $billId): array
    {
        $rows = db_connect()->query('SELECT p.id, p.sku, p.name, p.price, i.qty FROM table_bill_items i JOIN products p ON p.id = i.product_id WHERE i.bill_id = ? ORDER BY i.id', [$billId])->getResultArray();
        foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['qty'] = (int) $r['qty']; $r['price'] = (float) $r['price']; $r['sub'] = $r['price'] * $r['qty']; }
        return $rows;
    }

    /** @return array<int,int> product_id => qty yang sudah tercetak ke bar */
    public static function printedMap(int $billId): array
    {
        $m = [];
        foreach (db_connect()->table('table_bill_items')->where('bill_id', $billId)->get()->getResultArray() as $r) $m[(int) $r['product_id']] = (int) $r['printed_qty'];
        return $m;
    }

    /** Perkiraan subtotal/diskon/service/pajak/total (rumus sama dengan checkout). */
    public static function totals(array $lines, array $bill, array $loc): array
    {
        $sub = array_sum(array_column($lines, 'sub'));
        $dv = max(0, (float) ($bill['discount_value'] ?? 0));
        $disc = ($bill['discount_type'] ?? 'nominal') === 'percent' ? round($sub * min($dv, 100) / 100) : $dv;
        $disc = min($disc, $sub);
        $base = $sub - $disc;
        $svc = round($base * (float) ($loc['service_percent'] ?? 0) / 100);
        $tax = round(($base + $svc) * (float) ($loc['tax_percent'] ?? 0) / 100);
        return ['subtotal' => $sub, 'discount' => $disc, 'service' => $svc, 'tax' => $tax, 'total' => $base + $svc + $tax, 'qty' => array_sum(array_column($lines, 'qty'))];
    }

    /**
     * Simpan bill. Tanpa $id: buat baru, atau GABUNG ke bill terbuka di meja yang sama.
     * @return array{id:int,table_no:string,merged:bool}
     */
    public static function save(array $in, int $locId, int $userId): array
    {
        $db = db_connect();
        $table = mb_substr(trim((string) ($in['table_no'] ?? '')), 0, 20);
        if ($table === '') throw new RuntimeException('Nomor meja wajib diisi.');
        $items = [];
        foreach ($in['items'] ?? [] as $it) {
            $q = (int) ($it['qty'] ?? 0);
            if ($q > 0) $items[(int) $it['id']] = min(999, ($items[(int) $it['id']] ?? 0) + $q);
        }
        if (! $items) throw new RuntimeException('Pesanan masih kosong.');
        $ok = array_column($db->table('products')->select('id')->whereIn('id', array_keys($items))->where('is_active', 1)->get()->getResultArray(), 'id');
        if (count($ok) !== count($items)) throw new RuntimeException('Ada menu yang sudah tidak aktif.');

        $id = (int) ($in['id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $meta = [
            'customer_name' => trim((string) ($in['customer'] ?? '')) ?: null,
            'order_type' => in_array($in['order_type'] ?? '', ['dine_in', 'take_away', 'delivery'], true) ? $in['order_type'] : 'dine_in',
            'discount_type' => ($in['discount_type'] ?? '') === 'percent' ? 'percent' : 'nominal',
            'discount_value' => max(0, (float) ($in['discount_value'] ?? 0)), 'updated_at' => $now,
        ];
        $oldPrinted = [];
        $db->transBegin();
        try {
            $merged = false;
            $same = $db->query("SELECT * FROM table_bills WHERE location_id = ? AND status = 'open' AND LOWER(table_no) = LOWER(?) FOR UPDATE", [$locId, $table])->getRowArray();
            if ($id) {
                $bill = $db->query("SELECT * FROM table_bills WHERE id = ? AND location_id = ? AND status = 'open' FOR UPDATE", [$id, $locId])->getRowArray();
                if (! $bill) throw new RuntimeException('Bill tidak ditemukan atau sudah ditutup.');
                if ($same && (int) $same['id'] !== $id) throw new RuntimeException('Meja ' . $table . ' sedang dipakai bill lain. Buka bill itu atau pilih meja lain.');
                $db->table('table_bills')->where('id', $id)->update($meta + ['table_no' => $table]);
                $oldPrinted = self::printedMap($id);
                $db->table('table_bill_items')->where('bill_id', $id)->delete();
            } elseif ($same) { // meja sudah punya bill terbuka -> tambahkan pesanan
                $id = (int) $same['id'];
                $merged = true;
                $oldPrinted = self::printedMap($id);
                foreach ($db->table('table_bill_items')->where('bill_id', $id)->get()->getResultArray() as $old) {
                    $items[(int) $old['product_id']] = min(999, ($items[(int) $old['product_id']] ?? 0) + (int) $old['qty']);
                }
                $db->table('table_bill_items')->where('bill_id', $id)->delete();
                $db->table('table_bills')->where('id', $id)->update(['updated_at' => $now] + array_filter(['customer_name' => $meta['customer_name']]));
            } else {
                $db->table('table_bills')->insert($meta + ['location_id' => $locId, 'table_no' => $table, 'status' => 'open', 'opened_by' => $userId, 'opened_at' => $now]);
                $id = (int) $db->insertID();
            }
            ksort($items);
            // printed_qty: yang sudah tercetak ke bar; selisihnya dikirim sebagai ORDER (tambah) / BATAL (kurang)
            $delta = PrintService::delta($items, $oldPrinted);
            $barIds = array_column($db->table('products')->select('id')->whereIn('id', array_keys($items + $oldPrinted))->where('is_bar', 1)->get()->getResultArray(), 'id');
            $delta = array_intersect_key($delta, array_flip($barIds));
            foreach ($items as $pid => $q) {
                $db->table('table_bill_items')->insert(['bill_id' => $id, 'product_id' => $pid, 'qty' => $q, 'printed_qty' => in_array($pid, $barIds) ? $q : 0]);
            }
            if (! $db->transStatus()) throw new RuntimeException('Gagal menyimpan bill.');
            $db->transCommit();
            $bar = PrintService::sendDelta($locId, 'Meja ' . $table, [
                'table' => $table, 'ref' => 'Bill meja ' . $table, 'cashier' => PrintService::userName($userId),
                'order_type' => $meta['order_type'], 'customer' => (string) ($meta['customer_name'] ?? ''),
            ], $delta, ! empty($in['tablet_print']));
            return ['id' => $id, 'table_no' => $table, 'merged' => $merged, 'bar' => $bar];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e instanceof RuntimeException ? $e : new RuntimeException('Gagal menyimpan bill: ' . $e->getMessage());
        }
    }
}
