<?php

use App\Http\Controllers\HutangPelangganController;
use App\Http\Controllers\HutangPribadiController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\SaldoController;
use App\Http\Controllers\SpeechController;
use App\Http\Controllers\StokHasilController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pos'));

Route::get('/pos', [PosController::class, 'index'])->name('pos');

Route::prefix('pos')->name('pos.')->group(function () {
    // Tab Transaksi: simpan/edit transaksi, pembayaran, pengeluaran kas
    Route::post('/transaksi', [TransaksiController::class, 'simpanTransaksi'])->name('simpanTransaksi');
    Route::post('/transaksi/{id}/bayar', [TransaksiController::class, 'bayarModal'])->name('bayarModal');
    Route::post('/transaksi/{id}/load-for-payment', [TransaksiController::class, 'loadForPayment'])->name('loadForPayment');
    Route::post('/transaksi/{id}/toggle-kemarin', [TransaksiController::class, 'toggleKemarin'])->name('toggleKemarin');
    Route::post('/transaksi/{id}/hapus', [TransaksiController::class, 'hapusTransaksi'])->name('hapusTransaksi');

    // Speech-to-text
    Route::post('/speech/parse', [SpeechController::class, 'parseSpeech'])->name('speechParse');

    // Pengeluaran kas
    Route::post('/expense', [TransaksiController::class, 'simpanExpense'])->name('simpanExpense');
    Route::post('/expense/{id}/hapus', [TransaksiController::class, 'hapusExpense'])->name('hapusExpense');

    // Tab Produk
    Route::post('/produk', [ProdukController::class, 'simpanProduk'])->name('simpanProduk');
    Route::post('/produk/{id}/harga', [ProdukController::class, 'ubahHarga'])->name('ubahHarga');
    Route::post('/produk/{id}/hapus', [ProdukController::class, 'hapusProduk'])->name('hapusProduk');
    Route::post('/produk/seed', [ProdukController::class, 'seedProduk'])->name('seedProduk');

    // Tab Hasil — stok minyak & manajemen stok barang
    Route::post('/oil', [StokHasilController::class, 'simpanOil'])->name('simpanOil');
    Route::post('/oil/hapus', [StokHasilController::class, 'hapusOil'])->name('hapusOil');
    Route::post('/stock-mgmt', [StokHasilController::class, 'simpanStockMgmt'])->name('simpanStockMgmt');
    Route::post('/stock-mgmt/{id}/sacks', [StokHasilController::class, 'updateStockMgmtSacks'])->name('updateStockMgmtSacks');
    Route::post('/stock-mgmt/{id}/hapus', [StokHasilController::class, 'hapusStockMgmt'])->name('hapusStockMgmt');

    // Tab Hasil — sisa barang
    Route::post('/remain', [StokHasilController::class, 'simpanRemain'])->name('simpanRemain');
    Route::post('/remain/{id}/update', [StokHasilController::class, 'updateRemain'])->name('updateRemain');
    Route::post('/remain/{id}/hapus', [StokHasilController::class, 'hapusRemain'])->name('hapusRemain');

    // Tab Hasil — rekap hutang per pelanggan (grid Excel)
    Route::post('/hp', [HutangPelangganController::class, 'simpanHutangPelanggan'])->name('simpanHutangPelanggan');
    Route::post('/hp/{id}/hapus', [HutangPelangganController::class, 'hapusLedgerEntry'])->name('hapusLedgerEntry');
    Route::post('/hp/{name}/hapus-semua', [HutangPelangganController::class, 'hapusCustomerLedger'])->name('hapusCustomerLedger');
    Route::post('/hp/adjust-total', [HutangPelangganController::class, 'adjustTotalDebt'])->name('adjustTotalDebt');
    Route::post('/hp/rename', [HutangPelangganController::class, 'renameCustomer'])->name('renameCustomer');

    // Tab Hasil — daftar hutang pribadi
    Route::post('/hpr', [HutangPribadiController::class, 'simpanHutangPribadi'])->name('simpanHutangPribadi');
    Route::post('/hpr/{id}/hapus', [HutangPribadiController::class, 'hapusPersonalLedger'])->name('hapusPersonalLedger');
    Route::post('/hpr/{id}/update', [HutangPribadiController::class, 'updatePersonalLedger'])->name('updatePersonalLedger');

    // Tab Hasil — pengurangan saldo
    Route::post('/saldo', [SaldoController::class, 'simpanSaldo'])->name('simpanSaldo');
    Route::post('/saldo/hapus', [SaldoController::class, 'hapusSaldo'])->name('hapusSaldo');

    // Reset data
    Route::post('/reset-month', [PosController::class, 'resetMonth'])->name('resetMonth');
    Route::post('/reset-all', [PosController::class, 'resetAll'])->name('resetAll');
});
