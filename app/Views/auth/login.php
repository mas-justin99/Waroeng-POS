<?php helper('app'); ?>
<!doctype html>
<html lang="id"><head>
<?php include APPPATH . 'Views/layouts/head.php'; ?>
<title>Masuk · Waroeng Mbok Reneo POS</title>
</head>
<body>
<div class="login">
  <div class="blob" style="width:340px;height:340px;background:#FF8A8E;left:-90px;top:-80px"></div>
  <div class="blob" style="width:280px;height:280px;background:#FFD66B;right:-60px;bottom:-60px"></div>
  <form class="login-card" method="post" action="/login" id="lf">
    <?= csrf_field() ?>
    <img class="logo" src="<?= base_url('assets/img/logo.png') ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/img/logo.svg') ?>'" alt="Waroeng Mbok Reneo">
    <h4 class="text-center mb-1">Waroeng Mbok Reneo</h4>
    <p class="text-center text-muted mb-4">Masuk ke sistem kasir & backoffice</p>
    <?php if ($m = session()->getFlashdata('error')): ?><div class="alert-x err"><i class="bi bi-exclamation-circle-fill"></i><span><?= esc($m) ?></span></div><?php endif; ?>
    <?php if ($m = session()->getFlashdata('success')): ?><div class="alert-x ok"><i class="bi bi-check-circle-fill"></i><span><?= esc($m) ?></span></div><?php endif; ?>
    <div class="mb-3"><label class="form-label">Username</label>
      <div class="input-icon"><i class="bi bi-person"></i><input class="form-control" name="username" value="<?= esc(old('username')) ?>" autocomplete="username" autocapitalize="none" autofocus required></div></div>
    <div class="mb-4"><label class="form-label">Password</label>
      <div class="input-icon"><i class="bi bi-lock"></i><input class="form-control" type="password" name="password" autocomplete="current-password" required></div></div>
    <button class="btn btn-primary btn-lg w-100">Masuk <i class="bi bi-arrow-right"></i></button>
    <p class="text-center text-muted small mt-3 mb-0"><i class="bi bi-shield-lock"></i> Satu akun hanya bisa aktif di satu perangkat.</p>
  </form>
</div>
<?php include APPPATH . 'Views/layouts/scripts.php'; ?>
<script>
  if (window.gsap) {
  gsap.from('.login-card',{y:30,opacity:0,scale:.96,duration:.7,ease:'back.out(1.4)'});
  gsap.from('.login-card .logo',{rotate:-12,scale:.6,duration:.9,ease:'elastic.out(1,.55)',delay:.15});
  gsap.to('.blob',{x:'random(-30,30)',y:'random(-30,30)',duration:6,repeat:-1,yoyo:true,ease:'sine.inOut',stagger:1});
  }
</script>
</body></html>
