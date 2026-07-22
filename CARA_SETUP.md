# Cara menjalankan POS Krupuk (Laravel + Livewire)

File ini adalah project Laravel 12 yang sudah ditambahi kode aplikasi POS
(migration, model, komponen Livewire, view). Folder `vendor/` sengaja tidak
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

3. **Pasang Livewire** (framework reaktifnya):
   ```bash
   composer require livewire/livewire
   ```

4. **Siapkan file environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Siapkan database.** Project ini sudah diset pakai SQLite (paling simpel, tidak
   perlu install MySQL):
   ```bash
   touch database/database.sqlite
   ```
   (Di Windows/PowerShell kalau `touch` tidak ada, buat saja file kosong bernama
   `database.sqlite` di folder `database/` lewat File Explorer.)

   Kalau kamu lebih suka MySQL, ubah bagian `DB_*` di file `.env` sesuai
   database kamu, lalu buat database kosongnya dulu di phpMyAdmin/HeidiSQL.

6. **Jalankan migration** (bikin semua tabelnya):
   ```bash
   php artisan migrate
   ```

7. **Jalankan servernya:**
   ```bash
   php artisan serve
   ```

8. **Buka di browser:** http://127.0.0.1:8000/pos

## Pemakaian pertama kali
1. Buka tab **Master Produk**, klik "Isi contoh nama produk dari catatan" (atau
   tambah manual), lalu isi harga per kg masing-masing produk lewat "Ubah harga".
2. Baru mulai catat transaksi di tab **Transaksi Harian**.

## Kalau mau reset semua data
Ada tombol "Hapus semua data" di bagian bawah tab **Ringkasan**.

## Struktur kode yang saya tambahkan di atas skeleton Laravel bawaan
- `app/Models/` — Product, Sale, SaleItem, OilStock, StockManagement,
  StockRemaining, CustomerLedger, PersonalLedger, SaldoDeduction
- `app/Livewire/Pos/PosApp.php` — seluruh logika aplikasi (satu komponen Livewire
  reaktif, mirip strukturnya dengan demo HTML sebelumnya)
- `database/migrations/2026_07_14_*` — 9 migration untuk tabel-tabel di atas
- `resources/views/livewire/pos/pos-app.blade.php` — tampilan seluruh tab
- `resources/views/layouts/app.blade.php` — layout + styling (tema warna krupuk)
- `routes/web.php` — ditambahkan route `/pos`
- `app/helpers.php` — fungsi `rupiah()` untuk format Rp
