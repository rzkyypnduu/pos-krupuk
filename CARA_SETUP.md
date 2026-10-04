# Cara menjalankan POS Krupuk (Laravel + Blade)

File ini adalah project Laravel 12 yang sudah ditambahi kode aplikasi POS
(migration, model, controller, view Blade). Folder `vendor/` sengaja tidak
disertakan — itu akan terbentuk otomatis saat kamu menjalankan `composer install`
di komputermu sendiri (yang punya akses internet ke Packagist).

## Yang perlu sudah terpasang di komputer
- PHP 8.2 ke atas
- Composer
- (opsional) Laragon / XAMPP / Herd — kalau kamu sudah pakai salah satunya, tinggal
  taruh folder ini di dalam folder proyek biasanya (`www/` untuk Laragon, `htdocs/`
  untuk XAMPP)

## Langkah-langkah

1. **Ekstrak** file zip ini, lalu buka terminal di dalam folder `pos-krupuk`.

2. **Pasang semua dependency PHP:**
   ```bash
   composer install
   ```

3. **Siapkan file environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Siapkan database.** Project ini sudah diset pakai SQLite (paling simpel, tidak
   perlu install MySQL):
   ```bash
   touch database/database.sqlite
   ```
   (Di Windows/PowerShell kalau `touch` tidak ada, buat saja file kosong bernama
   `database.sqlite` di folder `database/` lewat File Explorer.)

   Kalau kamu lebih suka MySQL, ubah bagian `DB_*` di file `.env` sesuai
   database kamu, lalu buat database kosongnya dulu di phpMyAdmin/HeidiSQL.

5. **Jalankan migration** (bikin semua tabelnya):
   ```bash
   php artisan migrate
   ```

6. **Jalankan servernya:**
   ```bash
   php artisan serve
   ```

7. **Buka di browser:** http://127.0.0.1:8000/pos

## Pemakaian pertama kali
1. Buka tab **Master Produk**, klik "Isi contoh nama produk dari catatan" (atau
   tambah manual), lalu isi harga per kg masing-masing produk lewat "Ubah harga".
2. Baru mulai catat transaksi di tab **Transaksi Harian**.

## Kalau mau reset semua data
Ada tombol "Hapus semua data" di bagian bawah tab **Ringkasan**.

## Tampilan tab Transaksi Harian (1 kolom)
Halaman "Transaksi Hari Ini" disusun bertumpuk satu kolom:
1. Kartu **Transaksi Hari Ini** — berisi tanggal (tanggal aktif diganti lewat kolom tanggal
   di bilah tab bagian atas),
   tombol **+ Transaksi Baru**, form transaksi (hanya muncul setelah tombol ditekan),
   lalu tabel transaksi hari itu.
2. Kartu **Pengeluaran Laci**.
3. Kartu **Ringkasan Hari Ini** dan **Ringkasan Bulan**.

Form sengaja disembunyikan supaya tabel transaksi tetap lebar. Setelah disimpan,
tanggal aktif tidak ikut reset (state disimpan lewat query string `?tx_date=…&new=1`).

**Satu tanggal aktif untuk semua tab.** Kolom tanggal di bilah tab bagian atas adalah
satu-satunya kontrol tanggal; ganti tanggal di sana dan semua halaman ikut: tab
Transaksi menampilkan transaksi tanggal itu, sedangkan tab Hasil & Ringkasan menampilkan
bulan dari tanggal tersebut (bulan otomatis diturunkan dari tanggal — tidak ada kontrol
bulan terpisah lagi). URL lama `?bulan=…` masih didukung: dibuka sebagai tanggal 1 bulan itu.

**Posisi scroll dipertahankan.** Setelah menyimpan sesuatu (transaksi, pembayaran,
pengeluaran, dsb.) halaman tidak melompat ke atas — posisi tampilan tetap seperti
sebelum disimpan. Posisi scroll juga diingat **per tab**: pindah ke tab Hasil lalu
kembali ke Transaksi Harian, tampilan kembali ke posisi terakhir yang dilihat.
(Catatan: link yang ber-hash, misalnya **+ Transaksi Baru** → `#tx-baru`, tetap
di-scroll oleh browser ke tujuannya — tidak ditimpa pemulihan posisi.)

## Tab Hasil — lembar harian + salin-tempel per tanggal
Tab Hasil adalah **lembar harian** yang mengikuti tanggal aktif. Perilakunya:

- **Tidak ada form tanggal** di dalam tab — semua form (stok minyak, manajemen stok,
  sisa barang, hutang pribadi, pengurangan saldo) langsung menyimpan ke tanggal aktif.
  Kolom **Tanggal** di tabel-tabel juga tidak perlu diisi; pindah tanggal lewat kolom
  tanggal di bilah tab bagian atas.
- **Stok minyak** tanpa tabel, sama seperti pengurangan saldo: isi **jumlah** dan
  **harga satuan**, lalu **hasil perkalian (jumlah × harga)** langsung tampil (ikut
  berubah saat mengetik). Satu nilai per tanggal — simpan ulang menimpa nilai lama,
  plus tombol **Hapus**.
- **Manajemen stok barang** ditampilkan seperti tabel Excel: tiap pemegang punya
  **kolom per sak** (Sak 1, Sak 2, …) berisi angka kg-nya, lalu **Total (kg)**,
  Harga, dan Subtotal. Angka per sak bisa **diedit langsung di selnya** (tersimpan
  otomatis saat pindah sel) dan ditambah lewat tombol **+ Sak**; mengosongkan sel
  berarti menghapus sak itu. Form di atas tetap dipakai untuk menambah pemegang baru.
- **Sisa barang hari ini** juga seperti tabel Excel: nama produk **ditgetik bebas**
  (bukan pilihan dropdown), **harga tidak otomatis mengikuti harga produk** (default
  0, diisi sendiri bila perlu), dan di tabel **semua kolom bisa diedit langsung di
  selnya** — Produk (teks), Jumlah (kg), dan Harga — dengan subtotal ikut terhitung
  otomatis.
- **Daftar hutang pribadi (hari ini)** sama: kolom **Nama** dan **Jumlah** bisa
  diedit langsung di selnya (tersimpan otomatis saat pindah sel), dan total di
  bawah tabel ikut terhitung ulang otomatis.
- **Rekap hutang per pelanggan** ditampilkan seperti tabel/grid Excel: header
  **Nama | Hutang (gabungan) | Total**, semua baris selebar sama. Setiap angka
  bisa **diedit dengan klik** (klik sel → langsung jadi input, Enter simpan,
  Esc batal, tersimpan tanpa reload), entri per sel bisa **dihapus lewat tombol ×**
  di pojok kanan sel (muncul saat kursor diarahkan ke sel) **atau dengan
  mengosongkan angkanya lalu Enter** (langsung terhapus, seperti tabel lain), **klik nama** untuk
  mengganti nama pelanggan (riwayat lama ikut pindah, nama yang sudah dipakai
  ditolak), dan **klik Total** untuk mengubah total (selisih dicatat otomatis
  sebagai penyesuaian; tabel disegarkan ulang). Paling kanan tiap baris ada
  **tombol ikon (+)** untuk membuka modal *Tambah hutang* pelanggan itu dan
  tombol **Hapus** untuk menghapus seluruh riwayatnya di tanggal aktif.
  Tombol **Riwayat** sudah dihapus — detail hanya lewat URL `?hp_detail=Nama`.
  Total grand di bawah tabel ikut terhitung ulang otomatis setiap edit/hapus.
- **Ringkasan Hari Ini (baris, bukan kartu)** di paling atas tab Hasil: stok minyak,
  manajemen stok, sisa barang, hutang pelanggan, hutang pribadi, pengurangan saldo,
  lalu tiga baris hasil:
  - **Total aset hari ini** = stok minyak + total hutang pelanggan + stok barang + sisa barang
  - **Saldo** = total aset − hutang pribadi
  - **Total** = total aset − pengurangan saldo − hutang pribadi
  (Ringkasan harian yang dulu ada di tab Ringkasan sudah pindah ke sini; tab Ringkasan
  sekarang hanya neraca bulanan per tanggal aktif.)
- **Salin-tempel otomatis sekali per tanggal.** Saat sebuah tanggal dibuka pertama kali
  dan masih **kosong**, seluruh isi tab Hasil disalin dari tanggal yang terakhir dilihat
  (atau tanggal terdekat yang punya data). Setelah itu kedua tanggal **independen** —
  mengedit satu tanggal tidak mempengaruhi tanggal lain. Kalau tanggal target sudah
  punya isi, tidak ada salinan yang dilakukan. Penanda proses ada di tabel
  `hasil_sheets` (tanggal, sumber salinan).
- **Pengurangan saldo** menyimpan **satu nilai per tanggal** (Angka A − Angka B);
  simpan ulang menimpa nilai lama, dan hanya **hasil pengurangan** yang ditampilkan —
  tidak ada daftar/baris terpisah. Ada tombol **Hapus** untuk tanggal aktif.
- **Hutang pribadi** tidak lagi punya kolom catatan. **Rekap hutang pelanggan**
  mengikuti tanggal aktif (entri riwayat dengan `sheet_date` NULL dianggap entri lama
  dan tetap tampil di semua lembar).

Fitur speech-to-text di tab Transaksi tidak berubah.

## Fitur speech-to-text (untuk skripsi)
Tekan tombol **mikrofon** di kepala form **Transaksi Baru**, lalu ucapkan satu kalimat utuh,
misalnya: *"Budi dua kilo kuning satu setengah lempeng"*. Hasilnya:
- teks ucapan tampil di panel bawah mikrofon,
- nama pelanggan, jumlah tiap produk, dan catatan otomatis diisi ke form,
- data tetap bisa dikoreksi manual sebelum **Simpan transaksi**.

**Cara pemakaian mikrofon:**
- Mikrofon tetap menyala sampai ditekan lagi (jeda/berpikir sejenak **tidak** mengakhiri
  sesi; kalau layanan berhenti sendiri karena jeda, aplikasi otomatis merekam lagi).
- Hasil tiap sesi **menumpuk** pada teks sebelumnya, jadi boleh bicara bertahap.
  Dua tombol:
  - **Bersihkan** — di dalam kotak transkrip (ujung kanan), membuang teks suara saja;
    **isian di form tidak berubah**.
  - **Hapus** — di baris status, menghapus teks suara **dan** mengosongkan semua
    jumlah produk (nama pelanggan & catatan tidak dihapus).
- Kalau produk sama diucapkan dua kali (*"dua kuning tiga kuning"*), nilai terakhir
  yang menang (jadi `3 kg`, bukan `5 kg`).
- Angka pecahan: *setengah* → `0,5`; *dua setengah* / *2 setengah* → `2,5`;
  *tiga setengah* → `3,5`; *dua kilo setengah* → `2,5 kg`; *dua koma lima* → `2,5`.
- Hasil yang diragukan ditandai **(kurang yakin)** — itu pasangan longgar karena
  transkrip noise; kalau tidak ada produk sama sekali muncul pesan
  *"Produk tidak terdeteksi"* dan form tinggal diisi manual.

**Syarat pemakaian:**
- Browser **Google Chrome** atau **Microsoft Edge** (memakai Web Speech API `id-ID`).
- Halaman diakses lewat `localhost` / `http://127.0.0.1:8000` (harus HTTPS kalau diakses
  dari komputer lain — Chrome memblokir mikrofon di halaman non-HTTPS).
- Izin mikrofon di browser harus diberikan.
- Butuh koneksi internet (jasa transkripsi suara Google). Kalau tidak ada internet /
  browser tidak mendukung, tombol mikrofon nonaktif dan **input manual tetap jalan**.

**Catatan soal kebisingan:** akurasi transkripsi Google di ruang bising sepenuhnya
tergantung layanan Google, tidak bisa diperbaiki dari sisi aplikasi. Yang bisa dibantu:
mic headset/mendekat ke mikrofon, parser punya **pass longgar** (Levenshtein longgar
untuk kata berisik, ditandai *loose*), browser dikirim **N-best** (beberapa kandidat
teks) lalu server memilih yang paling masuk akal, dan sinyal *Produk tidak terdeteksi*
agar operator segera mengoreksi. Uji coba dulu di ruang tenang sebelum uji di ruang bising.

**Alur teknisnya:**
`public/js/pos-speech.js` (Web Speech API, `continuous` + auto-restart, akumulasi N-best)
→ `POST /pos/speech/parse` → `app/Services/SpeechParser.php` (normalisasi teks,
kata angka → digit, pencocokan nama produk dengan Levenshtein ketat + pass longgar,
penggabungan produk yang sama (timpa), ekstraksi jumlah + satuan) →
hasil diisi ke form → disimpan seperti transaksi biasa.

**Data untuk pengukuran skripsi** tersimpan di tabel `input_logs` (lihat
`app/Models/InputLog.php`) setiap kali transaksi disimpan:
- `method` = `speech` / `manual`, `raw_text` (transkrip asli), `normalized_text`,
- `parsed_json` (hasil parser), `final_json` (nilai akhir form / koreksi user),
- `match_json` (perbandingan parser vs final: akurasi nama, akurasi jumlah per
  produk, `overall` benar/salah), `duration_ms` (durasi input sampai simpan).

Contoh query akurasi:
```sql
SELECT method,
       COUNT(*) AS total,
       AVG(JSON_EXTRACT(match_json,'$.item_accuracy')) AS akurasi_item,
       AVG(JSON_EXTRACT(match_json,'$.overall')) AS transaksi_salah_satu,
       AVG(duration_ms)/1000 AS rata_detik
FROM input_logs
GROUP BY method;
```
(Fitur speech bisa dicoba dulu di lingkungan testing lewat `php artisan test`,
lihat `tests/Unit/SpeechParserTest.php` dan `tests/Feature/SpeechParseTest.php`.)


## Pembayaran (modal Bayar + bayar kemarin)
- Tiap baris transaksi punya tombol **Bayar** → muncul **modal pembayaran** (tanpa reload
  halaman): info tagihan/sudah dibayar/bayar kemarin + **daftar hutang pelanggan itu**
  (transaksi terbaru → terlama, tampilan statis tanpa preview pemotongan). Modal ditutup
  lewat tombol **×** (tanpa tombol Tutup).
- Tombol Bayar berwarna **hijau** selama belum pernah ditekan; begitu tombol **Bayar**
  dipencet sekali (termasuk dengan nominal 0/kosong) tombol jadi **abu** — anggapannya
  transaksi selesai & hari ini memang tidak bayar sama sekali. Masih bisa diklik lagi.
- **Ceklis "Bayar kemarin"** di baris tabel (hijau = aktif) membuat form **Bayar kemarin**
  muncul di modal. Ceklis **menyala otomatis** kalau transaksi itu sudah punya pembayaran
  kemarin (`paid_kemarin > 0`) — jadi tetap ON setelah reload/halaman baru. Kalau belum
  pernah bayar kemarin, ceklis mati dan **form default 0**. Saat ceklis ON, field nominal
  ikut **nilai terakhir yang tercatat** (bukan 0, bukan akumulasi).
- **Matikan ceklis = pindah alokasi uang, langsung live & tersimpan.** Saat ceklis OFF
  (di baris tabel maupun di modal), uang `paid_kemarin` otomatis pindah ke **bayar hari
  ini**: badge **"Bayar kemarin Rp…"** hilang, status dihitung ulang (bisa jadi
  **Lunas**/**Lebih**) **tanpa reload** — kolom **Dibayar** & rekap **Diterima** tetap
  (uang tidak hilang, hanya pindah). Di tabel, toggle langsung disimpan ke server
  (`POST /pos/transaksi/{id}/toggle-kemarin`); di modal, field "bayar hari ini" otomatis
  bertambah saat ceklis dimatikan, dan `paid_kemarin` jadi 0 saat disubmit. Menyalakan
  lagi ceklis tidak mengembalikan angka lama — nominal kemarin baru tercatat lewat form
  modal.
- Di modal ada dua form: **Bayar kemarin** (kolom `paid_kemarin`) dan **Bayar hari ini**
  (kolom `paid`). **Keduanya bersifat input terakhir menang (SET)** — koreksi 15rb lalu
  diganti 17rb = 17rb, bukan 32rb; boleh 0.
- Tombol **Bayar lunas** (muncul kalau masih ada tagihan) = bayar seluruh sisa hari ini,
  form kemarin diabaikan. Tombol **Bayar** (kanan bawah) = kirim sesuai form.
- Tombol **Edit** form transaksi punya pilihan sama lewat field Dibayar. Di tabel, kolom
  **Dibayar** = total kedua form (`paid + paid_kemarin`, mis. 40rb + 60rb → **Rp100.000**,
  hover untuk rinciannya); badge **"Bayar kemarin Rp…"** tetap tampil di kolom Status.
- Rekap **Diterima** (Hari Ini & Bulan) = semua uang masuk hari ini/bulan ini,
  termasuk pembayaran kemarin yang dicatat lewat modal.
- **Transaksi & pembayaran TIDAK menulis `customer_ledgers`** — tabel "Rekap hutang per
  pelanggan" di tab Hasil hanya berisi catatan **manual** (+ Tambah hutang, klik angka
  untuk edit, adjust total) plus riwayat lama yang sudah ada di DB.

## Struktur kode yang saya tambahkan di atas skeleton Laravel bawaan
- `app/Models/` — Product, Sale, SaleItem, OilStock, StockManagement,
  StockRemaining, CustomerLedger, PersonalLedger, SaldoDeduction, Expense, InputLog
- `app/Http/Controllers/` — dipisah per fitur supaya mudah dibaca:
  - `PosController.php` — halaman utama `GET /pos` (semua tab), neraca `ringkasan()`, reset bulan/semua
  - `TransaksiController.php` — simpan/edit transaksi, pembayaran (modal & lunas),
    bayar kemarin, pengeluaran kas, pencatatan `input_logs`
  - `SpeechController.php` — endpoint `parseSpeech`
  - `ProdukController.php` — CRUD produk & data contoh
  - `StokHasilController.php` — stok minyak, manajemen stok barang, sisa barang
  - `HutangPelangganController.php` — rekap hutang per pelanggan (grid Excel: edit sel/total, hapus, rename)
  - `HutangPribadiController.php` — daftar hutang pribadi
  - `SaldoController.php` — pengurangan saldo
  - `Concerns/PosHelpers.php` — trait logika bersama (tanggal aktif, parameter URL,
    lembar hasil salin-tempel per tanggal, pembulatan total, parsing qty koma desimal)
- `app/Services/HutangService.php` — perhitungan sisa hutang pelanggan (LIFO) +
  riwayat detail; dipakai PosController & HutangPelangganController
- `app/Services/SpeechParser.php` — parser rule-based untuk speech-to-text
- `database/migrations/2026_07_14_*` — 9 migration untuk tabel-tabel di atas,
  plus `2026_09_30_000001_create_input_logs_table.php` dan
  `2026_10_01_000001_add_paid_kemarin_to_sales_table.php` (kolom `sales.paid_kemarin`)
- `resources/views/pos/` — index.blade.php (halaman lengkap: head + konten) +
  _style.blade.php (seluruh CSS tema krupuk) + 4 tab (_tab_transaksi, _tab_produk,
  _tab_hasil, _tab_ringkasan) + 2 modal (_modal_hutang, _modal_bayar)
- `public/js/pos-speech.js` — mikrofon, Web Speech API, terapkan hasil ke form
- `public/js/pos-pay.js` — store Alpine modal Bayar (default 0, tanpa prefill/preview LIFO)
- `routes/web.php` — route `/pos` (GET + POST), `POST /pos/speech/parse`,
  `POST /pos/transaksi/{id}/bayar` (modal bayar) dan `POST /pos/transaksi/{id}/bayar-pas`
  (bayar lunas)
- `app/helpers.php` — fungsi `rupiah()` untuk format Rp
- `tests/Feature/PosPageTest.php`, `tests/Unit/SpeechParserTest.php`,
  `tests/Feature/SpeechParseTest.php`, `tests/Feature/PaymentModalTest.php`
  — test jalankan dengan `php artisan test`