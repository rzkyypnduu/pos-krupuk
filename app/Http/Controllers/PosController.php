<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Services\ServiceDataHalamanPos;
use App\Services\ServiceResetData;
use Illuminate\Http\Request;

/**
 * Halaman utama POS (satu rute GET /pos untuk semua tab) + reset data.
 * Endpoint per fitur ada di controller masing-masing: Transaksi, Produk,
 * StokHasil, HutangPelanggan, HutangPribadi, Saldo, dan Ucapan.
 */
class PosController extends Controller
{
    use BantuanPos;

    public function __construct(
        private ServiceDataHalamanPos $halaman,
        private ServiceResetData $reset,
    ) {}

    public function index(Request $request)
    {
        return view('pos.index', $this->halaman->untukHalaman($request));
    }

    public function resetBulan(Request $request)
    {
        // input() membaca body POST (hidden input "bulan") sekaligus query string lama.
        $bulan = (string) $request->input('bulan', '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return back()->withErrors(['bulan' => 'Pilih bulan yang valid dulu.']);
        }

        $this->reset->bulan($bulan);

        return $this->alihkanKePos($request, ['tab' => 'ringkasan'], 'Data bulan ini dihapus.');
    }

    public function resetSemua(Request $request)
    {
        // Konfirmasi dilakukan lewat dialog data-confirm di halaman Ringkasan.
        $this->reset->semua();

        return redirect()->route('pos', ['tab' => 'transaksi'])->with('success', 'Semua data dihapus.');
    }
}
