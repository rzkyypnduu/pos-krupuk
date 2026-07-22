<?php

namespace App\Livewire\Pos;

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
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PosApp extends Component
{
    public string $tab = 'transaksi';

    public string $activeMonth = '';

    public string $hpDetailName = '';

    public ?string $editingSaleId = null;

    public ?int $pendingPaySaleId = null;

    public bool $isPaymentFlow = false;

    // Transaksi
    public string $txDate = '';

    public string $txName = '';

    public array $txQty = [];

    public $txPaid = null;

    public bool $txPaidTouched = false;

    public string $txNote = '';

    // Pengeluaran
    public string $expDate = '';

    public $expAmount = null;

    public string $expNote = '';

    // Produk
    public string $prodName = '';

    public $prodPrice = null;

    // Oil
    public string $oilDate = '';

    public $oilQty = null;

    public $oilPrice = null;

    // Stock management
    public string $stockMgmtDate = '';

    public string $stockMgmtName = '';

    public $stockMgmtPrice = null;

    public string $stockMgmtSacks = ''; // JSON of sack qty values

    // Sisa barang
    public string $remainDate = '';

    public ?int $remainProductId = null;

    public $remainQty = null;

    public $remainPrice = null;

    // Hutang pribadi
    public string $hprDate = '';

    public string $hprName = '';

    public $hprAmount = null;

    public string $hprNote = '';

    // Saldo deduction
    public string $saldoDate = '';

    public $saldoA = null;

    public $saldoB = null;

    public string $saldoNote = '';

    // Customer debt modal
    public bool $debtModalOpen = false;

    public string $debtModalName = '';

    public $debtModalAmount = null;

    public string $debtModalDate = '';

    public string $debtModalNote = '';

    public function mount(): void
    {
        $this->activeMonth = now()->format('Y-m');
        $this->txDate = now()->toDateString();
        $this->expDate = now()->toDateString();
        $this->oilDate = now()->toDateString();
        $this->stockMgmtDate = now()->toDateString();
        $this->remainDate = now()->toDateString();
        $this->hprDate = now()->toDateString();
        $this->saldoDate = now()->toDateString();
        $this->debtModalDate = now()->toDateString();
        $this->stockMgmtSacks = '[]';

        foreach (Product::orderBy('name')->get() as $product) {
            $this->txQty[$product->id] = null;
        }

        $this->remainProductId = Product::orderBy('name')->first()?->id;
        $this->syncRemainPrice();
    }

    // ---------- Helpers ----------
    public static function roundTotal(int $total): int
    {
        $thousands = intdiv($total, 1000) * 1000;
        $remainder = $total - $thousands;

        return $remainder < 500 ? $thousands : $thousands + 1000;
    }

    public function txRawTotal(): int
    {
        $total = 0;
        foreach ($this->txQty as $productId => $qty) {
            if (! $qty) {
                continue;
            }
            $product = Product::find($productId);
            if (! $product) {
                continue;
            }
            $total += (float) $qty * $product->price;
        }

        return (int) round($total);
    }

    public function txRoundedTotal(): int
    {
        return self::roundTotal($this->txRawTotal());
    }

    public function txDiff(): int
    {
        return $this->txRoundedTotal() - (int) ($this->txPaid ?? 0);
    }

    public function updatedTxQty($value, $key): void
    {
        if (!is_null($value) && $value !== '') {
            $normalized = str_replace(',', '.', $value);
            $this->txQty[$key] = (float) $normalized;
        }
    }

    public function updatedTxPaid(): void
    {
        $this->txPaidTouched = true;
    }

    public function fillLunas(): void
    {
        $this->txPaid = $this->txRoundedTotal();
        $this->txPaidTouched = true;
    }

    public function incrementQty(int $productId): void
    {
        $this->txQty[$productId] = ($this->txQty[$productId] ?? 0) + 0.5;
    }

    public function decrementQty(int $productId): void
    {
        $this->txQty[$productId] = max(0, ($this->txQty[$productId] ?? 0) - 0.5);
    }

    public function confirmPay(int $saleId): void
    {
        $this->pendingPaySaleId = $saleId;
    }

    public function payExact(int $saleId): void
    {
        DB::transaction(function () use ($saleId) {
            $sale = Sale::find($saleId);
            if (! $sale) {
                return;
            }

            $sale->update([
                'paid' => $sale->rounded_total,
                'diff' => 0,
                'is_paid_btn_clicked' => true,
            ]);

            CustomerLedger::where('sale_id', $saleId)->delete();
        });

        $this->pendingPaySaleId = null;
    }

    public function payWithForm(int $saleId): void
    {
        $this->pendingPaySaleId = null;
        $this->isPaymentFlow = true;
        $this->loadSaleForPayment($saleId);
        $this->dispatch('scroll-to-form');
    }

    public function customerBalances(): array
    {
        return CustomerLedger::balances();
    }

    public function customerBalanceFor(string $name): int
    {
        $bals = $this->customerBalances();

        return $bals[$name] ?? 0;
    }

    public function customerNames(): array
    {
        return Sale::pluck('name')
            ->merge(CustomerLedger::pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function stockMgmtHolderNames(): array
    {
        return StockManagement::pluck('name')->unique()->sort()->values()->all();
    }

    // ---------- Month switching ----------
    public function setMonth(string $month): void
    {
        $this->activeMonth = $month;
    }

    public function prevMonth(): void
    {
        $d = \DateTime::createFromFormat('Y-m', $this->activeMonth);
        $d->modify('-1 month');
        $this->activeMonth = $d->format('Y-m');
    }

    public function nextMonth(): void
    {
        $d = \DateTime::createFromFormat('Y-m', $this->activeMonth);
        $d->modify('+1 month');
        $this->activeMonth = $d->format('Y-m');
    }

    public function monthLabel(): string
    {
        $d = \DateTime::createFromFormat('Y-m', $this->activeMonth);
        $names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $names[(int) $d->format('n') - 1].' '.$d->format('Y');
    }

    private function monthRange(): array
    {
        $start = $this->activeMonth.'-01';
        $end = \DateTime::createFromFormat('Y-m-d', $start)->modify('last day of this month')->format('Y-m-d');

        return [$start, $end];
    }

    // ---------- Transaksi Harian ----------
    public function saveTransaksi(): void
    {
        $this->validate([
            'txName' => 'required|string|max:255',
            'txDate' => 'required|date',
        ], ['txName.required' => 'Isi nama pelanggan dulu.']);

        $items = [];
        foreach ($this->txQty as $productId => $qty) {
            if ($qty && (float) $qty > 0) {
                $product = Product::find($productId);
                if ($product) {
                    $items[] = ['product' => $product, 'qty' => (float) $qty];
                }
            }
        }

        if (empty($items)) {
            $this->addError('txQty', 'Isi jumlah minimal satu produk.');

            return;
        }

        $rawTotal = (int) round(array_sum(array_map(fn ($i) => $i['qty'] * $i['product']->price, $items)));
        $roundedTotal = self::roundTotal($rawTotal);
        $paid = (int) ($this->txPaid ?? $roundedTotal);
        $diff = $roundedTotal - $paid;
        $date = $this->txDate;
        $name = $this->txName;
        $note = $this->txNote;
        DB::transaction(function () use ($items, $rawTotal, $roundedTotal, $paid, $diff, $date, $name, $note) {
            if ($this->editingSaleId) {
                $sale = Sale::find($this->editingSaleId);
                if (! $sale) {
                    $this->addError('txName', 'Transaksi tidak ditemukan.');

                    return;
                }
                $sale->update([
                    'date' => $date, 'name' => $name, 'raw_total' => $rawTotal,
                    'rounded_total' => $roundedTotal, 'paid' => $paid, 'diff' => $diff,
                    'note' => $note ?: null,
                    'is_paid_btn_clicked' => $this->isPaymentFlow ? true : $sale->is_paid_btn_clicked,
                ]);
                SaleItem::where('sale_id', $sale->id)->delete();
                CustomerLedger::where('sale_id', $sale->id)->delete();
            } else {
                $sale = Sale::create([
                    'date' => $date, 'name' => $name, 'raw_total' => $rawTotal,
                    'rounded_total' => $roundedTotal, 'paid' => $paid, 'diff' => $diff,
                    'note' => $note ?: null,
                ]);
            }

            foreach ($items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'name' => $item['product']->name,
                    'qty' => $item['qty'],
                    'price' => $item['product']->price,
                ]);
            }

            if ($diff > 0) {
                CustomerLedger::create([
                    'date' => $date, 'name' => $name, 'amount' => $diff,
                    'type' => 'tambah',
                    'note' => 'Kekurangan bayar transaksi'.($note ? ' — '.$note : ''),
                    'sale_id' => $sale->id,
                ]);
            } elseif ($diff < 0) {
                CustomerLedger::create([
                    'date' => $date, 'name' => $name, 'amount' => -$diff,
                    'type' => 'bayar',
                    'note' => 'Kelebihan bayar transaksi'.($note ? ' — '.$note : ''),
                    'sale_id' => $sale->id,
                ]);
            }
        });

        $this->resetTxForm();
    }

    public function loadSaleForPayment(int $saleId): void
    {
        $sale = Sale::with('items')->find($saleId);
        if (! $sale) {
            return;
        }

        $this->editingSaleId = $saleId;
        $this->txDate = $sale->date->format('Y-m-d');
        $this->txName = $sale->name;
        $this->txNote = $sale->note ?? '';
        $this->txPaid = $sale->paid;
        $this->txPaidTouched = true;

        foreach (array_keys($this->txQty) as $pid) {
            $this->txQty[$pid] = null;
        }
        foreach ($sale->items as $item) {
            if (array_key_exists($item->product_id, $this->txQty)) {
                $this->txQty[$item->product_id] = (float) $item->qty;
            }
        }
    }

    public function markPaidBtnClicked(int $saleId): void
    {
        Sale::where('id', $saleId)->update(['is_paid_btn_clicked' => true]);
    }

    public function resetTxForm(): void
    {
        $this->editingSaleId = null;
        $this->isPaymentFlow = false;
        $this->txName = '';
        $this->txNote = '';
        $this->txPaid = null;
        $this->txPaidTouched = false;
        foreach (array_keys($this->txQty) as $pid) {
            $this->txQty[$pid] = null;
        }
    }

    public function deleteTransaksi(int $saleId): void
    {
        Sale::where('id', $saleId)->delete();
        if ($this->editingSaleId === $saleId) {
            $this->resetTxForm();
        }
    }

    public function dayTransactions()
    {
        return Sale::where('date', $this->txDate)->with('items')->orderBy('id')->get();
    }

    public function monthSales()
    {
        [$start, $end] = $this->monthRange();

        return Sale::whereBetween('date', [$start, $end])->with('items')->orderBy('date')->orderBy('id')->get();
    }

    public function recapPerProduct(): array
    {
        $recap = [];
        foreach ($this->dayTransactions() as $sale) {
            foreach ($sale->items as $item) {
                $recap[$item->name] = ($recap[$item->name] ?? 0) + (float) $item->qty;
            }
        }

        return $recap;
    }

    public function recapBulanPerProduct(): array
    {
        $recap = [];
        foreach ($this->monthSales() as $sale) {
            foreach ($sale->items as $item) {
                $recap[$item->name] = ($recap[$item->name] ?? 0) + (float) $item->qty;
            }
        }
        ksort($recap);

        return $recap;
    }

    public function recapTotals(): array
    {
        $sales = $this->dayTransactions();
        $todayExp = Expense::where('date', $this->txDate)->get();

        return [
            'kg' => (float) $sales->flatMap->items->sum('qty'),
            'dibayar' => (int) $sales->sum('paid'),
            'pengeluaran' => (int) $todayExp->sum('amount'),
            'kas_bersih' => (int) $sales->sum('paid') - (int) $todayExp->sum('amount'),
        ];
    }

    public function recapBulanTotals(): array
    {
        $sales = $this->monthSales();
        $monthExp = Expense::whereBetween('date', $this->monthRange())->get();

        return [
            'kg' => (float) $sales->flatMap->items->sum('qty'),
            'dibayar' => (int) $sales->sum('paid'),
            'pengeluaran' => (int) $monthExp->sum('amount'),
            'kas_bersih' => (int) $sales->sum('paid') - (int) $monthExp->sum('amount'),
        ];
    }

    // ---------- Pengeluaran ----------
    public function saveExpense(): void
    {
        $amount = (int) ($this->expAmount ?? 0);
        if ($amount <= 0) {
            $this->addError('expAmount', 'Isi jumlah uang yang diambil.');

            return;
        }
        Expense::create([
            'date' => $this->expDate ?: now()->toDateString(),
            'amount' => $amount,
            'note' => $this->expNote ?: null,
        ]);
        $this->expAmount = null;
        $this->expNote = '';
    }

    public function deleteExpense(int $id): void
    {
        Expense::where('id', $id)->delete();
    }

    public function dayExpenses()
    {
        $date = $this->expDate ?: now()->toDateString();

        return Expense::where('date', $date)->orderBy('id')->get();
    }

    // ---------- Master Produk ----------
    public function addProduct(): void
    {
        $this->validate(['prodName' => 'required|string|max:255'], ['prodName.required' => 'Isi nama produk dulu.']);
        Product::create(['name' => $this->prodName, 'price' => (int) ($this->prodPrice ?? 0)]);
        $this->prodName = '';
        $this->prodPrice = null;
        $this->syncTxQtyKeys();
    }

    public function updateProductPrice(int $productId, $price): void
    {
        Product::where('id', $productId)->update(['price' => (int) ($price ?? 0)]);
    }

    public function deleteProduct(int $productId): void
    {
        Product::where('id', $productId)->delete();
        unset($this->txQty[$productId]);
    }

    public function seedProducts(): void
    {
        $names = ['Kuning', 'Lempeng', 'Kabur', 'Flowning', 'Gelung', 'Kecil OM', 'Kecil MJ', 'PJO', 'Kecil 2', 'TG', 'TG Mini', 'Drg', 'Lj', 'Uren', 'TM'];
        $existing = Product::pluck('name')->map(fn ($n) => strtolower($n))->all();
        foreach ($names as $name) {
            if (! in_array(strtolower($name), $existing, true)) {
                Product::create(['name' => $name, 'price' => 0]);
            }
        }
        $this->syncTxQtyKeys();
    }

    private function syncTxQtyKeys(): void
    {
        $ids = Product::pluck('id')->all();
        foreach ($ids as $id) {
            if (! array_key_exists($id, $this->txQty)) {
                $this->txQty[$id] = null;
            }
        }
        foreach (array_keys($this->txQty) as $id) {
            if (! in_array($id, $ids, true)) {
                unset($this->txQty[$id]);
            }
        }
        if (! $this->remainProductId || ! Product::find($this->remainProductId)) {
            $this->remainProductId = Product::orderBy('name')->first()?->id;
            $this->syncRemainPrice();
        }
    }

    // ---------- Stok Minyak ----------
    public function addOil(): void
    {
        if (! $this->oilQty) {
            $this->addError('oilQty', 'Isi jumlah dulu.');

            return;
        }
        OilStock::create(['date' => $this->oilDate ?: now()->toDateString(), 'qty' => $this->oilQty, 'price' => (int) ($this->oilPrice ?? 0)]);
        $this->oilQty = null;
        $this->oilPrice = null;
    }

    public function deleteOil(int $id): void
    {
        OilStock::where('id', $id)->delete();
    }

    public function monthOilStocks()
    {
        [$start, $end] = $this->monthRange();

        return OilStock::whereBetween('date', [$start, $end])->orderBy('id')->get();
    }

    // ---------- Manajemen Stok Barang ----------
    public function addStockMgmt(): void
    {
        $name = trim($this->stockMgmtName);
        if (! $name) {
            $this->addError('stockMgmtName', 'Isi nama dulu.');

            return;
        }

        $sacks = json_decode($this->stockMgmtSacks ?: '[]', true);
        $sacks = array_values(array_filter($sacks, fn ($v) => (float) $v > 0));
        if (empty($sacks)) {
            $this->addError('stockMgmtSacks', 'Isi minimal satu karung.');

            return;
        }

        $qty = array_sum($sacks);
        $date = $this->stockMgmtDate ?: now()->toDateString();
        $price = (int) ($this->stockMgmtPrice ?? 0);

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

        $this->stockMgmtName = '';
        $this->stockMgmtPrice = null;
        $this->stockMgmtSacks = '[]';
    }

    public function deleteStockMgmt(int $id): void
    {
        StockManagement::where('id', $id)->delete();
    }

    public function deleteStockBatch(int $itemId, string $batchId): void
    {
        $item = StockManagement::find($itemId);
        if (! $item) {
            return;
        }
        $batches = collect($item->batches ?? [])->reject(fn ($b) => ($b['id'] ?? '') === $batchId)->values()->all();
        if (empty($batches)) {
            $item->delete();
        } else {
            $item->update(['batches' => $batches]);
        }
    }

    public function monthStockMgmts()
    {
        [$start, $end] = $this->monthRange();

        return StockManagement::whereBetween('date', [$start, $end])->orWhereNull('date')->orderBy('id')->get();
    }

    // ---------- Sisa Barang ----------
    public function updatedRemainProductId(): void
    {
        $this->syncRemainPrice();
    }

    private function syncRemainPrice(): void
    {
        $product = Product::find($this->remainProductId);
        $this->remainPrice = $product?->price ?? 0;
    }

    public function addRemain(): void
    {
        $product = Product::find($this->remainProductId);
        if (! $product) {
            $this->addError('remainProductId', 'Tambahkan produk dulu di Master Produk.');

            return;
        }
        if (! $this->remainQty) {
            $this->addError('remainQty', 'Isi jumlah dulu.');

            return;
        }
        StockRemaining::create([
            'date' => $this->remainDate ?: now()->toDateString(),
            'name' => $product->name,
            'qty' => $this->remainQty,
            'price' => (int) ($this->remainPrice ?? $product->price),
        ]);
        $this->remainQty = null;
    }

    public function deleteRemain(int $id): void
    {
        StockRemaining::where('id', $id)->delete();
    }

    public function monthStockRemainings()
    {
        [$start, $end] = $this->monthRange();

        return StockRemaining::whereBetween('date', [$start, $end])->orderBy('id')->get();
    }

    // ---------- Hutang Pelanggan (LIFO) ----------
    public function processCustomerDebts(string $name): array
    {
        $entries = CustomerLedger::where('name', $name)->orderBy('date')->orderBy('id')->get();
        $debts = [];
        $deposit = 0;

        foreach ($entries as $l) {
            if ($l->type === 'tambah') {
                $debts[] = ['id' => $l->id, 'amount' => $l->amount, 'remaining' => $l->amount, 'date' => $l->date->format('Y-m-d')];
            } else {
                $payment = $l->amount;
                for ($i = count($debts) - 1; $i >= 0 && $payment > 0; $i--) {
                    $cut = min($payment, $debts[$i]['remaining']);
                    $debts[$i]['remaining'] -= $cut;
                    $payment -= $cut;
                }
                if ($payment > 0) {
                    $deposit += $payment;
                }
            }
        }

        $activeDebts = array_values(array_filter($debts, fn ($d) => $d['remaining'] > 0));
        $totalSisa = (int) array_sum(array_column($activeDebts, 'remaining'));

        return compact('activeDebts', 'totalSisa', 'deposit');
    }

    public function hpNames(): array
    {
        return CustomerLedger::select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn ($name) => $this->processCustomerDebts($name)['totalSisa'] > 0)
            ->values()
            ->all();
    }

    public function hpDetailEntries(string $name)
    {
        $entries = CustomerLedger::where('name', $name)->orderBy('date')->orderBy('id')->get();
        $running = 0;

        return $entries->map(function ($l) use (&$running) {
            $running += $l->type === 'tambah' ? $l->amount : -$l->amount;

            return (object) [
                'id' => $l->id,
                'date' => $l->date->format('Y-m-d'),
                'type' => $l->type,
                'amount' => $l->amount,
                'running' => $running,
                'note' => $l->note,
                'sale_id' => $l->sale_id,
            ];
        });
    }

    public function addHpRow(): void
    {
        $this->debtModalOpen = true;
        $this->debtModalName = '';
        $this->debtModalAmount = null;
        $this->debtModalNote = '';
    }

    public function addHpForCustomer(string $name): void
    {
        $this->debtModalOpen = true;
        $this->debtModalName = $name;
        $this->debtModalAmount = null;
        $this->debtModalNote = '';
    }

    public function saveDebtModal(): void
    {
        $this->validate([
            'debtModalName' => 'required|string|max:255',
            'debtModalAmount' => 'required|integer|min:1',
        ], [
            'debtModalName.required' => 'Isi nama pelanggan.',
            'debtModalAmount.required' => 'Isi jumlah hutang.',
        ]);

        CustomerLedger::create([
            'date' => $this->debtModalDate ?: now()->toDateString(),
            'name' => $this->debtModalName,
            'amount' => $this->debtModalAmount,
            'type' => 'tambah',
            'note' => $this->debtModalNote ?: 'Tambah hutang manual',
        ]);

        $this->debtModalOpen = false;
        $this->debtModalName = '';
        $this->debtModalAmount = null;
        $this->debtModalNote = '';
    }

    public function deleteLedgerEntry(int $id): void
    {
        CustomerLedger::where('id', $id)->delete();
    }

    public function deleteCustomerLedger(string $name): void
    {
        CustomerLedger::where('name', $name)->delete();
    }

    public function adjustDebtCell(int $debtId, int $newRemaining, int $originalAmount): void
    {
        $entry = CustomerLedger::find($debtId);
        if (! $entry) {
            return;
        }
        $paidPortion = $originalAmount - $newRemaining;
        $entry->update(['amount' => max($newRemaining + $paidPortion, 0)]);
    }

    public function adjustTotalDebt(string $name, int $newTotal): void
    {
        $current = $this->processCustomerDebts($name)['totalSisa'];
        $diff = $newTotal - $current;
        if ($diff !== 0) {
            CustomerLedger::create([
                'date' => now()->toDateString(),
                'name' => $name,
                'amount' => abs($diff),
                'type' => $diff > 0 ? 'tambah' : 'bayar',
                'note' => 'Penyesuaian manual saldo hutang',
            ]);
        }
    }

    // ---------- Hutang Pribadi ----------
    public function addHutangPribadi(): void
    {
        $this->validate([
            'hprName' => 'required|string|max:255',
            'hprAmount' => 'required|integer|min:1',
        ], ['hprName.required' => 'Isi nama dulu.', 'hprAmount.required' => 'Isi jumlah dulu.']);

        PersonalLedger::create([
            'date' => $this->hprDate ?: now()->toDateString(),
            'name' => $this->hprName,
            'amount' => $this->hprAmount,
            'note' => $this->hprNote ?: null,
        ]);

        $this->hprName = '';
        $this->hprAmount = null;
        $this->hprNote = '';
    }

    public function deletePersonalLedger(int $id): void
    {
        PersonalLedger::where('id', $id)->delete();
    }

    public function monthPersonalLedgers()
    {
        [$start, $end] = $this->monthRange();

        return PersonalLedger::whereBetween('date', [$start, $end])->orderBy('date')->orderBy('id')->get();
    }

    // ---------- Pengurangan Saldo ----------
    public function saveSaldo(): void
    {
        SaldoDeduction::create([
            'date' => $this->saldoDate ?: now()->toDateString(),
            'a' => (int) ($this->saldoA ?? 0),
            'b' => (int) ($this->saldoB ?? 0),
            'note' => $this->saldoNote ?: null,
        ]);
        $this->saldoA = null;
        $this->saldoB = null;
        $this->saldoNote = '';
    }

    public function deleteSaldo(int $id): void
    {
        SaldoDeduction::where('id', $id)->delete();
    }

    public function monthSaldoLogs()
    {
        [$start, $end] = $this->monthRange();

        return SaldoDeduction::whereBetween('date', [$start, $end])->orderBy('date')->orderBy('id')->get();
    }

    // ---------- Ringkasan ----------
    public function ringkasan(): array
    {
        [$start, $end] = $this->monthRange();

        $totalOil = OilStock::whereBetween('date', [$start, $end])->get()->sum(fn ($o) => $o->qty * $o->price);
        $totalStockMgmt = StockManagement::whereBetween('date', [$start, $end])->orWhereNull('date')->get()->sum(fn ($o) => $o->subtotal());
        $totalRemain = StockRemaining::whereBetween('date', [$start, $end])->get()->sum(fn ($o) => $o->qty * $o->price);
        $balances = $this->customerBalances();
        $totalHutangPel = array_sum(array_map(fn ($b) => $b > 0 ? $b : 0, $balances));
        $totalHutangPri = (int) PersonalLedger::whereBetween('date', [$start, $end])->sum('amount');
        $totalSaldo = (int) SaldoDeduction::whereBetween('date', [$start, $end])->get()->sum(fn ($s) => $s->result());
        $grand = $totalOil + $totalStockMgmt + $totalHutangPel + $totalRemain;

        // Daily
        $selDate = $this->txDate;
        $isSelMonth = str_starts_with($selDate, $this->activeMonth);
        $daily = $isSelMonth ? [
            'oil' => OilStock::where('date', $selDate)->get()->sum(fn ($o) => $o->qty * $o->price),
            'stockMgmt' => StockManagement::where('date', $selDate)->orWhereNull('date')->get()->sum(fn ($o) => $o->subtotal()),
            'remain' => StockRemaining::where('date', $selDate)->get()->sum(fn ($o) => $o->qty * $o->price),
            'hutangPel' => CustomerLedger::where('date', $selDate)->get()->sum(fn ($l) => $l->type === 'tambah' ? $l->amount : -$l->amount),
            'hutangPri' => (int) PersonalLedger::where('date', $selDate)->sum('amount'),
            'saldo' => SaldoDeduction::where('date', $selDate)->get()->sum(fn ($s) => $s->result()),
        ] : null;

        return compact('totalOil', 'totalStockMgmt', 'totalRemain', 'totalHutangPel', 'totalHutangPri', 'totalSaldo', 'grand', 'daily', 'isSelMonth', 'selDate');
    }

    // ---------- Reset ----------
    public function resetMonth(): void
    {
        [$start, $end] = $this->monthRange();
        Sale::whereBetween('date', [$start, $end])->delete();
        Expense::whereBetween('date', [$start, $end])->delete();
        OilStock::whereBetween('date', [$start, $end])->delete();
        StockManagement::whereBetween('date', [$start, $end])->delete();
        StockRemaining::whereBetween('date', [$start, $end])->delete();
        PersonalLedger::whereBetween('date', [$start, $end])->delete();
        SaldoDeduction::whereBetween('date', [$start, $end])->delete();
    }

    public function resetAll(): void
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

        $this->txQty = [];
        $this->txName = '';
        $this->txNote = '';
        $this->txPaid = null;
        $this->txPaidTouched = false;
        $this->prodName = '';
        $this->prodPrice = null;
    }

    // ---------- Render ----------
    public function render()
    {
        $products = Product::orderBy('name')->get();
        $sales = $this->dayTransactions();
        $dayExpenses = $this->dayExpenses();
        $recapPerProduct = $this->recapPerProduct();
        $recapTotals = $this->recapTotals();
        $recapBulanPerProduct = $this->recapBulanPerProduct();
        $recapBulanTotals = $this->recapBulanTotals();
        $oilStocks = $this->monthOilStocks();
        $stockMgmts = $this->monthStockMgmts();
        $stockRemainings = $this->monthStockRemainings();
        $customerBalances = $this->customerBalances();
        $hpNames = $this->hpNames();
        $personalLedgers = $this->monthPersonalLedgers();
        $saldoLogs = $this->monthSaldoLogs();
        $ringkasanData = $this->tab === 'ringkasan' ? $this->ringkasan() : null;
        $allCustomerNames = $this->customerNames();
        $stockMgmtHolderNames = $this->stockMgmtHolderNames();

        return view('livewire.pos.pos-app', compact(
            'products', 'sales', 'dayExpenses', 'recapPerProduct', 'recapTotals',
            'recapBulanPerProduct', 'recapBulanTotals',
            'oilStocks', 'stockMgmts', 'stockRemainings',
            'customerBalances', 'hpNames', 'personalLedgers', 'saldoLogs',
            'ringkasanData', 'allCustomerNames', 'stockMgmtHolderNames'
        ));
    }
}
