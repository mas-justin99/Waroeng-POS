<?php

namespace App\Controllers;

use App\Libraries\AuthService;

class Auth extends BaseController
{
    public function login()
    {
        if (AuthService::user()) return redirect()->to('/');
        return view('auth/login');
    }

    public function attempt()
    {
        $username = strtolower(trim((string) $this->request->getPost('username')));
        // batasi tebak password: 8 percobaan / menit per username + IP
        $throttler = service('throttler');
        if ($throttler->check('login_' . md5($this->request->getIPAddress() . '|' . $username), 8, MINUTE) === false) {
            return redirect()->to('/login')->with('error', 'Terlalu banyak percobaan login. Coba lagi dalam ' . $throttler->getTokenTime() . ' detik.')->withInput();
        }
        [$ok, $msg] = AuthService::attempt($username, (string) $this->request->getPost('password'));
        if (! $ok) {
            return redirect()->to('/login')->with('error', $msg)->withInput();
        }
        return redirect()->to('/');
    }

    public function logout()
    {
        AuthService::logout();
        return redirect()->to('/login')->with('success', 'Anda sudah logout.');
    }

    /** Heartbeat: menjaga last_seen agar perangkat lain tetap ditolak saat sesi ini aktif. */
    public function ping()
    {
        return $this->response->setJSON(['ok' => true]);
    }

    public function manifest()
    {
        return $this->response->setHeader('Content-Type', 'application/manifest+json')->setBody(json_encode([
            'name' => 'Waroeng Mbok Reneo POS', 'short_name' => 'Mbok Reneo POS', 'start_url' => '/', 'scope' => '/',
            'display' => 'standalone', 'background_color' => '#FFF6F5', 'theme_color' => '#E32F37', 'lang' => 'id',
            'icons' => [
                ['src' => base_url('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => base_url('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ]));
    }
}
