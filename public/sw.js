// Service worker minimal: cache aset statis (milik sendiri + CDN Bootstrap/GSAP/font) agar HP/tablet memuat cepat
// dan tetap tampil rapi saat sinyal warung putus-putus. Halaman & data transaksi TIDAK di-cache.
const C = 'wmr-static-v2';
const CDN = ['cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(
  caches.keys().then(k => Promise.all(k.filter(x => x !== C).map(x => caches.delete(x)))).then(() => self.clients.claim())));
self.addEventListener('fetch', e => {
  const u = new URL(e.request.url);
  const own = u.origin === location.origin && u.pathname.startsWith('/assets/');
  if (e.request.method !== 'GET' || !(own || CDN.includes(u.hostname))) return;
  e.respondWith(caches.open(C).then(c => c.match(e.request).then(hit => {
    const net = fetch(e.request).then(r => { if (r.ok || r.type === 'opaque') c.put(e.request, r.clone()); return r; }).catch(() => hit);
    return hit || net;
  })));
});
