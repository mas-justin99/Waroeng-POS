<?php

namespace App\Filters;

use App\Libraries\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (AuthService::user()) return;

        $hadSession = (bool) session()->get('uid');
        session()->destroy();
        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(401)->setJSON(['ok' => false, 'message' => 'Sesi berakhir, silakan login ulang.', 'login' => site_url('login')]);
        }
        $msg = $hadSession ? 'Sesi Anda berakhir (logout, login di perangkat lain, atau dipaksa keluar oleh admin).' : null;
        $r = redirect()->to('/login');
        return $msg ? $r->with('error', $msg) : $r;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
