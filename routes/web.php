<?php

use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/pos', [PosController::class, 'index'])->name('pos');

Route::post('/pos/transaksi', [PosController::class, 'simpanTransaksi'])->name('pos.simpanTransaksi');
Route::post('/pos/transaksi/{id}/bayar', [PosController::class, 'bayarModal'])->name('pos.bayarModal');
Route::post('/pos/transaksi/{id}/bayar-pas', [PosController::class, 'bayarPas'])->name('pos.bayarPas');
Route::post('/pos/transaksi/{id}/load-for-payment', [PosController::class, 'loadForPayment'])->name('pos.loadForPayment');
Route::post('/pos/transaksi/{id}/mark-paid', [PosController::class, 'markPaidBtn'])->name('pos.markPaidBtn');
Route::post('/pos/transaksi/{id}/toggle-kemarin', [PosController::class, 'toggleKemarin'])->name('pos.toggleKemarin');
Route::post('/pos/transaksi/batal-edit', [PosController::class, 'batalEdit'])->name('pos.batalEdit');
Route::post('/pos/transaksi/{id}/hapus', [PosController::class, 'hapusTransaksi'])->name('pos.hapusTransaksi');

Route::post('/pos/speech/parse', [PosController::class, 'parseSpeech'])->name('pos.speechParse');

Route::post('/pos/expense', [PosController::class, 'simpanExpense'])->name('pos.simpanExpense');
Route::post('/pos/expense/{id}/hapus', [PosController::class, 'hapusExpense'])->name('pos.hapusExpense');

Route::post('/pos/produk', [PosController::class, 'simpanProduk'])->name('pos.simpanProduk');
Route::post('/pos/produk/{id}/harga', [PosController::class, 'ubahHarga'])->name('pos.ubahHarga');
Route::post('/pos/produk/{id}/hapus', [PosController::class, 'hapusProduk'])->name('pos.hapusProduk');
Route::post('/pos/produk/seed', [PosController::class, 'seedProduk'])->name('pos.seedProduk');

Route::post('/pos/oil', [PosController::class, 'simpanOil'])->name('pos.simpanOil');
Route::post('/pos/oil/hapus', [PosController::class, 'hapusOil'])->name('pos.hapusOil');
Route::post('/pos/stock-mgmt/{id}/sacks', [PosController::class, 'updateStockMgmtSacks'])->name('pos.updateStockMgmtSacks');

Route::post('/pos/stock-mgmt', [PosController::class, 'simpanStockMgmt'])->name('pos.simpanStockMgmt');
Route::post('/pos/stock-mgmt/{id}/hapus', [PosController::class, 'hapusStockMgmt'])->name('pos.hapusStockMgmt');
Route::post('/pos/stock-mgmt/{itemId}/batch/{batchId}/hapus', [PosController::class, 'hapusStockBatch'])->name('pos.hapusStockBatch');

Route::post('/pos/remain', [PosController::class, 'simpanRemain'])->name('pos.simpanRemain');
Route::post('/pos/remain/{id}/update', [PosController::class, 'updateRemain'])->name('pos.updateRemain');
Route::post('/pos/remain/{id}/hapus', [PosController::class, 'hapusRemain'])->name('pos.hapusRemain');

Route::post('/pos/hp', [PosController::class, 'simpanHutangPelanggan'])->name('pos.simpanHutangPelanggan');
Route::post('/pos/hp/{id}/hapus', [PosController::class, 'hapusLedgerEntry'])->name('pos.hapusLedgerEntry');
Route::post('/pos/hp/{name}/hapus-semua', [PosController::class, 'hapusCustomerLedger'])->name('pos.hapusCustomerLedger');
Route::post('/pos/hp/adjust-cell', [PosController::class, 'adjustDebtCell'])->name('pos.adjustDebtCell');
Route::post('/pos/hp/adjust-total', [PosController::class, 'adjustTotalDebt'])->name('pos.adjustTotalDebt');
Route::post('/pos/hp/rename', [PosController::class, 'renameCustomer'])->name('pos.renameCustomer');

Route::post('/pos/hpr', [PosController::class, 'simpanHutangPribadi'])->name('pos.simpanHutangPribadi');
Route::post('/pos/hpr/{id}/hapus', [PosController::class, 'hapusPersonalLedger'])->name('pos.hapusPersonalLedger');
Route::post('/pos/hpr/{id}/update', [PosController::class, 'updatePersonalLedger'])->name('pos.updatePersonalLedger');

    Route::post('/pos/saldo', [PosController::class, 'simpanSaldo'])->name('pos.simpanSaldo');
    Route::post('/pos/saldo/hapus', [PosController::class, 'hapusSaldo'])->name('pos.hapusSaldo');

Route::post('/pos/reset-month', [PosController::class, 'resetMonth'])->name('pos.resetMonth');
Route::post('/pos/reset-all', [PosController::class, 'resetAll'])->name('pos.resetAll');
