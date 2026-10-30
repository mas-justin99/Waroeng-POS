<?= $this->extend('layouts/app') ?>
<?php $title = 'Pengguna'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div class="text-muted"><i class="bi bi-shield-lock"></i> Satu akun hanya bisa login di satu perangkat. Gunakan "Paksa logout" bila perangkat lama tertinggal.</div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uM" data-new="#uM" data-title="Pengguna baru"><i class="bi bi-plus-lg"></i> Tambah Pengguna</button></div>
<div class="card-x anim"><div class="table-wrap rtw"><table class="table-x rt"><thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Lokasi</th><th>Status</th><th>Terakhir aktif</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-l="Nama"><b><?= esc($r['name']) ?></b></td><td data-l="Username">@<?= esc($r['username']) ?></td><td data-l="Role"><?= esc(role_label($r['role'])) ?></td><td data-l="Lokasi"><?= esc($r['loc'] ?? 'Semua') ?></td>
<td data-l="Status"><?php if (! $r['is_active']): ?><span class="badge-soft badge-secondary">Nonaktif</span><?php elseif ($r['online']): ?><span class="badge-soft badge-success">● Online</span><?php else: ?><span class="badge-soft badge-info">Offline</span><?php endif; ?></td>
<td data-l="Terakhir aktif"><?= tgl($r['last_seen']) ?></td>
<td class="act text-end"><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#uM" data-edit="#uM" data-id="<?= $r['id'] ?>" data-name="<?= esc($r['name']) ?>" data-username="<?= esc($r['username']) ?>" data-role="<?= $r['role'] ?>" data-location_id="<?= $r['location_id'] ?>" data-title="Ubah pengguna">Ubah</button>
<?php if ($r['session_token']): ?><form class="d-inline" method="post" action="/admin/users/<?= $r['id'] ?>/force-logout" data-confirm="Paksa logout <?= esc($r['name']) ?>?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Paksa logout</button></form><?php endif; ?>
<form class="d-inline" method="post" action="/admin/users/<?= $r['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<div class="modal fade" id="uM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="/admin/users/save" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="id">
<div class="modal-header"><h5 class="modal-title">Pengguna</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-12"><label class="form-label">Nama lengkap</label><input name="name" class="form-control" required></div>
<div class="col-6"><label class="form-label">Username</label><input name="username" class="form-control" autocapitalize="none" required></div><div class="col-6"><label class="form-label">Password <small class="text-muted">(kosongkan bila tak diubah)</small></label><input name="password" type="password" class="form-control" autocomplete="new-password"></div>
<div class="col-6"><label class="form-label">Role</label><select name="role" class="form-select" data-default="cashier"><option value="cashier">Kasir</option><option value="kitchen">Dapur</option><option value="admin">Admin / Owner</option></select></div>
<div class="col-6"><label class="form-label">Lokasi</label><select name="location_id" class="form-select"><option value="">— (admin: semua)</option><?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>"><?= esc($l['name']) ?></option><?php endforeach; ?></select></div></div></div>
<div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?= $this->endSection() ?>
