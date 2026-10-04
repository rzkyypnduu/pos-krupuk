<?php

namespace App\Http\Controllers;

use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\HasilSheet;
use App\Models\InputLog;
use App\Models\OilStock;
use App\Models\PersonalLedger;
use App\Models\Product;
use App\Models\SaldoDeduction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockManagement;
use App\Models\StockRemaining;
use App\Services\SpeechParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'transaksi');
        $editingSaleId = $request->query('editing', null);
        $hpDetailName = $request->query('hp_detail', '');
        $debtModalOpen = $request->query('debt_modal', 0);
        $debtModalName = $request->query('debt_name', '');
        $pendingPaySaleId = $request->query('pay_confirm', null);
        $isPaymentFlow = $request->query('payment_flow', 0);
        $showTxForm = $request->has('new') || (bool) $editingSaleId;

        $products = Product::orderBy('name')->get();

        // Satu tanggal aktif tunggal untuk semua tab; bulan aktif = bulan dari tanggal itu.
        $txDateInput = old('txDate', $request->query('tx_date'));
        $bulanParam = (string) $request->query('bulan', '');
        if (is_string($txDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDateInput) && $this->isValidDate($txDateInput)) {
            $txDate = $txDateInput;
            $activeMonth = substr($txDate, 0, 7);
        } elseif (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanParam)) {
            // URL lama ?bulan=… tanpa tanggal → buka tanggal 1 bulan itu (tetap konsisten)
            $activeMonth = $bulanParam;
            $txDate = $activeMonth . '-01';
        } else {
            $txDate = now()->toDateString();
            $activeMonth = substr($txDate, 0, 7);
        }
        $txName = (string) old('txName', $request->query('tx_name', ''));
        $oldQty = old('qty');
        $txQty = [];
        foreach ($products as $product) {
            $qty = is_array($oldQty) && array_key_exists($product->id, $oldQty)
                ? $oldQty[$product->id]
                : $request->query('qty_' . $product->id, null);
            $txQty[$product->id] = self::parseQty($qty);
        }
        $txPaid = old('tx_paid', $request->query('tx_paid', null));
        $txPaidTouched = old('tx_paid_touched', $request->query('tx_paid_touched', 0));
        $txNote = (string) old('tx_note', $request->query('tx_note', ''));

        $expDate = $request->query('exp_date', now()->toDateString());
        $stockMgmtName = $request->query('sm_name', '');
        $stockMgmtPrice = $request->query('sm_price', null);
        $stockMgmtSacks = $request->query('sm_sacks', '[]');
        $remainName = (string) old('remainName', '');
        $remainQty = old('remainQty', '');
        $remainPrice = old('remainPrice', 0);

        // Tab Hasil = lembar harian. Saat pertama kali membuka tanggal yang masih
        // kosong, isi tab hasil disalin (copy-paste) dari tanggal terakhir yang
        // dilihat lalu kedua tanggal berjalan independen — edit satu tidak
        // mempengaruhi yang lain.
        if ($tab === 'hasil') {
            $this->ensureHasilSheet($txDate);
        }
        session(['hasil_last_date' => $txDate]);

        $sales = Sale::where('date', $txDate)->with('items')->orderBy('id')->get();
        $dayExpenses = Expense::where('date', $expDate ?: now()->toDateString())->orderBy('id')->get();

        $oilStocks = OilStock::where('date', $txDate)->orderBy('id')->get();
        $stockMgmts = StockManagement::where(fn ($q) => $q->where('date', $txDate)->orWhereNull('date'))->orderBy('id')->get();
        $stockRemainings = StockRemaining::where('date', $txDate)->orderBy('id')->get();
        $personalLedgers = PersonalLedger::where('date', $txDate)->orderBy('id')->get();
        $saldoLogs = SaldoDeduction::where('date', $txDate)->orderBy('id')->get();

        $customerBalances = CustomerLedger::balances($txDate);
        $hpNames = $this->scopedCustomerLedgers($txDate)->select('name')->distinct()->orderBy('name')->pluck('name')
            ->filter(fn ($name) => $this->processCustomerDebts($name, $txDate)['totalSisa'] > 0)->values()->all();
        $allCustomerNames = Sale::pluck('name')->merge(CustomerLedger::pluck('name'))->unique()->sort()->values()->all();
        $stockMgmtHolderNames = StockManagement::pluck('name')->unique()->sort()->values()->all();

        $hpProcessed = [];
        $maxCols = 1;
        $hpGrandTotal = 0;
        foreach ($hpNames as $name) {
            $p = $this->processCustomerDebts($name, $txDate);
            $hpProcessed[$name] = $p;
            if (count($p['activeDebts']) > $maxCols) $maxCols = count($p['activeDebts']);
            $hpGrandTotal += $p['totalSisa'];
        }

        $hpEntries = null;
        if ($hpDetailName) {
            $hpEntries = $this->hpDetailEntries($hpDetailName, $txDate);
        }

        $recapPerProduct = [];
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $recapPerProduct[$item->name] = ($recapPerProduct[$item->name] ?? 0) + (float) $item->qty;
            }
        }
        $diterimaHari = (int) $sales->sum('paid') + (int) $sales->sum('paid_kemarin');
        $pengeluaranHari = (int) $dayExpenses->sum('amount');
        $recapTotals = [
            'kg' => (float) $sales->flatMap->items->sum('qty'),
            'dibayar' => $diterimaHari,
            'pengeluaran' => $pengeluaranHari,
            'kas_bersih' => $diterimaHari - $pengeluaranHari,
        ];

        // Ringkasan Bulan (tab Transaksi) tetap bulanan — dihitung terpisah dari
        // data harian tab Hasil.
        [$start, $end] = $this->monthRange($activeMonth);
        $monthSales = Sale::whereBetween('date', [$start, $end])->with('items')->orderBy('date')->orderBy('id')->get();
        $recapBulanPerProduct = [];
        foreach ($monthSales as $sale) {
            foreach ($sale->items as $item) {
                $recapBulanPerProduct[$item->name] = ($recapBulanPerProduct[$item->name] ?? 0) + (float) $item->qty;
            }
        }
        ksort($recapBulanPerProduct);
        $monthExp = Expense::whereBetween('date', [$start, $end])->get();
        $diterimaBulan = (int) $monthSales->sum('paid') + (int) $monthSales->sum('paid_kemarin');
        $pengeluaranBulan = (int) $monthExp->sum('amount');
        $recapBulanTotals = [
            'kg' => (float) $monthSales->flatMap->items->sum('qty'),
            'dibayar' => $diterimaBulan,
            'pengeluaran' => $pengeluaranBulan,
            'kas_bersih' => $diterimaBulan - $pengeluaranBulan,
        ];

        $ringkasanData = null;
        if ($tab === 'ringkasan') {
            $ringkasanData = $this->ringkasan($activeMonth, $txDate);
        }

        $monthLabel = $this->monthLabel($activeMonth);

        return view('pos.index', compact(
            'tab', 'activeMonth', 'hpDetailName', 'editingSaleId', 'pendingPaySaleId', 'isPaymentFlow',
            'debtModalOpen', 'debtModalName', 'showTxForm',
            'txDate', 'txName', 'txQty', 'txPaid', 'txPaidTouched', 'txNote',
            'expDate', 'stockMgmtName', 'stockMgmtPrice', 'stockMgmtSacks',
            'remainName', 'remainQty', 'remainPrice',
            'products', 'sales', 'dayExpenses',
            'oilStocks', 'stockMgmts', 'stockRemainings',
            'customerBalances', 'hpNames', 'hpProcessed', 'hpGrandTotal', 'maxCols', 'hpEntries',
            'personalLedgers', 'saldoLogs',
            'recapPerProduct', 'recapTotals', 'recapBulanPerProduct', 'recapBulanTotals',
            'allCustomerNames', 'stockMgmtHolderNames', 'ringkasanData',
            'monthLabel'
        ));
    }

    private function normalizeMonth(?string $month): string
    {
        $month = (string) $month;

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function monthRange(string $activeMonth): array
    {
        $activeMonth = $this->normalizeMonth($activeMonth);
        $start = $activeMonth . '-01';
        $end = \DateTime::createFromFormat('Y-m-d', $start)->modify('last day of this month')->format('Y-m-d');
        return [$start, $end];
    }

    private function monthLabel(string $activeMonth): string
    {
        $d = \DateTime::createFromFormat('Y-m', $this->normalizeMonth($activeMonth));
        $names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $names[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }

    private function makeQueryParams(Request $request, array $overrides = []): array
    {
        $params = array_merge($request->query(), $overrides);
        return array_filter($params, fn ($v) => $v !== null && $v !== '');
    }

    /** Tanggal aktif dari query/input tx_date; default hari ini. */
    private function activeDate(Request $request): string
    {
        foreach ([$request->query('tx_date'), $request->input('tx_date')] as $date) {
            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $this->isValidDate($date)) {
                return $date;
            }
        }
        return now()->toDateString();
    }

    /** Entri hutang pelanggan milik lembar hasil tanggal aktif (entri legacy tanpa lembar ikut tampil). */
    private function scopedCustomerLedgers(string $sheetDate)
    {
        return CustomerLedger::where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'));
    }

    /**
     * Siapkan lembar hasil untuk tanggal aktif — proses sekali per tanggal.
     * Jika tanggal target masih kosong, seluruh isi tab hasil disalin (copy-paste)
     * dari tanggal sumber; setelah itu kedua tanggal independen — edit satu tidak
     * mempengaruhi yang lain.
     */
    private function ensureHasilSheet(string $txDate): void
    {
        if (HasilSheet::where('date', $txDate)->exists()) {
            return;
        }
        if ($this->hasilHasData($txDate)) {
            HasilSheet::create(['date' => $txDate, 'source_date' => null]);
            return;
        }
        $source = $this->findHasilSource($txDate);
        if ($source === null) {
            return; // belum ada sumber — diproses lagi pada kunjungan berikutnya
        }
        DB::transaction(function () use ($source, $txDate) {
            $this->cloneHasilSheet($source, $txDate);
            HasilSheet::create(['date' => $txDate, 'source_date' => $source]);
        });
    }

    /** Apakah tanggal itu sudah punya isi tab hasil? */
    private function hasilHasData(string $date): bool
    {
        return OilStock::where('date', $date)->exists()
            || StockManagement::where('date', $date)->exists()
            || StockRemaining::where('date', $date)->exists()
            || PersonalLedger::where('date', $date)->exists()
            || SaldoDeduction::where('date', $date)->exists()
            || CustomerLedger::where('sheet_date', $date)->exists();
    }

    /** Sumber salinan: tanggal terakhir yang dilihat, atau tanggal terdekat yang punya data. */
    private function findHasilSource(string $txDate): ?string
    {
        $last = session('hasil_last_date');
        if (is_string($last) && $last !== $txDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $last) && $this->hasilHasData($last)) {
            return $last;
        }

        $before = null;
        $after = null;
        foreach ($this->hasilDateBounds($txDate) as $bound) {
            if ($bound['before'] !== null && ($before === null || $bound['before'] > $before)) {
                $before = $bound['before'];
            }
            if ($bound['after'] !== null && ($after === null || $bound['after'] < $after)) {
                $after = $bound['after'];
            }
        }
        return $before ?? $after;
    }

    /** Batas tanggal yang punya data per tabel (kolom date; pelanggan memakai sheet_date). */
    private function hasilDateBounds(string $txDate): array
    {
        $bounds = [];
        foreach ([OilStock::class, StockManagement::class, StockRemaining::class, PersonalLedger::class, SaldoDeduction::class] as $model) {
            $bounds[] = [
                'before' => $model::where('date', '<', $txDate)->max('date'),
                'after' => $model::where('date', '>', $txDate)->min('date'),
            ];
        }
        $bounds[] = [
            'before' => CustomerLedger::whereNotNull('sheet_date')->where('sheet_date', '<', $txDate)->max('sheet_date'),
            'after' => CustomerLedger::whereNotNull('sheet_date')->where('sheet_date', '>', $txDate)->min('sheet_date'),
        ];
        return $bounds;
    }

    /** Salin seluruh isi tab hasil dari tanggal sumber ke tanggal target (independen). */
    private function cloneHasilSheet(string $source, string $target): void
    {
        $models = [OilStock::class, StockManagement::class, StockRemaining::class, PersonalLedger::class, SaldoDeduction::class];
        foreach ($models as $model) {
            foreach ($model::where('date', $source)->get() as $row) {
                $clone = $row->replicate();
                $clone->date = $target;
                $clone->save();
            }
        }
        // Rekap hutang pelanggan: entri disalin dengan lembar baru; tanggal riwayat
        // (date) tetap asli supaya catatan peristiwa tidak ikut bergeser.
        foreach (CustomerLedger::where('sheet_date', $source)->get() as $row) {
            $clone = $row->replicate();
            $clone->sheet_date = $target;
            $clone->save();
        }
    }

    public static function roundTotal(int $total): int
    {
        $thousands = intdiv($total, 1000) * 1000;
        $remainder = $total - $thousands;
        return $remainder < 500 ? $thousands : $thousands + 1000;
    }

    /**
     * Qty desimal dari form: UI memakai koma desimal ("1,5") — (float) "1,5" di PHP = 1,
     * jadi normalisasi dulu ke titik supaya setengah kg ikut tercatat & dihitung.
     */
    public static function parseQty(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public function parseSpeech(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
            'alternatives' => 'sometimes|array|max:5',
            'alternatives.*' => 'string|max:2000',
        ], [
            'text.required' => 'Teks suara kosong.',
        ]);

        $products = Product::orderBy('name')->get();
        $parser = new SpeechParser();

        // Browser mengirim beberapa kandidat hasil N-best; pilih yang paling masuk akal.
        $candidates = [$request->input('text', '')];
        foreach ((array) $request->input('alternatives', []) as $alt) {
            $alt = trim((string) $alt);
            if ($alt !== '' && !in_array($alt, $candidates, true)) {
                $candidates[] = $alt;
            }
        }

        $best = null;
        $bestScore = PHP_INT_MIN;
        foreach ($candidates as $candidate) {
            $parsed = $parser->parse($candidate, $products);
            $loose = count(array_filter($parsed['items'], fn ($i) => $i['confidence'] === 'loose'));
            $score = count($parsed['items']) * 10 + ($parsed['name'] !== null && $parsed['name'] !== '' ? 5 : 0) - $loose;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $parsed;
            }
        }

        return response()->json(['ok' => true, 'candidates' => count($candidates)] + $best);
    }

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
            $qty = self::parseQty($qty);
            if ($qty !== null && $qty > 0) {
                $product = Product::find($productId);
                if ($product) {
                    $items[] = ['product' => $product, 'qty' => $qty];
                }
            }
        }

        $rawTotal = (int) round(array_sum(array_map(fn ($i) => $i['qty'] * $i['product']->price, $items)));
        $roundedTotal = self::roundTotal($rawTotal);
        $paidTouched = $request->input('tx_paid_touched', 0);
        $paidVal = $request->input('tx_paid');
        $paid = $paidTouched && !is_null($paidVal) && $paidVal !== '' ? (int) str_replace(',', '.', str_replace('.', '', $paidVal)) : 0;

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
            // transaksi TIDAK menulis customer_ledgers: tabel hutang per pelanggan di tab Hasil
            // hanya berisi catatan manual (+ Tambah hutang, klik sel, adjust total) & riwayat lama
        });

        $this->logInput($request, $saleId, $name, $note, $items, $roundedTotal, $paid);

        // sukses: tutup form Transaksi Baru (buang query new/editing dari URL tujuan)
        return redirect()->route('pos', $this->makeQueryParams($request, [
            'tab' => 'transaksi', 'tx_date' => $date, 'new' => null, 'editing' => null,
        ]))->with('success', 'Transaksi berhasil disimpan.');
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
        $nameMatch = $this->sameName($parsed['name'] ?? null, $final['name']);

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

    private function sameName(?string $a, ?string $b): bool
    {
        return $this->normName($a) === $this->normName($b);
    }

    private function normName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value);
        return (string) $value;
    }

    private function sameText(?string $a, ?string $b): bool
    {
        return $this->normName($a) === $this->normName($b);
    }

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
            // pembayaran tidak menulis customer_ledgers — tabel hutang di tab Hasil
            // hanya berisi catatan manual & riwayat lama
        });

        return redirect()->route('pos', $this->makeQueryParams($request, [
            'tab' => 'transaksi'
        ]))->with('success', 'Pembayaran lunas.');
    }

    /**
     * Pembayaran lewat modal — keduanya bersifat "input terakhir menang" (SET, bukan akumulasi):
     * - "bayar hari ini" = TOTAL yang sudah dibayar transaksi ini (paid).
     * - "bayar kemarin" = nilai paid_kemarin transaksi ini.
     *   Ceklis ON → paid_kemarin = nilai form. Ceklis OFF → paid_kemarin = 0 (dinonaktifkan);
     *   client modal sudah menambahkan uang kemarin ke form "bayar hari ini" saat OFF,
     *   jadi uangnya pindah alokasi, tidak hilang (Dibayar & Diterima tetap).
     * Isian 0 diperbolehkan (mungkin memang belum bayar sama sekali).
     * Pembayaran TIDAK menulis customer_ledgers — tabel hutang di tab Hasil hanya
     * berisi catatan manual & riwayat lama.
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

        $parseAmount = fn ($key) => (int) preg_replace('/\D+/', '', (string) $request->input($key, '0'));
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

        return redirect()->route('pos', $this->makeQueryParams($request, [
            'tab' => 'transaksi'
        ]))->with('success', 'Pembayaran tercatat.');
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
            'payment_flow' => 0,
        ]);

        foreach ($sale->items as $item) {
            $params['qty_' . $item->product_id] = (float) $item->qty;
        }

        return redirect()->route('pos', $params);
    }

    public function markPaidBtn(Request $request, int $saleId)
    {
        Sale::where('id', $saleId)->update(['is_paid_btn_clicked' => true]);
        return back();
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

    public function batalEdit(Request $request)
    {
        $params = $this->makeQueryParams($request);
        unset($params['editing'], $params['payment_flow'], $params['tx_paid'], $params['tx_paid_touched']);
        $params['tab'] = 'transaksi';
        return redirect()->route('pos', $params);
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
        return redirect()->route('pos', $this->makeQueryParams($request, [
            'tab' => 'transaksi', 'exp_date' => $request->input('expDate') ?: now()->toDateString()
        ]))->with('success', 'Pengeluaran dicatat.');
    }

    public function hapusExpense(Request $request, int $id)
    {
        Expense::where('id', $id)->delete();
        return back()->with('success', 'Pengeluaran dihapus.');
    }

    public function simpanProduk(Request $request)
    {
        $request->validate(['prodName' => 'required|string|max:255'], ['prodName.required' => 'Isi nama produk dulu.']);
        Product::create(['name' => $request->input('prodName'), 'price' => (int) ($request->input('prodPrice') ?? 0)]);
        return redirect()->route('pos', $this->makeQueryParams($request, ['tab' => 'produk']))->with('success', 'Produk ditambahkan.');
    }

    public function ubahHarga(Request $request, int $id)
    {
        Product::where('id', $id)->update(['price' => (int) ($request->input('price') ?? 0)]);
        return back()->with('success', 'Harga diubah.');
    }

    public function hapusProduk(Request $request, int $id)
    {
        Product::where('id', $id)->delete();
        return redirect()->route('pos', $this->makeQueryParams($request, ['tab' => 'produk']))->with('success', 'Produk dihapus.');
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
        return redirect()->route('pos', $this->makeQueryParams($request, ['tab' => 'produk']))->with('success', 'Contoh produk ditambahkan.');
    }

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
        return redirect()->route('pos', $this->makeQueryParams($request, [
            'tab' => 'hasil'
        ]))->with('success', 'Stok minyak disimpan.');
    }

    public function hapusOil(Request $request)
    {
        OilStock::where('date', $this->activeDate($request))->delete();
        return back()->with('success', 'Stok minyak dihapus.');
    }

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
        return redirect()->route('pos', $this->makeQueryParams($request, [
            'tab' => 'hasil'
        ]))->with('success', 'Stok barang ditambahkan.');
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
            $qty = static::parseQty($value);
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

    public function hapusStockBatch(Request $request, int $itemId, string $batchId)
    {
        $item = StockManagement::find($itemId);
        if (!$item) return back();
        $batches = collect($item->batches ?? [])->reject(fn ($b) => ($b['id'] ?? '') === $batchId)->values()->all();
        if (empty($batches)) {
            $item->delete();
        } else {
            $item->update(['batches' => $batches]);
        }
        return back()->with('success', 'Batch dihapus.');
    }

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
        return redirect()->route('pos', $this->makeQueryParams($request, [
            'tab' => 'hasil'
        ]))->with('success', 'Sisa barang ditambahkan.');
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
        $row->qty = static::parseQty($request->input('qty')) ?? 0;
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
            $proc = $this->processCustomerDebts($name, $this->activeDate($request));
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
        $current = collect($this->processCustomerDebts($entry->name, $sheetDate)['activeDebts'])
            ->firstWhere('id', $entry->id);
        $remainingBefore = $current['remaining'] ?? 0;
        $paidPortion = $entry->amount - $remainingBefore;
        $entry->update(['amount' => max($newRemaining + $paidPortion, 0)]);

        $proc = $this->processCustomerDebts($entry->name, $sheetDate);
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
        $name = $request->input('name');
        $newTotal = (int) $request->input('new_total');
        $sheetDate = $this->activeDate($request);
        $current = $this->processCustomerDebts($name, $sheetDate)['totalSisa'];
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
        $proc = $this->processCustomerDebts($name, $sheetDate);
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'activeDebts' => $proc['activeDebts'],
                'totalSisa' => $proc['totalSisa'],
            ]);
        }
        return back()->with('success', 'Total hutang disesuaikan.');
    }

    public function simpanHutangPribadi(Request $request)
    {
        $request->validate([
            'hprName' => 'required|string|max:255',
            'hprAmount' => 'required|integer|min:1',
        ], ['hprName.required' => 'Isi nama dulu.', 'hprAmount.required' => 'Isi jumlah dulu.']);
        PersonalLedger::create([
            'date' => $this->activeDate($request),
            'name' => $request->input('hprName'),
            'amount' => $request->input('hprAmount'),
        ]);
        return redirect()->route('pos', $this->makeQueryParams($request, ['tab' => 'hasil']))->with('success', 'Hutang pribadi dicatat.');
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
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $row->name = $name;
        }
        $amount = static::parseQty($request->input('amount'));
        $row->amount = $amount !== null ? max(0, (int) round($amount)) : 0;
        $row->save();
        return response()->json(['ok' => true, 'name' => $row->name, 'amount' => $row->amount]);
    }

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

    public function resetMonth(Request $request)
    {
        $bulan = (string) $request->query('bulan', '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return back()->withErrors(['bulan' => 'Pilih bulan yang valid dulu.']);
        }
        [$start, $end] = $this->monthRange($bulan);
        Sale::whereBetween('date', [$start, $end])->delete();
        Expense::whereBetween('date', [$start, $end])->delete();
        OilStock::whereBetween('date', [$start, $end])->delete();
        StockManagement::whereBetween('date', [$start, $end])->delete();
        StockRemaining::whereBetween('date', [$start, $end])->delete();
        PersonalLedger::whereBetween('date', [$start, $end])->delete();
        SaldoDeduction::whereBetween('date', [$start, $end])->delete();
        return redirect()->route('pos', $this->makeQueryParams($request, ['tab' => 'ringkasan']))->with('success', 'Data bulan ini dihapus.');
    }

    public function resetAll(Request $request)
    {
        SaleItem::query()->delete();
        Sale::query()->delete();
        CustomerLedger::query()->delete();
        PersonalLedger::query()->delete();
        OilStock::query()->delete();
        StockManagement::query()->delete();
        StockRemaining::query()->delete();
        Product::query()->delete();
        SaldoDeduction::query()->delete();
        Expense::query()->delete();
        return redirect()->route('pos', ['tab' => 'transaksi'])->with('success', 'Semua data dihapus.');
    }

    public function processCustomerDebts(string $name, ?string $sheetDate = null): array
    {
        $query = CustomerLedger::where('name', $name);
        if ($sheetDate !== null) {
            $query->where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'));
        }
        $entries = $query->orderBy('date')->orderBy('id')->get();
        $debts = [];
        $deposit = 0;
        foreach ($entries as $l) {
            if ($l->type === 'tambah') {
                $debts[] = ['id' => $l->id, 'amount' => $l->amount, 'remaining' => $l->amount, 'date' => $l->date->format('Y-m-d')];
            } else {
                // LIFO: pembayaran memotong hutang TERBARU dulu (dari akhir daftar),
                // baru ke yang lebih lama — sejajar preview tick di modal Bayar
                $payment = $l->amount;
                for ($i = count($debts) - 1; $i >= 0 && $payment > 0; $i--) {
                    $cut = min($payment, $debts[$i]['remaining']);
                    $debts[$i]['remaining'] -= $cut;
                    $payment -= $cut;
                }
                if ($payment > 0) $deposit += $payment;
            }
        }
        $activeDebts = array_values(array_filter($debts, fn ($d) => $d['remaining'] > 0));
        $totalSisa = (int) array_sum(array_column($activeDebts, 'remaining'));
        return compact('activeDebts', 'totalSisa', 'deposit');
    }

    public function hpDetailEntries(string $name, ?string $sheetDate = null)
    {
        $query = CustomerLedger::where('name', $name);
        if ($sheetDate !== null) {
            $query->where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'));
        }
        $entries = $query->orderBy('date')->orderBy('id')->get();
        $running = 0;
        $sales = Sale::whereIn('id', $entries->pluck('sale_id')->filter()->unique())->get()->keyBy('id');
        return $entries->map(function ($l) use (&$running, $sales) {
            $running += $l->type === 'tambah' ? $l->amount : -$l->amount;
            return (object) [
                'id' => $l->id, 'date' => $l->date->format('Y-m-d'),
                'type' => $l->type, 'amount' => $l->amount, 'running' => $running,
                'note' => $l->note, 'sale_id' => $l->sale_id,
                'paid' => $l->sale_id && isset($sales[$l->sale_id]) ? $sales[$l->sale_id]->paid : null,
            ];
        });
    }

    public function ringkasan(string $activeMonth, string $txDate): array
    {
        // Neraca mengikuti lembar tanggal aktif (tab hasil per hari; data per tanggal
        // disalin sekali lalu independen — angka bulan tidak dipakai agar tidak
        // terhitung ganda dari salinan antar tanggal).
        $totalOil = OilStock::where('date', $txDate)->get()->sum(fn ($o) => $o->qty * $o->price);
        $totalStockMgmt = StockManagement::where(fn ($q) => $q->where('date', $txDate)->orWhereNull('date'))->get()->sum(fn ($o) => $o->subtotal());
        $totalRemain = StockRemaining::where('date', $txDate)->get()->sum(fn ($o) => $o->qty * $o->price);
        $balances = CustomerLedger::balances($txDate);
        $totalHutangPel = array_sum(array_map(fn ($b) => $b > 0 ? $b : 0, $balances));
        $totalHutangPri = (int) PersonalLedger::where('date', $txDate)->sum('amount');
        $totalSaldo = (int) SaldoDeduction::where('date', $txDate)->get()->sum(fn ($s) => $s->result());
        $grand = $totalOil + $totalStockMgmt + $totalHutangPel + $totalRemain;

        return compact(
            'totalOil', 'totalStockMgmt', 'totalRemain', 'totalHutangPel', 'totalHutangPri',
            'totalSaldo', 'grand', 'activeMonth', 'txDate'
        );
    }
}
