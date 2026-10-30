<?= $this->extend('layouts/app') ?>
<?php $title = 'Reservasi'; $tabs = ['today' => 'Hari Ini', 'upcoming' => 'Mendatang', 'past' => 'Lewat']; ?>
<?= $this->section('content') ?>
<div class="page-head anim">
  <div class="seg">
    <?php foreach ($tabs as $k => $l): ?><a href="/reservations?v=<?= $k ?>" class="<?= $view === $k ? 'on' : '' ?>"><?= $l ?><?php if (isset($counts[$k]) && $counts[$k]): ?> <span class="badge-soft badge-primary"><?= $counts[$k] ?></span><?php endif; ?></a><?php endforeach; ?>
  </div>
  <form class="d-flex gap-2 flex-grow-1 justify-content-end" method="get">
    <input type="hidden" name="v" value="<?= esc($view) ?>">
    <div class="input-icon" style="min-width:min(100%,240px)"><i class="bi bi-search"></i><input name="q" value="<?= esc($q) ?>" class="form-control" placeholder="Cari nama / telepon"></div>
    <?php if ($canEdit): ?><button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#rsvM" data-new="#rsvM" data-title="Reservasi baru"><i class="bi bi-plus-lg"></i> Reservasi</button><?php endif; ?>
  </form>
</div>
<?php if ($view === 'today'): ?><div class="text-muted small mb-2 anim"><i class="bi bi-people"></i> <?= $guests ?> tamu terjadwal hari ini · <?= tgl(date('Y-m-d H:i:s'), 'd M Y') ?></div><?php endif; ?>

<?php if (! $rows): ?><div class="card-x anim"><div class="empty">Belum ada reservasi<?= $view === 'today' ? ' untuk hari ini' : '' ?>.</div></div><?php endif; ?>
<div class="rsv-list">
<?php foreach ($rows as $r): [$sl, $sc] = $status[$r['status']] ?? [$r['status'], 'secondary']; $ts = strtotime($r['reserved_at']);
  $active = in_array($r['status'], ['booked', 'confirmed'], true); $left = (int) floor(($ts - time()) / 60); ?>
<div class="card-x card-b rsv anim <?= $active && $left < 0 ? 'rsv-late' : '' ?>">
  <div class="rsv-time"><b><?= date('H:i', $ts) ?></b><small><?= date('d M', $ts) ?></small></div>
  <div class="rsv-main">
    <div class="d-flex flex-wrap align-items-center gap-2"><b><?= esc($r['customer_name']) ?></b><span class="badge-soft badge-<?= $sc ?>"><?= $sl ?></span>
      <?php if ($active && $left <= 120 && $left >= -60): ?><span class="badge-soft <?= $left < 0 ? 'badge-danger' : 'badge-warning' ?>"><i class="bi bi-alarm"></i> <?= $left < 0 ? 'terlambat ' . abs($left) . ' mnt' : ($left === 0 ? 'sekarang' : $left . ' mnt lagi') ?></span><?php endif; ?></div>
    <div class="text-muted small"><i class="bi bi-people"></i> <?= (int) $r['pax'] ?> orang<?= $r['table_no'] ? ' · Meja ' . esc($r['table_no']) : '' ?><?= $r['phone'] ? ' · ' . esc($r['phone']) : '' ?><?= $r['dp_amount'] > 0 ? ' · DP ' . rupiah($r['dp_amount']) : '' ?></div>
    <?php if ($r['note']): ?><div class="small mt-1"><i class="bi bi-chat-left-text"></i> <?= esc($r['note']) ?></div><?php endif; ?>
  </div>
  <?php if ($canEdit): ?>
  <div class="rsv-act">
    <?php if ($r['wa'] && $active): ?><a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="<?= esc($r['wa']) ?>" title="Ingatkan pelanggan via WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
    <?php if ($active): ?>
      <?php if ($r['status'] === 'booked'): ?><form method="post" action="/reservations/<?= $r['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="confirmed"><input type="hidden" name="back" value="/reservations?v=<?= $view ?>"><button class="btn btn-sm btn-ghost" title="Konfirmasi"><i class="bi bi-check2"></i></button></form><?php endif; ?>
      <form method="post" action="/reservations/<?= $r['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="arrived"><input type="hidden" name="back" value="/reservations?v=<?= $view ?>"><button class="btn btn-sm btn-primary"><i class="bi bi-person-check"></i> Datang</button></form>
      <div class="dropdown"><button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#rsvM" data-edit="#rsvM" data-title="Ubah reservasi" data-id="<?= $r['id'] ?>" data-customer_name="<?= esc($r['customer_name'], 'attr') ?>" data-phone="<?= esc((string) $r['phone'], 'attr') ?>" data-reserved_at="<?= date('Y-m-d\TH:i', $ts) ?>" data-pax="<?= (int) $r['pax'] ?>" data-table_no="<?= esc((string) $r['table_no'], 'attr') ?>" data-dp_amount="<?= (int) $r['dp_amount'] ?>" data-note="<?= esc((string) $r['note'], 'attr') ?>" data-remind_minutes="<?= (int) $r['remind_minutes'] ?>"><i class="bi bi-pencil"></i> Ubah</button></li>
          <?php foreach (['no_show' => ['Tidak datang', 'bi-person-x'], 'cancelled' => ['Batalkan', 'bi-x-circle']] as $k => [$l, $ic]): ?>
          <li><form method="post" action="/reservations/<?= $r['id'] ?>/status" onsubmit="return confirm('<?= $l ?> reservasi ini?')"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $k ?>"><input type="hidden" name="back" value="/reservations?v=<?= $view ?>"><button class="dropdown-item text-danger"><i class="bi <?= $ic ?>"></i> <?= $l ?></button></form></li>
          <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php if ($canEdit): ?>
<div class="modal fade" id="rsvM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="/reservations/save"><?= csrf_field() ?><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title">Reservasi baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">Nama pemesan *</label><input name="customer_name" class="form-control" required maxlength="100"></div>
    <div class="col-12 col-sm-6"><label class="form-label">No. HP / WhatsApp</label><input name="phone" type="tel" inputmode="tel" class="form-control" placeholder="08xxxxxxxxxx" maxlength="20"></div>
    <div class="col-12 col-sm-6"><label class="form-label">Tanggal & jam *</label><input name="reserved_at" type="datetime-local" class="form-control" required></div>
    <div class="col-6 col-sm-4"><label class="form-label">Jumlah orang *</label><input name="pax" type="number" min="1" max="500" class="form-control" value="2" data-default="2" required></div>
    <div class="col-6 col-sm-4"><label class="form-label">No. meja</label><input name="table_no" class="form-control" maxlength="20" placeholder="mis. 5"></div>
    <div class="col-12 col-sm-4"><label class="form-label">DP (Rp)</label><input name="dp_amount" inputmode="numeric" class="form-control money" value="0" data-default="0"></div>
    <div class="col-12"><label class="form-label">Ingatkan</label>
      <select name="remind_minutes" class="form-select" data-default="60"><?php foreach ([0 => 'Tepat waktu', 15 => '15 menit sebelum', 30 => '30 menit sebelum', 60 => '1 jam sebelum', 120 => '2 jam sebelum', 180 => '3 jam sebelum', 1440 => '1 hari sebelum'] as $v => $l): ?><option value="<?= $v ?>" <?= $v === 60 ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Catatan</label><input name="note" class="form-control" maxlength="255" placeholder="mis. ulang tahun, minta kursi bayi" data-default=""></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>
