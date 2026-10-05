<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PosHelpers;
use App\Models\Expense;
use App\Models\InputLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tab Transaksi: simpan/edit transaksi, pembayaran (modal & lunas),
 * toggle bayar kemarin, hapus, serta pengeluaran kas.
 * Transaksi TIDAK menulis customer_ledgers — rekap hutang di tab Hasil
 * hanya berisi catatan manual & riwayat lama.
 */
class TransaksiController extends Controller
{
    use PosHelpers;

    public function simpanTransaksi(Request $request)
    {
        $request->validate([
            'txName' => 'required|string|max:255',
            'txDate' => 'required|date',
        ], ['txName.required' => 'Isi nama pelanggan dulu.']);

        $qtyInputs = $request->input('qty', []);
        if (!is_array($qtyInputs)) {
            $qtyInputs = [];
        }
        $items = [];
        foreach ($qtyInputs as $productId => $qty) {
            $qty = static::parseDecimal($qty);
            if ($qty !== null && $qty > 0) {
                $product = Product::find($productId);
                if ($product) {
                    $items[] = ['product' => $product, 'qty' => $qty];
                }
            }
        }

        // Edit transaksi yang ID-nya sudah tidak ada (mis. dihapus tab lain) harus
        // ditolak SEBELUM transaksi — kalau tidak, user tetap dapat pesan "berhasil"
        // padahal tidak ada data tersimpan.
        $editingId = $request->input('editing_sale_id');
        if ($editingId && !Sale::whereKey($editingId)->exists()) {
            return back()->withErrors([
                'editing_sale_id' => 'Transaksi yang diedit sudah tidak ditemukan (mungkin sudah dihapus). Muat ulang halaman lalu coba lagi.',
            ]);
        }

        $rawTotal = (int) round(array_sum(array_map(fn ($i) => $i['qty'] * $i['product']->price, $items)));
        $roundedTotal = static::roundTotal($rawTotal);
        $paidTouched = $request->input('tx_paid_touched', 0);
        $paidVal = $request->input('tx_paid');
        $paid = $paidTouched ? parseRupiah($paidVal) : 0;

        // tanpa produk diizinkan asalkan kolom Dibayar diisi (kasus: hari ini hanya bayar hutang)
        if (empty($items) && $paid <= 0) {
            return back()->withErrors([
                'txQty' => 'Isi jumlah minimal satu produk atau isi kolom Dibayar (untuk pembayaran hutang tanpa transaksi).',
            ])->withInput();
        }

        $diff = $roundedTotal - $paid;
        $date = $request->input('txDate');
        $name = trim((string) ($request->input('txName') ?? ''));
        // middleware ConvertEmptyStringsToNull mengubah input kosong menjadi null
        $note = (string) ($request->input('tx_note') ?? '');

        $saleId = 0;
        DB::transaction(function () use (&$saleId, $items, $rawTotal, $roundedTotal, $paid, $diff, $date, $name, $note, $request) {
            $paidKemarin = 0;
            $editingId = $request->input('editing_sale_id');
            if ($editingId) {
                $sale = Sale::find($editingId);
                if (!$sale) return;
                $paidKemarin = (int) $sale->paid_kemarin;
                $sale->update([
                    'date' => $date, 'name' => $name, 'raw_total' => $rawTotal,
                    'rounded_total' => $roundedTotal, 'paid' => $paid, 'diff' => $diff,
                    'note' => $note ?: null,
                    // edit tidak menurunkan status abu yang sudah di-set lewat modal Bayar
                    'is_paid_btn_clicked' => $sale->is_paid_btn_clicked || $paid > 0 || $paidKemarin > 0,
                ]);
                SaleItem::where('sale_id', $sale->id)->delete();
            } else {
                $sale = Sale::create([
                    'date' => $date, 'name' => $name, 'raw_total' => $rawTotal,
                    'rounded_total' => $roundedTotal, 'paid' => $paid, 'diff' => $diff,
                    'note' => $note ?: null, 'is_paid_btn_clicked' => $paid > 0,
                ]);
            }

            $saleId = $sale->id;

            foreach ($items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'name' => $item['product']->name,
                    'qty' => $item['qty'],
                    'price' => $item['product']->price,
                ]);
            }
            // (aturan "transaksi tidak menulis customer_ledgers" ada di docblock kelas)
        });

        $this->logInput($request, $saleId, $name, $note, $items, $roundedTotal, $paid);

        // sukses: tutup form Transaksi Baru (buang query new/editing dari URL tujuan)
        return $this->redirectToPos($request, [
            'tab' => 'transaksi', 'tx_date' => $date, 'new' => null, 'editing' => null,
        ], 'Transaksi berhasil disimpan.');
    }

    /**
     * Bayar lunas satu transaksi (set paid = total, diff = 0, ditandai selesai).
     * Tidak punya route sendiri — dipanggil internal oleh bayarModal saat 'lunas'.
     */
    public function bayarPas(Request $request, int $saleId)
    {
        $sale = Sale::find($saleId);
        if (!$sale) {
            return back()->with('error', 'Transaksi tidak ditemukan.');
        }
        if ($sale->rounded_total <= 0) {
            return back()->with('error', 'Transaksi ini tanpa tagihan — gunakan form Bayar.');
        }

        DB::transaction(function () use ($sale) {
            $sale->update([
                'paid' => $sale->rounded_total,
                'diff' => 0,
                'is_paid_btn_clicked' => true,
            ]);
            // (aturan "pembayaran tidak menulis customer_ledgers" ada di docblock kelas)
        });

        return $this->redirectToPos($request, ['tab' => 'transaksi'], 'Pembayaran lunas.');
    }

    /**
     * Pembayaran lewat modal — keduanya bersifat "input terakhir menang" (SET, bukan akumulasi):
     * - "bayar hari ini" = TOTAL yang sudah dibayar transaksi ini (paid).
     * - "bayar kemarin" = nilai paid_kemarin transaksi ini.
     *   Ceklis ON → paid_kemarin = nilai form. Ceklis OFF → paid_kemarin = 0 (dinonaktifkan);
     *   client modal sudah menambahkan uang kemarin ke form "bayar hari ini" saat OFF,
     *   jadi uangnya pindah alokasi, tidak hilang (Dibayar & Diterima tetap).
     * Isian 0 diperbolehkan (mungkin memang belum bayar sama sekali).
     */
    public function bayarModal(Request $request, int $saleId)
    {
        $sale = Sale::find($saleId);
        if (!$sale) {
            return back()->with('error', 'Transaksi tidak ditemukan.');
        }

        if ($request->input('lunas')) {
            return $this->bayarPas($request, $saleId);
        }

        $parseAmount = fn ($key) => parseRupiah($request->input($key, '0'));
        $pakaiKemarin = (bool) $request->input('pakai_kemarin');
        $payKemarin = $pakaiKemarin ? $parseAmount('bayar_kemarin') : 0;
        $payHariIni = $parseAmount('bayar_hari_ini');

        DB::transaction(function () use ($sale, $pakaiKemarin, $payKemarin, $payHariIni) {
            $sale->update([
                'paid' => $payHariIni,
                'diff' => $sale->rounded_total - $payHariIni,
                // ceklis OFF = nonaktifkan bayar kemarin (client sudah memindahkan
                // uangnya ke form "bayar hari ini" → tidak hilang, hanya pindah)
                'paid_kemarin' => $pakaiKemarin ? $payKemarin : 0,
                // tombol Bayar dipencet = ditandai selesai (abu), termasuk nominal 0/kosong:
                // anggapannya transaksi selesai & hari ini memang tidak bayar sama sekali
                'is_paid_btn_clicked' => true,
            ]);
        });

        return $this->redirectToPos($request, ['tab' => 'transaksi'], 'Pembayaran tercatat.');
    }

    public function loadForPayment(Request $request, int $saleId)
    {
        $sale = Sale::with('items')->find($saleId);
        if (!$sale) return back();

        $params = $this->makeQueryParams($request, [
            'tab' => 'transaksi',
            'editing' => $saleId,
            'tx_date' => $sale->date->format('Y-m-d'),
            'tx_name' => $sale->name,
            'tx_note' => $sale->note ?? '',
            // transaksi tanpa tagihan (bayar hutang): pertahankan nilai paid lama supaya tetap bisa disimpan ulang;
            // untuk tagihan: isi dengan paid sekarang supaya edit menampilkan angka asli (input terakhir menang)
            'tx_paid' => $sale->paid,
            'tx_paid_touched' => 1,
        ]);

        foreach ($sale->items as $item) {
            $params['qty_' . $item->product_id] = (float) $item->qty;
        }

        return redirect()->route('pos', $params);
    }

    /**
     * Toggle ceklis "Bayar kemarin" di baris tabel (live, via fetch).
     * OFF (active=0) = pindah alokasi: uang paid_kemarin dimasukkan ke "bayar hari ini"
     * (paid += paid_kemarin; paid_kemarin = 0; diff dihitung ulang). Total Dibayar &
     * rekap Diterima tidak berubah — uang hanya berpindah, tidak hilang.
     * ON (active=1) tidak mengubah angka (nilai kemarin baru tercatat lewat modal Bayar).
     * Tidak menyentuh customer_ledgers & is_paid_btn_clicked.
     */
    public function toggleKemarin(Request $request, int $saleId)
    {
        $sale = Sale::find($saleId);
        if (!$sale) {
            return response()->json(['ok' => false, 'error' => 'Transaksi tidak ditemukan.'], 404);
        }

        $active = (bool) $request->input('active');

        if (!$active && $sale->paid_kemarin > 0) {
            DB::transaction(function () use ($sale) {
                $sale->paid += $sale->paid_kemarin;
                $sale->paid_kemarin = 0;
                $sale->diff = $sale->rounded_total - $sale->paid;
                $sale->save();
            });
        }

        return response()->json([
            'ok' => true,
            'paid' => (int) $sale->paid,
            'paid_kemarin' => (int) $sale->paid_kemarin,
            'diff' => (int) $sale->diff,
        ]);
    }

    public function hapusTransaksi(Request $request, int $saleId)
    {
        Sale::where('id', $saleId)->delete();
        $params = $this->makeQueryParams($request, ['tab' => 'transaksi']);
        unset($params['editing']);
        return redirect()->route('pos', $params)->with('success', 'Transaksi dihapus.');
    }

    public function simpanExpense(Request $request)
    {
        $amount = (int) ($request->input('expAmount') ?? 0);
        if ($amount <= 0) {
            return back()->withErrors(['expAmount' => 'Isi jumlah uang yang diambil.'])->withInput();
        }
        Expense::create([
            'date' => $request->input('expDate') ?: now()->toDateString(),
            'amount' => $amount,
            'note' => $request->input('expNote') ?: null,
        ]);
        return $this->redirectToPos($request, [
            'tab' => 'transaksi', 'exp_date' => $request->input('expDate') ?: now()->toDateString(),
        ], 'Pengeluaran dicatat.');
    }

    public function hapusExpense(Request $request, int $id)
    {
        Expense::where('id', $id)->delete();
        return back()->with('success', 'Pengeluaran dihapus.');
    }

    /**
     * Catat metode input (speech/manual) + hasil parser untuk analisis akurasi & efisiensi.
     */
    private function logInput(Request $request, int $saleId, string $name, ?string $note, array $items, int $roundedTotal, int $paid): void
    {
        $note = (string) ($note ?? '');
        $method = $request->input('input_method');
        if (!$method) {
            return;
        }

        try {
            $final = [
                'name' => $name,
                'note' => $note ?: null,
                'qty' => [],
                'paid' => $paid,
                'total' => $roundedTotal,
            ];
            foreach ($items as $item) {
                $final['qty'][(string) $item['product']->id] = $item['qty'];
            }

            $parsed = null;
            $rawText = null;
            $normalized = null;
            $parsedInput = $request->input('input_parsed');
            if ($parsedInput) {
                $candidate = json_decode($parsedInput, true);
                if (is_array($candidate)) {
                    $parsed = $candidate;
                    $rawText = $candidate['raw'] ?? null;
                    $normalized = $candidate['normalized'] ?? null;
                }
            }
            if (!$rawText) {
                $rawText = $request->input('input_text');
            }

            $duration = $request->input('input_duration_ms');
            $startedAt = $request->input('input_started_at');

            InputLog::create([
                'method' => (string) $method,
                'tab' => 'transaksi',
                'device' => mb_substr((string) $request->userAgent(), 0, 255),
                'raw_text' => $rawText,
                'normalized_text' => $normalized,
                'parsed_json' => $parsed,
                'final_json' => $final,
                'match_json' => $parsed ? $this->compareParsed($parsed, $final) : null,
                'duration_ms' => is_numeric($duration) ? (int) $duration : null,
                'started_at' => $startedAt ? \Illuminate\Support\Carbon::parse($startedAt) : null,
                'sale_id' => $saleId ?: null,
                'status' => 'ok',
            ]);
        } catch (\Throwable $e) {
            report($e);
            try {
                InputLog::create([
                    'method' => (string) $method,
                    'tab' => 'transaksi',
                    'device' => mb_substr((string) $request->userAgent(), 0, 255),
                    'raw_text' => $request->input('input_text'),
                    'status' => 'error',
                    'error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            } catch (\Throwable $ignored) {
                // logging tidak boleh menggagalkan transaksi
            }
        }
    }

    /** Bandingkan hasil parser dengan nilai akhir form (ground truth). */
    private function compareParsed(array $parsed, array $final): array
    {
        $nameMatch = $this->sameText($parsed['name'] ?? null, $final['name']);

        $qtyMatch = [];
        $parsedQty = [];
        foreach (($parsed['items'] ?? []) as $item) {
            $pid = (string) ($item['product_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $parsedQty[$pid] = ($parsedQty[$pid] ?? 0) + (float) ($item['qty'] ?? 0);
        }
        $allPids = array_unique(array_merge(array_keys($parsedQty), array_keys($final['qty'])));
        foreach ($allPids as $pid) {
            $a = round($parsedQty[$pid] ?? 0, 3);
            $b = round($final['qty'][$pid] ?? 0, 3);
            $qtyMatch[$pid] = ['parsed' => $a, 'final' => $b, 'match' => $a === $b];
        }

        $parsedNote = ($parsed['note'] ?? null) ?: null;
        $finalNote = $final['note'];
        $noteMatch = $this->sameText($parsedNote, $finalNote);

        $itemsCorrect = array_filter($qtyMatch, fn ($m) => $m['match']);
        $itemAcc = count($allPids) > 0 ? count($itemsCorrect) / count($allPids) : ($nameMatch ? 1.0 : 0.0);

        return [
            'name_match' => $nameMatch,
            'note_match' => $noteMatch,
            'qty' => $qtyMatch,
            'item_accuracy' => round($itemAcc, 4),
            'overall' => $nameMatch && $noteMatch && $itemAcc >= 1.0,
        ];
    }

    /** Bandingkan dua teks setelah dinormalisasi (huruf kecil + spasi rapat) — dipakai untuk nama & catatan. */
    private function sameText(?string $a, ?string $b): bool
    {
        return $this->normText($a) === $this->normText($b);
    }

    private function normText(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value);
        return (string) $value;
    }
}
