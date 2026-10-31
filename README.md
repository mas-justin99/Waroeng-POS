# Waroeng Mbok Reneo — POS

Aplikasi kasir (POS) + backoffice untuk restoran **Waroeng Mbok Reneo**, dibuat dengan **CodeIgniter 4.7** (PHP 8.2+).
Tampilan terinspirasi LunaPOS / Jubelio POS: bersih, cepat dipelajari, dan dioptimalkan untuk **HP & tablet Android**.

| Lapisan | Pilihan |
|---|---|
| Framework | CodeIgniter 4.7 (PHP ≥ 8.2) |
| Database | **Neon** (PostgreSQL) untuk produksi · MySQL Laragon atau Postgres untuk lokal |
| Foto produk | **Cloudinary** |
| Hosting | **Vercel** (runtime `vercel-php`) |
| Dev lokal | **Laragon** |

---

## Fitur

**Alur stok warung (inti sistem)**
0. **Kasir membuka shift** (modal awal + stok awal) sebelum berjualan.
1. **Dapur** membuat **Pesanan Pembelian (PO)** setiap selesai memasak: pilih SKU → isi **HPP** dan **Qty**.
2. **Kasir** membuka **Penerimaan Pembelian**, mencocokkan barang, lalu menerima (boleh sebagian). Qty yang diterima otomatis menjadi **stok warung**.
3. Kasir menjual lewat **POS**; stok berkurang otomatis, HPP rata-rata (moving average) tercatat di tiap transaksi untuk laba kotor.

**Lainnya**
- **Buka / Tutup Kasir (2 shift per hari):** sebelum berjualan kasir wajib memilih **Shift 1 (Pagi)** atau **Shift 2 (Sore)**, mengisi **uang modal**, dan **menghitung stok awal** semua menu (angka sudah terisi sesuai sistem; selisih otomatis dicatat sebagai penyesuaian stok). Halaman Kasir terkunci sampai kasir dibuka. Saat tutup, kasir mengisi uang kas hasil hitung; sistem menampilkan selisih terhadap *modal + penjualan tunai*, mencatat stok akhir, dan menyimpan rekap di **Riwayat Shift** (bisa dicetak). Satu lokasi hanya boleh punya satu shift terbuka, dan tiap shift hanya bisa dibuka sekali per hari.
- **Struk Penutupan Shift:** begitu kasir menekan *Tutup Kasir*, muncul struk 80 mm berisi omset (subtotal, diskon, pajak, service, total), jumlah transaksi & porsi terjual, void, rincian per metode bayar, uang kasir (modal, penjualan tunai, seharusnya di laci, hasil hitung, **selisih**), daftar menu terjual, sisa stok akhir, serta kolom tanda tangan. Bisa dicetak ulang dari *Riwayat Shift* (pratinjau juga tersedia saat shift masih berjalan).
- **Reservasi + pengingat:** menu *Reservasi* (kasir, dapur, admin) untuk mencatat nama, no. HP, tanggal & jam, jumlah orang, meja, DP, dan catatan. Tab *Hari Ini / Mendatang / Lewat*, status (Dipesan → Terkonfirmasi → Datang / Tidak datang / Batal). Pengingat bisa diatur (tepat waktu, 15/30 menit, 1/2/3 jam, atau 1 hari sebelumnya): kartu pengingat muncul di layar + bunyi/getar + lencana di menu, sampai kasir menekan *Sudah diingatkan*. Tersedia tombol **WhatsApp** untuk mengirim pesan pengingat ke pelanggan. Hanya kasir/admin yang bisa input; dapur hanya melihat.
- **DP reservasi:** nominal DP dicatat di form reservasi bersama **foto bukti/struk DP** (opsional). Di kasir ada metode bayar **DP Reservasi**: pilih reservasi pelanggan, nominal DP otomatis memotong tagihan (bisa digabung dengan tunai/QRIS/dll). Satu DP hanya bisa dipakai sekali; reservasi otomatis berstatus *Sudah datang*; bila transaksi di-void, DP kembali bisa dipakai. DP tidak dihitung sebagai uang tunai di laci saat tutup kasir, dan muncul sebagai baris "DP Reservasi" di struk penutupan. Jika DP lebih besar dari tagihan, hanya sebesar tagihan yang terpotong (sisanya perlu dikembalikan ke pelanggan secara manual).
- **Kompres foto otomatis:** setiap unggah foto (foto produk, bukti DP) dikecilkan dulu di HP/browser (maks. 1600 px, JPEG ±80%) sehingga hemat kuota dan lolos batas unggah Vercel (4,5 MB), lalu dikompres lagi di server (GD) dan di Cloudinary. Foto 3 MB dari kamera HP biasanya menjadi sekitar 300-500 KB.
- Multi lokasi: stok, PO, penjualan, pajak/service, dan footer struk terpisah per outlet. Admin dapat berpindah lokasi dari bar atas.
- Peran: **Admin/Owner** (backoffice, semua lokasi), **Kasir**, **Dapur** (terkunci di lokasinya).
- **1 akun = 1 perangkat.** Login di perangkat kedua ditolak selama perangkat pertama aktif; perangkat yang sama boleh login ulang. Admin bisa *Paksa Logout*.
- Pembayaran lengkap & **split payment**: Tunai, QRIS, Kartu Debit, Kartu Kredit, Transfer Bank, GoPay, OVO, DANA, ShopeePay, LinkAja, GoFood, GrabFood, ShopeeFood, Voucher, Lainnya (bisa diaktif/nonaktifkan di backoffice).
- Diskon (Rp / %), pajak & service per lokasi, tipe pesanan (makan di tempat / bawa pulang / antar), nomor meja, **struk 80 mm** siap cetak.
- Void transaksi (admin) mengembalikan stok. Stok opname + batas stok minimum.
- Dashboard admin: omzet, laba kotor, tren harian, jam ramai, metode bayar, menu terlaris, perbandingan lokasi, stok menipis, PO tertunda, siapa yang sedang online. Laporan penjualan / produk / PO + **ekspor CSV**.
- Animasi halus (GSAP), PWA (bisa dipasang ke layar utama Android), cache aset untuk sinyal warung yang naik-turun.

## Akun demo (hasil seeder)

| Peran | Username | Password | Lokasi |
|---|---|---|---|
| Admin / Owner | `admin` | `admin123` | semua |
| Kasir | `kasir` | `kasir123` | Pusat |
| Dapur | `dapur` | `dapur123` | Pusat |
| Kasir / Dapur | `kasir2` / `dapur2` | `kasir123` / `dapur123` | Cabang 2 |

> **Ganti semua password lewat menu Pengguna sebelum dipakai sungguhan.**

---

## 1. Setup lokal dengan Laragon

1. Letakkan folder proyek di `C:\laragon\www\waroeng-pos`.
2. Pastikan PHP Laragon ≥ 8.2 dengan ekstensi `intl`, `mbstring`, `curl`, `gd` (kompres foto), `exif` (dan `pgsql` bila memakai Postgres/Neon). Cek lewat *Menu → PHP → Extensions*.
3. Jalankan Laragon → **Start All**, lalu buat database kosong `waroeng_pos` (HeidiSQL / *Menu → Database*).
4. Buka **Terminal Laragon** di folder proyek:
   ```bash
   composer install
   copy .env.example .env          # lalu cek DATABASE_URL di dalamnya
   php spark migrate                 # termasuk tabel shift, reservasi & DP
   php spark db:seed DatabaseSeeder  # data awal: lokasi, akun, metode bayar, contoh menu
   php spark db:seed DemoSeeder      # (opsional) stok & transaksi 14 hari agar dashboard terisi
   ```
5. Jalankan: `php spark serve` → buka <http://localhost:8080>.
   Alternatif: *Laragon → Menu → Apache → Auto virtual hosts* lalu buka `http://waroeng-pos.test` (document root harus folder `public`).

> Aplikasi memakai alamat absolut (`/pos`, `/login`, …), jadi jalankan di akar domain (`localhost:8080` atau `waroeng-pos.test`), bukan di sub-folder seperti `localhost/waroeng-pos/public`.

**Memakai Neon saat lokal** (opsional): isi `DATABASE_URL` dengan connection string Neon, lalu jalankan perintah `migrate` dan `db:seed` yang sama.

## 2. Neon (database produksi)

1. Buat project di <https://neon.tech>, pilih region **Singapore (ap-southeast-1)** agar dekat dengan Vercel `sin1`.
2. Salin **connection string** (boleh yang *pooled*), bentuknya:
   `postgresql://USER:PASSWORD@ep-xxxx-pooler.ap-southeast-1.aws.neon.tech/neondb?sslmode=require`
3. Buat tabel dari komputer Anda (Vercel tidak menjalankan `spark`). Di Terminal Laragon:
   ```bash
   set DATABASE_URL=postgresql://USER:PASSWORD@ep-xxxx-pooler.../neondb?sslmode=require
   php spark migrate
   php spark db:seed DatabaseSeeder
   ```
   (di PowerShell: `$env:DATABASE_URL="..."`). Sesi login juga disimpan di database (tabel `ci_sessions`) karena Vercel tidak punya disk permanen.

## 3. Cloudinary (foto produk)

1. Daftar di <https://cloudinary.com>, buka *Dashboard → API Keys* dan salin **API environment variable**:
   `cloudinary://API_KEY:API_SECRET@CLOUD_NAME`
2. Simpan sebagai `CLOUDINARY_URL` (di `.env` lokal dan di Vercel). Tanpa nilai ini, foto disimpan di `public/uploads` — **hanya untuk lokal**, tidak jalan di Vercel.

## 4. Deploy ke Vercel

1. Push proyek ke GitHub (`.env` tidak ikut karena ada di `.gitignore`).
2. Di <https://vercel.com/new> impor repository. *Framework Preset*: **Other**, kosongkan Build/Output command.
3. Isi **Environment Variables** (Settings → Environment Variables):

   | Nama | Nilai |
   |---|---|
   | `CI_ENVIRONMENT` | `production` |
   | `DATABASE_URL` | connection string Neon |
   | `CLOUDINARY_URL` | `cloudinary://…` |
   | `SESSION_IDLE_MINUTES` | `5` (opsional) |
   | `APP_BASE_URL` | kosong (otomatis mengikuti domain) |

4. Deploy. `vercel.json` sudah mengatur runtime PHP (`vercel-php`), region Singapore, dan rute aset. Vercel menjalankan `composer install` sendiri.
5. Buka domain Vercel Anda, login sebagai `admin`, lalu **ganti password** semua akun.

Catatan: versi `vercel-php` di `vercel.json` (`0.7.4`) bisa diganti dengan rilis terbaru dari <https://github.com/vercel-community/php>. Bila pesan error menyebut ekstensi PHP kurang, pastikan `intl`, `mbstring`, `pgsql` tersedia di runtime tersebut.

## 5. Logo & ikon

Logo yang tampil sekarang adalah placeholder "WMR". Untuk memasang logo asli, **timpa file berikut** (PNG, bentuk persegi):

| File | Ukuran | Dipakai di |
|---|---|---|
| `public/assets/img/logo.png` | 512×512 | login, sidebar, struk |
| `public/assets/img/icon-192.png` | 192×192 | ikon aplikasi Android / favicon |
| `public/assets/img/icon-512.png` | 512×512 | ikon aplikasi Android (splash) |

Begitu `logo.png` ada, otomatis dipakai menggantikan `logo.svg`. Warna merek ada di variabel CSS `--primary` pada `public/assets/css/app.css`.

## 6. Pasang di HP / tablet Android

Buka alamat aplikasi di Chrome → menu ⋮ → **Tambahkan ke layar utama**. Aplikasi terbuka layar penuh seperti aplikasi biasa. Untuk cetak struk: tombol *Cetak Struk* memakai dialog cetak browser (bisa diarahkan ke printer thermal lewat aplikasi printer di Android).

---

## Cara kerja 1 akun = 1 perangkat

- Saat login, server membuat token acak yang disimpan di tabel `users` dan di sesi perangkat.
- Setiap permintaan mencocokkan token; jika tidak sama (mis. admin paksa logout) perangkat itu otomatis keluar.
- Login dari perangkat lain **ditolak** selama akun masih aktif dalam `SESSION_IDLE_MINUTES` menit terakhir. Aplikasi mengirim sinyal "masih aktif" tiap 45 detik selama dibuka.
- Perangkat yang sama (dikenali lewat cookie perangkat) boleh login ulang kapan saja, misalnya setelah cache browser dibersihkan.

## Struktur penting

```
api/index.php            entry point Vercel
vercel.json              konfigurasi runtime & rute Vercel
app/Config/              Routes, Filters, Database (DATABASE_URL), Session (DB)
app/Controllers/         Pos, PurchaseOrders, Receiving, Stock, Sales, Admin/*
app/Libraries/           AuthService (1 perangkat), SalesService (checkout/void), Inventory (stok), Cloud (Cloudinary)
app/Database/            Migrations (skema) & Seeds (data awal, demo)
app/Views/               layouts, pos, po, receiving, stock, sales, admin
public/assets/           app.css, app.js, pos.js, logo & ikon
```

## Catatan teknis

- Tampilan memuat Bootstrap 5, Bootstrap Icons, GSAP, Chart.js, SweetAlert2, dan font Inter dari CDN. Perangkat perlu internet; setelah pertama kali dimuat, service worker menyimpannya sehingga halaman tetap rapi saat sinyal putus-putus. Transaksi tetap butuh koneksi ke server.
- **Batas pengingat reservasi:** pengingat dicek tiap 60 detik oleh aplikasi yang sedang terbuka (HP/tablet kasir). Tidak ada notifikasi latar belakang saat aplikasi ditutup, dan pesan WhatsApp ke pelanggan dikirim manual lewat tombolnya (bukan otomatis).
- Jika grafik/animasi gagal dimuat, fungsi kasir tetap berjalan (semua animasi bersifat tambahan).
- Pengujian yang sudah dijalankan (113 pengecekan): alur login 1 perangkat, buka/tutup shift & struk penutupan, reservasi (validasi, status, pengingat, pemisahan lokasi), PO dapur → penerimaan kasir (sebagian & penuh) → penjualan split payment → void, seluruh halaman admin, ekspor CSV, upload foto, dan boot runtime Vercel — pada PostgreSQL. Jalur MySQL (Laragon) memakai SQL yang sama, tetapi belum dijalankan terhadap server MySQL sungguhan; bila menemukan masalah saat `php spark migrate`, gunakan Postgres lokal atau Neon.
