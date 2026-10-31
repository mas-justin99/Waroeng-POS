<?php

namespace App\Libraries;

/** Upload gambar ke Cloudinary (signed upload via cURL, tanpa SDK). Fallback ke public/uploads saat lokal. */
class Cloud
{
    public static function configured(): bool
    {
        return (bool) getenv('CLOUDINARY_URL');
    }

    /**
     * Kompres gambar (GD): perbaiki rotasi EXIF, kecilkan sisi terpanjang ke $maxSide, simpan JPEG kualitas $quality.
     * Mengembalikan path file hasil (temp) atau path asli bila tak perlu / GD tidak tersedia. Selalu aman dipanggil.
     */
    public static function optimize(string $path, int $maxSide = 1600, int $quality = 80): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) return $path;
        $info = @getimagesize($path);
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 60_000_000) return $path;
        [$w, $h, $type] = $info;
        $orig = (int) filesize($path);
        if ($type === IMAGETYPE_JPEG && $orig < 250 * 1024 && max($w, $h) <= $maxSide) return $path; // sudah kecil

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (! $img) return $path;
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($deg && ($r = imagerotate($img, $deg, 0))) { $img = $r; }
        }
        $w = imagesx($img); $h = imagesy($img);
        if (max($w, $h) > $maxSide) {
            $k = $maxSide / max($w, $h);
            $sc = imagescale($img, max(1, (int) round($w * $k)), max(1, (int) round($h * $k)), IMG_BICUBIC);
            if ($sc) { $img = $sc; $w = imagesx($img); $h = imagesy($img); }
        }
        $canvas = imagecreatetruecolor($w, $h); // ratakan transparansi PNG/WEBP ke latar putih
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
        $out = tempnam(sys_get_temp_dir(), 'img');
        if ($out === false || ! imagejpeg($canvas, $out, $quality)) return $path;
        if ($type === IMAGETYPE_JPEG && (int) filesize($out) >= $orig && max($info[0], $info[1]) <= $maxSide) { @unlink($out); return $path; }
        return $out;
    }

    /** @return array{0:?string,1:?string} [url, error] */
    public static function upload(string $path, string $folder = 'waroeng-mbok-reneo', int $maxSide = 1280): array
    {
        if (! is_file($path)) return [null, 'File tidak ditemukan.'];
        $small = self::optimize($path, $maxSide);
        try {
            return self::send($small, $folder, $maxSide);
        } finally {
            if ($small !== $path) @unlink($small);
        }
    }

    private static function send(string $path, string $folder, int $maxSide): array
    {
        if (! self::configured()) {
            if (getenv('VERCEL')) return [null, 'CLOUDINARY_URL belum diatur di Vercel.'];
            $dir = FCPATH . 'uploads';
            if (! is_dir($dir)) mkdir($dir, 0775, true);
            $name = bin2hex(random_bytes(8)) . '.jpg';
            copy($path, $dir . '/' . $name);
            return [base_url('uploads/' . $name), null];
        }

        $c = parse_url(getenv('CLOUDINARY_URL'));
        $key = $c['user'] ?? '';
        $secret = $c['pass'] ?? '';
        $cloud = $c['host'] ?? '';
        $ts = time();
        $transform = "c_limit,w_{$maxSide},q_auto,f_auto";
        $sig = sha1("folder={$folder}&timestamp={$ts}&transformation={$transform}" . $secret);

        $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloud}/image/upload");
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($path), 'api_key' => $key, 'timestamp' => $ts,
                'folder' => $folder, 'transformation' => $transform, 'signature' => $sig,
            ],
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) return [null, 'Cloudinary: ' . $err];
        $j = json_decode($res, true);
        if (empty($j['secure_url'])) return [null, 'Cloudinary: ' . ($j['error']['message'] ?? 'upload gagal')];
        return [$j['secure_url'], null];
    }
}
