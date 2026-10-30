<?php helper('app'); include APPPATH . 'Views/layouts/nav.php'; $u = auth_user(); $loc = loc_row(); ?>
<!doctype html>
<html lang="id">
<head>
<?php include APPPATH . 'Views/layouts/head.php'; ?>
<title><?= esc($title ?? 'Dashboard') ?> · Waroeng Mbok Reneo</title>
<?= $this->renderSection('head') ?>
</head>
<body class="<?= esc($bodyClass ?? '') ?>"<?= has_role('cashier', 'kitchen', 'admin') ? ' data-rsv="/reservations/upcoming"' : '' ?>>
<aside class="sidebar">
  <a class="brand" href="/">
    <img src="<?= base_url('assets/img/logo.png') ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/img/logo.svg') ?>'" alt="Logo">
    <div><b>Mbok Reneo</b><small>Waroeng POS</small></div>
  </a>
  <?php $n = 0; foreach ($visible as $g => $items): ?>
    <?php if ($n++): ?><div class="nav-sep"></div><?php endif; ?>
    <div class="nav-group"><?= esc($g) ?></div>
    <?php foreach ($items as $it): [$href, $ic, $label] = $it; ?>
      <a class="nav-i <?= $isActive($href) ? 'active' : '' ?>" href="<?= $href ?>" title="<?= esc($label) ?>"><i class="bi <?= $ic ?>"></i><span><?= esc($label) ?></span></a>
    <?php endforeach; ?>
  <?php endforeach; ?>
</aside>

<div class="main">
  <header class="topbar">
    <h1><?= esc($title ?? '') ?></h1>
    <?php if ($u['role'] === 'admin'): $locs = locations_all(); ?>
      <div class="dropdown">
        <button class="chip" data-bs-toggle="dropdown"><i class="bi bi-geo-alt-fill text-primary"></i><span class="hide-m"><?= esc($loc['name'] ?? '-') ?></span><span class="d-md-none"><?= esc($loc['code'] ?? '-') ?></span><i class="bi bi-chevron-down small"></i></button>
        <div class="dropdown-menu dropdown-menu-end">
          <?php foreach ($locs as $l): ?>
            <form method="post" action="/switch-location"><?= csrf_field() ?><input type="hidden" name="location_id" value="<?= $l['id'] ?>"><input type="hidden" name="back" value="<?= esc($path) ?>">
              <button class="dropdown-item <?= $l['id'] == $loc['id'] ? 'fw-bold text-primary' : '' ?>"><?= esc($l['name']) ?></button></form>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <span class="chip"><i class="bi bi-geo-alt-fill text-primary"></i><span class="hide-m"><?= esc($loc['name'] ?? '-') ?></span><span class="d-md-none"><?= esc($loc['code'] ?? '-') ?></span></span>
    <?php endif; ?>
    <div class="dropdown">
      <button class="chip" data-bs-toggle="dropdown"><span class="avatar"><?= esc(strtoupper(substr($u['name'], 0, 1))) ?></span><span class="hide-m"><?= esc($u['name']) ?></span></button>
      <div class="dropdown-menu dropdown-menu-end" style="min-width:210px">
        <div class="px-3 py-2"><b><?= esc($u['name']) ?></b><br><small class="text-muted"><?= esc(role_label($u['role'])) ?> · @<?= esc($u['username']) ?></small></div>
        <hr class="my-1">
        <form method="post" action="/logout"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button></form>
      </div>
    </div>
  </header>

  <main class="content">
    <?php if ($m = session()->getFlashdata('success')): ?><div class="flash" data-type="ok" data-msg="<?= esc($m) ?>"></div><?php endif; ?>
    <?php if ($m = session()->getFlashdata('error')): ?><div class="flash" data-type="err" data-msg="<?= esc($m) ?>"></div><?php endif; ?>
    <?= $this->renderSection('content') ?>
  </main>
</div>

<!-- Navigasi bawah (HP) -->
<nav class="bottom-nav">
  <?php $flat = array_merge(...array_map('array_values', array_values($visible)));
  $want = ['admin' => ['Dashboard', 'Kasir', 'Shift', 'Laporan'], 'cashier' => ['Kasir', 'Shift', 'PO', 'Terima'], 'kitchen' => ['PO', 'Stok']][$u['role']] ?? [];
  $quick = array_values(array_filter($flat, fn ($i) => in_array($i[4] ?? $i[2], $want, true)));
  usort($quick, fn ($a, $b) => array_search($a[4] ?? $a[2], $want) <=> array_search($b[4] ?? $b[2], $want));
  foreach ($quick as $q): [$href, $ic, $label] = $q; ?>
    <a href="<?= $href ?>" class="<?= $isActive($href) ? 'active' : '' ?>"><i class="bi <?= $ic ?>"></i><?= esc($q[4] ?? $label) ?></a>
  <?php endforeach; ?>
  <button data-bs-toggle="offcanvas" data-bs-target="#menuMobile"><i class="bi bi-grid-3x3-gap-fill"></i>Menu</button>
</nav>
<div class="offcanvas offcanvas-bottom" id="menuMobile" style="height:auto;max-height:80vh;border-radius:22px 22px 0 0">
  <div class="offcanvas-header"><b>Menu</b><button class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body pt-0">
    <?php foreach ($visible as $g => $items): ?>
      <div class="nav-group" style="display:block;padding-left:4px"><?= esc($g) ?></div>
      <?php foreach ($items as $it): [$href, $ic, $label] = $it; ?>
        <a class="nav-i <?= $isActive($href) ? 'active' : '' ?>" href="<?= $href ?>"><i class="bi <?= $ic ?>"></i><span style="display:inline"><?= esc($label) ?></span></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <form method="post" action="/logout" class="mt-3"><?= csrf_field() ?><button class="btn btn-ghost w-100 text-danger"><i class="bi bi-box-arrow-right"></i>Keluar</button></form>
  </div>
</div>

<?php include APPPATH . 'Views/layouts/scripts.php'; ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
