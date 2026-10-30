<?= $this->extend('layouts/app') ?>
<?php $title = 'Dashboard'; $locs = locations_all(); $avg = $kpi['trx'] ? $kpi['sales'] / $kpi['trx'] : 0; ?>
<?= $this->section('content') ?>
<form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3 anim" id="ff">
  <div class="seg">
    <?php foreach (['today' => 'Hari ini', '7d' => '7 Hari', '30d' => '30 Hari', 'month' => 'Bulan ini'] as $k => $l): ?>
      <a class="<?= $range === $k ? 'on' : '' ?>" href="?range=<?= $k ?>&loc=<?= esc($loc) ?>"><?= $l ?></a><?php endforeach; ?>
  </div>
  <select name="loc" class="form-select" style="width:auto" onchange="this.form.submit()">
    <option value="all">Semua lokasi</option>
    <?php foreach ($locs as $l): ?><option value="<?= $l['id'] ?>" <?= (string) $loc === (string) $l['id'] ? 'selected' : '' ?>><?= esc($l['name']) ?></option><?php endforeach; ?>
  </select>
  <input type="hidden" name="range" value="custom">
  <input type="date" name="from" value="<?= esc($from) ?>" class="form-control" style="width:auto" onchange="this.form.submit()">
  <input type="date" name="to" value="<?= esc($to) ?>" class="form-control" style="width:auto" onchange="this.form.submit()">
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi"><div class="ic"><i class="bi bi-cash-stack"></i></div><div class="v" data-count="<?= $kpi['sales'] ?>" data-format="rupiah">Rp 0</div><div class="l">Total Penjualan</div></div></div>
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi k2"><div class="ic"><i class="bi bi-graph-up-arrow"></i></div><div class="v" data-count="<?= $kpi['profit'] ?>" data-format="rupiah">Rp 0</div><div class="l">Laba Kotor</div></div></div>
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi k3"><div class="ic"><i class="bi bi-receipt"></i></div><div class="v" data-count="<?= $kpi['trx'] ?>">0</div><div class="l">Transaksi · rata-rata <?= rupiah($avg) ?></div></div></div>
  <div class="col-6 col-xl-3 anim"><div class="card-x kpi k4"><div class="ic"><i class="bi bi-egg-fried"></i></div><div class="v" data-count="<?= $items ?>">0</div><div class="l">Porsi Terjual</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8 anim"><div class="card-x"><div class="card-h">Tren Penjualan & Laba</div><div class="card-b"><div style="height:280px"><canvas id="cTrend"></canvas></div></div></div></div>
  <div class="col-lg-4 anim"><div class="card-x h-100"><div class="card-h">Metode Pembayaran</div><div class="card-b"><?php if ($methods): ?><div style="height:200px"><canvas id="cPay"></canvas></div>
    <div class="mt-3"><?php foreach ($methods as $m): ?><div class="list-row py-2"><span><?= esc($m['n']) ?></span><b class="ms-auto num"><?= rupiah($m['t']) ?></b></div><?php endforeach; ?></div>
    <?php else: ?><div class="empty"><i class="bi bi-pie-chart"></i>Belum ada data</div><?php endif; ?></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4 anim"><div class="card-x h-100"><div class="card-h">Menu Terlaris</div><div class="card-b">
    <?php $mx = max(1, ...array_map(fn ($t) => (int) $t['q'], $top ?: [['q' => 1]])); foreach ($top as $i => $t): ?>
      <div class="mb-3"><div class="d-flex justify-content-between"><span class="fw-semibold"><?= $i + 1 ?>. <?= esc($t['name']) ?></span><b><?= $t['q'] ?>x</b></div><div class="progress-x mt-1"><i style="width:<?= round($t['q'] / $mx * 100) ?>%"></i></div></div>
    <?php endforeach; if (! $top): ?><div class="empty"><i class="bi bi-trophy"></i>Belum ada data</div><?php endif; ?></div></div></div>
  <div class="col-lg-4 anim"><div class="card-x h-100"><div class="card-h">Jam Ramai</div><div class="card-b"><div style="height:230px"><canvas id="cHour"></canvas></div></div></div></div>
  <div class="col-lg-4 anim"><div class="card-x h-100"><div class="card-h">Per Lokasi</div><div class="card-b">
    <?php foreach ($byLoc as $l): ?><div class="list-row"><div class="ph"><i class="bi bi-shop"></i></div><div><b><?= esc($l['n']) ?></b><br><small class="text-muted"><?= $l['trx'] ?> transaksi</small></div><b class="ms-auto num"><?= rupiah($l['t']) ?></b></div><?php endforeach; ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4 anim"><div class="card-x h-100"><div class="card-h">Perlu Perhatian</div><div class="card-b">
    <div class="list-row"><div class="ph" style="background:var(--warn-soft);color:var(--warn)"><i class="bi bi-clipboard2-check"></i></div><div><b><?= $poPending ?> PO menunggu diterima</b><br><small class="text-muted">Dari dapur ke kasir</small></div></div>
    <div class="list-row"><div class="ph" style="background:var(--info-soft);color:var(--info)"><i class="bi bi-boxes"></i></div><div><b>Nilai persediaan</b><br><small class="text-muted">berdasarkan HPP rata-rata</small></div><b class="ms-auto num"><?= rupiah($stockValue) ?></b></div>
    <div class="mt-3 fw-bold">Stok menipis</div>
    <?php foreach ($low as $s): ?><div class="list-row py-2"><span><?= esc($s['name']) ?><br><small class="text-muted"><?= esc($s['loc']) ?></small></span><span class="badge-soft <?= $s['qty'] <= 0 ? 'badge-danger' : 'badge-warning' ?> ms-auto"><?= $s['qty'] ?></span></div><?php endforeach; if (! $low): ?><div class="text-muted small">Semua stok aman 👍</div><?php endif; ?></div></div></div>
  <div class="col-lg-4 anim"><div class="card-x h-100"><div class="card-h">Transaksi Terbaru</div><div class="card-b">
    <?php foreach ($recent as $r): ?><a href="/sales/<?= $r['id'] ?>" class="list-row text-decoration-none text-reset"><div><b><?= esc($r['invoice_no']) ?></b><br><small class="text-muted"><?= tgl($r['created_at']) ?></small></div><div class="ms-auto text-end"><b class="num"><?= rupiah($r['total']) ?></b><br><?= po_badge($r['status']) ?></div></a><?php endforeach; if (! $recent): ?><div class="empty">Belum ada transaksi</div><?php endif; ?></div></div></div>
  <div class="col-lg-4 anim"><div class="card-x h-100"><div class="card-h">Pengguna Online <span class="badge-soft badge-success"><?= count($online) ?></span></div><div class="card-b">
    <?php foreach ($online as $o): ?><div class="list-row"><span class="avatar"><?= esc(strtoupper(substr($o['name'], 0, 1))) ?></span><div><b><?= esc($o['name']) ?></b><br><small class="text-muted"><?= esc(role_label($o['role'])) ?> · <?= esc($o['loc'] ?? 'Semua lokasi') ?></small></div></div><?php endforeach; if (! $online): ?><div class="empty">Tidak ada yang online</div><?php endif; ?></div></div></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
if (!window.Chart) {
  document.querySelectorAll('canvas').forEach(c => c.replaceWith(Object.assign(document.createElement('div'), { className: 'empty', textContent: 'Grafik tidak dapat dimuat. Periksa koneksi internet.' })));
} else {
Chart.defaults.font.family="Inter,system-ui,sans-serif"; Chart.defaults.color='#78716C';
const fmt=v=>'Rp '+Math.round(v).toLocaleString('id-ID'); const C=['#E32F37','#F59E0B','#16A34A','#0284C7','#7C3AED','#DB2777','#64748B','#0D9488'];
const tip={callbacks:{label:c=>' '+c.dataset.label+': '+fmt(c.parsed.y??c.parsed)}};
new Chart(cTrend,{type:'bar',data:{labels:<?= json_encode($labels) ?>,datasets:[{type:'bar',label:'Penjualan',data:<?= json_encode($sales) ?>,backgroundColor:'#F6999C',hoverBackgroundColor:'#E32F37',borderRadius:8,maxBarThickness:36},{type:'line',label:'Laba kotor',data:<?= json_encode($profit) ?>,borderColor:'#16A34A',backgroundColor:'#16A34A',tension:.35,pointRadius:3}]},
 options:{maintainAspectRatio:false,animation:{duration:1100,easing:'easeOutQuart'},plugins:{legend:{position:'bottom',labels:{usePointStyle:true}},tooltip:tip},scales:{y:{ticks:{callback:v=>v>=1e6?(v/1e6)+'jt':(v>=1e3?(v/1e3)+'rb':v)},grid:{color:'#F1ECE7'},border:{display:false}},x:{grid:{display:false}}}}});
<?php if ($methods): ?>new Chart(cPay,{type:'doughnut',data:{labels:<?= json_encode(array_column($methods, 'n')) ?>,datasets:[{data:<?= json_encode(array_map(fn ($m) => (float) $m['t'], $methods)) ?>,backgroundColor:C,borderWidth:2,borderColor:'#fff'}]},options:{maintainAspectRatio:false,cutout:'66%',animation:{animateRotate:true,duration:1200},plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>' '+c.label+': '+fmt(c.parsed)}}}}});<?php endif; ?>
new Chart(cHour,{type:'bar',data:{labels:[...Array(24).keys()].map(h=>String(h).padStart(2,'0')),datasets:[{label:'Penjualan',data:<?= json_encode(array_values($hours)) ?>,backgroundColor:'#FBCFD1',hoverBackgroundColor:'#E32F37',borderRadius:5}]},options:{maintainAspectRatio:false,animation:{duration:1000},plugins:{legend:{display:false},tooltip:tip},scales:{y:{display:false},x:{grid:{display:false},ticks:{maxTicksLimit:12}}}}});
}
</script>
<?= $this->endSection() ?>
