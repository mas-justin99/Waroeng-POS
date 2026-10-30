<?= $this->extend('layouts/app') ?>
<?php $title = 'Riwayat Shift'; use App\Libraries\ShiftService; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><h2>Riwayat Shift</h2><div class="text-muted">Rekap buka & tutup kasir per shift.</div></div>
<form class="d-flex gap-2 flex-wrap" method="get"><input type="date" name="from" class="form-control" value="<?= esc($from) ?>" style="width:auto"><input type="date" name="to" class="form-control" value="<?= esc($to) ?>" style="width:auto"><button class="btn btn-soft"><i class="bi bi-funnel"></i> Terapkan</button></form></div>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>Tanggal</th><th>Shift</th><th>Kasir</th><th>Buka – Tutup</th><th class="text-end">Modal</th><th class="text-end">Penjualan</th><th class="text-end">Selisih kas</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $d = (float) $r['cash_diff']; ?>
<tr><td data-l="Tanggal"><?= tgl($r['shift_date'], 'd M Y') ?></td><td data-l="Shift"><b><?= esc(ShiftService::LABELS[$r['shift_no']]) ?></b></td><td data-l="Kasir"><?= esc($r['opener']) ?></td>
<td data-l="Jam"><?= tgl($r['opened_at'], 'H:i') ?> – <?= $r['closed_at'] ? tgl($r['closed_at'], 'H:i') : '…' ?></td>
<td class="text-end num" data-l="Modal"><?= rupiah($r['opening_cash']) ?></td>
<td class="text-end num" data-l="Penjualan"><?= $r['status'] === 'closed' ? rupiah($r['sales_total']) : '-' ?></td>
<td class="text-end num <?= $r['status'] === 'closed' ? ($d < 0 ? 'diff-neg' : 'diff-pos') : '' ?>" data-l="Selisih"><?= $r['status'] === 'closed' ? (($d > 0 ? '+' : ($d < 0 ? '-' : '')) . rupiah(abs($d))) : '-' ?></td>
<td data-l="Status"><?= $r['status'] === 'open' ? '<span class="badge-soft badge-success">Buka</span>' : '<span class="badge-soft badge-secondary">Ditutup</span>' ?></td>
<td class="act" data-l=""><a class="btn btn-sm btn-ghost" target="_blank" href="/shifts/<?= $r['id'] ?>/receipt" title="Struk penutupan"><i class="bi bi-receipt"></i></a> <a class="btn btn-sm btn-ghost" href="/shifts/<?= $r['id'] ?>">Detail</a></td></tr>
<?php endforeach; ?>
<?php if (! $rows): ?><tr><td colspan="9"><div class="empty"><i class="bi bi-clock-history"></i>Belum ada shift pada rentang ini.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
