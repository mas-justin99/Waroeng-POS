/* Mode offline kasir: antrean transaksi di perangkat (IndexedDB) + sinkron otomatis & idempoten.
   Transaksi membawa client_uuid; server menolak memproses uuid yang sama dua kali. */
(function () {
  const DB = 'wmr-offline', ST = 'queue';
  const open = () => new Promise((res, rej) => {
    const r = indexedDB.open(DB, 1);
    r.onupgradeneeded = () => r.result.createObjectStore(ST, { keyPath: 'uuid' });
    r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error);
  });
  const run = async (mode, fn) => { const db = await open(); return new Promise((res, rej) => { const t = db.transaction(ST, mode), q = fn(t.objectStore(ST)); t.oncomplete = () => res(q && q.result); t.onerror = () => rej(t.error); }); };
  const all = async () => (await run('readonly', s => s.getAll()) || []).sort((a, b) => a.at.localeCompare(b.at));
  const pending = async () => (await all()).filter(x => !x.done);
  const get = uuid => run('readonly', s => s.get(uuid));
  const put = item => run('readwrite', s => s.put(item));
  const del = uuid => run('readwrite', s => s.delete(uuid));
  const patch = async (uuid, o) => { const it = await get(uuid); if (it) await put(Object.assign(it, o)); };
  const pad = n => String(n).padStart(2, '0');
  const stamp = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12));
  const ref = () => { const d = new Date(); return `OFF-${String(d.getFullYear()).slice(2)}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}-${Math.floor(Math.random() * 90 + 10)}`; };
  const meta = n => document.querySelector('meta[name=' + n + ']');

  /* ---------- status (pil kecil di pojok) ---------- */
  let pill = null, state = { n: 0, err: 0, login: false, busy: false, offline: false };
  function paint() {
    if (!document.body) return;
    if (!pill) {
      pill = document.createElement('a'); pill.href = '/pos'; pill.id = 'offPill';
      pill.style.cssText = 'position:fixed;left:12px;bottom:76px;z-index:1080;padding:7px 12px;border-radius:999px;font:600 12px Inter,system-ui,sans-serif;box-shadow:0 6px 18px rgba(0,0,0,.18);text-decoration:none;display:none;align-items:center;gap:6px';
      document.body.appendChild(pill);
    }
    const { n, err, login, busy, offline } = state;
    if (!n && !offline) { pill.style.display = 'none'; return; }
    let txt, bg = '#FFF4DB', fg = '#8A5A00';
    if (login) { txt = `⚠ ${n} transaksi menunggu — login ulang untuk mengirim`; bg = '#FDE2E2'; fg = '#A61B1B'; }
    else if (err) { txt = `⚠ ${err} transaksi gagal dikirim — periksa`; bg = '#FDE2E2'; fg = '#A61B1B'; }
    else if (busy) txt = `⟳ Mengirim ${n} transaksi…`;
    else if (offline) txt = n ? `Offline · ${n} transaksi menunggu` : 'Offline';
    else txt = `${n} transaksi menunggu dikirim`;
    pill.textContent = txt; pill.style.background = bg; pill.style.color = fg; pill.style.display = 'flex';
  }
  function guardClose() { // jangan tutup shift selama ada transaksi offline belum terkirim
    const f = document.querySelector('form[action$="/shift/close"]'); if (!f) return;
    let w = document.getElementById('offGuard');
    if (state.n) {
      if (!w) { w = document.createElement('div'); w.id = 'offGuard'; w.className = 'alert-x err'; w.innerHTML = '<i class="bi bi-cloud-slash"></i><span></span>'; f.prepend(w); }
      w.lastChild.textContent = `Ada ${state.n} transaksi offline yang belum terkirim ke server. Tunggu hingga terkirim (pastikan internet menyala) sebelum menutup shift.`;
    } else if (w) w.remove();
    f.querySelectorAll('button[type=submit],button:not([type])').forEach(b => { if (b.closest('form') === f) b.disabled = state.n > 0; });
  }
  async function refresh() {
    try { const q = await pending(); state.n = q.length; state.err = q.filter(x => x.error).length; } catch (e) { state.n = 0; }
    paint(); guardClose(); document.dispatchEvent(new CustomEvent('offline:count', { detail: state }));
  }

  /* ---------- sinkron ---------- */
  async function post(item) {
    const go = t => fetch('/pos/checkout', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': t, 'Accept': 'application/json' }, body: JSON.stringify(Object.assign({}, item.payload, { bar_printed: !!item.bar_printed })) });
    let r = await go(meta('csrf-token') ? meta('csrf-token').content : '');
    if (r.status === 403) { // token kedaluwarsa: ambil yang baru lalu coba sekali lagi
      const j = await (await fetch('/csrf', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json().catch(() => null);
      if (j && j.token) { if (meta('csrf-token')) meta('csrf-token').content = j.token; r = await go(j.token); }
    }
    return r;
  }
  let syncing = false;
  async function sync(force) {
    if (syncing) return; syncing = true; state.busy = true; state.login = false; paint();
    let sent = 0;
    try {
      for (const it of await pending()) {
        if (it.error && !force) continue;
        let r; try { r = await post(it); } catch (e) { state.offline = true; break; } // jaringan masih putus
        state.offline = false;
        if (r.status === 401) { state.login = true; break; }
        if ([502, 503, 504].includes(r.status)) break;
        const j = await r.json().catch(() => ({ ok: false, message: 'Respon server tidak valid' }));
        if (r.ok && j.ok !== false) { await patch(it.uuid, { done: j.invoice_no, doneAt: Date.now(), error: null }); sent++; document.dispatchEvent(new CustomEvent('offline:sent', { detail: { item: it, result: j } })); }
        else if (r.status === 403 || r.status >= 500) break;
        else await patch(it.uuid, { error: j.message || 'Ditolak server' });
      }
    } finally { syncing = false; state.busy = false; await refresh(); }
    if (sent && window.Toast) Toast.show(`${sent} transaksi offline berhasil dikirim ke server.`);
    return sent;
  }

  /* ---------- hangatkan cache cangkang struk offline ---------- */
  async function warm() {
    try {
      if (!navigator.onLine) return;
      const r = await fetch('/offline/receipt', { credentials: 'same-origin' }); if (!r.ok) return;
      const html = await r.text();
      for (const m of html.matchAll(/(?:href|src)="([^"]*\/assets\/[^"]+)"/g)) fetch(m[1]).catch(() => {});
    } catch (e) {}
  }

  window.Offline = { add: async item => { await put(item); await refresh(); }, all, pending, get, patch, del, sync, refresh, uuid, ref, stamp, warm, state: () => state };

  window.addEventListener('offline', () => { state.offline = true; paint(); });
  window.addEventListener('online', () => { state.offline = false; paint(); sync(); });
  document.addEventListener('DOMContentLoaded', () => {
    all().then(l => l.filter(x => x.done && Date.now() - (x.doneAt || 0) > 3 * 864e5).forEach(x => del(x.uuid))).catch(() => {}); // buang salinan struk lama
    state.offline = !navigator.onLine; refresh().then(() => { if (state.n) sync(); });
    if (document.querySelector('script[src*="pos.js"]')) warm();
    setInterval(() => { if (state.n && !state.login) sync(); }, 20000);
    // keluar akun: hapus salinan halaman kasir di perangkat
    document.addEventListener('submit', e => { if (e.target.matches('form[action$="/logout"]') && window.caches) caches.delete('wmr-pages-v1'); }, true);
  });
})();
