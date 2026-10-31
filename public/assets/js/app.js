/* Waroeng Mbok Reneo POS — animasi (GSAP), toast, helper */
(function () {
  const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
  const csrfName = () => document.querySelector('meta[name=csrf-name]')?.content || 'csrf_test_name';
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
  window.rp = rp;

  // ---- Toast
  window.Toast = {
    show(msg, type = 'ok') {
      let w = document.querySelector('.toast-wrap');
      if (!w) { w = document.createElement('div'); w.className = 'toast-wrap'; document.body.appendChild(w); }
      const t = document.createElement('div');
      t.className = 'toast-x ' + type;
      t.innerHTML = '<i class="bi ' + (type === 'ok' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + '"></i><span></span>';
      t.querySelector('span').textContent = msg;
      w.appendChild(t);
      if (window.gsap && !reduce) gsap.from(t, { x: 60, opacity: 0, duration: .4, ease: 'back.out(1.6)' });
      setTimeout(() => { if (window.gsap && !reduce) gsap.to(t, { opacity: 0, y: -10, duration: .3, onComplete: () => t.remove() }); else t.remove(); }, 3800);
    }
  };

  // ---- POST JSON helper (CSRF otomatis)
  window.api = async function (url, data) {
    const r = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
      body: JSON.stringify(data || {})
    });
    if (r.status === 401) { const j = await r.json().catch(() => ({})); location.href = j.login || '/login'; throw new Error('Sesi berakhir'); }
    const j = await r.json().catch(() => ({ ok: false, message: 'Respon server tidak valid' }));
    if (!r.ok || j.ok === false) throw new Error(j.message || 'Terjadi kesalahan');
    return j;
  };

  // ---- Animasi masuk halaman + counter
  function pageIn() {
    if (!window.gsap || reduce) return;
    const els = document.querySelectorAll('.anim');
    if (els.length) gsap.from(els, { y: 16, opacity: 0, duration: .55, stagger: .05, ease: 'power2.out', clearProps: 'transform,opacity' });
    document.querySelectorAll('[data-count]').forEach(el => {
      const end = parseFloat(el.dataset.count) || 0, fmt = el.dataset.format || 'int', o = { v: 0 };
      gsap.to(o, { v: end, duration: 1.1, ease: 'power2.out', onUpdate: () => { el.textContent = fmt === 'rupiah' ? rp(o.v) : Math.round(o.v).toLocaleString('id-ID'); } });
    });
    document.querySelectorAll('.progress-x>i').forEach(el => { const w = el.style.width; gsap.from(el, { width: 0, duration: .9, ease: 'power2.out', delay: .2 }); el.style.width = w; });
  }
  window.pageIn = pageIn;

  // ---- Ripple tombol
  document.addEventListener('pointerdown', e => {
    const b = e.target.closest('.btn'); if (!b || reduce) return;
    const r = b.getBoundingClientRect(), s = Math.max(r.width, r.height);
    const d = document.createElement('span'); d.className = 'ripple';
    d.style.cssText = `width:${s}px;height:${s}px;left:${e.clientX - r.left - s / 2}px;top:${e.clientY - r.top - s / 2}px`;
    b.appendChild(d); setTimeout(() => d.remove(), 600);
  });

  // ---- Konfirmasi (SweetAlert2) untuk form/tombol data-confirm
  document.addEventListener('submit', async e => {
    const f = e.target;
    if (f.dataset.confirmed) return;
    const msg = f.dataset.confirm; if (msg && window.Swal) {
      e.preventDefault();
      const r = await Swal.fire({ title: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, lanjut', cancelButtonText: 'Batal', confirmButtonColor: '#E32F37', reverseButtons: true });
      if (r.isConfirmed) { f.dataset.confirmed = 1; f.requestSubmit ? f.requestSubmit() : f.submit(); }
      return;
    }
    // cegah double submit
    f.querySelectorAll('button[type=submit]').forEach(b => setTimeout(() => { b.disabled = true; }, 0));
  });

  // ---- Heartbeat: jaga sesi tetap "aktif" & deteksi login dari perangkat lain
  async function ping() {
    try { await api('/ping', {}); } catch (e) { /* api() mengarahkan ke login saat 401 */ }
  }
  setInterval(() => { if (!document.hidden && document.querySelector('.sidebar, .bottom-nav')) ping(); }, 45000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden && document.querySelector('.sidebar')) ping(); });

  // ---- Modal edit: isi field dari data-* tombol
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-edit]'); if (!b) return;
    const m = document.querySelector(b.dataset.edit); if (!m) return;
    const d = b.dataset;
    m.querySelectorAll('[name]').forEach(f => {
      if (f.type === 'file') return;
      const v = d[f.name]; if (v !== undefined) f.value = v; else if (f.dataset.default !== undefined) f.value = f.dataset.default; else if (f.name !== csrfName() && f.type !== 'hidden') f.value = '';
    });
    const t = m.querySelector('.modal-title'); if (t && d.title) t.textContent = d.title;
  });
  document.querySelectorAll('[data-new]').forEach(b => b.addEventListener('click', () => {
    const m = document.querySelector(b.dataset.new); if (!m) return;
    m.querySelectorAll('[name]').forEach(f => { if (f.type !== 'hidden' && f.type !== 'file') f.value = f.dataset.default ?? ''; });
    m.querySelector('[name=id]').value = '';
    const t = m.querySelector('.modal-title'); if (t && b.dataset.title) t.textContent = b.dataset.title;
  }));


  // ---- Pengingat reservasi (berjalan selama aplikasi terbuka)
  const rsvUrl = document.body.dataset.rsv;
  if (rsvUrl) {
    const box = document.createElement('div'); box.id = 'rsvAlerts'; document.body.appendChild(box);
    const seen = new Set();
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const beep = () => { try { const a = new (window.AudioContext || window.webkitAudioContext)(), o = a.createOscillator(), g = a.createGain(); o.connect(g); g.connect(a.destination); o.frequency.value = 880; g.gain.value = .15; o.start(); setTimeout(() => { o.stop(); a.close(); }, 350); } catch (e) {} if (navigator.vibrate) navigator.vibrate([200, 100, 200]); };
    const badge = n => document.querySelectorAll('a[href="/reservations"]').forEach(a => { let d = a.querySelector('.nav-dot'); if (!n) { d && d.remove(); return; } if (!d) { d = document.createElement('span'); d.className = 'nav-dot'; a.appendChild(d); } d.textContent = n; });
    const ack = id => api('/reservations/' + id + '/ack', {}).catch(() => {});
    async function poll() {
      if (document.hidden) return;
      let r; try { r = await fetch(rsvUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }); if (!r.ok) return; r = await r.json(); } catch (e) { return; }
      const ids = new Set(r.items.map(i => i.id));
      box.querySelectorAll('[data-id]').forEach(el => { if (!ids.has(+el.dataset.id)) el.remove(); });
      let fresh = false;
      r.items.forEach(i => {
        const when = i.late ? 'terlambat ' + Math.abs(i.left) + ' mnt' : (i.left <= 0 ? 'sekarang' : i.left + ' menit lagi');
        let el = box.querySelector('[data-id="' + i.id + '"]');
        if (!el) { el = document.createElement('div'); el.className = 'rsv-alert'; el.dataset.id = i.id; box.appendChild(el); if (!seen.has(i.id)) { seen.add(i.id); fresh = true; } }
        el.innerHTML = '<div class="t"><i class="bi bi-alarm"></i> Reservasi ' + esc(i.time) + ' · ' + when + '</div><div>' + esc(i.name) + ' · ' + i.pax + ' orang' + (i.table ? ' · Meja ' + esc(i.table) : '') + '</div>'
          + '<div class="b"><button class="btn btn-sm btn-primary" data-ok>Sudah diingatkan</button><a class="btn btn-sm btn-ghost" href="/reservations">Lihat</a>' + (i.wa ? '<a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="' + esc(i.wa) + '"><i class="bi bi-whatsapp"></i></a>' : '') + '</div>';
        el.querySelector('[data-ok]').onclick = () => { ack(i.id); el.remove(); badge(box.children.length); };
      });
      badge(box.children.length);
      if (fresh) beep();
    }
    setTimeout(poll, 2500); setInterval(poll, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  }


  // ---- Kompres foto otomatis sebelum diunggah (hemat kuota & lolos batas 4,5 MB Vercel)
  (function () {
    const MAX = 1600, Q = 0.8;
    const kb = n => n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.round(n / 1024) + ' KB';
    async function load(file) {
      if (window.createImageBitmap) { try { return await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (e) {} }
      return await new Promise((ok, no) => { const u = URL.createObjectURL(file), i = new Image(); i.onload = () => { URL.revokeObjectURL(u); ok(i); }; i.onerror = no; i.src = u; });
    }
    function hint(inp, msg, err) {
      let h = inp.parentNode.querySelector('.img-hint');
      if (!h) { h = document.createElement('div'); h.className = 'form-text img-hint'; inp.insertAdjacentElement('afterend', h); }
      h.textContent = msg; h.style.color = err ? '#b45309' : '';
    }
    document.addEventListener('change', async e => {
      const inp = e.target;
      if (!(inp instanceof HTMLInputElement) || inp.type !== 'file' || !/image/.test(inp.accept || '') || !inp.files || !inp.files[0]) return;
      const f = inp.files[0];
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return;
      inp.dataset.busy = '1'; hint(inp, 'Mengompres foto…');
      try {
        const img = await load(f), w = img.width || img.naturalWidth, h = img.height || img.naturalHeight;
        const k = Math.min(1, MAX / Math.max(w, h));
        const cv = document.createElement('canvas'); cv.width = Math.max(1, Math.round(w * k)); cv.height = Math.max(1, Math.round(h * k));
        const cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(img, 0, 0, cv.width, cv.height);
        const blob = await new Promise(r => cv.toBlob(r, 'image/jpeg', Q));
        if (blob && (blob.size < f.size || k < 1)) {
          const nf = new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
          const dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
          hint(inp, 'Foto dikompres otomatis: ' + kb(f.size) + ' → ' + kb(nf.size));
        } else hint(inp, 'Foto ' + kb(f.size) + ' (sudah kecil)');
      } catch (err) { hint(inp, 'Foto dikirim apa adanya (' + kb(f.size) + ').', true); }
      delete inp.dataset.busy;
    });
    document.addEventListener('submit', e => {
      if (e.target.querySelector && e.target.querySelector('input[type=file][data-busy]')) { e.preventDefault(); Toast.show('Tunggu sebentar, foto sedang dikompres.', 'err'); }
    }, true);
  })();

  // ---- Service worker (PWA ringan, hanya aset statis)
  if ('serviceWorker' in navigator && location.protocol === 'https:') navigator.serviceWorker.register('/sw.js').catch(() => {});

  document.addEventListener('DOMContentLoaded', () => {
    pageIn();
    document.querySelectorAll('.flash').forEach(f => Toast.show(f.dataset.msg, f.dataset.type));
  });
})();
