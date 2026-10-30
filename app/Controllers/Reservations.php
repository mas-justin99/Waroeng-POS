<?php

namespace App\Controllers;

/** Reservasi meja + pengingat (di aplikasi & tautan WhatsApp ke pelanggan). */
class Reservations extends BaseController
{
    public const STATUS = [
        'booked'    => ['Dipesan', 'warning'],
        'confirmed' => ['Terkonfirmasi', 'info'],
        'arrived'   => ['Sudah datang', 'success'],
        'cancelled' => ['Dibatalkan', 'secondary'],
        'no_show'   => ['Tidak datang', 'danger'],
    ];

    public function index()
    {
        $view = in_array($this->request->getGet('v'), ['today', 'upcoming', 'past'], true) ? $this->request->getGet('v') : 'today';
        $q    = trim((string) $this->request->getGet('q'));
        $loc  = loc_id();
        $today = date('Y-m-d');

        $b = $this->db->table('reservations r')->select('r.*, u.name AS creator')->join('users u', 'u.id = r.created_by', 'left')->where('r.location_id', $loc);
        if ($view === 'today') {
            $b->where('r.reserved_at >=', $today . ' 00:00:00')->where('r.reserved_at <=', $today . ' 23:59:59')->orderBy('r.reserved_at', 'ASC');
        } elseif ($view === 'upcoming') {
            $b->where('r.reserved_at >', $today . ' 23:59:59')->orderBy('r.reserved_at', 'ASC');
        } else {
            $b->where('r.reserved_at <', $today . ' 00:00:00')->orderBy('r.reserved_at', 'DESC');
        }
        if ($q !== '') $b->groupStart()->like('r.customer_name', $q, 'both', null, true)->orLike('r.phone', $q)->groupEnd();
        $rows = $b->limit(300)->get()->getResultArray();

        $counts = [
            'today'    => (int) $this->db->query("SELECT COUNT(*) AS c FROM reservations WHERE location_id = ? AND status <> 'cancelled' AND reserved_at BETWEEN ? AND ?", [$loc, "$today 00:00:00", "$today 23:59:59"])->getRowArray()['c'],
            'upcoming' => (int) $this->db->query("SELECT COUNT(*) AS c FROM reservations WHERE location_id = ? AND status IN ('booked','confirmed') AND reserved_at > ?", [$loc, "$today 23:59:59"])->getRowArray()['c'],
        ];
        $guests = 0;
        foreach ($rows as &$r) {
            $r['wa'] = $this->waLink($r);
            if ($view === 'today' && in_array($r['status'], ['booked', 'confirmed', 'arrived'], true)) $guests += (int) $r['pax'];
        }
        return view('reservations/index', ['rows' => $rows, 'view' => $view, 'q' => $q, 'counts' => $counts, 'guests' => $guests,
            'canEdit' => in_array(auth_user()['role'], ['admin', 'cashier'], true), 'status' => self::STATUS]);
    }

    public function save()
    {
        $id    = (int) $this->request->getPost('id');
        $name  = trim((string) $this->request->getPost('customer_name'));
        $when  = trim((string) $this->request->getPost('reserved_at'));
        $pax   = (int) $this->request->getPost('pax');
        $phone = preg_replace('/[^0-9+]/', '', (string) $this->request->getPost('phone')) ?: null;
        $dp    = (float) preg_replace('/\D/', '', (string) $this->request->getPost('dp_amount'));
        $rm    = (int) $this->request->getPost('remind_minutes');

        $ts = strtotime(str_replace('T', ' ', $when));
        if ($name === '') return $this->back('/reservations', 'Nama pemesan wajib diisi.', 'error')->withInput();
        if (! $ts) return $this->back('/reservations', 'Tanggal & jam reservasi wajib diisi.', 'error')->withInput();
        if ($pax < 1 || $pax > 500) return $this->back('/reservations', 'Jumlah orang harus 1 - 500.', 'error')->withInput();
        if (! in_array($rm, [0, 15, 30, 60, 120, 180, 1440], true)) $rm = 60;
        if (! $id && $ts < time() - 300) return $this->back('/reservations', 'Jam reservasi sudah lewat. Pilih waktu yang akan datang.', 'error')->withInput();

        $data = [
            'customer_name' => $name, 'phone' => $phone, 'reserved_at' => date('Y-m-d H:i:00', $ts), 'pax' => $pax,
            'table_no' => trim((string) $this->request->getPost('table_no')) ?: null, 'dp_amount' => $dp,
            'note' => trim((string) $this->request->getPost('note')) ?: null, 'remind_minutes' => $rm, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $row = $this->db->table('reservations')->where('id', $id)->where('location_id', loc_id())->get()->getRowArray();
            if (! $row) return $this->back('/reservations', 'Reservasi tidak ditemukan.', 'error');
            if ($row['reserved_at'] !== $data['reserved_at'] || $row['remind_minutes'] != $rm) $data['ack_at'] = null; // jadwal berubah -> ingatkan lagi
            $this->db->table('reservations')->where('id', $id)->update($data);
            $msg = 'Reservasi diperbarui.';
        } else {
            $this->db->table('reservations')->insert($data + ['location_id' => loc_id(), 'status' => 'booked', 'created_by' => auth_user()['id'], 'created_at' => $data['updated_at']]);
            $msg = 'Reservasi disimpan. Pengingat akan muncul ' . ($rm ? $rm . ' menit' : 'tepat waktu') . ' sebelum jam reservasi.';
        }
        $v = date('Y-m-d', $ts) === date('Y-m-d') ? 'today' : (date('Y-m-d', $ts) > date('Y-m-d') ? 'upcoming' : 'past');
        return $this->back('/reservations?v=' . $v, $msg);
    }

    public function setStatus(int $id)
    {
        $to = (string) $this->request->getPost('status');
        if (! isset(self::STATUS[$to])) return $this->back('/reservations', 'Status tidak valid.', 'error');
        $upd = ['status' => $to, 'updated_at' => date('Y-m-d H:i:s')];
        if (in_array($to, ['arrived', 'cancelled', 'no_show'], true)) $upd['ack_at'] = date('Y-m-d H:i:s');
        $this->db->table('reservations')->where('id', $id)->where('location_id', loc_id())->update($upd);
        $back = (string) $this->request->getPost('back') ?: '/reservations';
        if (! str_starts_with($back, '/reservations')) $back = '/reservations';
        return $this->back($back, 'Status diubah: ' . self::STATUS[$to][0] . '.');
    }

    /** JSON: reservasi yang perlu diingatkan sekarang (dipanggil berkala oleh app.js). */
    public function upcoming()
    {
        $loc = loc_id();
        $now = time();
        $rows = $this->db->table('reservations')->where('location_id', $loc)->whereIn('status', ['booked', 'confirmed'])->where('ack_at', null)
            ->where('reserved_at >=', date('Y-m-d H:i:s', $now - 45 * 60))->where('reserved_at <=', date('Y-m-d H:i:s', $now + 1440 * 60))
            ->orderBy('reserved_at')->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $left = (int) floor((strtotime($r['reserved_at']) - $now) / 60);
            if ($left > (int) $r['remind_minutes']) continue;
            $out[] = ['id' => (int) $r['id'], 'name' => $r['customer_name'], 'pax' => (int) $r['pax'], 'table' => $r['table_no'],
                'time' => date('H:i', strtotime($r['reserved_at'])), 'left' => $left, 'late' => $left < 0, 'phone' => $r['phone'], 'wa' => $this->waLink($r)];
        }
        return $this->response->setJSON(['ok' => true, 'items' => $out]);
    }

    public function ack(int $id)
    {
        $this->db->table('reservations')->where('id', $id)->where('location_id', loc_id())->update(['ack_at' => date('Y-m-d H:i:s')]);
        return $this->response->setJSON(['ok' => true]);
    }

    /** Tautan WhatsApp berisi pesan pengingat untuk pelanggan. */
    private function waLink(array $r): ?string
    {
        $p = preg_replace('/\D/', '', (string) ($r['phone'] ?? ''));
        if ($p === '') return null;
        if (str_starts_with($p, '0')) $p = '62' . substr($p, 1);
        elseif (str_starts_with($p, '8')) $p = '62' . $p;
        $loc = loc_row((int) $r['location_id']);
        $ts = strtotime($r['reserved_at']);
        $txt = "Halo {$r['customer_name']}, kami dari Waroeng Mbok Reneo" . (! empty($loc['name']) ? " ({$loc['name']})" : '')
            . ". Mengingatkan reservasi Anda pada " . date('d/m/Y', $ts) . ' pukul ' . date('H:i', $ts) . " untuk {$r['pax']} orang"
            . ($r['table_no'] ? ", meja {$r['table_no']}" : '') . '. Kami tunggu kedatangannya. Matur nuwun!';
        return 'https://wa.me/' . $p . '?text=' . rawurlencode($txt);
    }
}
