<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PosHelpers;
use App\Models\SaldoDeduction;
use Illuminate\Http\Request;

/** Pengurangan saldo tab Hasil (Angka A − Angka B, satu nilai per tanggal). */
class SaldoController extends Controller
{
    use PosHelpers;

    public function simpanSaldo(Request $request)
    {
        // Satu nilai pengurangan saldo per tanggal — simpan menimpa nilai lama.
        $date = $this->activeDate($request);
        SaldoDeduction::where('date', $date)->delete();
        SaldoDeduction::create([
            'date' => $date,
            'a' => (int) ($request->input('saldoA') ?? 0),
            'b' => (int) ($request->input('saldoB') ?? 0),
        ]);
        return redirect()->route('pos', $this->makeQueryParams($request, ['tab' => 'hasil']))->with('success', 'Hasil pengurangan saldo disimpan.');
    }

    public function hapusSaldo(Request $request)
    {
        SaldoDeduction::where('date', $this->activeDate($request))->delete();
        return back()->with('success', 'Pengurangan saldo dihapus.');
    }
}
