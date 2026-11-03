/* Cetak struk ke printer thermal Bluetooth (mis. RPP02N) langsung dari Chrome Android.
   Membaca isi <div class="receipt"> -> perintah ESC/POS (teks, 32 kolom untuk 58 mm), lalu mengirim lewat:
   1) RawBT  : aplikasi perantara untuk printer Bluetooth klasik (SPP) - paling umum untuk RPP02N
   2) Web Bluetooth : langsung dari Chrome, khusus printer BLE
   3) Cetak biasa (dialog cetak browser) tetap tersedia. */
(function () {
  const ESC = 0x1b, GS = 0x1d, LF = 0x0a;
  const SERVICES = ['000018f0-0000-1000-8000-00805f9b34fb', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2', '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    '0000ffe0-0000-1000-8000-00805f9b34fb', '0000ff00-0000-1000-8000-00805f9b34fb', '0000ae30-0000-1000-8000-00805f9b34fb', '0000fee7-0000-1000-8000-00805f9b34fb'];

  const clean = s => String(s ?? '').replace(/[ \t]+/g, ' ').replace(/[·•]/g, '-').replace(/[–—]/g, '-').replace(/[“”]/g, '"').replace(/[‘’]/g, "'").replace(/…/g, '...')
    .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\x20-\x7e]/g, '?');
  const wrap = (t, w) => { // bungkus kata
    const out = []; t = clean(t).trim(); if (!t) return [''];
    for (let line of t.split('\n')) {
      while (line.length > w) { let k = line.lastIndexOf(' ', w); if (k < w * 0.4) k = w; out.push(line.slice(0, k).trimEnd()); line = line.slice(k).trimStart(); }
      out.push(line);
    }
    return out;
  };

  /** Ubah isi struk (elemen .receipt) menjadi byte ESC/POS. */
  function fromReceipt(root, cols) {
    cols = cols || 32;
    const bytes = [ESC, 0x40]; // reset printer
    const put = (str) => { for (const ch of clean(str)) bytes.push(ch.charCodeAt(0)); bytes.push(LF); };
    const align = n => bytes.push(ESC, 0x61, n);
    const bold = on => bytes.push(ESC, 0x45, on ? 1 : 0);
    const row = (l, r) => {
      l = clean(l).trim(); r = clean(r).trim();
      if (!r) return wrap(l, cols);
      const w = cols - r.length - 1, parts = wrap(l, Math.max(8, w)), last = parts.pop() ?? '';
      if (last.length + r.length + 1 <= cols) return [...parts, last + ' '.repeat(cols - last.length - r.length) + r];
      return [...parts, last, ' '.repeat(Math.max(0, cols - r.length)) + r];
    };

    for (const el of root.children) {
      if (el.tagName === 'IMG' || el.classList.contains('no-print')) continue;
      if (el.tagName === 'HR') { align(0); bold(0); put('-'.repeat(cols)); continue; }
      const isBold = (el.style && parseInt(el.style.fontWeight || '0', 10) >= 600) || el.tagName === 'B';
      if (el.classList.contains('r')) {
        const sp = [...el.children].map(s => s.textContent.trim());
        align(0); bold(isBold);
        if (sp.length > 2) { // baris tanda tangan
          const names = sp.filter(Boolean), half = Math.floor((cols - 1) / 2), ctr = (t, w) => { t = clean(t).slice(0, w); const p = Math.floor((w - t.length) / 2); return ' '.repeat(p) + t + ' '.repeat(w - t.length - p); };
          put(''); put(''); put(names.map(() => '-'.repeat(half)).join(' ')); put(names.map(n => ctr(n, half)).join(' '));
        } else row(sp[0] || '', sp[1] || '').forEach(put);
        bold(0); continue;
      }
      if (el.classList.contains('c')) { // blok tengah: pecah per <br>, tebalkan baris yang berisi <b>
        const lines = [[]]; 
        const walk = (n, b) => { for (const c of n.childNodes) {
          if (c.nodeType === 3) { const t = c.textContent.replace(/\s+/g, ' '); if (t.trim()) lines[lines.length - 1].push({ t, b }); }
          else if (c.tagName === 'BR') lines.push([]);
          else if (c.tagName !== 'IMG') walk(c, b || c.tagName === 'B' || c.tagName === 'STRONG');
        } };
        walk(el, false);
        align(1);
        for (const ln of lines) { const t = ln.map(x => x.t).join('').trim(); if (!t) continue; bold(ln.some(x => x.b)); wrap(t, cols).forEach(put); }
        bold(0); align(0); continue;
      }
      const t = el.textContent.trim(); // teks biasa
      align(0); bold(isBold); if (t) wrap(t, cols).forEach(put); bold(0);
    }
    bytes.push(ESC, 0x64, 4); // maju 4 baris agar struk bisa disobek
    return new Uint8Array(bytes);
  }

  const b64 = u8 => { let s = ''; for (let i = 0; i < u8.length; i += 0x8000) s += String.fromCharCode.apply(null, u8.subarray(i, i + 0x8000)); return btoa(s); };

  /** Kirim ke aplikasi RawBT (Android). Jika belum terpasang, Chrome membuka Play Store. */
  function rawbt(bytes) {
    const fb = encodeURIComponent('https://play.google.com/store/apps/details?id=ru.a402d.rawbtprinter');
    location.href = 'intent:base64,' + b64(bytes) + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;S.browser_fallback_url=' + fb + ';end;';
  }

  let dev = null, chr = null;
  async function findChar(server) {
    for (const s of await server.getPrimaryServices()) {
      for (const c of await s.getCharacteristics()) if (c.properties.write || c.properties.writeWithoutResponse) return c;
    }
    return null;
  }
  /** Kirim langsung dari Chrome lewat Web Bluetooth (printer BLE). */
  async function bluetooth(bytes, onStatus) {
    if (!navigator.bluetooth) throw new Error('Browser ini tidak mendukung Web Bluetooth. Pakai Chrome di Android (alamat harus https) atau tombol RawBT.');
    onStatus = onStatus || (() => {});
    if (!dev || !chr || !dev.gatt.connected) {
      onStatus('Pilih printer…');
      dev = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: SERVICES });
      dev.addEventListener('gattserverdisconnected', () => { chr = null; });
      onStatus('Menghubungkan ke ' + (dev.name || 'printer') + '…');
      const server = await dev.gatt.connect();
      chr = await findChar(server);
      if (!chr) { try { dev.gatt.disconnect(); } catch (e) {} dev = null; throw new Error('Printer ini tidak bisa dipakai lewat Web Bluetooth (kemungkinan Bluetooth klasik). Gunakan tombol RawBT.'); }
    }
    const noResp = !!chr.properties.writeWithoutResponse;
    let size = 100;
    for (let i = 0; i < bytes.length;) {
      const part = bytes.slice(i, i + size);
      try { noResp ? await chr.writeValueWithoutResponse(part) : await chr.writeValueWithResponse(part); i += part.length; }
      catch (e) { if (size > 20) { size = 20; continue; } throw e; }
      await new Promise(r => setTimeout(r, 25));
    }
    onStatus('Terkirim ke ' + (dev.name || 'printer'));
  }

  window.EscPos = { fromReceipt, rawbt, bluetooth, clean };

  /* Tombol di halaman struk: <div data-printbar> */
  document.addEventListener('DOMContentLoaded', () => {
    const bar = document.querySelector('[data-printbar]'); if (!bar) return;
    const st = bar.querySelector('[data-pstatus]'), sel = bar.querySelector('[data-pcols]');
    const say = (m, err) => { if (st) { st.textContent = m || ''; st.style.color = err ? '#b91c1c' : ''; } };
    try { const v = localStorage.getItem('wmr_cols'); if (v && sel) sel.value = v; } catch (e) {}
    const bytes = () => { try { localStorage.setItem('wmr_cols', sel.value); } catch (e) {} return fromReceipt(document.querySelector('.receipt'), parseInt(sel.value, 10)); };
    bar.addEventListener('click', async e => {
      const b = e.target.closest('[data-p]'); if (!b) return;
      if (b.dataset.p === 'print') return window.print();
      if (b.dataset.p === 'rawbt') { say('Membuka RawBT…'); return rawbt(bytes()); }
      if (b.dataset.p === 'bt') {
        b.disabled = true;
        try { await bluetooth(bytes(), say); } catch (err) { if (err && err.name === 'NotFoundError') say(''); else say(err.message || String(err), true); }
        b.disabled = false;
      }
    });
  });
})();
