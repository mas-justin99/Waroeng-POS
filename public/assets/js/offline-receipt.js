/* Isi struk / bon bar dari transaksi offline yang tersimpan di perangkat. */
(function () {
  const q = new URLSearchParams(location.search), id = q.get('u'), bar = q.get('bar') === '1';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const rp = n => Math.round(n).toLocaleString('id-ID');
  const otype = { dine_in: 'Makan di tempat', take_away: 'Bawa pulang', delivery: 'Delivery' };
  const row = (a, b) => `<div class="r"><span>${esc(a)}</span><span>${esc(b)}</span></div>`;
  document.addEventListener('DOMContentLoaded', async () => {
    const rc = document.getElementById('rc');
    const it = id ? await Offline.get(id).catch(() => null) : null;
    if (!it) { rc.innerHTML = '<div class="c">Struk tidak ditemukan di perangkat ini.</div>'; return; }
    const r = it.receipt, loc = r.loc || {}, no = it.done || r.ref, at = String(r.at).slice(8, 10) + '/' + String(r.at).slice(5, 7) + '/' + String(r.at).slice(0, 4) + ' ' + String(r.at).slice(11, 16);
    if (bar) {
      const lines = r.lines.filter(l => l.bar);
      rc.innerHTML = `<div class="c"><b style="font-size:14px">BON BAR</b></div>` + (r.table ? `<div style="font-weight:700;font-size:14px">MEJA: ${esc(r.table)}</div>` : '') +
        row('No', no) + row('Waktu', at) + row('Kasir', r.cashier || '-') + row('Tipe', (otype[r.otype] || r.otype) + (r.customer ? ' - ' + r.customer : '')) + '<hr>' +
        lines.map(l => `<div style="font-weight:700">${l.qty}x ${esc(l.name)}</div>`).join('') + '<hr>';
      document.querySelector('[data-printbar]').addEventListener('click', e => { if (e.target.closest('[data-p="rawbt"],[data-p="bt"],[data-p="print"]')) Offline.patch(id, { bar_printed: true }); }, true);
      return;
    }
    rc.innerHTML = `<div class="c"><b style="font-size:13px">WAROENG MBOK RENEO</b><br>${esc(loc.name)}<br>${loc.address ? esc(loc.address) + '<br>' : ''}${loc.phone ? 'Telp. ' + esc(loc.phone) + '<br>' : ''}</div><hr>` +
      row('No', no) + row('Tanggal', at) + row('Kasir', r.cashier || '-') + row('Tipe', (otype[r.otype] || r.otype) + (r.table ? ' / ' + r.table : '')) + (r.customer ? row('Pelanggan', r.customer) : '') + '<hr>' +
      r.lines.map(l => `<div>${esc(l.name)}</div>` + row(`${l.qty} x ${rp(l.price)}`, rp(l.sub))).join('') + '<hr>' +
      row('Subtotal', rp(r.sub)) + (r.disc > 0 ? row('Diskon', '-' + rp(r.disc)) : '') + (r.svc > 0 ? row('Service', rp(r.svc)) : '') + (r.tax > 0 ? row('Pajak', rp(r.tax)) : '') +
      `<div class="r" style="font-weight:700;font-size:13px"><span>TOTAL</span><span>${rp(r.total)}</span></div><hr>` +
      r.pays.map(p => row(p.name, rp(p.amount))).join('') + (r.change > 0 ? row('Kembalian', rp(r.change)) : '') + '<hr>' +
      (it.done ? '' : '<div class="c">Struk sementara (offline)</div>') + `<div class="c">${esc(loc.footer || 'Terima kasih')}</div>`;
  });
})();
