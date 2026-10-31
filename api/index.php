<?php

/**
 * Entry point Vercel (runtime vercel-php). Setara dengan public/index.php milik CodeIgniter 4.
 * Filesystem Vercel read-only kecuali /tmp, jadi folder writable diarahkan ke /tmp/writable (lihat app/Config/Paths.php).
 */
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';
// Vercel hanya melayani HTTPS di edge; CI4 hanya percaya header X-Forwarded-Proto dari proxy terdaftar, jadi tandai langsung.
$_SERVER['HTTPS']       = 'on';

$root = dirname(__DIR__);
define('FCPATH', $root . '/public/');
chdir(FCPATH);

foreach (['cache', 'logs', 'debugbar', 'session', 'uploads'] as $dir) {
    if (! is_dir("/tmp/writable/{$dir}")) {
        @mkdir("/tmp/writable/{$dir}", 0777, true);
    }
}

require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';

exit(CodeIgniter\Boot::bootWeb($paths));
