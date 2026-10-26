<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\BukuPelanggan;
use App\Services\ServiceHutang;
use Illuminate\Http\Request;

/**
 * Rekap hutang per pelanggan di tab Hasil.
 *
 * Tabel menampilkan hutang aktif per pelanggan yang diurutkan berdasarkan nama.
 * Pengguna dapat menambah entri, mengedit nominal hutang, dan menghapus entri
 * atau baris. Total ditampilkan dari perhitungan sisa di ServiceHutang.
 */
class HutangPelangganController extends Controller
{
    use BantuanPos;

    public function __construct(private ServiceHutang $hutang) {}

    public function simpanHutangPelanggan(Request $request)
    {
        // "15.000" dinormalisasi ke 15000; nilai bukan angka tetap ditolak validasi.
        $jumlah = $request->input('debtModalAmount');
        if (is_string($jumlah) && preg_match('/^[\d.\s]+$/', trim($jumlah))) {
            $request->merge(['debtModalAmount' => uraiRupiah($jumlah)]);
        }
        $request->validate([
            'debtModalName' => 'required|string|max:255',
            'debtModalAmount' => 'required|integer|min:1',
        ], [
            'debtModalName.required' => 'Isi nama pelanggan.',
            'debtModalAmount.required' => 'Isi jumlah hutang.',
        ]);
        BukuPelanggan::create([
            'tanggal' => $request->input('debtModalDate') ?: now()->toDateString(),
            'tanggal_lembar' => $this->tanggalAktif($request),
            'nama' => $request->input('debtModalName'),
            'jumlah' => $request->input('debtModalAmount'),
            'jenis' => 'tambah',
            'catatan' => $request->input('debtModalNote') ?: 'Tambah hutang manual',
        ]);

        return $this->alihkanKePos($request, ['tab' => 'hasil'], 'Hutang ditambahkan.');
    }

    public function hapusEntriBukuPelanggan(Request $request, int $id)
    {
        $entri = BukuPelanggan::find($id);
        if (! $entri) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Entri tidak ditemukan.'], 404)
                : back();
        }
        $nama = $entri->nama;
        $entri->delete();
        if ($request->expectsJson()) {
            $prosesHutang = $this->hutang->prosesHutangPelanggan($nama, $this->tanggalAktif($request));

            return $this->jsonHutang($prosesHutang);
        }

        return back()->with('success', 'Entri dihapus.');
    }

    public function gantiNamaPelanggan(Request $request)
    {
        $namaLama = trim((string) $request->input('old_name'));
        $namaBaru = trim((string) $request->input('new_name'));
        if ($namaLama === '' || $namaBaru === '') {
            return response()->json(['ok' => false, 'message' => 'Nama tidak boleh kosong.'], 422);
        }
        if ($namaLama !== $namaBaru) {
            if (BukuPelanggan::where('nama', $namaBaru)->exists()) {
                return response()->json(['ok' => false, 'message' => 'Nama "'.$namaBaru.'" sudah dipakai pelanggan lain.'], 422);
            }
            BukuPelanggan::where('nama', $namaLama)->update(['nama' => $namaBaru]);
        }

        return response()->json(['ok' => true, 'name' => $namaBaru]);
    }

    public function hapusBukuPelanggan(Request $request, string $nama)
    {
        $this->bukuPelangganLingkup($this->tanggalAktif($request))
            ->where('nama', $nama)->delete();

        return back()->with('success', 'Riwayat hutang dihapus.');
    }

    public function sesuaikanSelHutang(Request $request)
    {
        $idHutang = $request->input('debt_id');
        // Input kosong dianggap 0; nilai berformat "15.000" juga diterima.
        $sisaBaru = max(0, uraiRupiah($request->input('new_remaining')));
        $tanggalLembar = $this->tanggalAktif($request);

        $entri = BukuPelanggan::find($idHutang);
        if (! $entri) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Entri tidak ditemukan.'], 404)
                : back();
        }

        // Sisa tampil = jumlah entri dikurangi pengurang yang sudah tercatat.
        // Saat pengguna mengedit sisa, pengurang yang sudah ada dipertahankan;
        // jumlah entri disesuaikan agar sisa baru sama dengan nilai input.
        $current = collect($this->hutang->prosesHutangPelanggan($entri->nama, $tanggalLembar)['activeDebts'])
            ->firstWhere('id', $entri->id);
        $sisaSebelum = $current['remaining'] ?? 0;
        $bagianDibayar = $entri->jumlah - $sisaSebelum;
        $entri->update(['jumlah' => max($sisaBaru + $bagianDibayar, 0)]);

        $prosesHutang = $this->hutang->prosesHutangPelanggan($entri->nama, $tanggalLembar);
        if ($request->expectsJson()) {
            return $this->jsonHutang($prosesHutang);
        }

        return back()->with('success', 'Hutang disesuaikan.');
    }

    /** Respons JSON standar edit hutang: daftar entri aktif + total sisa (dipakai JS klien). */
    private function jsonHutang(array $prosesHutang)
    {
        return response()->json([
            'ok' => true,
            'activeDebts' => $prosesHutang['activeDebts'],
            'totalSisa' => $prosesHutang['totalSisa'],
        ]);
    }
}
