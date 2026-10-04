<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PosHelpers;
use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\OilStock;
use App\Models\PersonalLedger;
use App\Models\Product;
use App\Models\SaldoDeduction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockManagement;
use App\Models\StockRemaining;
use App\Services\HutangService;
use Illuminate\Http\Request;

/**
 * Halaman utama POS (satu rute GET /pos untuk semua tab) + ringkasan neraca
 * + reset data. Endpoint per fitur ada di controller masing-masing:
 * Transaksi, Produk, StokHasil, HutangPelanggan, HutangPribadi, Saldo, Speech.
 */
class PosController extends Controller
{
    use PosHelpers;

    public function __construct(private HutangService $hutang)
    {
    }

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
            ->filter(fn ($name) => $this->hutang->processCustomerDebts($name, $txDate)['totalSisa'] > 0)->values()->all();
        $allCustomerNames = Sale::pluck('name')->merge(CustomerLedger::pluck('name'))->unique()->sort()->values()->all();
        $stockMgmtHolderNames = StockManagement::pluck('name')->unique()->sort()->values()->all();

        $hpProcessed = [];
        $maxCols = 1;
        $hpGrandTotal = 0;
        foreach ($hpNames as $name) {
            $p = $this->hutang->processCustomerDebts($name, $txDate);
            $hpProcessed[$name] = $p;
            if (count($p['activeDebts']) > $maxCols) $maxCols = count($p['activeDebts']);
            $hpGrandTotal += $p['totalSisa'];
        }

        $hpEntries = null;
        if ($hpDetailName) {
            $hpEntries = $this->hutang->hpDetailEntries($hpDetailName, $txDate);
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

    /** Neraca tab Ringkasan — mengikuti lembar tanggal aktif (per hari, bukan bulan). */
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
}
