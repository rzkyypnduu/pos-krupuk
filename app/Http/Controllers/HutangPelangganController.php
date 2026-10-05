<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PosHelpers;
use App\Models\CustomerLedger;
use App\Services\HutangService;
use Illuminate\Http\Request;

/**
 * Rekap hutang per pelanggan (grid Excel di tab Hasil):
 * tambah entri, edit sel/total via JSON, hapus per sel atau per baris,
 * dan ganti nama pelanggan. Perhitungan sisa (LIFO) ada di HutangService.
 */
class HutangPelangganController extends Controller
{
    use PosHelpers;

    public function __construct(private HutangService $hutang)
    {
    }

    public function simpanHutangPelanggan(Request $request)
    {
        $request->validate([
            'debtModalName' => 'required|string|max:255',
            'debtModalAmount' => 'required|integer|min:1',
        ], [
            'debtModalName.required' => 'Isi nama pelanggan.',
            'debtModalAmount.required' => 'Isi jumlah hutang.',
        ]);
        CustomerLedger::create([
            'date' => $request->input('debtModalDate') ?: now()->toDateString(),
            'sheet_date' => $this->activeDate($request),
            'name' => $request->input('debtModalName'),
            'amount' => $request->input('debtModalAmount'),
            'type' => 'tambah',
            'note' => $request->input('debtModalNote') ?: 'Tambah hutang manual',
        ]);
        return redirect()->route('pos', $this->makeQueryParams($request, ['tab' => 'hasil']))->with('success', 'Hutang ditambahkan.');
    }

    public function hapusLedgerEntry(Request $request, int $id)
    {
        $entry = CustomerLedger::find($id);
        if (!$entry) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Entri tidak ditemukan.'], 404)
                : back();
        }
        $name = $entry->name;
        $entry->delete();
        if ($request->expectsJson()) {
            $proc = $this->hutang->processCustomerDebts($name, $this->activeDate($request));
            return response()->json([
                'ok' => true,
                'activeDebts' => $proc['activeDebts'],
                'totalSisa' => $proc['totalSisa'],
            ]);
        }
        return back()->with('success', 'Entri dihapus.');
    }

    public function renameCustomer(Request $request)
    {
        $old = trim((string) $request->input('old_name'));
        $new = trim((string) $request->input('new_name'));
        if ($old === '' || $new === '') {
            return response()->json(['ok' => false, 'message' => 'Nama tidak boleh kosong.'], 422);
        }
        if ($old !== $new) {
            if (CustomerLedger::where('name', $new)->exists()) {
                return response()->json(['ok' => false, 'message' => 'Nama "' . $new . '" sudah dipakai pelanggan lain.'], 422);
            }
            CustomerLedger::where('name', $old)->update(['name' => $new]);
        }
        return response()->json(['ok' => true, 'name' => $new]);
    }

    public function hapusCustomerLedger(Request $request, string $name)
    {
        $this->scopedCustomerLedgers($this->activeDate($request))
            ->where('name', $name)->delete();
        return back()->with('success', 'Riwayat hutang dihapus.');
    }

    public function adjustDebtCell(Request $request)
    {
        $debtId = $request->input('debt_id');
        $newRemaining = max(0, (int) $request->input('new_remaining'));
        $sheetDate = $this->activeDate($request);

        $entry = CustomerLedger::find($debtId);
        if (!$entry) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Entri tidak ditemukan.'], 404)
                : back();
        }

        // Sisa yang tampil = amount entri − pembayaran LIFO yang sudah menimpanya.
        // Saat user mengedit sisa, pembayaran yang sudah berjalan dipertahankan —
        // amount disesuaikan supaya sisa baru persis nilai yang dimasukkan.
        $current = collect($this->hutang->processCustomerDebts($entry->name, $sheetDate)['activeDebts'])
            ->firstWhere('id', $entry->id);
        $remainingBefore = $current['remaining'] ?? 0;
        $paidPortion = $entry->amount - $remainingBefore;
        $entry->update(['amount' => max($newRemaining + $paidPortion, 0)]);

        $proc = $this->hutang->processCustomerDebts($entry->name, $sheetDate);
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'activeDebts' => $proc['activeDebts'],
                'totalSisa' => $proc['totalSisa'],
            ]);
        }
        return back()->with('success', 'Hutang disesuaikan.');
    }

    public function adjustTotalDebt(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'new_total' => 'required|integer|min:0',
        ]);
        $name = $request->input('name');
        $newTotal = (int) $request->input('new_total');
        $sheetDate = $this->activeDate($request);
        $current = $this->hutang->processCustomerDebts($name, $sheetDate)['totalSisa'];
        $diff = $newTotal - $current;
        if ($diff !== 0) {
            CustomerLedger::create([
                'date' => $sheetDate,
                'sheet_date' => $sheetDate,
                'name' => $name,
                'amount' => abs($diff),
                'type' => $diff > 0 ? 'tambah' : 'bayar',
                'note' => 'Penyesuaian manual saldo hutang',
            ]);
        }
        $proc = $this->hutang->processCustomerDebts($name, $sheetDate);
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'activeDebts' => $proc['activeDebts'],
                'totalSisa' => $proc['totalSisa'],
            ]);
        }
        return back()->with('success', 'Total hutang disesuaikan.');
    }
}
