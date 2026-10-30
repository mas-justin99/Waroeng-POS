<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form', 'app'];

    protected \CodeIgniter\Database\BaseConnection $db;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    protected function back(string $to, string $msg, string $type = 'success')
    {
        return redirect()->to($to)->with($type, $msg);
    }

    protected function dateRange(): array
    {
        $from = $this->request->getGet('from') ?: date('Y-m-d');
        $to = $this->request->getGet('to') ?: date('Y-m-d');
        return [$from, $to, $from . ' 00:00:00', $to . ' 23:59:59'];
    }
}
