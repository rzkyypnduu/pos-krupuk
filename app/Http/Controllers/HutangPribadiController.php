<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PosHelpers;
use App\Models\PersonalLedger;
use Illuminate\Http\Request;

/** Daftar hutang pribadi (tab Hasil) — CRUD + edit inline mode Excel. */
class HutangPribadiController extends Controller
{
    use PosHelpers;

    public function simpanHutangPribadi(Request $request)
    {
        // "15.000" dinormalisasi ke 15000; nilai bukan angka tetap ditolak validasi.
        $amt = $request->input('hprAmount');
        if (is_string($amt) && preg_match('/^[\d.\s]+$/', trim($amt))) {
            $request->merge(['hprAmount' => parseRupiah($amt)]);
        }
        $request->validate([
            'hprName' => 'required|string|max:255',
            'hprAmount' => 'required|integer|min:1',
        ], ['hprName.required' => 'Isi nama dulu.', 'hprAmount.required' => 'Isi jumlah dulu.']);
        PersonalLedger::create([
            'date' => $this->activeDate($request),
            'name' => $request->input('hprName'),
            'amount' => $request->input('hprAmount'),
        ]);

        return $this->redirectToPos($request, ['tab' => 'hasil'], 'Hutang pribadi dicatat.');
    }

    public function hapusPersonalLedger(Request $request, int $id)
    {
        PersonalLedger::where('id', $id)->delete();

        return back()->with('success', 'Hutang pribadi dihapus.');
    }

    /** Simpan edit inline (mode Excel) untuk satu baris hutang pribadi. */
    public function updatePersonalLedger(Request $request, int $id)
    {
        $row = PersonalLedger::find($id);
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $row->name = $name;
        }
        // parseRupiah: "15.000" -> 15000 (parseDecimal lama salah baca titik jadi desimal)
        $row->amount = max(0, parseRupiah($request->input('amount')));
        $row->save();

        return response()->json(['ok' => true, 'name' => $row->name, 'amount' => $row->amount]);
    }
}
