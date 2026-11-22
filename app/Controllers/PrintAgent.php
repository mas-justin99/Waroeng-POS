<?php

namespace App\Controllers;

/** API untuk agen cetak di outlet (token per outlet, tanpa sesi login). */
class PrintAgent extends BaseController
{
    private const MAX_ATTEMPTS = 5, CLAIM_TIMEOUT = 60;

    private function loc(): ?array
    {
        $t = trim($this->request->getHeaderLine('X-Print-Token'));
        if ($t === '' || strlen($t) < 20) return null;
        return $this->db->table('locations')->where('print_token', $t)->get()->getRowArray() ?: null;
    }

    private function deny()
    {
        return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'message' => 'Token tidak valid.']);
    }

    public function pull()
    {
        $loc = $this->loc();
        if (! $loc) return $this->deny();
        $now = date('Y-m-d H:i:s');
        $stale = date('Y-m-d H:i:s', time() - self::CLAIM_TIMEOUT);
        $this->db->table('locations')->where('id', $loc['id'])->update(['agent_seen_at' => $now]);

        $this->db->transBegin();
        $rows = $this->db->query("SELECT j.id, j.payload, j.attempts, p.host, p.port FROM print_jobs j JOIN printers p ON p.id = j.printer_id
            WHERE j.location_id = ? AND p.is_active = 1 AND (j.status = 'pending' OR (j.status = 'printing' AND j.claimed_at < ?)) AND j.attempts < ?
            ORDER BY j.id LIMIT 10 FOR UPDATE", [$loc['id'], $stale, self::MAX_ATTEMPTS])->getResultArray();
        foreach ($rows as $r) $this->db->query("UPDATE print_jobs SET status = 'printing', claimed_at = ?, attempts = attempts + 1 WHERE id = ?", [$now, $r['id']]);
        $this->db->transCommit();
        // job yang kehabisan percobaan saat 'printing' ditandai gagal
        $this->db->query("UPDATE print_jobs SET status = 'failed', error = COALESCE(error, 'Agen tidak merespons') WHERE location_id = ? AND status = 'printing' AND claimed_at < ? AND attempts >= ?", [$loc['id'], $stale, self::MAX_ATTEMPTS]);

        return $this->response->setJSON(['ok' => true, 'jobs' => array_map(fn ($r) => ['id' => (int) $r['id'], 'host' => $r['host'], 'port' => (int) $r['port'], 'data' => $r['payload']], $rows)]);
    }

    public function ack()
    {
        $loc = $this->loc();
        if (! $loc) return $this->deny();
        $in = $this->request->getJSON(true) ?? [];
        $now = date('Y-m-d H:i:s');
        foreach ($in['results'] ?? [] as $r) {
            $id = (int) ($r['id'] ?? 0);
            if (! $id) continue;
            if (! empty($r['ok'])) {
                $this->db->table('print_jobs')->where('id', $id)->where('location_id', $loc['id'])->update(['status' => 'done', 'done_at' => $now, 'error' => null]);
            } else {
                $err = mb_substr((string) ($r['error'] ?? 'Gagal mencetak'), 0, 190);
                $this->db->query("UPDATE print_jobs SET error = ?, status = CASE WHEN attempts >= ? THEN 'failed' ELSE 'pending' END WHERE id = ? AND location_id = ?", [$err, self::MAX_ATTEMPTS, $id, $loc['id']]);
            }
        }
        return $this->response->setJSON(['ok' => true]);
    }
}
