<?php

namespace App\Libraries;

class ShiftService
{
    public const LABELS = [1 => 'Shift 1 (Pagi)', 2 => 'Shift 2 (Sore)'];

    /** Nama shift + kasir, mis. "Shift 1 (Pagi) · Kasir 2". */
    public static function label(array $shift): string
    {
        return (self::LABELS[(int) $shift['shift_no']] ?? 'Shift') . ' · Kasir ' . (int) ($shift['register_no'] ?? 1);
    }

    /**
     * Shift terbuka milik seorang kasir di sebuah lokasi. Tanpa $userId (admin): shift terbuka dengan nomor kasir terkecil.
     * Satu outlet boleh punya beberapa shift terbuka sekaligus (satu per kasir/register).
     */
    public static function current(int $locId, ?int $userId = null): ?array
    {
        $b = db_connect()->table('cash_shifts')->where('location_id', $locId)->where('status', 'open');
        if ($userId) $b->where('cashier_id', $userId);
        return $b->orderBy('register_no')->orderBy('id')->get(1)->getRowArray() ?: null;
    }

    /** Shift yang dipakai pengguna saat ini: kasir -> miliknya sendiri; admin -> kasir pertama yang terbuka. */
    public static function forUser(int $locId, array $user): ?array
    {
        return self::current($locId, $user['role'] === 'admin' ? null : (int) $user['id']);
    }

    /** Semua shift terbuka di lokasi, lengkap dengan nama kasir. */
    public static function openList(int $locId): array
    {
        return db_connect()->query("SELECT c.*, u.name AS opener FROM cash_shifts c LEFT JOIN users u ON u.id = c.cashier_id
                                    WHERE c.location_id = ? AND c.status = 'open' ORDER BY c.register_no, c.id", [$locId])->getResultArray();
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
        // terjual per kasir/shift: dihitung dari transaksi shift ini (stok outlet dipakai bersama beberapa kasir)
        $soldBy = array_column($db->query("SELECT i.product_id, SUM(i.qty) AS q FROM sale_items i JOIN sales s ON s.id = i.sale_id
                                           WHERE s.shift_id = ? AND s.status = 'paid' GROUP BY i.product_id", [$shift['id']])->getResultArray(), 'q', 'product_id');
        foreach ($rows as &$r) {
            $x = $m[$r['product_id']] ?? [];
            $r['in']     = $x['receipt'] ?? 0;
            $r['sold']   = (int) ($soldBy[$r['product_id']] ?? 0);
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
