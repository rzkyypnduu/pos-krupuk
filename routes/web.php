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

Route::get('/', fn () => view('welcome'));

Route::get('/pos', [PosController::class, 'index'])->name('pos');

// Tab Transaksi: simpan/edit transaksi, pembayaran, pengeluaran kas
Route::post('/pos/transaksi', [TransaksiController::class, 'simpanTransaksi'])->name('pos.simpanTransaksi');
Route::post('/pos/transaksi/{id}/bayar', [TransaksiController::class, 'bayarModal'])->name('pos.bayarModal');
Route::post('/pos/transaksi/{id}/bayar-pas', [TransaksiController::class, 'bayarPas'])->name('pos.bayarPas');
Route::post('/pos/transaksi/{id}/load-for-payment', [TransaksiController::class, 'loadForPayment'])->name('pos.loadForPayment');
Route::post('/pos/transaksi/{id}/mark-paid', [TransaksiController::class, 'markPaidBtn'])->name('pos.markPaidBtn');
Route::post('/pos/transaksi/{id}/toggle-kemarin', [TransaksiController::class, 'toggleKemarin'])->name('pos.toggleKemarin');
Route::post('/pos/transaksi/batal-edit', [TransaksiController::class, 'batalEdit'])->name('pos.batalEdit');
Route::post('/pos/transaksi/{id}/hapus', [TransaksiController::class, 'hapusTransaksi'])->name('pos.hapusTransaksi');

// Speech-to-text
Route::post('/pos/speech/parse', [SpeechController::class, 'parseSpeech'])->name('pos.speechParse');

// Pengeluaran kas
Route::post('/pos/expense', [TransaksiController::class, 'simpanExpense'])->name('pos.simpanExpense');
Route::post('/pos/expense/{id}/hapus', [TransaksiController::class, 'hapusExpense'])->name('pos.hapusExpense');

// Tab Produk
Route::post('/pos/produk', [ProdukController::class, 'simpanProduk'])->name('pos.simpanProduk');
Route::post('/pos/produk/{id}/harga', [ProdukController::class, 'ubahHarga'])->name('pos.ubahHarga');
Route::post('/pos/produk/{id}/hapus', [ProdukController::class, 'hapusProduk'])->name('pos.hapusProduk');
Route::post('/pos/produk/seed', [ProdukController::class, 'seedProduk'])->name('pos.seedProduk');

// Tab Hasil — stok minyak & manajemen stok barang
Route::post('/pos/oil', [StokHasilController::class, 'simpanOil'])->name('pos.simpanOil');
Route::post('/pos/oil/hapus', [StokHasilController::class, 'hapusOil'])->name('pos.hapusOil');
Route::post('/pos/stock-mgmt/{id}/sacks', [StokHasilController::class, 'updateStockMgmtSacks'])->name('pos.updateStockMgmtSacks');

Route::post('/pos/stock-mgmt', [StokHasilController::class, 'simpanStockMgmt'])->name('pos.simpanStockMgmt');
Route::post('/pos/stock-mgmt/{id}/hapus', [StokHasilController::class, 'hapusStockMgmt'])->name('pos.hapusStockMgmt');
Route::post('/pos/stock-mgmt/{itemId}/batch/{batchId}/hapus', [StokHasilController::class, 'hapusStockBatch'])->name('pos.hapusStockBatch');

// Tab Hasil — sisa barang
Route::post('/pos/remain', [StokHasilController::class, 'simpanRemain'])->name('pos.simpanRemain');
Route::post('/pos/remain/{id}/update', [StokHasilController::class, 'updateRemain'])->name('pos.updateRemain');
Route::post('/pos/remain/{id}/hapus', [StokHasilController::class, 'hapusRemain'])->name('pos.hapusRemain');

// Tab Hasil — rekap hutang per pelanggan (grid Excel)
Route::post('/pos/hp', [HutangPelangganController::class, 'simpanHutangPelanggan'])->name('pos.simpanHutangPelanggan');
Route::post('/pos/hp/{id}/hapus', [HutangPelangganController::class, 'hapusLedgerEntry'])->name('pos.hapusLedgerEntry');
Route::post('/pos/hp/{name}/hapus-semua', [HutangPelangganController::class, 'hapusCustomerLedger'])->name('pos.hapusCustomerLedger');
Route::post('/pos/hp/adjust-cell', [HutangPelangganController::class, 'adjustDebtCell'])->name('pos.adjustDebtCell');
Route::post('/pos/hp/adjust-total', [HutangPelangganController::class, 'adjustTotalDebt'])->name('pos.adjustTotalDebt');
Route::post('/pos/hp/rename', [HutangPelangganController::class, 'renameCustomer'])->name('pos.renameCustomer');

// Tab Hasil — daftar hutang pribadi
Route::post('/pos/hpr', [HutangPribadiController::class, 'simpanHutangPribadi'])->name('pos.simpanHutangPribadi');
Route::post('/pos/hpr/{id}/hapus', [HutangPribadiController::class, 'hapusPersonalLedger'])->name('pos.hapusPersonalLedger');
Route::post('/pos/hpr/{id}/update', [HutangPribadiController::class, 'updatePersonalLedger'])->name('pos.updatePersonalLedger');

// Tab Hasil — pengurangan saldo
Route::post('/pos/saldo', [SaldoController::class, 'simpanSaldo'])->name('pos.simpanSaldo');
Route::post('/pos/saldo/hapus', [SaldoController::class, 'hapusSaldo'])->name('pos.hapusSaldo');

// Reset data
Route::post('/pos/reset-month', [PosController::class, 'resetMonth'])->name('pos.resetMonth');
Route::post('/pos/reset-all', [PosController::class, 'resetAll'])->name('pos.resetAll');
