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

        // Idempotensi: transaksi dengan client_uuid yang sama tidak diproses dua kali (respon hilang / sinkron offline ulang)
        $uuid = preg_match('/^[A-Za-z0-9\-]{16,40}$/', (string) ($in['client_uuid'] ?? '')) ? (string) $in['client_uuid'] : null;
        $dup = fn () => $uuid ? $db->table('sales')->where('client_uuid', $uuid)->where('location_id', $locId)->get()->getRowArray() : null;
        if ($d = $dup()) return ['id' => (int) $d['id'], 'invoice_no' => $d['invoice_no'], 'total' => (float) $d['total'], 'change' => (float) $d['change_amount'], 'duplicate' => true];
        $offline = $uuid && ! empty($in['offline_ref']);

        $items = [];
        foreach ($in['items'] ?? [] as $it) {
            $q = (int) ($it['qty'] ?? 0);
            if ($q > 0) $items[(int) $it['id']] = ($items[(int) $it['id']] ?? 0) + $q;
        }
        if (! $items) throw new RuntimeException('Keranjang kosong.');

        $cu = db_connect()->table('users')->select('id, role')->where('id', $userId)->get()->getRowArray() ?: ['id' => $userId, 'role' => 'cashier'];
        $shift = ShiftService::forUser($locId, $cu);
        if ($offline && ! empty($in['shift_id'])) { // transaksi offline masuk ke shift saat transaksi terjadi (walau sudah ditutup)
            $os = $db->table('cash_shifts')->where('id', (int) $in['shift_id'])->where('location_id', $locId)->get()->getRowArray();
            if ($os && ((int) $os['cashier_id'] === $userId || $cu['role'] === 'admin')) $shift = $os;
        }
        if (! $shift) throw new RuntimeException('Kasir belum dibuka. Buka kasir dulu (isi modal awal & stok awal).');

        $printDelta = $items; // [product_id => qty] yang perlu dicetak ke bar
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
                $rsv = null;
                if ($m['type'] === 'dp') {
                    // DP reservasi: kunci baris reservasi agar tidak dipakai dua kali
                    $rid = (int) ($pm['reservation_id'] ?? 0);
                    if (in_array($rid, array_column($pays, 'rid'), true)) throw new RuntimeException('Reservasi yang sama tidak bisa dipakai dua kali.');
                    $rsv = $db->query('SELECT * FROM reservations WHERE id = ? AND location_id = ? FOR UPDATE', [$rid, $locId])->getRowArray();
                    if (! $rsv || in_array($rsv['status'], ['cancelled', 'no_show'], true)) throw new RuntimeException('Reservasi untuk DP tidak valid.');
                    if (! empty($rsv['dp_sale_id'])) throw new RuntimeException('DP reservasi ' . $rsv['customer_name'] . ' sudah dipakai.');
                    if ((float) $rsv['dp_amount'] <= 0) throw new RuntimeException('Reservasi ini tidak memiliki DP.');
                    $amt = min($amt, (float) $rsv['dp_amount']);
                }
                $pays[] = ['m' => $m, 'amount' => $amt, 'rid' => $rsv['id'] ?? null, 'rsv' => $rsv,
                    'ref' => $rsv ? 'DP ' . $rsv['customer_name'] . ' (' . date('d/m H:i', strtotime($rsv['reserved_at'])) . ')' : (trim((string) ($pm['reference'] ?? '')) ?: null)];
                $tender += $amt;
                if ($m['type'] === 'cash') $cashTender += $amt;
            }
            if (! $pays) throw new RuntimeException('Pilih metode pembayaran.');
            if ($tender + 0.001 < $total) throw new RuntimeException('Pembayaran kurang ' . rupiah($total - $tender) . '.');
            $change = $tender - $total;
            if ($change > $cashTender + 0.001) throw new RuntimeException('Kelebihan bayar hanya boleh pada pembayaran tunai.');

            // Buat nomor invoice
            $now = date('Y-m-d H:i:s');
            $at = $offline ? strtotime((string) ($in['offline_at'] ?? '')) : false;
            $created = ($at && $at <= time() && $at > time() - 14 * 86400) ? date('Y-m-d H:i:s', $at) : $now;
            $prefix = 'INV/' . $loc['code'] . '/' . date('ymd', strtotime($created)) . '/';
            $n = $db->table('sales')->like('invoice_no', $prefix, 'after')->countAllResults() + 1;
            $invoice = $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);

            $db->table('sales')->insert([
                'invoice_no' => $invoice, 'location_id' => $locId, 'cashier_id' => $userId, 'shift_id' => $shift['id'],
                'order_type' => in_array($in['order_type'] ?? '', ['dine_in', 'take_away', 'delivery'], true) ? $in['order_type'] : 'dine_in',
                'table_no' => trim((string) ($in['table_no'] ?? '')) ?: null,
                'customer_name' => trim((string) ($in['customer'] ?? '')) ?: null,
                'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'service' => $service,
                'total' => $total, 'paid' => $tender, 'change_amount' => $change, 'cogs' => 0, 'status' => 'paid',
                'note' => trim((string) ($in['note'] ?? '')) ?: null, 'created_at' => $created,
                'client_uuid' => $uuid, 'offline_ref' => $offline ? mb_substr((string) $in['offline_ref'], 0, 40) : null,
            ]);
            $saleId = (int) $db->insertID();

            $cogs = 0;
            foreach ($lines as $l) {
                $mv = Inventory::move($locId, (int) $l['p']['id'], -$l['qty'], 'sale', 'sale', $saleId, $invoice, $userId, null, $offline);
                $cost = $mv['avg_cost'];
                $cogs += $cost * $l['qty'];
                $db->table('sale_items')->insert([
                    'sale_id' => $saleId, 'product_id' => $l['p']['id'], 'sku' => $l['p']['sku'], 'name' => $l['p']['name'],
                    'qty' => $l['qty'], 'price' => $l['p']['price'], 'cost' => $cost, 'subtotal' => $l['sub'],
                ]);
            }
            $db->table('sales')->where('id', $saleId)->update(['cogs' => round($cogs, 2)]);

            // Bill meja yang dibayar ditutup & ditautkan ke penjualan ini
            $billId = (int) ($in['bill_id'] ?? 0);
            if ($billId) {
                $bill = $db->query("SELECT id FROM table_bills WHERE id = ? AND location_id = ? AND status = 'open' FOR UPDATE", [$billId, $locId])->getRowArray();
                if (! $bill) throw new RuntimeException('Bill meja sudah ditutup (mungkin sudah dibayar atau dibatalkan). Muat ulang halaman kasir.');
                $printed = [];
                foreach ($db->table('table_bill_items')->where('bill_id', $billId)->get()->getResultArray() as $bi) $printed[(int) $bi['product_id']] = (int) $bi['printed_qty'];
                $printDelta = PrintService::delta($items, $printed);
                $db->table('table_bills')->where('id', $billId)->update(['status' => 'paid', 'sale_id' => $saleId, 'closed_at' => $now, 'updated_at' => $now]);
            }

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
                    'amount' => $amt, 'reference' => $p['ref'], 'reservation_id' => $p['rid'],
                ]);
                if ($p['rid']) {
                    $upd = ['dp_sale_id' => $saleId, 'updated_at' => $now];
                    if (in_array($p['rsv']['status'], ['booked', 'confirmed'], true)) { $upd['status'] = 'arrived'; $upd['ack_at'] = $now; }
                    $db->table('reservations')->where('id', $p['rid'])->update($upd);
                }
            }

            if (! $db->transStatus()) throw new RuntimeException('Gagal menyimpan transaksi.');
            $db->transCommit();
            $bar = PrintService::sendDelta($locId, $invoice, [
                'table' => trim((string) ($in['table_no'] ?? '')), 'ref' => $invoice, 'cashier' => PrintService::userName($userId),
                'order_type' => $db->table('sales')->select('order_type')->where('id', $saleId)->get()->getRowArray()['order_type'] ?? 'dine_in',
                'customer' => trim((string) ($in['customer'] ?? '')),
            ], ! empty($in['bar_printed']) ? [] : $printDelta, ! empty($in['tablet_print'])); // bon bar sudah dicetak dari perangkat (offline / tablet_print)
            return ['id' => $saleId, 'invoice_no' => $invoice, 'total' => $total, 'change' => $change, 'bar' => $bar];
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($d = $dup()) return ['id' => (int) $d['id'], 'invoice_no' => $d['invoice_no'], 'total' => (float) $d['total'], 'change' => (float) $d['change_amount'], 'duplicate' => true];
            throw $e instanceof RuntimeException ? $e : new RuntimeException('Gagal menyimpan transaksi: ' . $e->getMessage());
        }
    }

    /** Boleh mengubah transaksi? null = boleh, selain itu alasan penolakan. Admin: semua; kasir: transaksinya sendiri di shift yang masih terbuka. */
    public static function editBlock(array $sale, array $user): ?string
    {
        if ($sale['status'] !== 'paid') return 'Hanya transaksi lunas yang bisa diubah.';
        if ($user['role'] === 'admin') return null;
        if ((int) $sale['cashier_id'] !== (int) $user['id']) return 'Kasir hanya bisa mengubah transaksinya sendiri.';
        $sh = db_connect()->table('cash_shifts')->where('id', (int) $sale['shift_id'])->get()->getRowArray();
        if (! $sh || $sh['status'] !== 'open') return 'Shift transaksi ini sudah ditutup. Minta admin untuk mengubahnya.';
        return null;
    }

    /** Persen service & pajak yang berlaku saat transaksi (diturunkan dari angka tersimpan). */
    public static function ratios(array $sale): array
    {
        $base = (float) $sale['subtotal'] - (float) $sale['discount'];
        $sp = $base > 0 ? round((float) $sale['service'] / $base * 100, 2) : 0.0;
        $tp = ($base + (float) $sale['service']) > 0 ? round((float) $sale['tax'] / ($base + (float) $sale['service']) * 100, 2) : 0.0;
        return [$sp, $tp];
    }

    /**
     * Ubah isi transaksi yang sudah lunas. Stok: qty dikurangi -> stok kembali; qty ditambah -> stok berkurang.
     * Selisih total: kurang bayar -> pembayaran tambahan wajib; kelebihan -> dicatat sebagai pengembalian.
     * @param array $in {items:[{id,qty}], discount, reason, payments:[{method_id,amount,reference}], refund_method_id}
     */
    public static function edit(int $saleId, array $in, array $user): array
    {
        $db = db_connect();
        $uid = (int) $user['id'];
        $reason = trim((string) ($in['reason'] ?? ''));
        if ($reason === '') throw new RuntimeException('Alasan perubahan wajib diisi.');
        $new = [];
        foreach ($in['items'] ?? [] as $it) {
            $q = (int) ($it['qty'] ?? 0);
            if ($q > 0) $new[(int) $it['id']] = ($new[(int) $it['id']] ?? 0) + $q;
        }
        if (! $new) throw new RuntimeException('Transaksi harus punya minimal satu item. Untuk membatalkan seluruhnya gunakan Void.');

        $db->transBegin();
        try {
            $s = $db->query('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$saleId])->getRowArray();
            if (! $s) throw new RuntimeException('Transaksi tidak ditemukan.');
            if ($m = self::editBlock($s, $user)) throw new RuntimeException($m);
            $locId = (int) $s['location_id'];

            $oldRows = array_column($db->table('sale_items')->where('sale_id', $saleId)->get()->getResultArray(), null, 'product_id');
            $add = array_diff(array_keys($new), array_keys($oldRows));
            $prods = $add ? array_column($db->table('products')->whereIn('id', $add)->where('is_active', 1)->get()->getResultArray(), null, 'id') : [];
            foreach ($add as $pid) if (! isset($prods[$pid])) throw new RuntimeException('Ada produk yang tidak aktif atau tidak ditemukan.');

            // Total baru (harga item lama dipertahankan; item baru memakai harga sekarang)
            $subtotal = 0;
            foreach ($new as $pid => $q) $subtotal += ($oldRows[$pid]['price'] ?? $prods[$pid]['price']) * $q;
            $dv = array_key_exists('discount', $in) ? max(0, (float) $in['discount']) : (float) $s['discount'];
            $discount = min($dv, $subtotal);
            [$sp, $tp] = self::ratios($s);
            $base = $subtotal - $discount;
            $service = round($base * $sp / 100);
            $tax = round(($base + $service) * $tp / 100);
            $total = $base + $service + $tax;
            $diff = $total - (float) $s['total'];

            // Stok & baris item
            $cogs = 0;
            $changes = [];
            $barDelta = [];
            $ids = array_unique(array_merge(array_keys($new), array_keys($oldRows)));
            sort($ids);
            foreach ($ids as $pid) {
                $oq = (int) ($oldRows[$pid]['qty'] ?? 0);
                $nq = (int) ($new[$pid] ?? 0);
                $name = $oldRows[$pid]['name'] ?? $prods[$pid]['name'];
                $cost = (float) ($oldRows[$pid]['cost'] ?? 0);
                if ($nq !== $oq) {
                    $mv = Inventory::move($locId, (int) $pid, $oq - $nq, 'sale_edit', 'sale', $saleId, 'Ubah ' . $s['invoice_no'], $uid, $oq ? $cost : null, $nq < $oq);
                    if (! $oq) $cost = $mv['avg_cost'];
                    $changes[] = ['name' => $name, 'old' => $oq, 'new' => $nq];
                    $barDelta[$pid] = $nq - $oq;
                }
                if ($nq > 0) {
                    $price = (float) ($oldRows[$pid]['price'] ?? $prods[$pid]['price']);
                    $cogs += $cost * $nq;
                    if ($oq && $nq !== $oq) $db->table('sale_items')->where('id', $oldRows[$pid]['id'])->update(['qty' => $nq, 'subtotal' => $price * $nq]);
                    elseif (! $oq) $db->table('sale_items')->insert(['sale_id' => $saleId, 'product_id' => $pid, 'sku' => $prods[$pid]['sku'], 'name' => $name, 'qty' => $nq, 'price' => $price, 'cost' => $cost, 'subtotal' => $price * $nq]);
                } elseif ($oq) {
                    $db->table('sale_items')->where('id', $oldRows[$pid]['id'])->delete();
                }
            }
            if (! $changes && abs($diff) < 0.001 && abs($discount - (float) $s['discount']) < 0.001) throw new RuntimeException('Tidak ada perubahan.');
            if (! $changes) $cogs = (float) $s['cogs'];

            // Selisih uang
            $paid = (float) $s['paid'];
            $changeAmt = (float) $s['change_amount'];
            $note = null;
            if ($diff > 0.001) {
                $methods = array_column($db->table('payment_methods')->where('is_active', 1)->get()->getResultArray(), null, 'id');
                $tender = 0; $cashT = 0; $pays = [];
                foreach ($in['payments'] ?? [] as $pm) {
                    $amt = (float) ($pm['amount'] ?? 0);
                    $m = $methods[(int) ($pm['method_id'] ?? 0)] ?? null;
                    if ($amt <= 0 || ! $m) continue;
                    if ($m['type'] === 'dp') throw new RuntimeException('DP tidak bisa dipakai untuk pembayaran tambahan.');
                    $pays[] = ['m' => $m, 'amount' => $amt, 'ref' => trim((string) ($pm['reference'] ?? '')) ?: null];
                    $tender += $amt;
                    if ($m['type'] === 'cash') $cashT += $amt;
                }
                if ($tender + 0.001 < $diff) throw new RuntimeException('Kekurangan bayar ' . rupiah($diff) . ', pembayaran tambahan baru ' . rupiah($tender) . '.');
                $extraChange = $tender - $diff;
                if ($extraChange > $cashT + 0.001) throw new RuntimeException('Kelebihan bayar hanya boleh pada pembayaran tunai.');
                $rem = $extraChange;
                foreach ($pays as $p) {
                    $amt = $p['amount'];
                    if ($p['m']['type'] === 'cash' && $rem > 0) { $c = min($rem, $amt); $amt -= $c; $rem -= $c; }
                    if ($amt <= 0) continue;
                    $db->table('sale_payments')->insert(['sale_id' => $saleId, 'method_id' => $p['m']['id'], 'method_name' => $p['m']['name'], 'amount' => $amt, 'reference' => $p['ref'] ?? 'Tambahan ubah transaksi']);
                }
                $paid += $tender;
                $changeAmt += $extraChange;
            } elseif ($diff < -0.001) {
                $rm = $db->table('payment_methods')->where('id', (int) ($in['refund_method_id'] ?? 0))->where('is_active', 1)->where('type !=', 'dp')->get()->getRowArray()
                    ?: $db->table('payment_methods')->where('type', 'cash')->where('is_active', 1)->orderBy('id')->get()->getRowArray();
                if (! $rm) throw new RuntimeException('Metode untuk pengembalian tidak ditemukan.');
                $db->table('sale_payments')->insert(['sale_id' => $saleId, 'method_id' => $rm['id'], 'method_name' => $rm['name'], 'amount' => $diff, 'reference' => 'Pengembalian ubah transaksi']);
                $paid += $diff;
            }

            $db->table('sales')->where('id', $saleId)->update([
                'subtotal' => $subtotal, 'discount' => $discount, 'service' => $service, 'tax' => $tax, 'total' => $total,
                'paid' => $paid, 'change_amount' => $changeAmt, 'cogs' => round($cogs, 2), 'edit_count' => (int) $s['edit_count'] + 1,
            ]);
            $db->table('sale_edits')->insert([
                'sale_id' => $saleId, 'user_id' => $uid, 'reason' => mb_substr($reason, 0, 200), 'old_total' => $s['total'], 'new_total' => $total, 'diff' => $diff,
                'detail' => json_encode(['changes' => $changes, 'discount' => [(float) $s['discount'], $discount]], JSON_UNESCAPED_UNICODE), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $db->transStatus()) throw new RuntimeException('Gagal menyimpan perubahan.');
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e instanceof RuntimeException ? $e : new RuntimeException('Gagal mengubah transaksi: ' . $e->getMessage());
        }
        PrintService::sendDelta($locId, $s['invoice_no'] . ' (ubah)', ['table' => (string) $s['table_no'], 'ref' => $s['invoice_no'], 'cashier' => PrintService::userName($uid),
            'order_type' => $s['order_type'], 'customer' => (string) $s['customer_name']], $barDelta);
        return ['total' => $total, 'diff' => $diff, 'change' => $diff > 0 ? max(0, $paid - (float) $s['paid'] - $diff) : 0, 'changes' => count($changes)];
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
            // DP yang dipakai transaksi ini dikembalikan ke reservasi (bisa dipakai lagi)
            $db->table('reservations')->where('dp_sale_id', $saleId)->update(['dp_sale_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
