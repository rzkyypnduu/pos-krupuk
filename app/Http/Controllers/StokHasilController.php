<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PosHelpers;
use App\Models\OilStock;
use App\Models\StockManagement;
use App\Models\StockRemaining;
use Illuminate\Http\Request;

/**
 * Input tab Hasil selain hutang: stok minyak (1 nilai per tanggal),
 * manajemen stok barang (per pemegang, banyak batch sak), dan sisa barang.
 * Semua endpoint edit inline memakai JSON untuk mode Excel.
 */
class StokHasilController extends Controller
{
    use PosHelpers;

    // ---------- Stok minyak ----------

    public function simpanOil(Request $request)
    {
        if (!$request->input('oilQty')) {
            return back()->withErrors(['oilQty' => 'Isi jumlah dulu.'])->withInput();
        }
        // Satu nilai stok minyak per tanggal — simpan menimpa nilai lama.
        $date = $this->activeDate($request);
        OilStock::where('date', $date)->delete();
        OilStock::create([
            'date' => $date,
            'qty' => $request->input('oilQty'),
            'price' => (int) ($request->input('oilPrice') ?? 0),
        ]);
        return $this->redirectToPos($request, ['tab' => 'hasil'], 'Stok minyak disimpan.');
    }

    public function hapusOil(Request $request)
    {
        OilStock::where('date', $this->activeDate($request))->delete();
        return back()->with('success', 'Stok minyak dihapus.');
    }

    // ---------- Manajemen stok barang ----------

    public function simpanStockMgmt(Request $request)
    {
        $stockMgmtNameInput = $request->input('stockMgmtName', '');
        $name = is_string($stockMgmtNameInput) ? trim($stockMgmtNameInput) : '';
        if (!$name) {
            return back()->withErrors(['stockMgmtName' => 'Isi nama dulu.'])->withInput();
        }
        $sacks = json_decode($request->input('stockMgmtSacks') ?: '[]', true);
        if (!is_array($sacks)) {
            $sacks = [];
        }
        $sacks = array_values(array_filter($sacks, fn ($v) => is_numeric($v) && (float) $v > 0));
        if (empty($sacks)) {
            return back()->withErrors(['stockMgmtSacks' => 'Isi minimal satu karung.'])->withInput();
        }
        $qty = array_sum($sacks);
        $date = $this->activeDate($request);
        $price = (int) ($request->input('stockMgmtPrice') ?? 0);
        $batch = ['id' => uniqid(), 'date' => $date, 'price' => $price, 'sacks' => $sacks];

        $existing = StockManagement::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if ($existing) {
            $batches = $existing->batches ?? [];
            $batches[] = $batch;
            $existing->update(['batches' => $batches, 'price' => $price ?: $existing->price, 'date' => $date]);
        } else {
            StockManagement::create([
                'date' => $date, 'name' => $name, 'qty' => $qty, 'price' => $price,
                'batches' => [$batch],
            ]);
        }
        return $this->redirectToPos($request, ['tab' => 'hasil'], 'Stok barang ditambahkan.');
    }

    public function hapusStockMgmt(Request $request, int $id)
    {
        StockManagement::where('id', $id)->delete();
        return back()->with('success', 'Data stok dihapus.');
    }

    /** Simpan ulang daftar kg per sak dari edit inline tabel (mode Excel). */
    public function updateStockMgmtSacks(Request $request, int $id)
    {
        $row = StockManagement::find($id);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        $input = $request->input('sacks');
        $sacks = [];
        foreach (is_array($input) ? $input : [] as $value) {
            $qty = static::parseDecimal($value);
            if ($qty !== null && $qty > 0) {
                $sacks[] = $qty;
            }
        }
        $total = array_sum($sacks);
        $price = (int) $row->price;
        $row->update([
            'qty' => $total,
            'batches' => [[
                'id' => $row->batches[0]['id'] ?? uniqid(),
                'date' => $row->date->toDateString(),
                'price' => $price,
                'sacks' => $sacks,
            ]],
        ]);
        return response()->json([
            'ok' => true,
            'sacks' => $row->batches[0]['sacks'] ?? [],
            'totalQty' => $total,
            'subtotal' => $row->subtotal(),
        ]);
    }

    // ---------- Sisa barang ----------

    public function simpanRemain(Request $request)
    {
        $name = trim((string) $request->input('remainName', ''));
        if ($name === '') {
            return back()->withErrors(['remainName' => 'Isi nama produk dulu.'])->withInput();
        }
        if ($request->input('remainQty') === null || $request->input('remainQty') === '') {
            return back()->withErrors(['remainQty' => 'Isi jumlah dulu.'])->withInput();
        }
        StockRemaining::create([
            'date' => $this->activeDate($request),
            'name' => $name,
            'qty' => $request->input('remainQty'),
            'price' => (int) ($request->input('remainPrice') ?? 0),
        ]);
        return $this->redirectToPos($request, ['tab' => 'hasil'], 'Sisa barang ditambahkan.');
    }

    /** Simpan edit inline (mode Excel) untuk satu baris sisa barang. */
    public function updateRemain(Request $request, int $id)
    {
        $row = StockRemaining::find($id);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $row->name = $name;
        }
        $row->qty = static::parseDecimal($request->input('qty')) ?? 0;
        $row->price = max(0, (int) ($request->input('price') ?? 0));
        $row->save();
        return response()->json([
            'ok' => true,
            'name' => $row->name,
            'qty' => $row->qty,
            'price' => $row->price,
            'subtotal' => $row->subtotal(),
        ]);
    }

    public function hapusRemain(Request $request, int $id)
    {
        StockRemaining::where('id', $id)->delete();
        return back()->with('success', 'Sisa barang dihapus.');
    }
}
