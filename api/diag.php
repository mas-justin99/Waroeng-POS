<?php
// Halaman diagnosa sementara untuk Vercel. HAPUS setelah masalah selesai (lihat README). Tidak menampilkan password.
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
$ok = fn ($b) => $b ? 'OK  ' : 'GAGAL';
echo "== PHP ==\nversi: " . PHP_VERSION . "\n";
foreach (['pgsql', 'pdo_pgsql', 'mbstring', 'intl', 'curl', 'openssl', 'json'] as $e) echo $ok(extension_loaded($e)) . " ekstensi $e\n";
echo "\n== File ==\n";
foreach (['app/Config/Paths.php', 'vendor/autoload.php', 'vendor/codeigniter4/framework/system/Boot.php', 'public/index.php'] as $f) echo $ok(is_file("$root/$f")) . " $f\n";
echo $ok(is_dir('/tmp') && is_writable('/tmp')) . " /tmp bisa ditulis\n";
echo "\n== Request ==\n";
foreach (['REQUEST_URI', 'SCRIPT_NAME', 'SCRIPT_FILENAME', 'PATH_INFO', 'HTTP_HOST', 'HTTP_X_FORWARDED_PROTO'] as $k) echo "$k = " . ($_SERVER[$k] ?? '(kosong)') . "\n";
echo "\n== Environment ==\n";
$env = getenv('CI_ENVIRONMENT') ?: ($_SERVER['CI_ENVIRONMENT'] ?? '');
echo "CI_ENVIRONMENT = " . ($env ?: '(kosong)') . "\n";
echo "VERCEL = " . (getenv('VERCEL') ?: '(kosong)') . "\n";
echo "CLOUDINARY_URL = " . (getenv('CLOUDINARY_URL') ? 'terisi' : '(kosong)') . "\n";
$url = getenv('DATABASE_URL');
echo "DATABASE_URL = " . ($url ? 'terisi (' . strlen($url) . ' karakter)' : '(KOSONG)') . "\n";
if ($url) {
    $p = parse_url($url);
    if (! $p || empty($p['host'])) { echo "GAGAL DATABASE_URL tidak bisa dibaca (ada tanda kutip / awalan 'psql' / karakter aneh?). Awal nilai: " . substr($url, 0, 12) . "...\n"; exit; }
    echo "scheme=" . ($p['scheme'] ?? '?') . " host=" . $p['host'] . " db=" . ltrim($p['path'] ?? '', '/') . " user=" . ($p['user'] ?? '?') . "\n";
    if (extension_loaded('pgsql')) {
        $opt = preg_match('/^(ep-[a-z0-9-]+?)(-pooler)?\./i', $p['host'], $m) ? " options='endpoint={$m[1]}'" : '';
        $dsn = "host={$p['host']} port=" . ($p['port'] ?? 5432) . " user={$p['user']} password='" . urldecode($p['pass'] ?? '') . "' dbname=" . ltrim($p['path'], '/') . " sslmode=require connect_timeout=8" . $opt;
        $c = @pg_connect($dsn);
        if ($c) {
            echo "OK   koneksi database berhasil\n";
            $r = @pg_query($c, "select count(*) from information_schema.tables where table_schema='public'");
            echo $r ? "OK   jumlah tabel: " . pg_fetch_result($r, 0, 0) . " (harus 20-an; 0 = migrate belum dijalankan)\n" : "GAGAL query\n";
            $r = @pg_query($c, "select count(*) from users");
            echo $r ? "OK   jumlah user: " . pg_fetch_result($r, 0, 0) . "\n" : "GAGAL tabel users tidak ada -> jalankan migrate & db:seed\n";
        } else {
            echo "GAGAL koneksi: " . str_replace(urldecode($p['pass'] ?? '#'), '***', (string) error_get_last()['message']) . "\n";
        }
    } else {
        echo "GAGAL ekstensi pgsql tidak ada di runtime Vercel ini -> aplikasi tidak bisa terhubung ke Neon.\n";
    }
}
