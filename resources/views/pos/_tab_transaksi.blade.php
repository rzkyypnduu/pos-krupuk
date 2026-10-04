@php
    $totalKg = collect($txQty)->filter()->sum();
    $rawTotal = 0;
    foreach ($txQty as $pid => $qty) {
        if ($qty && (float) $qty > 0) {
            $p = $products->firstWhere('id', $pid);
            if ($p) $rawTotal += (float) $qty * $p->price;
        }
    }
    $rawTotal = (int) round($rawTotal);
    $roundedTotal = \App\Http\Controllers\PosController::roundTotal($rawTotal);
    $paidVal = $txPaid !== null ? $txPaid : 0;
    $paidParsed = $txPaidTouched && !is_null($txPaid) && $txPaid !== '' ? (int) str_replace(',', '.', str_replace('.', '', $txPaid)) : 0;
    $diff = $roundedTotal - $paidParsed;
    $bal = $customerBalances[$txName] ?? 0;

    $qParams = ['bulan' => $activeMonth, 'tab' => 'transaksi', 'tx_date' => $txDate];
    $openForm = $showTxForm || $errors->has('txName') || $errors->has('txQty');
    $formParams = array_merge($qParams, request()->has('new') ? ['new' => 1] : []);
    $newTxParams = ['bulan' => $activeMonth, 'tab' => 'transaksi', 'tx_date' => $txDate, 'new' => 1];
    $txDateLabel = \Illuminate\Support\Carbon::parse($txDate)->locale('id')->isoFormat('dddd, D MMMM YYYY');
@endphp

<div class="tx-page">
    <div class="card tx-section" id="tx-baru">
        <div class="tx-page-head">
            <div>
                <h2>Transaksi Hari Ini</h2>
                <p class="tx-page-date">{{ $txDateLabel }}</p>
            </div>
            <div class="tx-page-actions">
                @unless($openForm)
                    <a href="{{ route('pos', $newTxParams) }}#tx-baru" class="btn-primary">+ Transaksi Baru</a>
                @endunless
            </div>
        </div>

        @if($openForm)
            <div class="tx-form-card" id="tx-form-card">
                <div class="tx-form-head">
                    <div class="tx-form-head-title">
                        <h2>Transaksi Baru</h2>
                        <span class="tx-speech-hint">Tekan tombol mikrofon lalu ucapkan: nama, jumlah, produk</span>
                    </div>
                    <div class="tx-form-head-actions">
                        <button type="button" class="tx-mic" id="txMicBtn" title="Speech-to-text: ucapkan transaksi" aria-label="Speech-to-text: ucapkan transaksi">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm-1-9c0-.55.45-1 1-1s1 .45 1 1v6c0 .55-.45 1-1 1s-1-.45-1-1V5zm6 6c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
                        </button>
                        <a href="{{ route('pos', $qParams) }}" class="btn-ghost btn-ghost-dark">Tutup</a>
                    </div>
                </div>
                <div class="tx-speech-panel" id="txSpeechPanel" data-parse-url="{{ route('pos.speechParse') }}" hidden>
                    <div class="tx-speech-head">
                        <span class="tx-speech-dot" id="txSpeechDot"></span>
                        <span class="tx-speech-status" id="txSpeechStatus">Siap merekam</span>
                        <button type="button" class="tx-speech-clear" id="txSpeechClear" title="Hapus teks suara dan semua jumlah di form">Hapus</button>
                    </div>
                    <div class="tx-speech-transcript" id="txSpeechTranscript" hidden>
                        <span class="tx-speech-transcript-text" id="txSpeechTranscriptText"></span>
                        <button type="button" class="tx-speech-delete" id="txSpeechDelete" title="Bersihkan teks suara saja — data di form tidak berubah">Bersihkan</button>
                    </div>
                    <ul class="tx-speech-items" id="txSpeechItems" hidden></ul>
                </div>
                <div class="tx-form-body">
                    <form method="POST" action="{{ route('pos.simpanTransaksi', $formParams) }}" id="txForm">
                        @csrf
                        <input type="hidden" name="txDate" value="{{ $txDate }}">
                        <input type="hidden" name="editing_sale_id" value="{{ $editingSaleId }}">
                        <input type="hidden" name="tx_paid_touched" value="{{ $txPaidTouched ? 1 : 0 }}" id="txPaidTouched">
                        <input type="hidden" name="input_method" id="inputMethod" value="manual">
                        <input type="hidden" name="input_text" id="inputText" value="">
                        <input type="hidden" name="input_parsed" id="inputParsed" value="">
                        <input type="hidden" name="input_duration_ms" id="inputDurationMs" value="">
                        <input type="hidden" name="input_started_at" id="inputStartedAt" value="">

                        <div class="tx-customer-row">
                            <div class="tx-name-field">
                                <label for="txName">Nama pelanggan</label>
                                <input type="text" id="txName" name="txName" value="{{ $txName }}" list="customerNames" placeholder="Cari nama..." oninput="document.getElementById('txPaidTouched').value='1'">
                                @error('txName') <div class="field-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="tx-debt-pill {{ $bal > 0 ? 'debt' : ($bal < 0 ? 'credit' : 'zero') }}">
                                @if($txName)
                                    @if($bal > 0) Hutang {{ rupiah($bal) }}
                                    @elseif($bal < 0) Deposit {{ rupiah(-$bal) }}
                                    @else Lunas @endif
                                @else Lunas @endif
                            </div>
                        </div>

                        <div id="qtyGridWrap" style="margin-top:14px;">
                            <div class="form-group-label" style="font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);">Jumlah per produk (kg)</div>
                            @if ($products->isEmpty())
                                <div class="empty">Tambahkan produk dulu di tab Master Produk.</div>
                            @else
                                <div class="tx-product-grid" id="qtyGrid">
                                    @foreach ($products as $product)
                                        @php $qtyVal = $txQty[$product->id] ?? null; @endphp
                                        <div class="product-card {{ $qtyVal && (float)$qtyVal > 0 ? 'has-qty' : '' }}">
                                            <div class="product-card-name">{{ $product->name }}</div>
                                            <div class="product-card-input" x-data="{ qty: '{{ $qtyVal && (float)$qtyVal > 0 ? str_replace('.', ',', (string)$qtyVal) : '' }}' }">
                                                <button type="button" class="qty-btn" @click="let v = parseFloat(qty.replace(',','.')) || 0; v = Math.max(0, v - 0.5); qty = v === 0 ? '' : String(v).replace('.',','); $refs['input_{{ $product->id }}'].value = qty; $refs['input_{{ $product->id }}'].dispatchEvent(new Event('input', {bubbles:true}))">&minus;</button>
                                                <input type="text" inputmode="decimal" step="0.5"
                                                       x-ref="input_{{ $product->id }}"
                                                       name="qty[{{ $product->id }}]"
                                                       x-model="qty"
                                                       class="qty-input" placeholder="0"
                                                       data-product-id="{{ $product->id }}"
                                                       oninput="this.closest('.product-card').classList.toggle('has-qty', this.value !== '' && parseFloat(this.value.replace(',','.')) > 0); recalcTotals();">
                                                <button type="button" class="qty-btn" @click="let v = parseFloat(qty.replace(',','.')) || 0; v = v + 0.5; qty = String(v).replace('.',','); $refs['input_{{ $product->id }}'].value = qty; $refs['input_{{ $product->id }}'].dispatchEvent(new Event('input', {bubbles:true}))">+</button>
                                            </div>
                                            @if($product->price && $qtyVal && (float)$qtyVal > 0)
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
                                <div class="value" id="totalKgDisplay">{{ fmtKg($totalKg) }} kg</div>
                            </div>
                            <div class="tx-total-stat">
                                <div class="label">Belanja (asli)</div>
                                <div class="value" id="rawTotalDisplay">{{ rupiah($rawTotal) }}</div>
                            </div>
                            <div class="tx-total-stat highlight">
                                <div class="label">Tagihan</div>
                                <div class="value" id="roundedTotalDisplay">{{ rupiah($roundedTotal) }}</div>
                            </div>
                        </div>

                        <div class="tx-payment-row">
                            <div class="tx-paid-group">
                                <label for="txPaid">Dibayar (Rp)</label>
                                <div class="input-group">
                                    <input type="text" id="txPaid" name="tx_paid" inputmode="numeric" value="{{ $txPaid }}" placeholder="0" oninput="document.getElementById('txPaidTouched').value='1'; updateStatus()">
                                    <button type="button" class="pas-btn" onclick="document.getElementById('txPaid').value='{{ $roundedTotal }}'; document.getElementById('txPaidTouched').value='1'; updateStatus()">Pas</button>
                                </div>
                            </div>
                            <div class="tx-status-group">
                                <div class="form-group-label">Status</div>
                                <div class="status-box {{ !$txPaidTouched ? 'debt' : ($diff > 0 ? 'debt' : ($diff < 0 ? 'paid' : 'zero')) }}" id="statusBox">
                                    @if(!$txPaidTouched) Belum dibayar: {{ rupiah($roundedTotal) }}
                                    @elseif($diff > 0) Kurang {{ rupiah($diff) }}
                                    @elseif($diff < 0) Lebih {{ rupiah(-$diff) }}
                                    @else Lunas &#10003; @endif
                                </div>
                            </div>
                        </div>
                        <p class="note" style="margin:-6px 0 0;font-size:14px;">Dibayar &lt; tagihan &rarr; status <b>Kurang</b>. &gt; &rarr; status <b>Lebih</b>. <b>Pas</b> = lunas. Angka <b>Dibayar</b> hanya mencatat penerimaan uang transaksi ini — tidak mengubah tabel hutang di tab Hasil.</p>

                        <div class="tx-note" style="margin-top:12px;">
                            <input type="text" name="tx_note" value="{{ $txNote }}" placeholder="Catatan (opsional)">
                        </div>

                        @if($editingSaleId)
                            @php $editSale = $sales->firstWhere('id', $editingSaleId); @endphp
                            @if($editSale)
                                <div class="tx-edit-banner">
                                    Mode bayar: <strong>{{ $editSale->name }}</strong> &mdash; {{ $editSale->date->format('Y-m-d') }}
                                    <a href="{{ route('pos', array_merge($qParams, ['tab' => 'transaksi'])) }}" class="ghost" style="margin-left:auto;text-decoration:none;">Batal</a>
                                </div>
                            @endif
                        @endif

                        <div class="tx-actions">
                            <button type="submit" class="primary">{{ $editingSaleId ? 'Simpan pembayaran' : 'Simpan transaksi' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="table-wrap tx-section-table">
            <table class="tx-table">
                <thead><tr><th>Nama</th><th>Produk</th><th class="num">Kg</th><th class="num">Tagihan</th><th class="num">Dibayar</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sales as $tx)
                        <tr>
                            <td>{{ $tx->name }}</td>
                            <td style="font-size:19px;line-height:1.6;">
                                @foreach($tx->items as $item)
                                    <div>{{ $item->name }}: {{ fmtKg($item->qty) }} kg</div>
                                @endforeach
                            </td>
                            <td class="num">{{ fmtKg($tx->items->sum('qty')) }}</td>
                            <td class="num">{{ rupiah($tx->rounded_total) }}</td>
                            <td class="num" title="{{ 'Bayar kemarin ' . rupiah($tx->paid_kemarin) . ' + hari ini ' . rupiah($tx->paid) }}"
                                :title="$store.pay.dibayarTitle({{ $tx->id }})">{{ rupiah($tx->paid + $tx->paid_kemarin) }}</td>
                            <td>
                                <div class="status-stack">
                                <span class="badge {{ $tx->diff > 0 ? 'debt' : ($tx->diff < 0 ? 'paid' : 'zero') }}"
                                      x-effect="$el.className = 'badge ' + $store.pay.statusClass({{ $tx->id }})"
                                      x-text="$store.pay.statusText({{ $tx->id }})">{{ $tx->diff > 0 ? 'Kurang ' . rupiah($tx->diff) : ($tx->diff < 0 ? 'Lebih ' . rupiah(-$tx->diff) : 'Lunas') }}</span>
                                <span class="badge paid-outline" title="Sebagian uang dipakai membayar hutang sebelumnya"
                                      x-show="$store.pay.kemarin[{{ $tx->id }}] && $store.pay.rows[{{ $tx->id }}] && $store.pay.rows[{{ $tx->id }}].paid_kemarin > 0"
                                      x-text="'Bayar kemarin ' + $store.pay.rp($store.pay.rows[{{ $tx->id }}] ? $store.pay.rows[{{ $tx->id }}].paid_kemarin : 0)"
                                      @if($tx->paid_kemarin <= 0) style="display:none;" @endif>Bayar kemarin {{ rupiah($tx->paid_kemarin) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                <button type="button" class="ghost {{ $tx->is_paid_btn_clicked ? 'btn-paid-done' : 'btn-pay-open' }}"
                                        title="Buka modal pembayaran {{ $tx->name }}"
                                        @click="$store.pay.open({{ $tx->id }})">Bayar</button>
                                <label class="ck-kemarin" title="Aktifkan bila pembeli juga membayar hutang sebelumnya"
                                       :class="$store.pay.kemarin[{{ $tx->id }}] ? 'on' : ''">
                                    <input type="checkbox" id="ckKmr{{ $tx->id }}"
                                           x-model="$store.pay.kemarin[{{ $tx->id }}]"
                                           @change="$store.pay.toggleRow({{ $tx->id }}, $event.target.checked)">
                                    <span>Bayar kemarin</span>
                                </label>
                                <form method="POST" action="{{ route('pos.loadForPayment', $tx->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="ghost" title="Edit transaksi">Edit</button>
                                </form>
                                <form method="POST" action="{{ route('pos.hapusTransaksi', $tx->id) }}" style="display:inline;" onsubmit="return confirm('Hapus transaksi {{ $tx->name }} ini? Riwayat hutang tidak akan dihapus.')">
                                    @csrf
                                    <button type="submit" class="ghost danger">Hapus</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty" style="padding:18px;font-size:15px;">Belum ada transaksi hari ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Pengeluaran Laci</h2>
        <form method="POST" action="{{ route('pos.simpanExpense', $qParams) }}" class="tx-expense-form">
            @csrf
            <input type="hidden" name="expDate" value="{{ $txDate }}">
            <input type="number" name="expAmount" min="0" step="500" placeholder="Jumlah (Rp)" style="flex:2;">
            <input type="text" name="expNote" placeholder="Keperluan" style="flex:3;">
            <button type="submit" class="primary" style="background:var(--ink);font-size:15px;padding:12px 16px;">Simpan</button>
        </form>
        @error('expAmount') <div class="field-error">{{ $message }}</div> @enderror
        <div class="table-wrap">
            <table class="tx-table">
                <thead><tr><th>Keperluan</th><th class="num">Jumlah</th><th></th></tr></thead>
                <tbody>
                    @forelse ($dayExpenses as $e)
                        <tr>
                            <td>{{ $e->note ?? '-' }}</td>
                            <td class="num">{{ rupiah($e->amount) }}</td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('pos.hapusExpense', $e->id) }}" style="display:inline;" onsubmit="return confirm('Hapus pengeluaran ini?')">
                                    @csrf
                                    <button type="submit" class="ghost danger" style="font-size:13px;padding:6px 10px;">Hapus</button>
                                </form>
                            </td>
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
            <h2 style="margin-bottom:0;">Ringkasan Hari Ini</h2>
            <span style="font-size:13px;color:var(--ink-soft);" x-html="showRecap ? '&#9650;' : '&#9660;'"></span>
        </div>
        <div x-show="showRecap" style="margin-top:14px;">
            <div class="tx-summ-block">
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
                        <div class="tx-summ-row">
                            <span>Diterima</span>
                            <span class="num green">{{ rupiah($recapTotals['dibayar']) }}</span>
                        </div>
                        <div class="tx-summ-row">
                            <span>Pengeluaran</span>
                            <span class="num red">&minus;{{ rupiah($recapTotals['pengeluaran']) }}</span>
                        </div>
                        <div class="tx-summ-row kas">
                            <span>Kas bersih</span>
                            <span class="num green">{{ rupiah($recapTotals['kas_bersih']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <div class="card" x-data="{ showBulan: true }">
        <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;" @click="showBulan = !showBulan">
            <h2 style="margin-bottom:0;">Ringkasan Bulan ({{ $monthLabel }})</h2>
            <span style="font-size:13px;color:var(--ink-soft);" x-html="showBulan ? '&#9650;' : '&#9660;'"></span>
        </div>
        <div x-show="showBulan" style="margin-top:14px;">
            <div class="tx-summ-block">
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
                        <div class="tx-summ-row">
                            <span>Diterima</span>
                            <span class="num green">{{ rupiah($recapBulanTotals['dibayar']) }}</span>
                        </div>
                        <div class="tx-summ-row">
                            <span>Pengeluaran</span>
                            <span class="num red">&minus;{{ rupiah($recapBulanTotals['pengeluaran']) }}</span>
                        </div>
                        <div class="tx-summ-row kas">
                            <span>Kas bersih</span>
                            <span class="num green">{{ rupiah($recapBulanTotals['kas_bersih']) }}</span>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStatus() {
    var paid = document.getElementById('txPaid').value;
    var paidNum = paid ? parseInt(String(paid).replace(/\./g,'').replace(',','.')) : 0;
    var rounded = {{ $roundedTotal }};
    var diff = rounded - paidNum;
    var box = document.getElementById('statusBox');
    if (diff > 0) { box.className = 'status-box debt'; box.innerHTML = 'Kurang ' + formatRupiah(diff); }
    else if (diff < 0) { box.className = 'status-box paid'; box.innerHTML = 'Lebih ' + formatRupiah(-diff); }
    else { box.className = 'status-box zero'; box.innerHTML = 'Lunas &#10003;'; }
}
function formatRupiah(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }
function recalcTotals() {
    var inputs = document.querySelectorAll('[data-product-id]');
    var prices = {!! $products->pluck('price','id')->toJson() !!};
    var totalKg = 0, rawTotal = 0;
    inputs.forEach(function(inp) {
        var pid = inp.getAttribute('data-product-id');
        var v = parseFloat(inp.value.replace(',','.')) || 0;
        if (v > 0) { totalKg += v; rawTotal += v * (prices[pid] || 0); }
    });
    rawTotal = Math.round(rawTotal);
    var thousands = Math.floor(rawTotal / 1000) * 1000;
    var remainder = rawTotal - thousands;
    var rounded = remainder < 500 ? thousands : thousands + 1000;
    document.getElementById('totalKgDisplay').textContent = totalKg.toFixed(1).replace('.',',') + ' kg';
    document.getElementById('rawTotalDisplay').textContent = formatRupiah(rawTotal);
    document.getElementById('roundedTotalDisplay').textContent = formatRupiah(rounded);
    document.getElementById('statusBox').innerHTML = 'Belum dibayar: ' + formatRupiah(rounded);
    document.getElementById('statusBox').className = 'status-box debt';
}
</script>
<script src="{{ asset('js/pos-speech.js') }}?v={{ filemtime(public_path('js/pos-speech.js')) }}" defer></script>
