<?php

namespace App\Libraries;

/** Upload gambar ke Cloudinary (signed upload via cURL, tanpa SDK). Fallback ke public/uploads saat lokal. */
class Cloud
{
    public static function configured(): bool
    {
        return (bool) getenv('CLOUDINARY_URL');
    }

    /** @return array{0:?string,1:?string} [url, error] */
    public static function upload(string $path, string $folder = 'waroeng-mbok-reneo'): array
    {
        if (! is_file($path)) return [null, 'File tidak ditemukan.'];

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
        $transform = 'c_limit,w_800,q_auto,f_auto';
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
