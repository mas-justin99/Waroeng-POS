<?php

namespace App\Libraries;

use RuntimeException;

class Inventory
{
    /**
     * Mutasi stok. WAJIB dipanggil di dalam transaksi DB (memakai SELECT ... FOR UPDATE).
     * @return array{balance:int, avg_cost:float}
     */
    public static function move(int $loc, int $pid, int $qty, string $type, ?string $refType = null, ?int $refId = null,
        ?string $note = null, ?int $userId = null, ?float $unitCost = null, bool $allowNegative = false): array
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $row = $db->query('SELECT * FROM stocks WHERE location_id = ? AND product_id = ? FOR UPDATE', [$loc, $pid])->getRowArray();
        if (! $row) {
            $db->table('stocks')->insert(['location_id' => $loc, 'product_id' => $pid, 'qty' => 0, 'avg_cost' => 0, 'updated_at' => $now]);
            $row = $db->query('SELECT * FROM stocks WHERE location_id = ? AND product_id = ? FOR UPDATE', [$loc, $pid])->getRowArray();
        }
        $old = (int) $row['qty'];
        $avg = (float) $row['avg_cost'];
        $new = $old + $qty;
        if ($new < 0 && ! $allowNegative) {
            $p = $db->table('products')->select('name')->where('id', $pid)->get()->getRowArray();
            throw new RuntimeException('Stok "' . ($p['name'] ?? $pid) . '" tidak cukup (tersisa ' . $old . ').');
        }
        if ($type === 'receipt' && $unitCost !== null && $qty > 0) {
            $base = max($old, 0);
            $avg = ($base + $qty) > 0 ? (($base * $avg) + ($qty * $unitCost)) / ($base + $qty) : $unitCost;
        }
        $db->table('stocks')->where('id', $row['id'])->update(['qty' => $new, 'avg_cost' => round($avg, 2), 'updated_at' => $now]);
        $db->table('stock_movements')->insert([
            'location_id' => $loc, 'product_id' => $pid, 'type' => $type, 'qty' => $qty,
            'unit_cost' => $unitCost ?? $avg, 'balance' => $new, 'ref_type' => $refType, 'ref_id' => $refId,
            'note' => $note, 'user_id' => $userId, 'created_at' => $now,
        ]);
        return ['balance' => $new, 'avg_cost' => $avg];
    }
}
