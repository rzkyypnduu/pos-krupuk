<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\PotonganSaldo;
use Illuminate\Http\Request;

/** Pengurangan saldo tab Hasil (Angka A − Angka B, satu nilai per tanggal). */
class SaldoController extends Controller
{
    use BantuanPos;

    public function simpanSaldo(Request $request)
    {
        // Input Rupiah boleh memakai titik ribuan; nilai bukan angka tetap ditolak.
        foreach (['saldoA', 'saldoB'] as $kunci) {
            $nilai = $request->input($kunci);
            if (is_string($nilai) && preg_match('/^[\d.\s]+$/', trim($nilai))) {
                $request->merge([$kunci => uraiRupiah($nilai)]);
            }
        }

        $request->validate([
            'saldoA' => 'nullable|integer',
            'saldoB' => 'nullable|integer',
        ], [
            'saldoA.integer' => 'Angka A harus bilangan bulat.',
            'saldoB.integer' => 'Angka B harus bilangan bulat.',
        ]);
        // Satu nilai pengurangan saldo per tanggal; simpan menimpa nilai lama.
        $tanggal = $this->tanggalAktif($request);
        PotonganSaldo::where('tanggal', $tanggal)->delete();
        PotonganSaldo::create([
            'tanggal' => $tanggal,
            'nilai_a' => (int) ($request->input('saldoA') ?? 0),
            'nilai_b' => (int) ($request->input('saldoB') ?? 0),
        ]);

        return $this->alihkanKePos($request, ['tab' => 'hasil'], 'Hasil pengurangan saldo disimpan.');
    }

    public function hapusSaldo(Request $request)
    {
        PotonganSaldo::where('tanggal', $this->tanggalAktif($request))->delete();

        return back()->with('success', 'Pengurangan saldo dihapus.');
    }
}
