<?= $this->extend('layouts/app') ?>
<?php $title = 'Printer Bar'; ?>
<?= $this->section('content') ?>
<?php $base = rtrim(base_url(), '/'); ?>
<div class="page-head anim"><div><h2>Printer Bar (LAN)</h2><div class="text-muted small">Outlet: <?= esc(loc_row()['name'] ?? '') ?> · produk bertanda "Bar": <b><?= $barCount ?></b></div></div>
<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#prM" data-new="#prM" data-title="Printer baru"><i class="bi bi-plus-lg"></i> Tambah Printer</button></div>

<div class="alert-x <?= $online ? 'ok' : 'err' ?> anim"><i class="bi <?= $online ? 'bi-check-circle' : 'bi-exclamation-triangle' ?>"></i><span>
<?php if ($online): ?>Agen cetak <b>online</b> (terakhir <?= esc(date('H:i:s', strtotime($seen))) ?>). Antrean: <?= $pending ?>.
<?php else: ?>Agen cetak <b>offline</b><?= $seen ? ' (terakhir terlihat ' . esc(date('d/m H:i', strtotime($seen))) . ')' : ' (belum pernah tersambung)' ?>. Pesanan bar tetap masuk antrean (<?= $pending ?>) dan tercetak saat agen aktif.<?php endif; ?></span></div>

<div class="row g-3">
<div class="col-lg-7 anim"><div class="card-x"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>Printer</th><th>Alamat</th><th>Kertas</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($printers as $p): ?>
<tr><td data-l="Printer"><b><?= esc($p['name']) ?></b></td><td data-l="Alamat"><?= esc($p['host']) ?>:<?= (int) $p['port'] ?></td><td data-l="Kertas"><?= $p['cols'] == 48 ? '80 mm' : '58 mm' ?><?= $p['cut'] ? ' · potong' : '' ?></td>
<td data-l="Status"><?= $p['is_active'] ? '<span class="badge-soft badge-success">Aktif</span>' : '<span class="badge-soft badge-secondary">Nonaktif</span>' ?></td>
<td class="act"><div class="d-flex gap-1 flex-wrap justify-content-end">
<form method="post" action="/admin/printers/<?= $p['id'] ?>/test"><?= csrf_field() ?><button class="btn btn-sm btn-soft">Tes</button></form>
<button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#prM" data-edit="#prM" data-id="<?= $p['id'] ?>" data-name="<?= esc($p['name']) ?>" data-host="<?= esc($p['host']) ?>" data-port="<?= (int) $p['port'] ?>" data-cols="<?= (int) $p['cols'] ?>" data-cut="<?= (int) $p['cut'] ?>" data-title="Ubah printer">Ubah</button>
<form method="post" action="/admin/printers/<?= $p['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= $p['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
<form method="post" action="/admin/printers/<?= $p['id'] ?>/delete" data-confirm="Hapus printer ini?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost text-danger">Hapus</button></form></div></td></tr>
<?php endforeach; if (! $printers): ?><tr><td colspan="5"><div class="empty"><i class="bi bi-printer"></i>Belum ada printer bar. Tambahkan printer LAN (IP + port 9100).</div></td></tr><?php endif; ?></tbody></table></div></div>

<div class="card-x mt-3"><div class="card-b"><div class="fw-bold mb-2">Antrean cetak terbaru</div>
<?php foreach ($jobs as $j): $c = ['done' => 'success', 'failed' => 'danger', 'pending' => 'warning', 'printing' => 'info'][$j['status']] ?? 'secondary'; ?>
<div class="list-row"><div><b><?= esc($j['ref']) ?></b> <small class="text-muted">· <?= esc($j['kind']) ?> · <?= esc($j['printer'] ?? '-') ?> · <?= esc(date('d/m H:i', strtotime($j['created_at']))) ?></small><?= $j['error'] ? '<br><small class="text-danger">' . esc($j['error']) . '</small>' : '' ?></div>
<div class="ms-auto d-flex gap-2 align-items-center"><span class="badge-soft badge-<?= $c ?>"><?= esc($j['status']) ?></span>
<?php if (in_array($j['status'], ['failed', 'done'], true)): ?><form method="post" action="/admin/printers/job/<?= $j['id'] ?>/retry"><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Cetak ulang</button></form><?php endif; ?></div></div>
<?php endforeach; if (! $jobs): ?><div class="text-muted small">Belum ada pekerjaan cetak.</div><?php endif; ?></div></div></div>

<div class="col-lg-5 anim"><div class="card-x card-b">
<div class="fw-bold mb-2"><i class="bi bi-pc-display"></i> Agen cetak outlet</div>
<p class="small text-muted">Aplikasi berjalan di cloud sehingga tidak bisa menjangkau IP printer LAN. Jalankan <b>agen cetak</b> (PHP) di satu komputer di outlet yang satu jaringan dengan printer bar.</p>
<label class="form-label small">Token outlet (rahasia)</label>
<div class="input-group mb-2"><input class="form-control" readonly id="tok" value="<?= esc($token) ?>"><button class="btn btn-soft" type="button" onclick="navigator.clipboard.writeText(document.getElementById('tok').value)">Salin</button></div>
<form method="post" action="/admin/printers/token" data-confirm="Buat token baru? Agen lama berhenti sampai token diperbarui."><?= csrf_field() ?><button class="btn btn-sm btn-ghost mb-3">Buat token baru</button></form>
<label class="form-label small">Perintah menjalankan</label>
<pre class="small p-2 rounded" style="background:#f6f1ee;white-space:pre-wrap;word-break:break-all">php print-agent.php --url=<?= esc($base) ?> --token=TOKEN_DI_ATAS</pre>
<ol class="small ps-3 mb-0"><li>Salin folder <code>tools/print-agent</code> ke komputer outlet (PHP 8 + ekstensi curl).</li><li>Jalankan perintah di atas (atau klik <code>start-agent.bat</code> setelah mengisi URL &amp; token).</li><li>Tekan <b>Tes</b> pada printer.</li></ol>
</div></div>
</div>

<div class="modal fade" id="prM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="/admin/printers/save"><?= csrf_field() ?><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title">Printer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">Nama</label><input name="name" class="form-control" required placeholder="Printer Bar"></div>
    <div class="col-8"><label class="form-label">IP / host printer</label><input name="host" class="form-control" required placeholder="192.168.1.50"></div>
    <div class="col-4"><label class="form-label">Port</label><input name="port" type="number" class="form-control" value="9100" data-default="9100"></div>
    <div class="col-6"><label class="form-label">Lebar kertas</label><select name="cols" class="form-select" data-default="32"><option value="32">58 mm (32 kolom)</option><option value="48">80 mm (48 kolom)</option></select></div>
    <div class="col-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="cut" value="1" id="prCut" data-default="1" checked><label class="form-check-label" for="prCut">Potong kertas otomatis</label></div></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?= $this->endSection() ?>
