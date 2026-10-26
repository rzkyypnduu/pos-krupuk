<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\Produk;
use Illuminate\Http\Request;

/** Tab Produk: daftar harga, tambah/ubah/hapus produk, dan data contoh. */
class ProdukController extends Controller
{
    use BantuanPos;

    public function simpanProduk(Request $request)
    {
        $request->validate(['prodName' => 'required|string|max:255'], ['prodName.required' => 'Isi nama produk dulu.']);
        Produk::create(['nama' => $request->input('prodName'), 'harga' => uraiRupiah($request->input('prodPrice'))]);

        return $this->alihkanKePos($request, ['tab' => 'produk'], 'Produk ditambahkan.');
    }

    public function ubahHarga(Request $request, int $id)
    {
        // "15.000" dinormalisasi ke 15000; nilai bukan angka tetap ditolak validasi.
        $harga = $request->input('price');
        if (is_string($harga) && preg_match('/^[\d.\s]+$/', trim($harga))) {
            $request->merge(['price' => uraiRupiah($harga)]);
        }
        // Kosong = 0 (harga direset); nilai bukan angka tetap ditolak.
        $request->validate(['price' => 'nullable|integer|min:0'], ['price.integer' => 'Harga harus angka.']);
        Produk::where('id', $id)->update(['harga' => (int) ($request->input('price') ?? 0)]);

        return back()->with('success', 'Harga diubah.');
    }

    public function hapusProduk(Request $request, int $id)
    {
        Produk::where('id', $id)->delete();

        return $this->alihkanKePos($request, ['tab' => 'produk'], 'Produk dihapus.');
    }

    public function isiProdukAwal(Request $request)
    {
        $daftarNama = ['Kuning', 'Lempeng', 'Kabur', 'Flowning', 'Gelung', 'Kecil OM', 'Kecil MJ', 'PJO', 'Kecil 2', 'TG', 'TG Mini', 'Drg', 'Lj', 'Uren', 'TM'];
        $yangAda = Produk::pluck('nama')->map(fn ($n) => strtolower($n))->all();
        foreach ($daftarNama as $nama) {
            if (! in_array(strtolower($nama), $yangAda, true)) {
                Produk::create(['nama' => $nama, 'harga' => 0]);
            }
        }

        return $this->alihkanKePos($request, ['tab' => 'produk'], 'Contoh produk ditambahkan.');
    }
}
