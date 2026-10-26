<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\BukuPribadi;
use Illuminate\Http\Request;

/** Daftar hutang pribadi (tab Hasil) — CRUD + edit inline mode Excel. */
class HutangPribadiController extends Controller
{
    use BantuanPos;

    public function simpanHutangPribadi(Request $request)
    {
        // "15.000" dinormalisasi ke 15000; nilai bukan angka tetap ditolak validasi.
        $jumlah = $request->input('hprAmount');
        if (is_string($jumlah) && preg_match('/^[\d.\s]+$/', trim($jumlah))) {
            $request->merge(['hprAmount' => uraiRupiah($jumlah)]);
        }
        $request->validate([
            'hprName' => 'required|string|max:255',
            'hprAmount' => 'required|integer|min:1',
        ], ['hprName.required' => 'Isi nama dulu.', 'hprAmount.required' => 'Isi jumlah dulu.']);
        BukuPribadi::create([
            'tanggal' => $this->tanggalAktif($request),
            'nama' => $request->input('hprName'),
            'jumlah' => $request->input('hprAmount'),
        ]);

        return $this->alihkanKePos($request, ['tab' => 'hasil'], 'Hutang pribadi dicatat.');
    }

    public function hapusBukuPribadi(Request $request, int $id)
    {
        BukuPribadi::where('id', $id)->delete();

        return back()->with('success', 'Hutang pribadi dihapus.');
    }

    /** Simpan edit inline (mode Excel) untuk satu baris hutang pribadi. */
    public function perbaruiBukuPribadi(Request $request, int $id)
    {
        $baris = BukuPribadi::find($id);
        if (! $baris) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        $nama = trim((string) $request->input('name', ''));
        if ($nama !== '') {
            $baris->nama = $nama;
        }
        // uraiRupiah: "15.000" -> 15000 (uraiDesimal lama salah baca titik jadi desimal)
        $baris->jumlah = max(0, uraiRupiah($request->input('amount')));
        $baris->save();

        return response()->json(['ok' => true, 'name' => $baris->nama, 'amount' => $baris->jumlah]);
    }
}
