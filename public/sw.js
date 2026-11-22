// Service worker: (1) cache aset statis (milik sendiri + CDN) agar cepat; (2) mode offline kasir:
// halaman /pos terakhir yang berhasil dimuat disimpan agar kasir tetap bisa dibuka saat internet putus,
// dan cangkang struk offline (/offline/receipt). Data transaksi tidak di-cache di sini (disimpan di IndexedDB oleh offline.js).
const C = 'wmr-static-v3', PG = 'wmr-pages-v1';
const CDN = ['cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];
const GATE = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title>' +
  '<body style="font-family:system-ui,sans-serif;background:#fff5f5;display:grid;place-items:center;min-height:100vh;margin:0"><div style="text-align:center;padding:24px;max-width:340px">' +
  '<div style="font-size:42px">📴</div><h2 style="color:#E32F37">Tidak ada koneksi</h2><p style="color:#555">Halaman ini butuh internet. Kasir tetap bisa dipakai untuk jualan; transaksi akan dikirim otomatis saat internet kembali.</p>' +
  '<a href="/pos" style="display:inline-block;background:#E32F37;color:#fff;padding:12px 22px;border-radius:12px;text-decoration:none;font-weight:700">Buka Kasir</a></div></body>';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(
  caches.keys().then(k => Promise.all(k.filter(x => x !== C && x !== PG).map(x => caches.delete(x)))).then(() => self.clients.claim())));

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const u = new URL(req.url), same = u.origin === location.origin;

  // Cangkang struk offline: jaringan dulu, cadangan dari cache
  if (same && u.pathname === '/offline/receipt') {
    e.respondWith(fetch(req).then(r => { if (r.ok) { const cp = r.clone(); caches.open(C).then(c => c.put(req, cp)); } return r; })
      .catch(() => caches.open(C).then(c => c.match(req, { ignoreSearch: true })).then(h => h || new Response(GATE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))));
    return;
  }

  // Navigasi halaman: simpan /pos terakhir; bila offline sajikan salinannya
  if (req.mode === 'navigate' && same) {
    e.respondWith(fetch(req).then(r => {
      if (u.pathname === '/pos' && !u.search && r.ok && !r.redirected) { const cp = r.clone(); caches.open(PG).then(c => c.put('/pos', cp)); }
      return r;
    }).catch(async () => {
      if (['/', '/pos', '/shift'].includes(u.pathname)) { const h = await caches.open(PG).then(c => c.match('/pos')); if (h) return h; }
      return new Response(GATE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    }));
    return;
  }

  // Aset statis: cache dulu, perbarui di belakang
  const own = same && u.pathname.startsWith('/assets/');
  if (!(own || CDN.includes(u.hostname))) return;
  e.respondWith(caches.open(C).then(c => c.match(req).then(hit => {
    const net = fetch(req).then(r => { if (r.ok || r.type === 'opaque') c.put(req, r.clone()); return r; }).catch(() => hit); // clone langsung (sinkron)
    return hit || net;
  })));
});
