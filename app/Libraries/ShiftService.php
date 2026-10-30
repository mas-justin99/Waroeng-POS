<?php

namespace App\Libraries;

class ShiftService
{
    public const LABELS = [1 => 'Shift 1 (Pagi)', 2 => 'Shift 2 (Sore)'];

    /** Shift yang sedang dibuka di sebuah lokasi (maks. satu). */
    public static function current(int $locId): ?array
    {
        return db_connect()->table('cash_shifts')->where('location_id', $locId)->where('status', 'open')->get()->getRowArray() ?: null;
    }

    /** Ringkasan penjualan & kas sebuah shift. */
    public static function summary(array $shift): array
    {
        $db  = db_connect();
        $id  = (int) $shift['id'];
        $tot = $db->query("SELECT COUNT(*) AS trx, COALESCE(SUM(total),0) AS total, COALESCE(SUM(discount),0) AS disc,
                                  COALESCE(SUM(subtotal),0) AS sub, COALESCE(SUM(tax),0) AS tax, COALESCE(SUM(service),0) AS svc
                           FROM sales WHERE shift_id = ? AND status = 'paid'", [$id])->getRowArray();
        $qty = (int) ($db->query("SELECT COALESCE(SUM(i.qty),0) AS q FROM sale_items i JOIN sales s ON s.id = i.sale_id
                                  WHERE s.shift_id = ? AND s.status = 'paid'", [$id])->getRowArray()['q'] ?? 0);
        $void = $db->query("SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS t FROM sales WHERE shift_id = ? AND status = 'void'", [$id])->getRowArray();
        $pays = $db->query("SELECT p.method_name AS name, pm.type AS type, SUM(p.amount) AS amount
                            FROM sale_payments p JOIN sales s ON s.id = p.sale_id LEFT JOIN payment_methods pm ON pm.id = p.method_id
                            WHERE s.shift_id = ? AND s.status = 'paid' GROUP BY p.method_name, pm.type ORDER BY amount DESC", [$id])->getResultArray();
        $cash = 0.0;
        foreach ($pays as $p) {
            if ($p['type'] === 'cash') $cash += (float) $p['amount'];
        }
        return [
            'trx' => (int) $tot['trx'], 'total' => (float) $tot['total'], 'discount' => (float) $tot['disc'],
            'subtotal' => (float) $tot['sub'], 'tax' => (float) $tot['tax'], 'service' => (float) $tot['svc'], 'qty_sold' => $qty,
            'void_count' => (int) $void['c'], 'void_total' => (float) $void['t'],
            'payments' => $pays, 'cash_sales' => $cash,
            'expected_cash' => (float) $shift['opening_cash'] + $cash,
        ];
    }

    /** Pergerakan stok per produk selama shift: awal, masuk, terjual, penyesuaian, akhir. */
    public static function stockReport(array $shift): array
    {
        $db   = db_connect();
        $to   = $shift['closed_at'] ?: date('Y-m-d H:i:s');
        $rows = $db->query('SELECT ss.product_id, p.sku, p.name, ss.system_qty, ss.opening_qty, ss.closing_qty,
                                   COALESCE(st.qty, 0) AS now_qty
                            FROM shift_stocks ss JOIN products p ON p.id = ss.product_id
                            LEFT JOIN stocks st ON st.product_id = ss.product_id AND st.location_id = ?
                            WHERE ss.shift_id = ? ORDER BY p.name', [$shift['location_id'], $shift['id']])->getResultArray();
        $mv = $db->query("SELECT product_id, type, SUM(qty) AS q FROM stock_movements
                          WHERE location_id = ? AND created_at >= ? AND created_at <= ?
                            AND NOT (type = 'adjust' AND ref_type = 'shift' AND ref_id = ?)
                          GROUP BY product_id, type", [$shift['location_id'], $shift['opened_at'], $to, $shift['id']])->getResultArray();
        $m = [];
        foreach ($mv as $r) $m[$r['product_id']][$r['type']] = (int) $r['q'];
        foreach ($rows as &$r) {
            $x = $m[$r['product_id']] ?? [];
            $r['in']     = $x['receipt'] ?? 0;
            $r['sold']   = -(($x['sale'] ?? 0) + ($x['void'] ?? 0));
            $r['adjust'] = $x['adjust'] ?? 0;
            $r['end']    = $shift['status'] === 'closed' && $r['closing_qty'] !== null ? (int) $r['closing_qty'] : (int) $r['now_qty'];
            $r['diff_open'] = (int) $r['opening_qty'] - (int) $r['system_qty'];
        }
        return $rows;
    }

    /** Menu terjual selama shift (tidak termasuk transaksi void). */
    public static function itemsSold(int $shiftId): array
    {
        return db_connect()->query("SELECT i.sku, i.name, SUM(i.qty) AS qty, SUM(i.subtotal) AS total
                                    FROM sale_items i JOIN sales s ON s.id = i.sale_id
                                    WHERE s.shift_id = ? AND s.status = 'paid' GROUP BY i.sku, i.name ORDER BY qty DESC, i.name", [$shiftId])->getResultArray();
    }
}
