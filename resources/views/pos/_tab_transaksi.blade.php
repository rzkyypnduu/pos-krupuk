@php
    $totalKg = collect($txQty)->filter()->sum();
    $rawTotal = 0;
    foreach ($txQty as $pid => $qty) {
        if ($qty && (float) $qty > 0) {
            $p = $products->firstWhere('id', $pid);
            if ($p)
                $rawTotal += (float) $qty * $p->price;
        }
    }
    $rawTotal = (int) round($rawTotal);
    $roundedTotal = \App\Http\Controllers\PosController::roundTotal($rawTotal); // = JS recalcTotals() di bawah (lihat komentar di situ)
    $paidParsed = $txPaidTouched ? parseRupiah($txPaid) : 0; // parser sama dengan TransaksiController::simpanTransaksi
    $diff = $roundedTotal - $paidParsed;

    $qParams = ['bulan' => $activeMonth, 'tab' => 'transaksi', 'tx_date' => $txDate];
    $openForm = $showTxForm || $errors->has('txName') || $errors->has('txQty') || $errors->has('editing_sale_id');
    $formParams = array_merge($qParams, request()->has('new') ? ['new' => 1] : []);
    $newTxParams = ['bulan' => $activeMonth, 'tab' => 'transaksi', 'tx_date' => $txDate, 'new' => 1];
    $txDateLabel = \Illuminate\Support\Carbon::parse($txDate)->locale('id')->isoFormat('dddd, D MMMM YYYY');
@endphp

{{-- Bagian 1: header tanggal, tabel transaksi hari ini, & form baru/edit (id #tx-baru) --}}
<div class="tx-page">
    <div class="card tx-section" id="tx-baru">
        <div class="tx-page-head">
            <div>
                <h2>Transaksi Hari Ini</h2>
                <p class="tx-page-date">{{ $txDateLabel }}</p>
            </div>
            <div class="tx-page-actions">
                @unless($openForm)
                    <a href="{{ route('pos', $newTxParams) }}#tx-baru" class="btn btn-primary">+ Transaksi Baru</a>
                @endunless
            </div>
        </div>

        @if($openForm)
            <div class="tx-form-card" id="tx-form-card">
                <div class="tx-form-head">
                    <div class="tx-form-head-title">
                        <h2>Transaksi Baru</h2>
                    </div>
                    <div class="tx-form-head-actions">
                        <button type="button" class="tx-mic" id="txMicBtn" title="Speech-to-text: ucapkan transaksi"
                            aria-label="Speech-to-text: ucapkan transaksi">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path
                                    d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm-1-9c0-.55.45-1 1-1s1 .45 1 1v6c0 .55-.45 1-1 1s-1-.45-1-1V5zm6 6c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
                            </svg>
                            <span>Ucapkan pesanan</span>
                        </button>
                        <a href="{{ route('pos', $qParams) }}" class="btn btn-outline-light">Tutup</a>
                    </div>
                </div>
                <div class="tx-speech-panel" id="txSpeechPanel" data-parse-url="{{ route('pos.speechParse') }}" hidden>
                    <div class="tx-speech-head">
                        <span class="tx-speech-dot" id="txSpeechDot"></span>
                        <span class="tx-speech-status" id="txSpeechStatus">Siap merekam</span>
                        <button type="button" class="tx-speech-clear" id="txSpeechClear"
                            title="Hapus teks suara dan semua jumlah di form">Hapus</button>
                    </div>
                    <div class="tx-speech-transcript" id="txSpeechTranscript" hidden>
                        <span class="tx-speech-transcript-text" id="txSpeechTranscriptText"></span>
                        <button type="button" class="tx-speech-delete" id="txSpeechDelete"
                            title="Bersihkan teks suara saja — data di form tidak berubah">Bersihkan</button>
                    </div>
                    <ul class="tx-speech-items" id="txSpeechItems" hidden></ul>
                </div>
                <div class="tx-form-body">
                    @error('editing_sale_id')
                    <div class="field-error" style="margin-bottom:10px;">{{ $message }}</div> @enderror
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
                                <input type="text" id="txName" name="txName" value="{{ $txName }}" list="customerNames"
                                    placeholder="Cari nama..." oninput="document.getElementById('txPaidTouched').value='1'">
                                @error('txName')
                                <div class="field-error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div id="qtyGridWrap" style="margin-top:14px;">
                            <div class="form-group-label"
                                style="font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);">
                                Jumlah per produk (kg)</div>
                            @if ($products->isEmpty())
                                <div class="empty">Tambahkan produk dulu di tab Master Produk.</div>
                            @else
                                <div class="tx-product-grid" id="qtyGrid">
                                    @foreach ($products as $product)
                                        @php $qtyVal = $txQty[$product->id] ?? null; @endphp
                                        <div class="product-card {{ $qtyVal && (float) $qtyVal > 0 ? 'has-qty' : '' }}">
                                            <div class="product-card-name">{{ $product->name }}</div>
                                            <div class="product-card-input">
                                                <button type="button" class="qty-btn" data-qty-step="qtyInput{{ $product->id }}"
                                                    data-delta="-0.5" aria-label="Kurangi">&minus;</button>
                                                <input type="text" inputmode="decimal" step="0.5" id="qtyInput{{ $product->id }}"
                                                    name="qty[{{ $product->id }}]"
                                                    value="{{ $qtyVal && (float) $qtyVal > 0 ? str_replace('.', ',', (string) $qtyVal) : '' }}"
                                                    class="qty-input" placeholder="0" data-product-id="{{ $product->id }}"
                                                    oninput="this.closest('.product-card').classList.toggle('has-qty', this.value !== '' && parseFloat(this.value.replace(',','.')) > 0); recalcTotals();">
                                                <button type="button" class="qty-btn" data-qty-step="qtyInput{{ $product->id }}"
                                                    data-delta="0.5" aria-label="Tambah">+</button>
                                            </div>
                                            @if($product->price && $qtyVal && (float) $qtyVal > 0)
                                                <div class="product-card-subtotal">{{ rupiah($product->price * $qtyVal) }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @error('txQty')
                                <div class="field-error">{{ $message }}</div> @enderror
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
                                    <input type="text" id="txPaid" name="tx_paid" inputmode="numeric" value="{{ $txPaid }}"
                                        data-money
                                        placeholder="0"
                                        oninput="document.getElementById('txPaidTouched').value='1'; updateStatus()">
                                    <button type="button" class="pas-btn"
                                        onclick="var p=document.getElementById('txPaid'); p.value='{{ $roundedTotal }}'; p.dispatchEvent(new Event('input', {bubbles:true}));">Pas</button>
                                </div>
                            </div>
                            <div class="tx-status-group">
                                <div class="form-group-label">Status</div>
                                <div class="status-box {{ !$txPaidTouched ? 'debt' : ($diff > 0 ? 'debt' : ($diff < 0 ? 'paid' : 'zero')) }}"
                                    id="statusBox">
                                    @if(!$txPaidTouched) Belum dibayar: {{ rupiah($roundedTotal) }}
                                    @elseif($diff > 0) Kurang {{ rupiah($diff) }}
                                    @elseif($diff < 0) Lebih {{ rupiah(-$diff) }}
                                    @else Lunas &#10003; @endif
                                </div>
                            </div>
                        </div>

                        <div class="tx-note" style="margin-top:12px;">
                            <input type="text" name="tx_note" value="{{ $txNote }}" placeholder="Catatan (opsional)">
                        </div>

                        @if($editingSaleId)
                            @php $editSale = $sales->firstWhere('id', $editingSaleId); @endphp
                            @if($editSale)
                                <div class="tx-edit-banner">
                                    Mode bayar: <strong>{{ $editSale->name }}</strong> &mdash;
                                    {{ $editSale->date->format('Y-m-d') }}
                                    <a href="{{ route('pos', array_merge($qParams, ['tab' => 'transaksi'])) }}"
                                        class="btn btn-outline-secondary" style="margin-left:auto;text-decoration:none;">Batal</a>
                                </div>
                            @endif
                        @endif

                        <div class="tx-actions">
                            <button type="submit"
                                class="btn btn-primary">{{ $editingSaleId ? 'Simpan pembayaran' : 'Simpan transaksi' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="table-wrap tx-section-table">
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Produk</th>
                        <th class="num">Kg</th>
                        <th class="num">Tagihan</th>
                        <th class="num">Dibayar</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $tx)
                        <tr data-pay-row="{{ $tx->id }}">
                            <td>{{ $tx->name }}</td>
                            <td style="font-size:19px;line-height:1.6;">
                                @foreach($tx->items as $item)
                                    <div>{{ $item->name }}: {{ fmtKg($item->qty) }} kg</div>
                                @endforeach
                            </td>
                            <td class="num">{{ fmtKg($tx->items->sum('qty')) }}</td>
                            <td class="num">{{ rupiah($tx->rounded_total) }}</td>
                            <td class="num" data-pay-paid
                                title="{{ 'Bayar kemarin ' . rupiah($tx->paid_kemarin) . ' + hari ini ' . rupiah($tx->paid) }}">{{ rupiah($tx->paid + $tx->paid_kemarin) }}</td>
                            <td>
                                <div class="status-stack">
                                    <span class="badge {{ $tx->diff > 0 ? 'debt' : ($tx->diff < 0 ? 'paid' : 'zero') }}"
                                        data-pay-badge>{{ $tx->diff > 0 ? 'Kurang ' . rupiah($tx->diff) : ($tx->diff < 0 ? 'Lebih ' . rupiah(-$tx->diff) : 'Lunas') }}</span>
                                    <span class="badge paid-outline{{ $tx->paid_kemarin > 0 ? '' : ' d-none' }}"
                                        data-pay-kbadge title="Sebagian uang dipakai membayar hutang sebelumnya">Bayar
                                        kemarin {{ rupiah($tx->paid_kemarin) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button"
                                        class="btn {{ $tx->is_paid_btn_clicked ? 'btn-outline-secondary' : 'btn-success' }}"
                                        title="Buka modal pembayaran {{ $tx->name }}"
                                        data-pay-open="{{ $tx->id }}">Bayar</button>
                                    <label class="ck-kemarin{{ $tx->paid_kemarin > 0 ? ' on' : '' }}"
                                        title="Aktifkan bila pembeli juga membayar hutang sebelumnya">
                                        <input type="checkbox" id="ckKmr{{ $tx->id }}" data-pay-ck="{{ $tx->id }}"
                                            @checked($tx->paid_kemarin > 0)>
                                        <span>Bayar kemarin</span>
                                    </label>
                                    <form method="POST" action="{{ route('pos.loadForPayment', $tx->id) }}"
                                        style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary"
                                            title="Edit transaksi">Edit</button>
                                    </form>
                                    <form method="POST" action="{{ route('pos.hapusTransaksi', $tx->id) }}"
                                        style="display:inline;"
                                        data-confirm="Hapus transaksi {{ $tx->name }} ini? Riwayat hutang tidak akan dihapus.">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty" style="padding:18px;font-size:15px;">Belum ada transaksi hari ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bagian 2: pengeluaran laci (kas keluar harian) --}}
    <div class="card">
        <h2>Pengeluaran Laci</h2>
        <form method="POST" action="{{ route('pos.simpanExpense', $qParams) }}" class="tx-expense-form">
            @csrf
            <input type="hidden" name="expDate" value="{{ $txDate }}">
            <input type="text" inputmode="numeric" name="expAmount" data-money min="0" placeholder="Jumlah (Rp)" style="flex:2;">
            <input type="text" name="expNote" placeholder="Keperluan" style="flex:3;">
            <button type="submit" class="btn btn-dark">Simpan</button>
        </form>
        @error('expAmount')
        <div class="field-error">{{ $message }}</div> @enderror
        <div class="table-wrap">
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>Keperluan</th>
                        <th class="num">Jumlah</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dayExpenses as $e)
                        <tr>
                            <td>{{ $e->note ?? '-' }}</td>
                            <td class="num">{{ rupiah($e->amount) }}</td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('pos.hapusExpense', $e->id) }}" style="display:inline;"
                                    data-confirm="Hapus pengeluaran ini?">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="empty" style="padding:14px;font-size:15px;">Belum ada pengeluaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bagian 3: rekap hari ini (Diterima/pengeluaran/kas bersih) — toggle: Bootstrap Collapse --}}
    <div class="card">
        <button type="button" class="recap-toggle" data-bs-toggle="collapse" data-bs-target="#recapHari"
            aria-expanded="true" aria-controls="recapHari">
            <h2 style="margin-bottom:0;">Ringkasan Hari Ini</h2>
            <span style="font-size:13px;color:var(--ink-soft);" aria-hidden="true">&#9650;</span>
        </button>
        <div class="collapse show" id="recapHari" style="margin-top:14px;">
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
                    <div
                        style="display:flex;justify-content:space-between;padding:2px 0;border-top:1px solid var(--line);margin-top:4px;padding-top:6px;font-weight:700;">
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

    {{-- Bagian 4: rekap bulan berjalan --}}
    <div class="card">
        <button type="button" class="recap-toggle" data-bs-toggle="collapse" data-bs-target="#recapBulan"
            aria-expanded="true" aria-controls="recapBulan">
            <h2 style="margin-bottom:0;">Ringkasan Bulan ({{ $monthLabel }})</h2>
            <span style="font-size:13px;color:var(--ink-soft);" aria-hidden="true">&#9650;</span>
        </button>
        <div class="collapse show" id="recapBulan" style="margin-top:14px;">
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
                    <div
                        style="display:flex;justify-content:space-between;padding:2px 0;border-top:1px solid var(--line);margin-top:4px;padding-top:6px;font-weight:700;">
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

{{-- Bagian 5: JS live — updateStatus (parsing Dibayar, pasangan parseRupiah) &
recalcTotals (pembulatan, pasangan PosHelpers::roundTotal — lihat komentar di dalamnya) --}}
<script>
    function updateStatus() {
        var paid = document.getElementById('txPaid').value;
        // parse = parseRupiah() di PHP; parseInt di sini membuang desimal (input rupiah tanpa desimal)
        var paidNum = paid ? parseInt(String(paid).replace(/\./g, '').replace(',', '.')) : 0;
        var rounded = {{ $roundedTotal }};
        var diff = rounded - paidNum;
        var box = document.getElementById('statusBox');
        if (diff > 0) { box.className = 'status-box debt'; box.innerHTML = 'Kurang ' + formatRupiah(diff); }
        else if (diff < 0) { box.className = 'status-box paid'; box.innerHTML = 'Lebih ' + formatRupiah(-diff); }
        else { box.className = 'status-box zero'; box.innerHTML = 'Lunas &#10003;'; }
    }
    function formatRupiah(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }
    // pasangan fmtKg() di PHP: bulat -> "1" (tanpa ,0), desimal -> "1,5" (maks 2 desimal)
    function fmtKgJs(qty) {
        var n = Number(qty) || 0;
        if (n === Math.trunc(n)) return String(Math.trunc(n));
        return String(Math.round(n * 100) / 100).replace('.', ',');
    }
    function recalcTotals() {
        var inputs = document.querySelectorAll('[data-product-id]');
        var prices = {!! $products->pluck('price', 'id')->toJson() !!};
        var totalKg = 0, rawTotal = 0;
        inputs.forEach(function (inp) {
            var pid = inp.getAttribute('data-product-id');
            var v = parseFloat(inp.value.replace(',', '.')) || 0;
            if (v > 0) { totalKg += v; rawTotal += v * (prices[pid] || 0); }
        });
        rawTotal = Math.round(rawTotal);
        // aturan pembulatan yang sama dengan PosHelpers::roundTotal (PHP) — ubah dua tempat
        var thousands = Math.floor(rawTotal / 1000) * 1000;
        var remainder = rawTotal - thousands;
        var rounded = remainder < 500 ? thousands : thousands + 1000;
        document.getElementById('totalKgDisplay').textContent = fmtKgJs(totalKg) + ' kg';
        document.getElementById('rawTotalDisplay').textContent = formatRupiah(rawTotal);
        document.getElementById('roundedTotalDisplay').textContent = formatRupiah(rounded);
        document.getElementById('statusBox').innerHTML = 'Belum dibayar: ' + formatRupiah(rounded);
        document.getElementById('statusBox').className = 'status-box debt';
    }
</script>
{{-- Speech-to-text: browser (pos-speech.js di bundle Vite) -> route pos.speech ->
SpeechController::parseSpeech -> SpeechParser (rule-based) -> hasil diisi ke form. --}}