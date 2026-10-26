# POS Krupuk

Aplikasi point-of-sale harian untuk usaha krupuk: transaksi kasir (input suara/manual),
master produk, lembar hasil harian (stok minyak & barang, sisa barang, rekap hutang
pelanggan (pecahan hutang), hutang pribadi, pengurangan saldo), ringkasan bulanan + grafik penjualan.

Dibangun dengan **Laravel 12** + **Bootstrap 5** (via Sass) + **Vite** — tanpa Alpine,
tanpa CDN, seluruh aset dibundel lokal.

## Kebutuhan

- PHP >= 8.2 (ekstensi `pdo_mysql`)
- Composer
- Node.js >= 18 + npm
- MySQL 8 (atau MariaDB 10.4+)

## Instalasi cepat

```bash
composer setup
```

Menjalankan: `composer install` → salin `.env` → `php artisan key:generate` →
`php artisan migrate` → `npm install` → `npm run build`.

Salin `.env.example` ke `.env` lalu sesuaikan kredensial database sebelum
`composer setup` (atau jalankan langkah demi langkah).

## Perintah sehari-hari

| Perintah | Fungsi |
| --- | --- |
| `composer dev` | Jalankan bersamaan: server, queue, log (pail), Vite |
| `composer test` | Clear config + jalankan seluruh test PHPUnit |
| `npm run build` | Build aset produksi (`public/build`) |
| `npm run dev` | Vite dev server (HMR) |
| `vendor/bin/pint` | Rapikan gaya kode PHP (PSR-12) |

Buka `http://127.0.0.1:8000` (atau vhost Laragon) — `/` mengarah ke tab Transaksi.

## Struktur

```
app/
  Http/Controllers/          endpoint POS per tab/fitur
    Concerns/BantuanPos.php  helper tanggal, parameter URL, pembulatan
  Models/                    model Eloquent (Produk, Penjualan, BukuPelanggan, dll.)
  Services/
    ServiceDataHalamanPos.php  query & data halaman POS
    ServiceHutang.php          perhitungan sisa hutang pelanggan
    ServiceLembarHasil.php     lembar hasil salin-tempel per tanggal
    ServiceResetData.php       reset bulan/semua data
    PenguraiUcapan.php         parser rule-based speech-to-text
  Support/helpers.php        rupiah(), uraiRupiah(), tampilkanKilogram()
bootstrap/                   app & routing (Laravel 11+ style)
config/                      konfigurasi framework
database/migrations/         skema Indonesia: penjualan, item_penjualan, produk, dll.
resources/
  css/app.scss               variabel tema + import Bootstrap + pos.scss
  css/pos.scss               seluruh gaya khas POS (#pos-root scope)
  js/app.js                  entry Vite
  js/pos/format.js           format rupiah, kg, angka
  js/pos/http.js             fetch JSON/form + CSRF
  js/pos/antarmuka.js        confirm, qty, toast, input uang, scroll
  js/pos/pembayaran.js       modal pembayaran
  js/pos/suara.js            speech-to-text
  js/pos/grafik.js           Chart.js (dimuat hanya di tab Ringkasan)
  js/pos/hasil/              modul minyak, manajemen stok, sisa stok,
                             hutang pelanggan, hutang pribadi
  views/pos/                 index + 4 partial tab + 2 modal + partial hasil/
routes/web.php               route dikelompokkan prefix pos.*
tests/Feature|Unit           test fitur & unit
```

## Speech-to-text

Tekan mikrofon di form Transaksi Baru lalu ucapkan, mis. *"Budi dua kilo kuning"*.
Butuh Chrome/Edge + izin mikrofon + internet (transkripsi Google). Parser berjalan
di server (`app/Services/SpeechParser.php`, tanpa API key). Selengkapnya di
[CARA_SETUP.md](CARA_SETUP.md).

## Dokumentasi lengkap

Panduan operasional, penjelasan tiap tab, dan catatan teknis ada di
**[CARA_SETUP.md](CARA_SETUP.md)**.

## Test

```bash
composer test
```

Butuh database testing `pos_krupuk_testing` (diatur di `phpunit.xml`).

## Lisensi

MIT
