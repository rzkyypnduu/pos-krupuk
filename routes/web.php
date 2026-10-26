<?php

use App\Http\Controllers\HutangPelangganController;
use App\Http\Controllers\HutangPribadiController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\SaldoController;
use App\Http\Controllers\StokHasilController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\UcapanController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pos'));

Route::get('/pos', [PosController::class, 'index'])->name('pos');

Route::prefix('pos')->name('pos.')->group(function () {
    // Tab Transaksi: simpan/edit transaksi, pembayaran, pengeluaran kas
    Route::post('/transaksi', [TransaksiController::class, 'simpanTransaksi'])->name('simpanTransaksi');
    Route::post('/transaksi/{id}/bayar', [TransaksiController::class, 'bayarLewatModal'])->name('bayarModal');
    Route::post('/transaksi/{id}/load-for-payment', [TransaksiController::class, 'muatUntukPembayaran'])->name('loadForPayment');
    Route::post('/transaksi/{id}/toggle-kemarin', [TransaksiController::class, 'alihkanKemarin'])->name('toggleKemarin');
    Route::post('/transaksi/{id}/hapus', [TransaksiController::class, 'hapusTransaksi'])->name('hapusTransaksi');

    // Speech-to-text
    Route::post('/speech/parse', [UcapanController::class, 'uraiUcapan'])->name('speechParse');

    // Pengeluaran kas
    Route::post('/expense', [TransaksiController::class, 'simpanPengeluaran'])->name('simpanExpense');
    Route::post('/expense/{id}/hapus', [TransaksiController::class, 'hapusPengeluaran'])->name('hapusExpense');

    // Tab Produk
    Route::post('/produk', [ProdukController::class, 'simpanProduk'])->name('simpanProduk');
    Route::post('/produk/{id}/harga', [ProdukController::class, 'ubahHarga'])->name('ubahHarga');
    Route::post('/produk/{id}/hapus', [ProdukController::class, 'hapusProduk'])->name('hapusProduk');
    Route::post('/produk/seed', [ProdukController::class, 'isiProdukAwal'])->name('seedProduk');

    // Tab Hasil — stok minyak & manajemen stok barang
    Route::post('/oil', [StokHasilController::class, 'simpanStokMinyak'])->name('simpanOil');
    Route::post('/oil/hapus', [StokHasilController::class, 'hapusStokMinyak'])->name('hapusOil');
    Route::post('/stock-mgmt', [StokHasilController::class, 'simpanManajemenStok'])->name('simpanStockMgmt');
    Route::post('/stock-mgmt/{id}/sacks', [StokHasilController::class, 'perbaruiKarungManajemenStok'])->name('updateStockMgmtSacks');
    Route::post('/stock-mgmt/{id}/harga', [StokHasilController::class, 'perbaruiHargaManajemenStok'])->name('updateStockMgmtPrice');
    Route::post('/stock-mgmt/{id}/hapus', [StokHasilController::class, 'hapusManajemenStok'])->name('hapusStockMgmt');

    // Tab Hasil — sisa barang
    Route::post('/remain', [StokHasilController::class, 'simpanSisaStok'])->name('simpanRemain');
    Route::post('/remain/{id}/update', [StokHasilController::class, 'perbaruiSisaStok'])->name('updateRemain');
    Route::post('/remain/{id}/hapus', [StokHasilController::class, 'hapusSisaStok'])->name('hapusRemain');

    // Tab Hasil — rekap hutang per pelanggan (grid Excel)
    Route::post('/hp', [HutangPelangganController::class, 'simpanHutangPelanggan'])->name('simpanHutangPelanggan');
    Route::post('/hp/{id}/hapus', [HutangPelangganController::class, 'hapusEntriBukuPelanggan'])->name('hapusLedgerEntry');
    Route::post('/hp/{name}/hapus-semua', [HutangPelangganController::class, 'hapusBukuPelanggan'])->name('hapusCustomerLedger');
    Route::post('/hp/adjust-cell', [HutangPelangganController::class, 'sesuaikanSelHutang'])->name('adjustDebtCell');
    Route::post('/hp/rename', [HutangPelangganController::class, 'gantiNamaPelanggan'])->name('renameCustomer');

    // Tab Hasil — daftar hutang pribadi
    Route::post('/hpr', [HutangPribadiController::class, 'simpanHutangPribadi'])->name('simpanHutangPribadi');
    Route::post('/hpr/{id}/hapus', [HutangPribadiController::class, 'hapusBukuPribadi'])->name('hapusPersonalLedger');
    Route::post('/hpr/{id}/update', [HutangPribadiController::class, 'perbaruiBukuPribadi'])->name('updatePersonalLedger');

    // Tab Hasil — pengurangan saldo
    Route::post('/saldo', [SaldoController::class, 'simpanSaldo'])->name('simpanSaldo');
    Route::post('/saldo/hapus', [SaldoController::class, 'hapusSaldo'])->name('hapusSaldo');

    // Reset data
    Route::post('/reset-month', [PosController::class, 'resetBulan'])->name('resetMonth');
    Route::post('/reset-all', [PosController::class, 'resetSemua'])->name('resetAll');
});
