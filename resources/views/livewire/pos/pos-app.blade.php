<div id="pos-root">
    <nav class="tabs">
        <button type="button" class="tab-btn {{ $tab === 'transaksi' ? 'active' : '' }}" wire:click="$set('tab', 'transaksi')">Transaksi Harian</button>
        <button type="button" class="tab-btn {{ $tab === 'produk' ? 'active' : '' }}" wire:click="$set('tab', 'produk')">Master Produk</button>
        <button type="button" class="tab-btn {{ $tab === 'hasil' ? 'active' : '' }}" wire:click="$set('tab', 'hasil')">Hasil</button>
        <button type="button" class="tab-btn {{ $tab === 'ringkasan' ? 'active' : '' }}" wire:click="$set('tab', 'ringkasan')">Ringkasan</button>
        <div class="month-switch">
            <button type="button" class="month-nav-btn" wire:click="prevMonth" @if($activeMonth <= '2020-01') disabled @endif>&larr;</button>
            <label style="margin:0;">Bulan aktif</label>
            <input type="month" wire:model.live="activeMonth">
            <span class="month-label">{{ $this->monthLabel() }}</span>
            <button type="button" class="month-nav-btn" wire:click="nextMonth">&rarr;</button>
        </div>
    </nav>

    <datalist id="customerNames">
        @foreach ($allCustomerNames as $n)
            <option value="{{ $n }}"></option>
        @endforeach
    </datalist>

    <main>
        {{-- ===================== TRANSAKSI HARIAN ===================== --}}
        <div class="tab-panel {{ $tab === 'transaksi' ? 'active' : '' }}" id="tab-transaksi">
            <div class="tx-layout">
                {{-- LEFT: Transaction Form --}}
                <div class="tx-main">
                    <div class="card tx-form-card" x-data x-on:scroll-to-form.window="$el.scrollIntoView({behavior:'smooth',block:'start'})">
                        <div class="tx-form-head">
                            <h2>Transaksi Baru</h2>
                            <input type="date" wire:model.live="txDate" id="txDate">
                        </div>
                        <div class="tx-form-body">
                            <div class="tx-customer-row">
                                <div class="tx-name-field">
                                    <label for="txName">Nama pelanggan</label>
                                    <input type="text" id="txName" wire:model.live="txName" list="customerNames" placeholder="Cari nama...">
                                    @error('txName') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                @php $bal = $this->customerBalanceFor($txName); @endphp
                                <div class="tx-debt-pill {{ $bal > 0 ? 'debt' : ($bal < 0 ? 'credit' : 'zero') }}" id="txOldDebtHint">
                                    @if($txName)
                                        @if($bal > 0) Hutang {{ rupiah($bal) }}
                                        @elseif($bal < 0) Deposit {{ rupiah(-$bal) }}
                                        @else Lunas @endif
                                    @else Lunas @endif
                                </div>
                            </div>

                            <div id="qtyGridWrap" style="margin-top:14px;">
                                <label style="font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);">Jumlah per produk (kg)</label>
                                @if ($products->isEmpty())
                                    <div class="empty">Tambahkan produk dulu di tab Master Produk.</div>
                                @else
                                    <div class="tx-product-grid" id="qtyGrid">
                                        @foreach ($products as $product)
                                            @php $qtyVal = $this->txQty[$product->id] ?? 0; @endphp
                                            <div class="product-card {{ $qtyVal > 0 ? 'has-qty' : '' }}" wire:key="product-{{ $product->id }}">
                                                <div class="product-card-name">{{ $product->name }}</div>
                                                <div class="product-card-input">
                                                    <button type="button" class="qty-btn" wire:click="decrementQty({{ $product->id }})">&minus;</button>
                                                    <input type="text" inputmode="decimal" step="0.5"
                                                           x-data="{ pid: {{ $product->id }}, qty: '' }"
                                                           x-init="
                                                               qty = '{{ $qtyVal > 0 ? str_replace('.', ',', (string) $qtyVal) : '' }}';
                                                               $wire.$watch('txQty.' + pid, val => {
                                                                   if (val !== undefined && val !== null && val !== '' && val != 0) {
                                                                       let f = String(val).replace('.', ',');
                                                                       if (f.endsWith(',0')) f = f.slice(0, -2);
                                                                       qty = f;
                                                                   } else { qty = ''; }
                                                               });
                                                           "
                                                           x-model="qty"
                                                           x-on:input.debounce.400ms="$wire.set('txQty.' + pid, qty.replace(',', '.'))"
                                                           class="qty-input" placeholder="0">
                                                    <button type="button" class="qty-btn" wire:click="incrementQty({{ $product->id }})">+</button>
                                                </div>
                                                @if($product->price && $qtyVal > 0)
                                                    <div class="product-card-subtotal">{{ rupiah($product->price * $qtyVal) }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('txQty') <div class="field-error">{{ $message }}</div> @enderror
                                @endif
                            </div>

                            <div class="tx-totals-bar">
                                <div class="tx-total-stat">
                                    <div class="label">Total kg</div>
                                    <div class="value">{{ collect($this->txQty)->filter()->sum() }} kg</div>
                                </div>
                                <div class="tx-total-stat">
                                    <div class="label">Belanja (asli)</div>
                                    <div class="value">{{ rupiah($this->txRawTotal()) }}</div>
                                </div>
                                <div class="tx-total-stat highlight">
                                    <div class="label">Tagihan</div>
                                    <div class="value">{{ rupiah($this->txRoundedTotal()) }}</div>
                                </div>
                            </div>

                            <div class="tx-payment-row">
                                <div class="tx-paid-group">
                                    <label for="txPaid">Dibayar (Rp)</label>
                                    <div class="input-group">
                                        <input type="number" id="txPaid" min="0" step="500" wire:model.live.debounce.400ms="txPaid" placeholder="0">
                                        <button type="button" class="pas-btn" wire:click="fillLunas">Pas</button>
                                    </div>
                                </div>
                                <div class="tx-status-group">
                                    <label>Status</label>
                                    @php $diff = $this->txDiff(); @endphp
                                    <div class="status-box {{ !$txPaidTouched ? 'debt' : ($diff > 0 ? 'debt' : ($diff < 0 ? 'paid' : 'zero')) }}">
                                        @if(!$txPaidTouched) Belum dibayar: {{ rupiah($this->txRoundedTotal()) }}
                                        @elseif($diff > 0) Kurang {{ rupiah($diff) }}
                                        @elseif($diff < 0) Lebih {{ rupiah(-$diff) }}
                                        @else Lunas &#10003; @endif
                                    </div>
                                </div>
                            </div>
                            <p class="note" style="margin:-6px 0 0;font-size:14px;">Dibayar &lt; tagihan &rarr; hutang bertambah. &gt; &rarr; potong hutang. <b>Pas</b> = bayar pas sesuai tagihan.</p>

                            <div class="tx-note" style="margin-top:12px;">
                                <input type="text" wire:model="txNote" placeholder="Catatan (opsional)">
                            </div>

                            @if($editingSaleId)
                                @php $editSale = $sales->firstWhere('id', $editingSaleId); @endphp
                                @if($editSale)
                                    <div class="tx-edit-banner">
                                        Mode bayar: <strong>{{ $editSale->name }}</strong> &mdash; {{ $editSale->date->format('Y-m-d') }}
                                        <button type="button" class="ghost" wire:click="resetTxForm" style="margin-left:auto;">Batal</button>
                                    </div>
                                @endif
                            @endif

                            <div class="tx-actions">
                                <button type="button" class="primary" wire:click="saveTransaksi">{{ $editingSaleId ? 'Simpan pembayaran' : 'Simpan transaksi' }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT COLUMN (expenses + recaps) --}}
                <div class="tx-sidebar">

                    <div class="card">
                        <h2>Pengeluaran Laci</h2>
                        <div class="tx-expense-form">
                            <input type="number" wire:model="expAmount" min="0" step="500" placeholder="Jumlah (Rp)" style="flex:2;">
                            <input type="text" wire:model="expNote" placeholder="Keperluan" style="flex:3;">
                            <button type="button" class="primary" wire:click="saveExpense" style="background:var(--ink);font-size:15px;padding:12px 16px;">Simpan</button>
                        </div>
                        @error('expAmount') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="table-wrap">
                            <table class="tx-table" id="expTable">
                                <thead><tr><th>Keperluan</th><th class="num">Jumlah</th><th></th></tr></thead>
                                <tbody>
                                    @forelse ($dayExpenses as $e)
                                        <tr wire:key="exp-{{ $e->id }}">
                                            <td>{{ $e->note ?? '-' }}</td>
                                            <td class="num">{{ rupiah($e->amount) }}</td>
                                            <td style="text-align:right;"><button type="button" class="ghost danger" wire:click="deleteExpense({{ $e->id }})" style="font-size:13px;padding:6px 10px;">Hapus</button></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="empty" style="padding:14px;font-size:15px;">Belum ada pengeluaran.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card" x-data="{ showRecap: true }">
                        <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;" @click="showRecap = !showRecap">
                            <h2 style="margin-bottom:0;">Rekap Hari Ini</h2>
                            <span style="font-size:13px;color:var(--ink-soft);" x-html="showRecap ? '&#9650;' : '&#9660;'"></span>
                        </div>
                        <div x-show="showRecap" style="margin-top:14px;">
                            <div class="tx-sidebar-stat-grid">
                                <div class="tx-sidebar-stat" style="grid-column:1/-1;">
                                    <div class="lbl">Kg terjual per produk</div>
                                    <div style="margin-top:6px;font-size:14px;">
                                        @forelse ($recapPerProduct as $prodName => $kg)
                                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                                <span>{{ $prodName }}</span>
                                                <span class="num">{{ fmtKg($kg) }} kg</span>
                                            </div>
                                        @empty
                                            <span class="num">0 kg</span>
                                        @endforelse
                                        <div style="display:flex;justify-content:space-between;padding:2px 0;border-top:1px solid var(--line);margin-top:4px;padding-top:6px;font-weight:700;">
                                            <span>Total</span>
                                            <span class="num">{{ fmtKg($recapTotals['kg']) }} kg</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="tx-sidebar-stat">
                                    <div class="lbl">Diterima</div>
                                    <div class="num green">{{ rupiah($recapTotals['dibayar']) }}</div>
                                </div>
                                <div class="tx-sidebar-stat">
                                    <div class="lbl">Pengeluaran</div>
                                    <div class="num red">&minus;{{ rupiah($recapTotals['pengeluaran']) }}</div>
                                </div>
                                <div class="tx-sidebar-stat">
                                    <div class="lbl">Kas bersih</div>
                                    <div class="num green">{{ rupiah($recapTotals['kas_bersih']) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" x-data="{ showBulan: true }">
                        <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;" @click="showBulan = !showBulan">
                            <h2 style="margin-bottom:0;">Rekap {{ $this->monthLabel() }}</h2>
                            <span style="font-size:13px;color:var(--ink-soft);" x-html="showBulan ? '&#9650;' : '&#9660;'"></span>
                        </div>
                        <div x-show="showBulan" style="margin-top:14px;">
                            <div class="tx-sidebar-stat-grid">
                                <div class="tx-sidebar-stat" style="grid-column:1/-1;">
                                    <div class="lbl">Kg terjual per produk</div>
                                    <div style="margin-top:6px;font-size:14px;">
                                        @forelse ($recapBulanPerProduct as $prodName => $kg)
                                            <div style="display:flex;justify-content:space-between;padding:2px 0;">
                                                <span>{{ $prodName }}</span>
                                                <span class="num">{{ fmtKg($kg) }} kg</span>
                                            </div>
                                        @empty
                                            <span class="num">0 kg</span>
                                        @endforelse
                                        <div style="display:flex;justify-content:space-between;padding:2px 0;border-top:1px solid var(--line);margin-top:4px;padding-top:6px;font-weight:700;">
                                            <span>Total</span>
                                            <span class="num">{{ fmtKg($recapBulanTotals['kg']) }} kg</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="tx-sidebar-stat">
                                    <div class="lbl">Diterima</div>
                                    <div class="num green">{{ rupiah($recapBulanTotals['dibayar']) }}</div>
                                </div>
                                <div class="tx-sidebar-stat">
                                    <div class="lbl">Pengeluaran</div>
                                    <div class="num red">&minus;{{ rupiah($recapBulanTotals['pengeluaran']) }}</div>
                                </div>
                                <div class="tx-sidebar-stat">
                                    <div class="lbl">Kas bersih</div>
                                    <div class="num green">{{ rupiah($recapBulanTotals['kas_bersih']) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- FULL WIDTH (riwayat transaksi below form, spanning both columns) --}}
                <div style="grid-column: 1 / -1;">
                    <div class="card">
                        <h2 style="font-size:24px;">Transaksi Hari Ini</h2>
                        <div class="table-wrap">
                            <table class="tx-table" id="txTable">
                                <thead><tr><th>Nama</th><th>Produk</th><th class="num">Kg</th><th class="num">Tagihan</th><th class="num">Dibayar</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    @forelse ($sales as $tx)
                                        <tr wire:key="sale-{{ $tx->id }}">
                                            <td>{{ $tx->name }}</td>
                                            <td style="font-size:19px;line-height:1.6;">
                                                @foreach($tx->items as $item)
                                                    <div>{{ $item->name }}: {{ fmtKg($item->qty) }} kg</div>
                                                @endforeach
                                            </td>
                                            <td class="num">{{ fmtKg($tx->items->sum('qty')) }}</td>
                                            <td class="num">{{ rupiah($tx->rounded_total) }}</td>
                                            <td class="num">{{ rupiah($tx->paid) }}</td>
                                            <td>
                                                @if($tx->diff > 0) <span class="badge debt">Kurang {{ rupiah($tx->diff) }}</span>
                                                @elseif($tx->diff < 0) <span class="badge paid">Lebih {{ rupiah(-$tx->diff) }}</span>
                                                @else <span class="badge zero">Lunas</span> @endif
                                            </td>
                                            <td class="row-actions">
                                                <button type="button" class="ghost" style="{{ $tx->is_paid_btn_clicked ? 'background:#E5E7EB;color:#6B7280;border:none;' : 'background:var(--paid);color:#FFF;border:none;font-weight:bold;' }}" wire:click="confirmPay({{ $tx->id }})">Bayar</button>
                                                <button type="button" class="ghost" wire:click="loadSaleForPayment({{ $tx->id }})" title="Edit transaksi">Edit</button>
                                                <button type="button" class="ghost" wire:click="$set('tab', 'hasil'); $set('hpDetailName', '{{ $tx->name }}')" title="Riwayat Hutang">Riwayat</button>
                                                <button type="button" class="ghost danger" wire:click="deleteTransaksi({{ $tx->id }})" wire:confirm="Hapus transaksi {{ $tx->name }} ini? Riwayat hutang tidak akan dihapus.">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="empty" style="padding:18px;font-size:15px;">Belum ada transaksi hari ini.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== MASTER PRODUK ===================== --}}
        <div class="tab-panel {{ $tab === 'produk' ? 'active' : '' }}" id="tab-produk">
            <div class="card">
                <h2>Tambah produk</h2>
                <div class="field-row">
                    <div class="field">
                        <label for="prodName">Nama produk</label>
                        <input type="text" id="prodName" wire:model="prodName" placeholder="Contoh: Kabur">
                        @error('prodName') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="prodPrice">Harga per kg (Rp)</label>
                        <input type="number" id="prodPrice" wire:model="prodPrice" min="0" step="500">
                    </div>
                </div>
                <button type="button" class="primary" wire:click="addProduct">Tambah produk</button>
                <button type="button" class="ghost" style="margin-left:8px;" wire:click="seedProducts">Isi contoh nama produk dari catatan</button>
            </div>
            <div class="card">
                <h2>Daftar produk</h2>
                <div class="table-wrap">
                    <table id="prodTable">
                        <thead><tr><th>Nama</th><th class="num">Harga / kg</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($products as $p)
                                <tr wire:key="prod-{{ $p->id }}">
                                    <td>{{ $p->name }}</td>
                                    <td class="num">{{ $p->price ? rupiah($p->price) : '<span class="warn-price">belum diatur</span>' }}</td>
                                    <td class="row-actions">
                                        <button type="button" class="ghost" wire:click="$set('updatePriceId', {{ $p->id }})" x-data x-on:click="$el.closest('tr').querySelector('.price-input').style.display='block'; $el.closest('tr').querySelector('.price-display').style.display='none'; $nextTick(()=>$el.closest('tr').querySelector('.price-input').focus())">Ubah harga</button>
                                        <button type="button" class="ghost danger" wire:click="deleteProduct({{ $p->id }})" wire:confirm="Hapus produk {{ $p->name }}?">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty">Belum ada produk. Tambahkan produk atau pakai tombol contoh di atas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ===================== HASIL ===================== --}}
        <div class="tab-panel {{ $tab === 'hasil' ? 'active' : '' }}" id="tab-hasil">
            {{-- 1. HUTANG PELANGGAN --}}
            <div class="section-header">
                <h2>1. Rekap Hutang Pelanggan</h2>
                <p>Rekap bersifat kumulatif (semua waktu). Setiap transaksi mencatat <strong>total tagihan</strong> sebagai hutang baru. Pembayaran memotong hutang yang paling kiri (terlama) terlebih dahulu (FIFO). Klik pada angka hutang untuk mengubahnya secara manual.</p>
            </div>

            <div class="card">
                <h2>Rekap hutang per pelanggan</h2>
                <div class="hp-toolbar">
                    <button type="button" class="primary" wire:click="addHpRow">+ Tambah hutang</button>
                </div>
                @php
                    $hpProcessed = [];
                    $maxCols = 1;
                    $hpGrandTotal = 0;
                    foreach ($hpNames as $name) {
                        $p = $this->processCustomerDebts($name);
                        $hpProcessed[$name] = $p;
                        if (count($p['activeDebts']) > $maxCols) $maxCols = count($p['activeDebts']);
                        $hpGrandTotal += $p['totalSisa'];
                    }
                @endphp
                <div class="table-wrap">
                    <table id="hpSummaryTable" style="white-space: nowrap;">
                        <thead>
                            @if(!empty($hpNames))
                                <tr>
                                    <th>Nama</th>
                                    @for($i = 0; $i < $maxCols; $i++)
                                        <th class="num mat-col">Hutang {{ $i+1 }}</th>
                                    @endfor
                                    <th></th>
                                    <th class="num">Total Hutang</th>
                                    <th></th>
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @forelse ($hpNames as $name)
                                @php $p = $hpProcessed[$name]; @endphp
                                <tr wire:key="hp-{{ $name }}">
                                    <td><strong>{{ $name }}</strong></td>
                                    @for($i = 0; $i < $maxCols; $i++)
                                        @if(isset($p['activeDebts'][$i]))
                                            @php $d = $p['activeDebts'][$i]; @endphp
                                            <td class="num mat-col" x-data="{ editing: false, val: {{ $d['remaining'] }} }">
                                                <span x-show="!editing" x-on:click="editing = true; $nextTick(()=>$el.nextElementSibling.focus())" style="cursor:pointer;" title="Klik untuk edit">{{ rupiah($d['remaining']) }}<br><span style="font-size:12px;color:var(--ink-soft);">{{ $d['date'] }}</span></span>
                                                <input x-show="editing" x-cloak type="number" x-model="val" min="0" step="500" style="width:110px;text-align:right;font-family:var(--mono);padding:6px 8px;"
                                                    x-on:blur="editing = false; $wire.adjustDebtCell({{ $d['id'] }}, val, {{ $d['amount'] }})"
                                                    x-on:keydown.enter="$el.blur()"
                                                    x-on:keydown.escape="editing = false">
                                            </td>
                                        @else
                                            <td class="mat-col empty-cell"></td>
                                        @endif
                                    @endfor
                                    <td style="text-align:center;"><button type="button" class="ghost" style="padding:6px 10px;" wire:click="addHpForCustomer('{{ $name }}')" title="Tambah hutang baru untuk {{ $name }}">+</button></td>
                                    <td class="num">
                                        <span class="badge debt" x-data="{ editing: false, val: {{ $p['totalSisa'] }} }">
                                            <span x-show="!editing" x-on:click="editing = true; $nextTick(()=>$el.nextElementSibling.focus())" style="cursor:pointer;" title="Klik untuk edit manual">{{ rupiah($p['totalSisa']) }}</span>
                                            <input x-show="editing" x-cloak type="number" x-model="val" min="0" step="500" style="width:120px;text-align:right;font-family:var(--mono);padding:6px 8px;"
                                                x-on:blur="editing = false; $wire.adjustTotalDebt('{{ $name }}', val)"
                                                x-on:keydown.enter="$el.blur()"
                                                x-on:keydown.escape="editing = false">
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="row-actions" style="justify-content:flex-end;">
                                            <button type="button" class="ghost" wire:click="$set('hpDetailName', '{{ $name }}')">Riwayat</button>
                                            <button type="button" class="ghost danger" wire:click="deleteCustomerLedger('{{ $name }}')" wire:confirm="Hapus semua riwayat hutang milik '{{ $name }}'? Tindakan ini tidak bisa dibatalkan.">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty">Belum ada catatan hutang pelanggan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(!empty($hpNames))
                    <div class="summary-line total"><span>Total hutang pelanggan (piutang toko)</span><span class="val">{{ rupiah($hpGrandTotal) }}</span></div>
                @endif
            </div>

            @if($hpDetailName)
                <div class="card" id="hpDetailCard">
                    <h2 id="hpDetailTitle">Detail hutang &mdash; {{ $hpDetailName }}</h2>
                    @php $hpEntries = $this->hpDetailEntries($hpDetailName); @endphp
                    <div class="table-wrap">
                            <table id="hpDetailTable">
                                <thead><tr><th>Tanggal</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Dibayar</th><th class="num">Saldo berjalan</th><th>Catatan</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($hpEntries as $entry)
                                    <tr wire:key="hpd-{{ $entry->id }}">
                                        <td>{{ $entry->date }}</td>
                                        <td>{!! $entry->type === 'tambah' ? '<span style="color:var(--debt);">+ Tambah hutang</span>' : '<span style="color:var(--paid);">&minus; Bayar hutang</span>' !!}</td>
                                        <td class="num">{{ $entry->type === 'tambah' ? '+' : '-' }}{{ rupiah($entry->amount) }}</td>
                                        <td class="num">{{ $entry->sale_id && $entry->paid !== null ? rupiah($entry->paid) : '-' }}</td>
                                        <td class="num">{!! $entry->running > 0 ? rupiah($entry->running) : ($entry->running < 0 ? '&minus;'.rupiah(-$entry->running).' (deposit)' : 'Lunas (Rp0)') !!}</td>
                                        <td>{{ $entry->note ?? '' }}</td>
                                        <td class="row-actions">
                                            @if($entry->sale_id)
                                                <button type="button" class="ghost" wire:click="$set('tab', 'transaksi'); loadSaleForPayment({{ $entry->sale_id }})">Lihat Transaksi</button>
                                            @endif
                                            <button type="button" class="ghost danger" wire:click="deleteLedgerEntry({{ $entry->id }})">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="empty">Belum ada riwayat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="ghost" style="margin-top:8px;" wire:click="$set('hpDetailName', '')">Tutup detail</button>
                </div>
            @endif

            {{-- 2. STOK BARANG --}}
            <div class="section-header">
                <h2>2. Stok Barang</h2>
                <p>Data pada bagian ini menampilkan riwayat bulan aktif yang dipilih di bagian atas halaman.</p>
            </div>

            <div class="card sub-section">
                <h3>Stok minyak</h3>
                <div class="field-row">
                    <div class="field"><label>Tanggal</label><input type="date" wire:model="oilDate"></div>
                    <div class="field"><label>Jumlah (liter/kg)</label><input type="number" wire:model="oilQty" min="0" step="0.5"></div>
                    <div class="field"><label>Harga satuan (Rp)</label><input type="number" wire:model="oilPrice" min="0" step="500"></div>
                    <div class="field" style="flex:0; align-self:flex-end;"><button type="button" class="primary" wire:click="addOil">Tambah</button></div>
                </div>
                @error('oilQty') <div class="field-error">{{ $message }}</div> @enderror
                <div class="table-wrap">
                    <table id="oilTable"><thead><tr><th>Tanggal</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($oilStocks as $o)
                                <tr wire:key="oil-{{ $o->id }}">
                                    <td>{{ $o->date?->format('Y-m-d') ?? '-' }}</td>
                                    <td class="num">{{ fmtKg($o->qty) }}</td>
                                    <td class="num">{{ rupiah($o->price) }}</td>
                                    <td class="num">{{ rupiah($o->subtotal()) }}</td>
                                    <td><button type="button" class="ghost danger" wire:click="deleteOil({{ $o->id }})">Hapus</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">Belum ada data stok minyak.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="summary-line total"><span>Total nilai stok minyak</span><span class="val" id="oilTotal">{{ rupiah($oilStocks->sum(fn($o) => $o->subtotal())) }}</span></div>
            </div>

            <div class="card sub-section">
                <h3>Manajemen stok barang (per pemegang / gudang)</h3>
                <div class="field-row">
                    <div class="field"><label>Tanggal</label><input type="date" wire:model="stockMgmtDate"></div>
                    <div class="field"><label>Nama</label><input type="text" wire:model="stockMgmtName" placeholder="Contoh: gun, imam" list="stockHolderNames"></div>
                    <datalist id="stockHolderNames">
                        @foreach ($stockMgmtHolderNames as $n)
                            <option value="{{ $n }}"></option>
                        @endforeach
                    </datalist>
                    <div class="field"><label>Harga per kg (Rp)</label><input type="number" wire:model="stockMgmtPrice" min="0" step="500"></div>
                </div>
                @error('stockMgmtName') <div class="field-error">{{ $message }}</div> @enderror
                <div class="sub-section" style="margin-top:10px;">
                    <label>Kg per karung (boleh lebih dari satu karung)</label>
                    <div id="stockMgmtSackList" x-data="{ sacks: [] }">
                        <template x-for="(sack, index) in sacks" :key="index">
                            <div class="sack-row">
                                <input type="number" min="0" step="0.5" x-model="sacks[index]" class="sack-qty-input" placeholder="kg karung" @input.debounce="updateSacks">
                                <button type="button" class="ghost danger sack-remove-btn" @click="sacks.splice(index,1); if(sacks.length===0) sacks.push(''); updateSacks()">Hapus</button>
                            </div>
                        </template>
                        <button type="button" class="ghost" style="margin-top:4px;" @click="sacks.push(''); updateSacks()">+ Tambah karung</button>
                    </div>
                    @error('stockMgmtSacks') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                @error('stockMgmtSacks') <div class="field-error">{{ $message }}</div> @enderror
                <button type="button" class="primary" style="margin-top:8px;" x-on:click="
                    const sacks = JSON.parse(JSON.stringify($el.closest('.card').querySelector('#stockMgmtSackList').__x.$data.sacks.filter(v => parseFloat(v) > 0)));
                    $wire.set('stockMgmtSacks', JSON.stringify(sacks));
                    $wire.addStockMgmt();
                    $el.closest('.card').querySelector('#stockMgmtSackList').__x.$data.sacks = [''];"
                >Tambah ke stok</button>
                <div class="table-wrap" style="margin-top:14px;">
                    <table id="stockMgmtTable">
                        <thead><tr><th>Tanggal</th><th>Nama</th><th class="num">Total (kg)</th><th class="num">Harga</th><th class="num">Subtotal</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($stockMgmts as $o)
                                <tr wire:key="sm-{{ $o->id }}">
                                    <td>{{ $o->date?->format('Y-m-d') ?? '-' }}</td>
                                    <td>{{ $o->name }}</td>
                                    <td class="num">{{ $o->subtotal() > 0 ? round($o->qty) : round(StockManagement::totalQty($o->batches ?? [])) }} kg</td>
                                    <td class="num">{{ rupiah($o->price) }}</td>
                                    <td class="num">{{ rupiah($o->subtotal()) }}</td>
                                    <td class="row-actions">
                                        <button type="button" class="ghost" x-data x-on:click="$el.closest('tr').nextElementSibling?.classList.contains('detail-row') ? $el.closest('tr').nextElementSibling.remove() : $el.closest('table').querySelector('tbody').insertBefore(
                                            (()=>{const tr=document.createElement('tr');tr.className='detail-row';tr.innerHTML='<td colspan=\'6\'><div style=\'padding:10px;background:#FFFDF9;border:1px solid var(--line);border-radius:8px;\'><table style=\'font-size:15px;\'><thead><tr><th>Tanggal</th><th>Berat per karung</th><th class=num>Total kg</th><th class=num>Harga</th><th class=num>Subtotal</th><th></th></tr></thead><tbody>'+
                                            @json($o->batches ?? []).map(b=>{
                                                const sacks = (b.sacks||[]).map(k=>k+'kg').join(', ');
                                                const bq = (b.sacks||[]).reduce((s,k)=>s+k,0);
                                                const bp = b.price != null ? b.price : {{ $o->price }};
                                                const sub = bq * bp;
                                                return '<tr><td>'+(b.date||'')+'</td><td>'+sacks+'</td><td class=num>'+bq+' kg</td><td class=num>Rp{{ number_format($o->price,0,'','.') }}</td><td class=num>Rp'+(sub).toLocaleString('id-ID')+'</td><td><button class=\'ghost danger\' onclick=\'window.Livewire.find(\\''.addslashes($__livewire->id()).'\\').deleteStockBatch({{ $o->id }},\\\\''+b.id+'\\\\\')\'>Hapus</button></td></tr>';
                                            }).join('')+
                                            '</tbody></table></div></td>';return tr;})()
                                        , $el.closest('tr').nextSibling)">Detail karung</button>
                                        <button type="button" class="ghost danger" wire:click="deleteStockMgmt({{ $o->id }})">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="summary-line total" style="margin-top:10px;"><span>Total manajemen stok barang</span><span class="val" id="stockMgmtTotal">{{ rupiah($stockMgmts->sum(fn($o) => $o->subtotal())) }}</span></div>
            </div>

            <div class="card sub-section">
                <h3>Sisa barang hari ini</h3>
                <div class="field-row">
                    <div class="field"><label>Tanggal</label><input type="date" wire:model="remainDate"></div>
                    <div class="field">
                        <label>Produk</label>
                        <select wire:model.live="remainProductId">
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Jumlah (kg)</label><input type="number" wire:model="remainQty" min="0" step="0.5"></div>
                    <div class="field"><label>Harga (Rp)</label><input type="number" wire:model="remainPrice" min="0" step="500"></div>
                </div>
                @error('remainProductId') <div class="field-error">{{ $message }}</div> @enderror
                @error('remainQty') <div class="field-error">{{ $message }}</div> @enderror
                <button type="button" class="primary" wire:click="addRemain">Tambah</button>
                <div class="table-wrap" style="margin-top:10px;">
                    <table id="remainTable">
                        <thead><tr><th>Tanggal</th><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($stockRemainings as $o)
                                <tr wire:key="rem-{{ $o->id }}">
                                    <td>{{ $o->date?->format('Y-m-d') ?? '-' }}</td>
                                    <td>{{ $o->name }}</td>
                                    <td class="num">{{ fmtKg($o->qty) }}</td>
                                    <td class="num">{{ rupiah($o->price) }}</td>
                                    <td class="num">{{ rupiah($o->subtotal()) }}</td>
                                    <td><button type="button" class="ghost danger" wire:click="deleteRemain({{ $o->id }})">Hapus</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="summary-line total"><span>Total sisa barang hari ini</span><span class="val" id="remainTotal">{{ rupiah($stockRemainings->sum(fn($o) => $o->subtotal())) }}</span></div>
            </div>

            {{-- 3. HUTANG PRIBADI --}}
            <div class="section-header">
                <h2>3. Hutang Pribadi</h2>
                <p>Data hutang pribadi mengikuti bulan aktif yang dipilih di bagian atas halaman.</p>
            </div>

            <div class="card">
                <h2>Catat hutang pribadi</h2>
                <div class="field-row">
                    <div class="field"><label>Tanggal</label><input type="date" wire:model="hprDate"></div>
                    <div class="field"><label>Nama</label><input type="text" wire:model="hprName">
                        @error('hprName') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field"><label>Jumlah (Rp)</label><input type="number" wire:model="hprAmount" min="0" step="500">
                        @error('hprAmount') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="field"><label>Catatan (opsional)</label><input type="text" wire:model="hprNote"></div>
                <button type="button" class="primary" wire:click="addHutangPribadi">Simpan</button>
            </div>
            <div class="card">
                <h2>Daftar hutang pribadi (bulan ini)</h2>
                <div class="table-wrap">
                    <table id="hprTable">
                        <thead><tr><th>Tanggal</th><th>Nama</th><th class="num">Jumlah</th><th>Catatan</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($personalLedgers as $l)
                                <tr wire:key="hpr-{{ $l->id }}">
                                    <td>{{ $l->date->format('Y-m-d') }}</td>
                                    <td>{{ $l->name }}</td>
                                    <td class="num">{{ rupiah($l->amount) }}</td>
                                    <td>{{ $l->note ?? '' }}</td>
                                    <td><button type="button" class="ghost danger" wire:click="deletePersonalLedger({{ $l->id }})">Hapus</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">Belum ada catatan hutang pribadi bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="summary-line total"><span>Total hutang pribadi bulan ini</span><span class="val" id="hprGrandTotal">{{ rupiah($personalLedgers->sum('amount')) }}</span></div>
            </div>

            {{-- 4. PENGURANGAN SALDO --}}
            <div class="section-header">
                <h2>4. Pengurangan Saldo</h2>
                <p>Alat bantu hitung selisih saldo &mdash; angka bebas, terpisah dari perhitungan lain. Mengikuti bulan aktif.</p>
            </div>

            <div class="card">
                <h2>Tambah catatan pengurangan saldo</h2>
                <div class="field-row">
                    <div class="field"><label>Tanggal</label><input type="date" wire:model="saldoDate"></div>
                    <div class="field"><label>Angka A</label><input type="number" wire:model="saldoA" step="1"></div>
                    <div class="field"><label>Angka B</label><input type="number" wire:model="saldoB" step="1"></div>
                </div>
                <div class="field"><label>Catatan (opsional)</label><input type="text" wire:model="saldoNote"></div>
                <button type="button" class="primary" wire:click="saveSaldo">Simpan</button>
            </div>
            <div class="card">
                <h2>Daftar pengurangan saldo (bulan ini)</h2>
                <div class="table-wrap">
                    <table id="saldoTable">
                        <thead><tr><th>Tanggal</th><th class="num">A</th><th class="num">B</th><th class="num">Hasil (A&minus;B)</th><th>Catatan</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($saldoLogs as $l)
                                <tr wire:key="saldo-{{ $l->id }}">
                                    <td>{{ $l->date->format('Y-m-d') }}</td>
                                    <td class="num">{{ number_format($l->a, 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format($l->b, 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format($l->result(), 0, ',', '.') }}</td>
                                    <td>{{ $l->note ?? '' }}</td>
                                    <td><button type="button" class="ghost danger" wire:click="deleteSaldo({{ $l->id }})">Hapus</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">Belum ada catatan pengurangan saldo bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="summary-line total"><span>Total hasil (A&minus;B) bulan ini</span><span class="val" id="saldoGrandTotal">{{ number_format($saldoLogs->sum(fn($l) => $l->result()), 0, ',', '.') }}</span></div>
            </div>
        </div>

        {{-- ===================== RINGKASAN ===================== --}}
        <div class="tab-panel {{ $tab === 'ringkasan' ? 'active' : '' }}" id="tab-ringkasan">
            @if($ringkasanData)
                <div class="card" id="ringkasanHariIniCard">
                    <div class="field-row" style="align-items:end; margin-bottom:14px;">
                        <div style="flex:1;"><h2 style="margin-bottom:4px;">Ringkasan neraca & stok</h2><div class="hint" style="margin:0;">Pilih tanggal untuk melihat ringkasan harian.</div></div>
                        <div class="field" style="margin:0; min-width:190px;"><label for="ringkasanDate">Tanggal ringkasan</label><input type="date" id="ringkasanDate" wire:model.live="txDate"></div>
                    </div>
                    <h3 style="margin-bottom:14px;">Tanggal: <span id="ringkasanHariIniTanggal">{{ $ringkasanData['selDate'] }}</span></h3>
                    @if($ringkasanData['daily'] !== null)
                        <div class="stat-grid">
                            <div class="stat-box"><div class="label">Stok minyak (hari ini)</div><div class="value" id="hiOil">{{ rupiah($ringkasanData['daily']['oil']) }}</div></div>
                            <div class="stat-box"><div class="label">Manajemen stok (hari ini)</div><div class="value" id="hiStockMgmt">{{ rupiah($ringkasanData['daily']['stockMgmt']) }}</div></div>
                            <div class="stat-box"><div class="label">Sisa barang (hari ini)</div><div class="value" id="hiRemain">{{ rupiah($ringkasanData['daily']['remain']) }}</div></div>
                            <div class="stat-box"><div class="label">Hutang pelanggan (bertambah hari ini)</div><div class="value" id="hiHutangPel">{{ rupiah(max($ringkasanData['daily']['hutangPel'], 0)) }}</div></div>
                            <div class="stat-box"><div class="label">Hutang pribadi (hari ini)</div><div class="value" id="hiHutangPri">{{ rupiah($ringkasanData['daily']['hutangPri']) }}</div></div>
                            <div class="stat-box"><div class="label">Pengurangan saldo (hari ini)</div><div class="value" id="hiSaldo">{{ number_format($ringkasanData['daily']['saldo'], 0, ',', '.') }}</div></div>
                        </div>
                        @php $hiGrand = $ringkasanData['daily']['oil'] + $ringkasanData['daily']['stockMgmt'] + max($ringkasanData['daily']['hutangPel'], 0) + $ringkasanData['daily']['remain']; @endphp
                        <div class="stat-box highlight" style="margin-top:14px;">
                            <div class="label">Total penambahan aset hari ini</div>
                            <div class="value" id="hiGrand">{{ rupiah($hiGrand) }}</div>
                        </div>
                    @else
                        <p class="note" style="color:var(--debt);">Bulan aktif bukan bulan berjalan, ringkasan hari ini tidak tersedia. Silakan ganti bulan di bagian atas.</p>
                    @endif
                </div>

                <div class="card">
                    <h2>Ringkasan neraca total (semua waktu/bulan) &mdash; <span id="ringkasanBulanLabel2">{{ $this->monthLabel() }}</span></h2>
                    <div class="stat-grid">
                        <div class="stat-box"><div class="label">Total stok minyak</div><div class="value" id="sumOil">{{ rupiah($ringkasanData['totalOil']) }}</div></div>
                        <div class="stat-box"><div class="label">Total manajemen stok barang</div><div class="value" id="sumStockMgmt">{{ rupiah($ringkasanData['totalStockMgmt']) }}</div></div>
                        <div class="stat-box"><div class="label">Total sisa barang</div><div class="value" id="sumRemain">{{ rupiah($ringkasanData['totalRemain']) }}</div></div>
                        <div class="stat-box"><div class="label">Total hutang pelanggan (semua waktu)</div><div class="value" id="sumHutangPel">{{ rupiah($ringkasanData['totalHutangPel']) }}</div></div>
                        <div class="stat-box"><div class="label">Total hutang pribadi bulan ini</div><div class="value" id="sumHutangPri">{{ rupiah($ringkasanData['totalHutangPri']) }}</div></div>
                        <div class="stat-box"><div class="label">Pengurangan saldo bulan ini (A&minus;B)</div><div class="value" id="sumSaldo">{{ number_format($ringkasanData['totalSaldo'], 0, ',', '.') }}</div></div>
                    </div>
                    <div class="stat-box highlight" style="margin-top:14px;">
                        <div class="label">Total keseluruhan aset</div>
                        <div class="value" id="sumGrand">{{ rupiah($ringkasanData['grand']) }}</div>
                    </div>
                    <p class="note">Rumus mengikuti catatan: total stok minyak + total manajemen stok barang + total hutang pelanggan + total sisa barang. Hutang pribadi dan pengurangan saldo ditampilkan terpisah sebagai informasi tambahan.</p>
                </div>

                <div class="card">
                    <h2>Kelola data</h2>
                    <button type="button" class="ghost danger" wire:click="resetMonth" wire:confirm="Hapus semua data bulan {{ $this->monthLabel() }}? Tindakan ini tidak bisa dibatalkan.">Hapus data bulan ini saja</button>
                    <button type="button" class="ghost danger" style="margin-left:8px;" wire:click="resetAll" wire:confirm="Yakin hapus SEMUA data POS (semua bulan)? Tindakan ini tidak bisa dibatalkan.">Hapus SEMUA data (semua bulan)</button>
                </div>
            @endif
        </div>
    </main>

    {{-- MODAL HUTANG --}}
    <div class="modal-backdrop {{ $debtModalOpen ? 'open' : '' }}" id="debtModal" wire:click="$set('debtModalOpen', false)" style="{{ $debtModalOpen ? 'display:flex;' : 'display:none;' }}">
        <form class="debt-modal" id="debtForm" wire:submit.prevent="saveDebtModal" onclick="event.stopPropagation()">
            <div class="debt-modal-head">
                <h2 id="debtModalTitle">{{ $debtModalName ? 'Tambah hutang — ' . $debtModalName : 'Tambah hutang pelanggan' }}</h2>
                <p id="debtModalSubtitle">Catatan hutang baru akan ditambahkan ke rekap.</p>
            </div>
            <div class="debt-modal-body">
                <div class="field">
                    <label for="debtModalName">Nama pelanggan</label>
                    <input id="debtModalName" type="text" wire:model="debtModalName" required placeholder="Contoh: Mut">
                    @error('debtModalName') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="debtModalAmount">Jumlah hutang (Rp)</label>
                    <input id="debtModalAmount" type="number" wire:model="debtModalAmount" min="0" step="500" required placeholder="0">
                    @error('debtModalAmount') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="debtModalDate">Tanggal</label>
                    <input id="debtModalDate" type="date" wire:model="debtModalDate" required>
                </div>
                <div class="field">
                    <label for="debtModalNote">Catatan (opsional)</label>
                    <input id="debtModalNote" type="text" wire:model="debtModalNote" placeholder="Contoh: Hutang belanja">
                </div>
                <div class="modal-actions">
                    <button type="button" class="ghost" wire:click="$set('debtModalOpen', false)">Batal</button>
                    <button type="submit" class="primary">Simpan hutang</button>
                </div>
            </div>
        </form>
    </div>

    {{-- MODAL KONFIRMASI BAYAR --}}
    <div class="modal-backdrop {{ $pendingPaySaleId ? 'open' : '' }}" id="payConfirmModal" wire:click="$set('pendingPaySaleId', null)" style="{{ $pendingPaySaleId ? 'display:flex;' : 'display:none;' }}">
        <div class="pay-confirm-modal" onclick="event.stopPropagation()">
            @if($pendingPaySaleId)
                @php $pendingSale = $sales->firstWhere('id', $pendingPaySaleId); @endphp
                @if($pendingSale)
                    <div class="pay-confirm-head">
                        <h2>Konfirmasi Pembayaran</h2>
                        <p>Bayar <strong>{{ $pendingSale->name }}</strong> &mdash; Tagihan <strong>{{ rupiah($pendingSale->rounded_total) }}</strong></p>
                    </div>
                    <div class="pay-confirm-body">
                        <p style="margin:0 0 6px;">Apakah dibayar <strong>uang pas</strong> ({{ rupiah($pendingSale->rounded_total) }})?</p>
                        <p class="note" style="margin:0 0 16px;">Jika ya, transaksi langsung dianggap lunas. Jika tidak, form akan diisi dengan jumlah yang bisa disesuaikan.</p>
                        <div class="modal-actions">
                            <button type="button" class="ghost" wire:click="$set('pendingPaySaleId', null)">Batal</button>
                            <button type="button" class="ghost" wire:click="payWithForm({{ $pendingPaySaleId }})">Isi jumlah sendiri</button>
                            <button type="button" class="primary" wire:click="payExact({{ $pendingPaySaleId }})">Ya, uang pas</button>
                        </div>
                    </div>
                @else
                    <div class="pay-confirm-body">
                        <p>Data transaksi tidak ditemukan.</p>
                        <div class="modal-actions">
                            <button type="button" class="ghost" wire:click="$set('pendingPaySaleId', null)">Tutup</button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
