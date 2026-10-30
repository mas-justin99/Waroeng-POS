<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $u = auth_user();
        if ($u && ($u['role'] === 'admin' || in_array($u['role'], $arguments ?? [], true))) return;

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Tidak punya akses.']);
        }
        return redirect()->to('/')->with('error', 'Anda tidak punya akses ke halaman tersebut.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
