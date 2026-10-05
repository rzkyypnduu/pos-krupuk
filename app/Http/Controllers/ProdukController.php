<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PosHelpers;
use App\Models\Product;
use Illuminate\Http\Request;

/** Tab Produk: daftar harga, tambah/ubah/hapus produk, dan data contoh. */
class ProdukController extends Controller
{
    use PosHelpers;

    public function simpanProduk(Request $request)
    {
        $request->validate(['prodName' => 'required|string|max:255'], ['prodName.required' => 'Isi nama produk dulu.']);
        Product::create(['name' => $request->input('prodName'), 'price' => (int) ($request->input('prodPrice') ?? 0)]);
        return $this->redirectToPos($request, ['tab' => 'produk'], 'Produk ditambahkan.');
    }

    public function ubahHarga(Request $request, int $id)
    {
        // Kosong = 0 (harga direset); nilai bukan angka tetap ditolak.
        $request->validate(['price' => 'nullable|integer|min:0'], ['price.integer' => 'Harga harus angka.']);
        Product::where('id', $id)->update(['price' => (int) ($request->input('price') ?? 0)]);
        return back()->with('success', 'Harga diubah.');
    }

    public function hapusProduk(Request $request, int $id)
    {
        Product::where('id', $id)->delete();
        return $this->redirectToPos($request, ['tab' => 'produk'], 'Produk dihapus.');
    }

    public function seedProduk(Request $request)
    {
        $names = ['Kuning', 'Lempeng', 'Kabur', 'Flowning', 'Gelung', 'Kecil OM', 'Kecil MJ', 'PJO', 'Kecil 2', 'TG', 'TG Mini', 'Drg', 'Lj', 'Uren', 'TM'];
        $existing = Product::pluck('name')->map(fn ($n) => strtolower($n))->all();
        foreach ($names as $name) {
            if (!in_array(strtolower($name), $existing, true)) {
                Product::create(['name' => $name, 'price' => 0]);
            }
        }
        return $this->redirectToPos($request, ['tab' => 'produk'], 'Contoh produk ditambahkan.');
    }
}
