<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\PrintService;

/** Printer LAN bar per outlet + token agen cetak + antrean. */
class Printers extends BaseController
{
    public function index()
    {
        $loc = $this->db->table('locations')->where('id', loc_id())->get()->getRowArray();
        if (empty($loc['print_token'])) {
            $this->db->table('locations')->where('id', loc_id())->update(['print_token' => PrintService::newToken()]);
            $loc = $this->db->table('locations')->where('id', loc_id())->get()->getRowArray();
        }
        $seen = $loc['agent_seen_at'] ? strtotime($loc['agent_seen_at']) : 0;
        return view('admin/printers', [
            'printers' => $this->db->table('printers')->where('location_id', loc_id())->orderBy('name')->get()->getResultArray(),
            'jobs' => $this->db->table('print_jobs j')->select('j.id, j.kind, j.ref, j.status, j.attempts, j.error, j.created_at, j.done_at, p.name AS printer')
                ->join('printers p', 'p.id = j.printer_id', 'left')->where('j.location_id', loc_id())->orderBy('j.id', 'DESC')->limit(30)->get()->getResultArray(),
            'token' => $loc['print_token'], 'online' => $seen && time() - $seen < 30, 'seen' => $loc['agent_seen_at'],
            'pending' => $this->db->table('print_jobs')->where('location_id', loc_id())->whereIn('status', ['pending', 'printing'])->countAllResults(),
            'barCount' => $this->db->table('products')->where('is_bar', 1)->where('is_active', 1)->countAllResults(),
        ]);
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');
        $name = trim((string) $this->request->getPost('name'));
        $host = trim((string) $this->request->getPost('host'));
        if ($name === '' || ! preg_match('/^[A-Za-z0-9.\-]{1,100}$/', $host)) return $this->back('/admin/printers', 'Nama dan alamat IP/host printer wajib diisi dengan benar.', 'error');
        $data = ['name' => mb_substr($name, 0, 60), 'host' => $host, 'port' => min(65535, max(1, (int) $this->request->getPost('port') ?: 9100)),
            'cols' => (int) $this->request->getPost('cols') === 48 ? 48 : 32, 'cut' => $this->request->getPost('cut') ? 1 : 0];
        if ($id) {
            $this->db->table('printers')->where('id', $id)->where('location_id', loc_id())->update($data);
        } else {
            $this->db->table('printers')->insert($data + ['location_id' => loc_id(), 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        }
        return $this->back('/admin/printers', 'Printer disimpan.');
    }

    public function toggle(int $id)
    {
        $this->db->query('UPDATE printers SET is_active = 1 - is_active WHERE id = ? AND location_id = ?', [$id, loc_id()]);
        return $this->back('/admin/printers', 'Status printer diubah.');
    }

    public function delete(int $id)
    {
        $this->db->table('print_jobs')->where('printer_id', $id)->where('location_id', loc_id())->whereIn('status', ['pending', 'printing'])->delete();
        $this->db->table('printers')->where('id', $id)->where('location_id', loc_id())->delete();
        return $this->back('/admin/printers', 'Printer dihapus.');
    }

    public function test(int $id)
    {
        $p = $this->db->table('printers')->where('id', $id)->where('location_id', loc_id())->get()->getRowArray();
        if (! $p) return $this->back('/admin/printers', 'Printer tidak ditemukan.', 'error');
        $bytes = PrintService::ticket(['title' => 'TES PRINTER', 'ref' => $p['name'] . ' ' . $p['host'] . ':' . $p['port'], 'cashier' => auth_user()['name'] ?? ''], [['Es Teh Manis', 2], ['Kopi Susu', 1]], (int) $p['cols'], (bool) $p['cut']);
        $this->db->table('print_jobs')->insert(['location_id' => loc_id(), 'printer_id' => $p['id'], 'kind' => 'test', 'ref' => 'Tes ' . $p['name'],
            'payload' => base64_encode($bytes), 'status' => 'pending', 'attempts' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        return $this->back('/admin/printers', 'Tes cetak dikirim ke antrean. Pastikan agen cetak berjalan di outlet.');
    }

    public function token()
    {
        $this->db->table('locations')->where('id', loc_id())->update(['print_token' => PrintService::newToken()]);
        return $this->back('/admin/printers', 'Token baru dibuat. Perbarui token di komputer agen cetak.');
    }

    public function retry(int $id)
    {
        $this->db->table('print_jobs')->where('id', $id)->where('location_id', loc_id())->update(['status' => 'pending', 'attempts' => 0, 'error' => null, 'claimed_at' => null]);
        return $this->back('/admin/printers', 'Cetak diulang.');
    }
}
