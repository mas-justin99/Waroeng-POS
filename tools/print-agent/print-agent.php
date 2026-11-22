<?php
/**
 * Agen cetak Waroeng POS — jalankan di komputer outlet (satu jaringan dengan printer LAN).
 *
 *   php print-agent.php --url=https://domain-anda.vercel.app --token=TOKEN
 *
 * Opsi:
 *   --interval=3        detik antar pengecekan antrean (default 3)
 *   --hours=10-23       hanya polling pada jam ini (default 24 jam) — menghemat compute Neon
 *   --insecure          lewati verifikasi SSL (hanya bila CA Windows bermasalah)
 *   --cacert=cacert.pem file CA bundle untuk curl
 *   --once              ambil & cetak sekali lalu berhenti (untuk uji)
 */
if (PHP_SAPI !== 'cli') exit("Jalankan lewat command line.\n");
if (! function_exists('curl_init')) exit("Ekstensi PHP curl belum aktif.\n");

$o = getopt('', ['url:', 'token:', 'interval::', 'hours::', 'insecure', 'cacert::', 'once']);
$url = rtrim($o['url'] ?? getenv('PRINT_URL') ?: '', '/');
$token = $o['token'] ?? getenv('PRINT_TOKEN') ?: '';
if ($url === '' || $token === '') exit("Wajib: --url=... --token=...\n");
$interval = max(1, (int) ($o['interval'] ?? 3));
$hours = null;
if (! empty($o['hours']) && preg_match('/^(\d{1,2})-(\d{1,2})$/', $o['hours'], $m)) $hours = [(int) $m[1], (int) $m[2]];
$insecure = isset($o['insecure']);
$cacert = $o['cacert'] ?? null;

function say(string $s): void { echo '[' . date('H:i:s') . '] ' . $s . "\n"; }

function api(string $path, array $body = []): ?array
{
    global $url, $token, $insecure, $cacert;
    $ch = curl_init($url . $path);
    $opt = [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Print-Token: ' . $token]];
    if ($insecure) { $opt[CURLOPT_SSL_VERIFYPEER] = false; $opt[CURLOPT_SSL_VERIFYHOST] = 0; }
    if ($cacert) $opt[CURLOPT_CAINFO] = $cacert;
    curl_setopt_array($ch, $opt);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($r === false) { say('Koneksi ke server gagal: ' . $err); return null; }
    if ($code === 401) { say('Token ditolak server (401). Periksa token di halaman Admin > Printer Bar.'); return null; }
    $j = json_decode($r, true);
    if ($code !== 200 || ! is_array($j)) { say("Respon server tidak valid (HTTP $code)."); return null; }
    return $j;
}

function printTo(string $host, int $port, string $data): ?string
{
    $s = @stream_socket_client("tcp://$host:$port", $en, $es, 5);
    if (! $s) return "Tidak bisa terhubung ke $host:$port ($es)";
    stream_set_timeout($s, 5);
    $len = strlen($data);
    for ($w = 0; $w < $len;) {
        $n = @fwrite($s, substr($data, $w, 4096));
        if ($n === false || $n === 0) { fclose($s); return 'Gagal mengirim data ke printer'; }
        $w += $n;
    }
    usleep(300000); // beri waktu printer menerima buffer sebelum koneksi ditutup
    fclose($s);
    return null;
}

say("Agen cetak aktif -> $url (interval {$interval}s" . ($hours ? ", jam {$hours[0]}-{$hours[1]}" : '') . ')');
do {
    $h = (int) date('G');
    if ($hours && ! ($hours[0] <= $hours[1] ? ($h >= $hours[0] && $h < $hours[1]) : ($h >= $hours[0] || $h < $hours[1]))) {
        sleep(30);
        continue;
    }
    $res = api('/print/agent/pull');
    if ($res && ! empty($res['jobs'])) {
        $results = [];
        foreach ($res['jobs'] as $j) {
            $err = printTo($j['host'], (int) $j['port'], (string) base64_decode($j['data'], true));
            say(($err ? 'GAGAL ' : 'OK    ') . "job #{$j['id']} -> {$j['host']}:{$j['port']}" . ($err ? " ($err)" : ''));
            $results[] = ['id' => $j['id'], 'ok' => $err === null, 'error' => $err];
        }
        api('/print/agent/ack', ['results' => $results]);
        continue; // cek lagi segera bila masih ada antrean
    }
    if (isset($o['once'])) break;
    sleep($interval);
} while (! isset($o['once']));
