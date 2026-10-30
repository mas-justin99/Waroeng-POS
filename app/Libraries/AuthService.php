<?php

namespace App\Libraries;

/**
 * Autentikasi dengan aturan 1 akun = 1 perangkat.
 * Token sesi disimpan di users.session_token; setiap request dicocokkan dengan token di sesi browser.
 * Login baru ditolak selama sesi lama masih aktif (last_seen < SESSION_IDLE_MINUTES). Admin dapat paksa logout.
 */
class AuthService
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;

        $s = session();
        $uid = $s->get('uid');
        $tok = $s->get('token');
        if (! $uid || ! $tok) return null;

        $db = db_connect();
        $u = $db->table('users')->where('id', $uid)->get()->getRowArray();
        if (! $u || (int) $u['is_active'] !== 1 || ! $u['session_token'] || ! hash_equals($u['session_token'], $tok)) {
            return null;
        }

        // perbarui last_seen paling sering tiap 20 detik
        if (! $u['last_seen'] || time() - strtotime($u['last_seen']) > 20) {
            $now = date('Y-m-d H:i:s');
            $db->table('users')->where('id', $uid)->update(['last_seen' => $now]);
            $u['last_seen'] = $now;
        }
        unset($u['password_hash']);
        return self::$user = $u;
    }

    public static function locationId(): int
    {
        $u = self::user();
        if (! $u) return 0;
        if ($u['role'] !== 'admin') return (int) $u['location_id'];

        $sel = (int) session()->get('loc_id');
        $db = db_connect();
        if ($sel && $db->table('locations')->where('id', $sel)->where('is_active', 1)->countAllResults() > 0) {
            return $sel;
        }
        $first = $db->table('locations')->where('is_active', 1)->orderBy('id')->get(1)->getRowArray();
        return (int) ($first['id'] ?? 0);
    }

    /** @return array{0:bool,1:string} */
    public static function attempt(string $username, string $password): array
    {
        $db = db_connect();
        $u = $db->table('users')->where('username', strtolower(trim($username)))->get()->getRowArray();
        if (! $u || ! password_verify($password, $u['password_hash'])) {
            return [false, 'Username atau password salah.'];
        }
        if ((int) $u['is_active'] !== 1) {
            return [false, 'Akun dinonaktifkan. Hubungi admin.'];
        }
        if ($u['role'] !== 'admin' && ! $u['location_id']) {
            return [false, 'Akun belum ditetapkan ke lokasi. Hubungi admin.'];
        }

        // Aturan satu perangkat: ID perangkat disimpan di cookie permanen. Perangkat yang sama boleh login ulang
        // (mis. cache dibersihkan); perangkat lain ditolak selama sesi lama masih aktif.
        $did = (string) ($_COOKIE['wmr_device'] ?? '');
        if (! preg_match('/^[a-f0-9]{32}$/', $did)) $did = bin2hex(random_bytes(16));
        $sameDevice = $u['device_id'] && hash_equals((string) $u['device_id'], $did);
        $active = $u['session_token'] && $u['last_seen'] && time() - strtotime($u['last_seen']) < idle_minutes() * 60;
        if ($active && ! $sameDevice) {
            return [false, 'Akun ini sedang dipakai di perangkat lain. Logout dari perangkat tersebut, '
                . 'atau tunggu ' . idle_minutes() . ' menit tanpa aktivitas, atau minta admin untuk paksa logout.'];
        }
        setcookie('wmr_device', $did, [
            'expires' => time() + 86400 * 730, 'path' => '/', 'secure' => service('request')->isSecure(),
            'httponly' => true, 'samesite' => 'Lax',
        ]);

        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $db->table('users')->where('id', $u['id'])->update([
            'session_token' => $token, 'device_id' => $did, 'last_seen' => $now, 'last_login_at' => $now,
        ]);
        $s = session();
        $s->regenerate(true);
        $s->set(['uid' => (int) $u['id'], 'token' => $token]);
        self::$loaded = false;
        return [true, ''];
    }

    public static function logout(): void
    {
        $uid = session()->get('uid');
        $tok = session()->get('token');
        if ($uid && $tok) {
            db_connect()->table('users')->where('id', $uid)->where('session_token', $tok)
                ->update(['session_token' => null]);
        }
        session()->destroy();
        self::$user = null;
        self::$loaded = false;
    }
}
