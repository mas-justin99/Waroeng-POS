<?php

use App\Libraries\AuthService;

if (! function_exists('rupiah')) {
    function rupiah($n, bool $prefix = true): string
    {
        $s = number_format((float) $n, 0, ',', '.');
        return $prefix ? 'Rp ' . $s : $s;
    }
}

if (! function_exists('tgl')) {
    function tgl(?string $dt, string $fmt = 'd M Y H:i'): string
    {
        if (! $dt) return '-';
        $bulan = ['Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun',
                  'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des'];
        $s = date($fmt, strtotime($dt));
        return strtr($s, $bulan);
    }
}

if (! function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return AuthService::user();
    }
}

if (! function_exists('has_role')) {
    function has_role(string ...$roles): bool
    {
        $u = AuthService::user();
        return $u && ($u['role'] === 'admin' || in_array($u['role'], $roles, true));
    }
}

if (! function_exists('loc_id')) {
    /** Lokasi aktif. Non-admin terkunci pada lokasinya; admin bisa berpindah. */
    function loc_id(): int
    {
        return AuthService::locationId();
    }
}

if (! function_exists('locations_all')) {
    function locations_all(bool $onlyActive = true): array
    {
        $b = db_connect()->table('locations')->orderBy('name');
        if ($onlyActive) $b->where('is_active', 1);
        return $b->get()->getResultArray();
    }
}

if (! function_exists('loc_row')) {
    function loc_row(?int $id = null): array
    {
        static $cache = [];
        $id = $id ?: loc_id();
        return $cache[$id] ??= (db_connect()->table('locations')->where('id', $id)->get()->getRowArray() ?: []);
    }
}

if (! function_exists('role_label')) {
    function role_label(string $r): string
    {
        return ['admin' => 'Admin / Owner', 'cashier' => 'Kasir', 'kitchen' => 'Dapur'][$r] ?? $r;
    }
}

if (! function_exists('po_badge')) {
    function po_badge(string $s): string
    {
        $m = ['sent' => ['warning', 'Menunggu Diterima'], 'partial' => ['info', 'Diterima Sebagian'],
              'received' => ['success', 'Selesai Diterima'], 'cancelled' => ['secondary', 'Dibatalkan'],
              'paid' => ['success', 'Lunas'], 'void' => ['danger', 'Void']];
        [$c, $t] = $m[$s] ?? ['secondary', $s];
        return '<span class="badge-soft badge-' . $c . '">' . esc($t) . '</span>';
    }
}

if (! function_exists('order_type_label')) {
    function order_type_label(string $t): string
    {
        return ['dine_in' => 'Makan di Tempat', 'take_away' => 'Bawa Pulang', 'delivery' => 'Delivery'][$t] ?? $t;
    }
}

if (! function_exists('idle_minutes')) {
    function idle_minutes(): int
    {
        return (int) (getenv('SESSION_IDLE_MINUTES') ?: 5);
    }
}
