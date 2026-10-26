<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\ManajemenStok;
use App\Models\SisaStok;
use App\Models\StokMinyak;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Input tab Hasil selain hutang: stok minyak (1 nilai per tanggal),
 * manajemen stok barang (per pemegang, banyak batch sak), dan sisa barang.
 * Semua endpoint edit inline memakai JSON untuk mode Excel.
 */
class StokHasilController extends Controller
{
    use BantuanPos;

    // ---------- Stok minyak ----------

    public function simpanStokMinyak(Request $request)
    {
        if (! $request->input('oilQty')) {
            return back()->withErrors(['oilQty' => 'Isi jumlah dulu.'])->withInput();
        }
        // Satu nilai stok minyak per tanggal — simpan menimpa nilai lama.
        $tanggal = $this->tanggalAktif($request);
        StokMinyak::where('tanggal', $tanggal)->delete();
        StokMinyak::create([
            'tanggal' => $tanggal,
            'jumlah' => $request->input('oilQty'),
            'harga' => uraiRupiah($request->input('oilPrice')), // aman terima "15.000"
        ]);

        return $this->alihkanKePos($request, ['tab' => 'hasil'], 'Stok minyak disimpan.');
    }

    public function hapusStokMinyak(Request $request)
    {
        StokMinyak::where('tanggal', $this->tanggalAktif($request))->delete();

        return back()->with('success', 'Stok minyak dihapus.');
    }

    // ---------- Manajemen stok barang ----------

    public function simpanManajemenStok(Request $request)
    {
        $stockMgmtNameInput = $request->input('stockMgmtName', '');
        $nama = is_string($stockMgmtNameInput) ? trim($stockMgmtNameInput) : '';
        if (! $nama) {
            return back()->withErrors(['stockMgmtName' => 'Isi nama dulu.'])->withInput();
        }
        $karung = json_decode($request->input('stockMgmtSacks') ?: '[]', true);
        if (! is_array($karung)) {
            $karung = [];
        }
        $karung = array_values(array_filter($karung, fn ($v) => is_numeric($v) && (float) $v > 0));
        if (empty($karung)) {
            return back()->withErrors(['stockMgmtSacks' => 'Isi minimal satu karung.'])->withInput();
        }
        $jumlah = array_sum($karung);
        $tanggal = $this->tanggalAktif($request);
        $harga = uraiRupiah($request->input('stockMgmtPrice')); // aman terima "15.000"
        $kelompok = ['id' => uniqid(), 'date' => $tanggal, 'price' => $harga, 'sacks' => $karung];

        $yangAda = ManajemenStok::whereRaw('LOWER(nama) = ?', [strtolower($nama)])->first();
        if ($yangAda) {
            $daftarKelompok = $yangAda->kelompok ?? [];
            $daftarKelompok[] = $kelompok;
            $yangAda->update(['kelompok' => $daftarKelompok, 'harga' => $harga ?: $yangAda->harga, 'tanggal' => $tanggal]);
        } else {
            ManajemenStok::create([
                'tanggal' => $tanggal, 'nama' => $nama, 'jumlah' => $jumlah, 'harga' => $harga,
                'kelompok' => [$kelompok],
            ]);
        }

        return $this->alihkanKePos($request, ['tab' => 'hasil'], 'Stok barang ditambahkan.');
    }

    public function hapusManajemenStok(Request $request, int $id)
    {
        ManajemenStok::where('id', $id)->delete();

        return back()->with('success', 'Data stok dihapus.');
    }

    /** Simpan ulang daftar kg per sak dari edit inline tabel (mode Excel). */
    public function perbaruiKarungManajemenStok(Request $request, int $id)
    {
        $baris = ManajemenStok::find($id);
        if (! $baris) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        $masukan = $request->input('sacks');
        $karung = [];
        foreach (is_array($masukan) ? $masukan : [] as $nilai) {
            $jumlah = static::uraiDesimal($nilai);
            if ($jumlah !== null && $jumlah > 0) {
                $karung[] = $jumlah;
            }
        }
        $total = array_sum($karung);
        $harga = (int) $baris->harga;
        $baris->update([
            'jumlah' => $total,
            'kelompok' => [[
                'id' => $baris->kelompok[0]['id'] ?? uniqid(),
                'date' => Carbon::parse($baris->tanggal)->toDateString(),
                'price' => $harga,
                'sacks' => $karung,
            ]],
        ]);

        return response()->json([
            'ok' => true,
            'sacks' => $baris->kelompok[0]['sacks'] ?? [],
            'totalQty' => $total,
            'price' => $baris->harga,
            'subtotal' => $baris->subtotal(),
        ]);
    }

    /** Simpan harga per kg untuk seluruh kelompok milik satu pemegang stok. */
    public function perbaruiHargaManajemenStok(Request $request, int $id)
    {
        $baris = ManajemenStok::find($id);
        if (! $baris) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $harga = max(0, uraiRupiah($request->input('price')));
        $kelompok = $baris->kelompok ?? [];
        foreach ($kelompok as &$bagian) {
            $bagian['price'] = $harga;
        }
        unset($bagian);

        $baris->update([
            'harga' => $harga,
            'kelompok' => $kelompok,
        ]);

        return response()->json([
            'ok' => true,
            'price' => $baris->harga,
            'subtotal' => $baris->subtotal(),
        ]);
    }

    // ---------- Sisa barang ----------

    public function simpanSisaStok(Request $request)
    {
        $nama = trim((string) $request->input('remainName', ''));
        if ($nama === '') {
            return back()->withErrors(['remainName' => 'Isi nama produk dulu.'])->withInput();
        }
        if ($request->input('remainQty') === null || $request->input('remainQty') === '') {
            return back()->withErrors(['remainQty' => 'Isi jumlah dulu.'])->withInput();
        }
        SisaStok::create([
            'tanggal' => $this->tanggalAktif($request),
            'nama' => $nama,
            'jumlah' => $request->input('remainQty'),
            'harga' => uraiRupiah($request->input('remainPrice')), // aman terima "15.000"
        ]);

        return $this->alihkanKePos($request, ['tab' => 'hasil'], 'Sisa barang ditambahkan.');
    }

    /** Simpan edit inline (mode Excel) untuk satu baris sisa barang. */
    public function perbaruiSisaStok(Request $request, int $id)
    {
        $baris = SisaStok::find($id);
        if (! $baris) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        $nama = trim((string) $request->input('name', ''));
        if ($nama !== '') {
            $baris->nama = $nama;
        }
        $baris->jumlah = static::uraiDesimal($request->input('qty')) ?? 0;
        $baris->harga = max(0, uraiRupiah($request->input('price'))); // aman terima "15.000"
        $baris->save();

        return response()->json([
            'ok' => true,
            'name' => $baris->nama,
            'qty' => $baris->jumlah,
            'price' => $baris->harga,
            'subtotal' => $baris->subtotal(),
        ]);
    }

    public function hapusSisaStok(Request $request, int $id)
    {
        SisaStok::where('id', $id)->delete();

        return back()->with('success', 'Sisa barang dihapus.');
    }
}
