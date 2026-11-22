<?php

namespace App\Libraries;

/** Tiket order bar (ESC/POS) + antrean cetak untuk printer LAN. Tidak pernah menggagalkan transaksi. */
class PrintService
{
    private const ESC = "\x1B", GS = "\x1D";

    private static function ascii(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return preg_replace('/[^\x20-\x7E]/', '', $t === false ? $s : $t);
    }

    private static function wrap(string $s, int $w): array
    {
        $out = [];
        foreach (explode("\n", wordwrap(self::ascii($s), max(8, $w), "\n", true)) as $l) $out[] = $l;
        return $out ?: [''];
    }

    /**
     * @param array $meta  {title, table, ref, order_type, cashier, customer}
     * @param array $lines [[name, qty]]  (qty boleh negatif untuk BATAL)
     */
    public static function ticket(array $meta, array $lines, int $cols = 32, bool $cut = true): string
    {
        $E = self::ESC; $G = self::GS;
        $o = $E . '@' . $E . 't' . "\x00";
        $o .= $E . 'a' . "\x01" . $E . 'E' . "\x01" . $G . '!' . "\x11" . self::ascii($meta['title'] ?? 'BAR') . "\n" . $G . '!' . "\x00" . $E . 'E' . "\x00";
        $o .= $E . 'a' . "\x00";
        if (! empty($meta['table'])) $o .= $E . 'E' . "\x01" . $G . '!' . "\x11" . 'MEJA: ' . self::ascii(mb_substr($meta['table'], 0, 12)) . "\n" . $G . '!' . "\x00" . $E . 'E' . "\x00";
        $info = [];
        if (! empty($meta['ref'])) $info[] = $meta['ref'];
        $info[] = date('d/m/Y H:i') . (! empty($meta['cashier']) ? '  ' . $meta['cashier'] : '');
        if (! empty($meta['order_type'])) $info[] = order_type_label($meta['order_type']) . (! empty($meta['customer']) ? ' - ' . $meta['customer'] : '');
        foreach ($info as $i) foreach (self::wrap($i, $cols) as $l) $o .= $l . "\n";
        $o .= str_repeat('-', $cols) . "\n";
        foreach ($lines as [$name, $qty]) {
            $q = ($qty < 0 ? '-' : '') . abs($qty) . 'x ';
            $w = $cols - strlen($q);
            $o .= $E . 'E' . "\x01" . $G . '!' . "\x01"; // tebal + tinggi ganda (lebar normal agar muat)
            foreach (self::wrap($name, $w) as $k => $l) $o .= ($k === 0 ? $q : str_repeat(' ', strlen($q))) . $l . "\n";
            $o .= $G . '!' . "\x00" . $E . 'E' . "\x00";
        }
        $o .= str_repeat('-', $cols) . "\n\n\n\n";
        if ($cut) $o .= $G . 'V' . "\x42" . "\x00";
        return $o;
    }

    /** Antrekan satu tiket ke semua printer aktif di outlet. @return int jumlah job */
    public static function enqueue(int $locId, string $kind, string $ref, array $meta, array $lines): int
    {
        if (! $lines) return 0;
        try {
            $db = db_connect();
            $n = 0;
            foreach ($db->table('printers')->where('location_id', $locId)->where('is_active', 1)->get()->getResultArray() as $p) {
                $bytes = self::ticket($meta, $lines, (int) $p['cols'], (bool) $p['cut']);
                $db->table('print_jobs')->insert([
                    'location_id' => $locId, 'printer_id' => $p['id'], 'kind' => $kind, 'ref' => mb_substr($ref, 0, 60),
                    'payload' => base64_encode($bytes), 'status' => 'pending', 'attempts' => 0, 'created_at' => date('Y-m-d H:i:s'),
                ]);
                $n++;
            }
            return $n;
        } catch (\Throwable $e) {
            log_message('error', 'PrintService enqueue: ' . $e->getMessage());
            return 0;
        }
    }

    /** Hanya baris produk bar dari daftar [product_id => qty]. @return array<int,array{0:string,1:int}> */
    public static function barLines(array $qtyByProduct): array
    {
        if (! $qtyByProduct) return [];
        $rows = db_connect()->table('products')->select('id, name')->whereIn('id', array_keys($qtyByProduct))->where('is_bar', 1)->orderBy('name')->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) if ($qtyByProduct[$r['id']] != 0) $out[] = [$r['name'], (int) $qtyByProduct[$r['id']]];
        return $out;
    }

    /** Kirim selisih [product_id => delta]: positif = tiket ORDER, negatif = tiket BATAL. */
    public static function sendDelta(int $locId, string $ref, array $meta, array $delta): void
    {
        $lines = self::barLines($delta);
        $add = array_values(array_filter($lines, fn ($l) => $l[1] > 0));
        $del = array_values(array_filter($lines, fn ($l) => $l[1] < 0));
        if ($add) self::enqueue($locId, 'order', $ref, ['title' => 'ORDER BAR'] + $meta, $add);
        if ($del) self::enqueue($locId, 'cancel', $ref, ['title' => 'BATAL BAR'] + $meta, $del);
    }

    /** Selisih: qty sekarang - qty yang sudah tercetak (semua id dari kedua sisi). */
    public static function delta(array $now, array $printed): array
    {
        $d = [];
        foreach (array_keys($now + $printed) as $pid) $d[$pid] = (int) ($now[$pid] ?? 0) - (int) ($printed[$pid] ?? 0);
        return array_filter($d);
    }

    public static function userName(int $uid): string
    {
        return (string) (db_connect()->table('users')->select('name')->where('id', $uid)->get()->getRowArray()['name'] ?? '');
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(20));
    }
}
