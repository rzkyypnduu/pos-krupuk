<?php

namespace App\Services;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\BukuPelanggan;
use App\Models\BukuPribadi;
use App\Models\ItemPenjualan;
use App\Models\LembarHasil;
use App\Models\ManajemenStok;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\PotonganSaldo;
use App\Models\Produk;
use App\Models\SisaStok;
use App\Models\StokMinyak;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus data operasional POS. Catatan input (catatan_input) tidak ikut
 * dihapus karena dipakai untuk analisis akurasi dan efisiensi input.
 */
class ServiceResetData
{
    use BantuanPos;

    /** Hapus seluruh data operasional pada satu bulan. */
    public function bulan(string $bulan): void
    {
        [$start, $end] = $this->rentangBulan($bulan);

        DB::transaction(function () use ($start, $end) {
            Penjualan::whereBetween('tanggal', [$start, $end])->delete();
            Pengeluaran::whereBetween('tanggal', [$start, $end])->delete();
            StokMinyak::whereBetween('tanggal', [$start, $end])->delete();
            ManajemenStok::whereBetween('tanggal', [$start, $end])->delete();
            SisaStok::whereBetween('tanggal', [$start, $end])->delete();
            BukuPribadi::whereBetween('tanggal', [$start, $end])->delete();
            PotonganSaldo::whereBetween('tanggal', [$start, $end])->delete();
            LembarHasil::whereBetween('tanggal', [$start, $end])->delete();
        });
    }

    /** Hapus seluruh data operasional, semua bulan. */
    public function semua(): void
    {
        DB::transaction(function () {
            ItemPenjualan::query()->delete();
            Penjualan::query()->delete();
            BukuPelanggan::query()->delete();
            BukuPribadi::query()->delete();
            StokMinyak::query()->delete();
            ManajemenStok::query()->delete();
            SisaStok::query()->delete();
            Produk::query()->delete();
            PotonganSaldo::query()->delete();
            Pengeluaran::query()->delete();
            LembarHasil::query()->delete();
        });
    }
}
