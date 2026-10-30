<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $range = $this->request->getGet('range') ?: 'today';
        $today = date('Y-m-d');
        [$from, $to] = match ($range) {
            '7d' => [date('Y-m-d', strtotime('-6 days')), $today],
            '30d' => [date('Y-m-d', strtotime('-29 days')), $today],
            'month' => [date('Y-m-01'), $today],
            'custom' => [$this->request->getGet('from') ?: $today, $this->request->getGet('to') ?: $today],
            default => [$today, $today],
        };
        $loc = $this->request->getGet('loc') ?: 'all';
        $f = $from . ' 00:00:00';
        $t = $to . ' 23:59:59';
        $db = $this->db;
        $locSql = $loc !== 'all' ? ' AND s.location_id = ' . (int) $loc : '';

        $kpi = $db->query("SELECT COUNT(*) AS trx, COALESCE(SUM(total),0) AS sales, COALESCE(SUM(subtotal-discount-cogs),0) AS profit,
                           COALESCE(SUM(discount),0) AS discount, COALESCE(SUM(tax+service),0) AS taxes
                           FROM sales s WHERE s.status='paid' AND s.created_at BETWEEN ? AND ? $locSql", [$f, $t])->getRowArray();
        $items = (int) ($db->query("SELECT COALESCE(SUM(i.qty),0) AS q FROM sale_items i JOIN sales s ON s.id=i.sale_id
                           WHERE s.status='paid' AND s.created_at BETWEEN ? AND ? $locSql", [$f, $t])->getRowArray()['q'] ?? 0);

        // Penjualan per hari (isi hari kosong)
        $daily = array_column($db->query("SELECT DATE(s.created_at) AS d, SUM(total) AS t, SUM(subtotal-discount-cogs) AS p FROM sales s
                           WHERE s.status='paid' AND s.created_at BETWEEN ? AND ? $locSql GROUP BY DATE(s.created_at)", [$f, $t])->getResultArray(), null, 'd');
        $labels = $sales = $profit = [];
        for ($d = strtotime($from); $d <= strtotime($to) && count($labels) < 93; $d = strtotime('+1 day', $d)) {
            $k = date('Y-m-d', $d);
            $labels[] = date('d/m', $d);
            $sales[] = (float) ($daily[$k]['t'] ?? 0);
            $profit[] = (float) ($daily[$k]['p'] ?? 0);
        }

        $hours = array_fill(0, 24, 0);
        foreach ($db->query("SELECT EXTRACT(HOUR FROM s.created_at) AS h, SUM(total) AS t FROM sales s
                 WHERE s.status='paid' AND s.created_at BETWEEN ? AND ? $locSql GROUP BY EXTRACT(HOUR FROM s.created_at)", [$f, $t])->getResultArray() as $r) {
            $hours[(int) $r['h']] = (float) $r['t'];
        }

        $methods = $db->query("SELECT p.method_name AS n, SUM(p.amount) AS t FROM sale_payments p JOIN sales s ON s.id = p.sale_id
                 WHERE s.status='paid' AND s.created_at BETWEEN ? AND ? $locSql GROUP BY p.method_name ORDER BY t DESC", [$f, $t])->getResultArray();
        $top = $db->query("SELECT i.name, SUM(i.qty) AS q, SUM(i.subtotal) AS t FROM sale_items i JOIN sales s ON s.id = i.sale_id
                 WHERE s.status='paid' AND s.created_at BETWEEN ? AND ? $locSql GROUP BY i.name ORDER BY q DESC LIMIT 6", [$f, $t])->getResultArray();
        $byLoc = $db->query("SELECT l.name AS n, COUNT(s.id) AS trx, COALESCE(SUM(s.total),0) AS t FROM locations l
                 LEFT JOIN sales s ON s.location_id = l.id AND s.status='paid' AND s.created_at BETWEEN ? AND ?
                 WHERE l.is_active = 1 GROUP BY l.id, l.name ORDER BY t DESC", [$f, $t])->getResultArray();

        $stockLoc = $loc !== 'all' ? ' AND st.location_id = ' . (int) $loc : '';
        $low = $db->query("SELECT p.name, p.sku, st.qty, st.min_qty, l.name AS loc FROM stocks st JOIN products p ON p.id = st.product_id
                 JOIN locations l ON l.id = st.location_id WHERE p.is_active = 1 AND st.qty <= st.min_qty $stockLoc ORDER BY st.qty LIMIT 8")->getResultArray();
        $poLoc = $loc !== 'all' ? ' AND location_id = ' . (int) $loc : '';
        $poPending = (int) $db->query("SELECT COUNT(*) AS c FROM purchase_orders WHERE status IN ('sent','partial') $poLoc")->getRowArray()['c'];
        $stockValue = (float) ($db->query("SELECT COALESCE(SUM(st.qty * st.avg_cost),0) AS v FROM stocks st WHERE st.qty > 0 $stockLoc")->getRowArray()['v'] ?? 0);
        $online = $db->query("SELECT u.name, u.role, l.name AS loc FROM users u LEFT JOIN locations l ON l.id = u.location_id
                 WHERE u.session_token IS NOT NULL AND u.last_seen > ? ORDER BY u.name", [date('Y-m-d H:i:s', time() - idle_minutes() * 60)])->getResultArray();
        $recent = $db->query("SELECT s.id, s.invoice_no, s.total, s.status, s.created_at, l.code FROM sales s JOIN locations l ON l.id = s.location_id
                 WHERE 1=1 $locSql ORDER BY s.id DESC LIMIT 6")->getResultArray();

        return view('admin/dashboard', compact('range', 'from', 'to', 'loc', 'kpi', 'items', 'labels', 'sales', 'profit', 'hours', 'methods',
            'top', 'byLoc', 'low', 'poPending', 'stockValue', 'online', 'recent'));
    }
}
