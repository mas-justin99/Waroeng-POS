<?= $this->extend('layouts/app') ?>
<?php $title = 'Buat Pesanan Pembelian'; ?>
<?= $this->section('content') ?>
<div class="page-head anim"><div><a href="/po" class="small"><i class="bi bi-arrow-left"></i> Pesanan Pembelian</a><h2>Pesanan dari Dapur</h2>
<div class="text-muted">Daftarkan makanan yang baru dibuat (SKU, HPP, Qty). Kasir akan menerimanya menjadi stok warung.</div></div></div>
<form method="post" action="/po/store" id="poForm" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-lg-8 anim"><div class="card-x card-b">
    <label class="form-label">Cari menu / SKU</label>
    <div class="input-icon position-relative"><i class="bi bi-search"></i><input id="ps" class="form-control" placeholder="Ketik nama atau SKU, lalu Enter / ketuk hasil" autocomplete="off">
      <div id="sug" class="card-x d-none" style="position:absolute;left:0;right:0;top:52px;z-index:20;max-height:280px;overflow:auto;padding:6px"></div></div>
    <div class="table-wrap mt-3"><table class="table-x" id="lt"><thead><tr><th>SKU / Menu</th><th style="width:150px">HPP / porsi</th><th style="width:150px">Qty</th><th class="text-end">Subtotal</th><th></th></tr></thead><tbody></tbody></table></div>
    <div id="emptyL" class="empty"><i class="bi bi-basket"></i>Belum ada item. Cari menu di atas.</div>
  </div></div>
  <div class="col-lg-4 anim"><div class="card-x card-b">
    <div class="list-row"><span class="text-muted">Jenis item</span><b class="ms-auto" id="t-n">0</b></div>
    <div class="list-row"><span class="text-muted">Total porsi</span><b class="ms-auto" id="t-q">0</b></div>
    <div class="list-row"><span class="text-muted">Total HPP</span><b class="ms-auto num" id="t-h" style="font-size:1.25rem">Rp 0</b></div>
    <label class="form-label mt-3">Catatan (opsional)</label><textarea name="notes" class="form-control" rows="2" maxlength="250" placeholder="mis. Masakan pagi, dikirim 06.30"></textarea>
    <button class="btn btn-primary btn-lg w-100 mt-3" id="sub" disabled><i class="bi bi-send"></i> Kirim ke Kasir</button>
  </div></div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const PR = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const rows = {}; const $ = s => document.querySelector(s);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function draw(){
  const ids = Object.keys(rows); let q=0,h=0;
  $('#lt tbody').innerHTML = ids.map((id,i)=>{ const r=rows[id],p=r.p; q+=r.qty; h+=r.qty*r.hpp;
    return `<tr data-id="${id}"><td><input type="hidden" name="items[${i}][product_id]" value="${id}"><b>${esc(p.sku)}</b><br><small class="text-muted">${esc(p.name)}</small></td>
    <td><input class="form-control" name="items[${i}][hpp]" data-f="hpp" inputmode="numeric" value="${r.hpp}"></td>
    <td><div class="stepper"><button type="button" data-f="dec">−</button><input name="items[${i}][qty]" data-f="qty" inputmode="numeric" value="${r.qty}"><button type="button" data-f="inc">+</button></div></td>
    <td class="text-end num fw-bold">${rp(r.qty*r.hpp)}</td><td><button type="button" class="btn btn-sm btn-ghost text-danger" data-f="rm"><i class="bi bi-trash3"></i></button></td></tr>`}).join('');
  $('#emptyL').style.display = ids.length ? 'none':''; $('#lt').style.display = ids.length ? '':'none';
  $('#t-n').textContent=ids.length; $('#t-q').textContent=q; $('#t-h').textContent=rp(h); $('#sub').disabled=!ids.length;
}
function add(id){ const p=PR.find(x=>x.id==id); if(!p) return; if(rows[id]) rows[id].qty++; else rows[id]={p,qty:1,hpp:p.hpp}; draw(); $('#ps').value=''; $('#sug').classList.add('d-none'); if(window.gsap) gsap.from(`#lt tr[data-id="${id}"]`,{opacity:0,x:-20,duration:.35}); }
$('#ps').addEventListener('input',e=>{ const q=e.target.value.toLowerCase().trim(); if(!q){$('#sug').classList.add('d-none');return;}
  const m=PR.filter(p=>p.name.toLowerCase().includes(q)||p.sku.toLowerCase().includes(q)).slice(0,8);
  $('#sug').innerHTML = m.length ? m.map(p=>`<div class="list-row" style="padding:10px;cursor:pointer" data-id="${p.id}"><div class="ph" style="width:36px;height:36px">${esc(p.name[0])}</div><div><b>${esc(p.name)}</b><br><small class="text-muted">${esc(p.sku)} · HPP ${rp(p.hpp)}</small></div></div>`).join('') : '<div class="empty" style="padding:14px">Tidak ditemukan</div>';
  $('#sug').classList.remove('d-none'); });
$('#ps').addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); const q=e.target.value.toLowerCase().trim(); const m=PR.find(p=>p.sku.toLowerCase()===q)||PR.find(p=>p.name.toLowerCase().includes(q)||p.sku.toLowerCase().includes(q)); if(m) add(m.id);} });
$('#sug').addEventListener('click',e=>{ const r=e.target.closest('[data-id]'); if(r) add(r.dataset.id); });
document.addEventListener('click',e=>{ if(!e.target.closest('#sug,#ps')) $('#sug').classList.add('d-none'); });
$('#lt').addEventListener('click',e=>{ const b=e.target.closest('[data-f]'); if(!b) return; const id=b.closest('tr').dataset.id;
  if(b.dataset.f==='inc') rows[id].qty++; else if(b.dataset.f==='dec') rows[id].qty=Math.max(1,rows[id].qty-1); else if(b.dataset.f==='rm') delete rows[id]; else return; draw(); });
$('#lt').addEventListener('change',e=>{ const id=e.target.closest('tr').dataset.id; if(e.target.dataset.f==='qty') rows[id].qty=Math.max(1,parseInt(e.target.value)||1); if(e.target.dataset.f==='hpp') rows[id].hpp=Math.max(0,parseFloat(e.target.value)||0); draw(); });
draw();
</script>
<?= $this->endSection() ?>
