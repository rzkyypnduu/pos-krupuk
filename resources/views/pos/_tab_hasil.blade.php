@php
    $qParams = ['bulan' => $activeMonth, 'tx_date' => $txDate, 'tab' => 'hasil'];
    $asetHari = $oilStocks->sum(fn ($o) => $o->qty * $o->price)
        + $stockMgmts->sum(fn ($o) => $o->subtotal())
        + $stockRemainings->sum(fn ($o) => $o->qty * $o->price)
        + $hpGrandTotal;
    $hprHari = (int) $personalLedgers->sum('amount');
    $saldoHari = (int) $saldoLogs->sum(fn ($l) => $l->result());
    $saldoTerakhir = $saldoLogs->last();
    $oilSaved = $oilStocks->first();
    // Baris tabel Manajemen stok — sacks semua batch digabung (flat) per pemegang.
    $mgmtRows = $stockMgmts->map(fn ($o) => [
        'id' => $o->id,
        'date' => $o->date?->format('Y-m-d') ?? '-',
        'name' => $o->name,
        'price' => $o->price,
        'sacks' => collect($o->batches ?? [])
            ->flatMap(fn ($b) => $b['sacks'] ?? [])
            ->map(fn ($v) => is_numeric($v) ? (float) $v : 0)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all(),
        'totalQty' => round(\App\Models\StockManagement::totalQty($o->batches ?? []), 2),
        'subtotal' => $o->subtotal(),
    ])->values()->all();
    // Baris tabel Sisa barang — semua kolom (produk, jumlah, harga) diedit seperti Excel.
    $remainRows = $stockRemainings->map(fn ($o) => [
        'id' => $o->id,
        'date' => $o->date?->format('Y-m-d') ?? '-',
        'name' => $o->name,
        'qty' => $o->qty + 0,
        'price' => $o->price,
    ])->values()->all();
    // Baris Daftar hutang pribadi — kolom nama & jumlah diedit seperti Excel.
    $hprRows = $personalLedgers->map(fn ($l) => [
        'id' => $l->id,
        'date' => $l->date->format('Y-m-d'),
        'name' => $l->name,
        'amount' => $l->amount,
    ])->values()->all();

    // Perhitungan untuk render awal (dipertahankan konsisten oleh JS setelah edit)
    $mgmtMaxSak = max([1, ...array_map(fn ($r) => count($r['sacks']), $mgmtRows)]);
    $mgmtGrand = array_sum(array_column($mgmtRows, 'subtotal'));
    $remainGrand = array_sum(array_map(fn ($r) => $r['qty'] * $r['price'], $remainRows));
    $hprGrand = array_sum(array_column($hprRows, 'amount'));
@endphp

{{-- 1. HUTANG PELANGGAN --}}
<div class="section-header">
    <h2>1. Rekap Hutang Pelanggan</h2>
</div>

<div class="card">
    <h2>Rekap hutang per pelanggan</h2>
    <div class="hp-toolbar">
        <a href="{{ route('pos', array_merge($qParams, ['debt_modal' => 1, 'debt_name' => ''])) }}" class="btn btn-primary btn-plus">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Tambah hutang
        </a>
    </div>

    <div class="table-wrap">
        <table id="hpSummaryTable" class="hp-grid" style="white-space: nowrap;">
            <thead>
                <tr>
                    <th class="hp-head">Nama</th>
                    <th class="hp-head num" colspan="{{ max((int) $maxCols, 1) }}">Hutang</th>
                    <th class="hp-head num">Total</th>
                    <th class="hp-head"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hpNames as $name)
                    @php $p = $hpProcessed[$name]; @endphp
                    <tr data-name="{{ $name }}" data-total="{{ $p['totalSisa'] }}">
                        <td class="hp-name-cell">
                            <span class="hp-name" title="Klik untuk ubah nama">{{ $name }}</span>
                            <input class="hp-inp hp-name-inp" type="text" value="{{ $name }}" data-val="{{ $name }}" maxlength="255">
                        </td>
                        @for ($i = 0; $i < max((int) $maxCols, 1); $i++)
                            @if (isset($p['activeDebts'][$i]))
                                @php $d = $p['activeDebts'][$i]; @endphp
                                <td class="num hp-cell hp-debt-cell" data-debt-id="{{ $d['id'] }}">
                                    <span class="hp-amt" title="Klik untuk edit — {{ $d['date'] }}">{{ rupiah($d['remaining']) }}</span>
                                    <input class="hp-inp" type="text" inputmode="numeric" data-money value="{{ number_format($d['remaining'], 0, ',', '.') }}" data-val="{{ number_format($d['remaining'], 0, ',', '.') }}">
                                </td>
                            @else
                                <td class="num hp-cell empty-cell">&ndash;</td>
                            @endif
                        @endfor
                        <td class="num hp-cell hp-total-cell">
                            <span class="hp-amt hp-total-amt" title="Klik untuk ubah total">{{ rupiah($p['totalSisa']) }}</span>
                            <input class="hp-inp" type="text" inputmode="numeric" data-money value="{{ number_format($p['totalSisa'], 0, ',', '.') }}" data-val="{{ number_format($p['totalSisa'], 0, ',', '.') }}">
                        </td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end; align-items:center;">
                                <a class="btn-icon" href="{{ route('pos', array_merge($qParams, ['debt_modal' => 1, 'debt_name' => $name])) }}"
                                   title="Tambah hutang baru untuk {{ $name }}" aria-label="Tambah hutang baru">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </a>
                                <form method="POST" action="{{ route('pos.hapusCustomerLedger', array_merge($qParams, ['name' => $name])) }}" style="display:inline;" data-confirm="Hapus semua riwayat hutang pelanggan ini di tanggal ini? Tindakan ini tidak bisa dibatalkan.">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="99" class="empty">Belum ada catatan hutang pelanggan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="summary-line total"><span>Total hutang pelanggan (piutang toko)</span><span class="val" id="hpGrandTotal">{{ rupiah($hpGrandTotal) }}</span></div>
</div>

@if($hpDetailName && $hpEntries)
    <div class="card" id="hpDetailCard">
        <h2 id="hpDetailTitle">Detail hutang &mdash; {{ $hpDetailName }}</h2>
        <div class="table-wrap">
            <table id="hpDetailTable">
                <thead><tr><th>Jenis</th><th class="num">Jumlah</th><th class="num">Dibayar</th><th class="num">Saldo berjalan</th><th>Catatan</th><th></th></tr></thead>
                <tbody>
                    @forelse ($hpEntries as $entry)
                        <tr>
                            <td>{!! $entry->type === 'tambah' ? '<span style="color:var(--debt);">+ Tambah hutang</span>' : '<span style="color:var(--paid);">&minus; Bayar hutang</span>' !!}</td>
                            <td class="num">{{ $entry->type === 'tambah' ? '+' : '-' }}{{ rupiah($entry->amount) }}</td>
                            <td class="num">{{ $entry->sale_id && $entry->paid !== null ? rupiah($entry->paid) : '-' }}</td>
                            <td class="num">{!! $entry->running > 0 ? rupiah($entry->running) : ($entry->running < 0 ? '&minus;'.rupiah(-$entry->running).' (deposit)' : 'Lunas (Rp0)') !!}</td>
                            <td>{{ $entry->note ?? '' }}</td>
                            <td>
                                <div class="row-actions">
                                @if($entry->sale_id)
                                    <form method="POST" action="{{ route('pos.loadForPayment', $entry->sale_id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary">Lihat Transaksi</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('pos.hapusLedgerEntry', $entry->id) }}" style="display:inline;" data-confirm="Hapus entri ini?">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger">Hapus</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <a href="{{ route('pos', array_merge($qParams)) }}" class="btn btn-outline-secondary" style="margin-top:8px;">Tutup detail</a>
    </div>
@endif

{{-- 2. STOK BARANG --}}
<div class="section-header">
    <h2>2. Stok Barang</h2>
</div>

<div class="card sub-section">
    <h3>Stok minyak</h3>
    <form method="POST" action="{{ route('pos.simpanOil', $qParams) }}">
        @csrf
        <div class="field-row">
            <div class="field"><label for="oilQty">Jumlah (liter/kg)</label><input type="number" id="oilQty" name="oilQty" min="0" step="0.5" value="{{ old('oilQty', $oilSaved->qty ?? '') }}"></div>
            <div class="field"><label for="oilPrice">Harga satuan (Rp)</label><input type="text" inputmode="numeric" id="oilPrice" name="oilPrice" data-money min="0" value="{{ old('oilPrice', $oilSaved->price ?? '') }}"></div>
            <div class="field" style="flex:0; align-self:flex-end;"><button type="submit" class="btn btn-primary">Simpan</button></div>
        </div>
    </form>
    @error('oilQty') <div class="field-error">{{ $message }}</div> @enderror
    <div class="summary-line total" style="margin-top:12px;">
        <span>Hasil perkalian (jumlah &times; harga)</span>
        <span class="val" id="oilCalc" data-initial="{{ $oilSaved ? rupiah($oilSaved->subtotal()) : 'Belum ada' }}">{{ $oilSaved ? rupiah($oilSaved->subtotal()) : 'Belum ada' }}</span>
    </div>
    @if($oilSaved)
        <form method="POST" action="{{ route('pos.hapusOil', $qParams) }}" style="margin-top:8px;" data-confirm="Hapus stok minyak tanggal ini?">
            @csrf
            <button type="submit" class="btn btn-outline-danger">Hapus</button>
        </form>
    @endif
</div>

<div class="card sub-section">
    <h3>Manajemen stok barang (per pemegang / gudang)</h3>
    <form method="POST" action="{{ route('pos.simpanStockMgmt', $qParams) }}" id="stockMgmtFormEl">
        @csrf
        <input type="hidden" name="stockMgmtSacks" id="stockMgmtSacks" value="">
        <div class="field-row">
            <div class="field"><label for="stockMgmtName">Nama</label><input type="text" id="stockMgmtName" name="stockMgmtName" value="{{ $stockMgmtName }}" placeholder="Contoh: gun, imam" list="stockHolderNames"></div>
            <datalist id="stockHolderNames">
                @foreach ($stockMgmtHolderNames as $n)
                    <option value="{{ $n }}"></option>
                @endforeach
            </datalist>
            <div class="field"><label for="stockMgmtPrice">Harga per kg (Rp)</label><input type="text" inputmode="numeric" id="stockMgmtPrice" name="stockMgmtPrice" data-money min="0" value="{{ $stockMgmtPrice }}"></div>
        </div>
        @error('stockMgmtName') <div class="field-error">{{ $message }}</div> @enderror
        <div class="sub-section" style="margin-top:10px;" id="sackRows">
            <div class="form-group-label">Kg per karung</div>
            <div class="sack-row" data-sack-row>
                <input type="number" min="0" step="0.5" class="sack-qty-input" placeholder="kg karung">
                <button type="button" class="btn btn-outline-danger btn-sm sack-remove-btn" data-sack-remove>Hapus</button>
            </div>
            <button type="button" class="btn btn-outline-secondary" style="margin-top:4px;" data-sack-add>+ Tambah karung</button>
        </div>
        @error('stockMgmtSacks') <div class="field-error">{{ $message }}</div> @enderror
        <button type="submit" class="btn btn-primary" style="margin-top:8px;">Tambah ke stok</button>
    </form>

    <div class="table-wrap" style="margin-top:14px;">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    @for ($i = 1; $i <= $mgmtMaxSak; $i++)
                        <th class="num" data-sak-th>Sak {{ $i }}</th>
                    @endfor
                    <th class="num">Total (kg)</th>
                    <th class="num">Harga</th>
                    <th class="num">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mgmtRows as $r)
                    <tr data-mgmt-row="{{ $r['id'] }}">
                        <td><strong>{{ $r['name'] }}</strong></td>
                        @for ($si = 0; $si < $mgmtMaxSak; $si++)
                            <td class="num" data-mgmt-cell>
                                <input type="number" min="0" step="0.5" class="sack-cell" data-sack-input
                                       value="{{ $r['sacks'][$si] ?? '' }}" data-val="{{ $r['sacks'][$si] ?? '' }}" placeholder="kg">
                            </td>
                        @endfor
                        <td class="num" data-mgmt-total>{{ fmtKg($r['totalQty']) }} kg</td>
                        <td class="num" data-mgmt-price>{{ rupiah($r['price']) }}</td>
                        <td class="num" data-mgmt-sub>{{ rupiah($r['subtotal']) }}</td>
                        <td>
                            <div class="row-actions">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-mgmt-add-sack title="Tambah sak baru">+ Sak</button>
                                <form method="POST" action="{{ route('pos.hapusStockMgmt', $r['id']) }}" style="display:inline;" data-confirm="Hapus data ini?">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $mgmtMaxSak + 5 }}" class="empty">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="summary-line total" style="margin-top:10px;"><span>Total manajemen stok barang</span><span class="val" id="mgmtGrand">{{ rupiah($mgmtGrand) }}</span></div>
</div>

<div class="card sub-section">
    <h3>Sisa barang hari ini</h3>
    <form method="POST" action="{{ route('pos.simpanRemain', $qParams) }}">
        @csrf
        <div class="field-row">
            <div class="field">
                <label for="remainName">Produk</label>
                <input type="text" id="remainName" name="remainName" value="{{ $remainName }}" placeholder="Nama produk bebas">
            </div>
            <div class="field"><label for="remainQty">Jumlah (kg)</label><input type="number" id="remainQty" name="remainQty" min="0" step="0.5" value="{{ $remainQty }}"></div>
            <div class="field"><label for="remainPrice">Harga (Rp)</label><input type="text" inputmode="numeric" id="remainPrice" name="remainPrice" data-money min="0" value="{{ $remainPrice }}"></div>
        </div>
        @error('remainName') <div class="field-error">{{ $message }}</div> @enderror
        @error('remainQty') <div class="field-error">{{ $message }}</div> @enderror
        <button type="submit" class="btn btn-primary">Tambah</button>
    </form>
    <div class="table-wrap" style="margin-top:10px;">
        <table>
            <thead><tr><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th><th></th></tr></thead>
            <tbody>
                @forelse ($remainRows as $r)
                    <tr data-remain-row="{{ $r['id'] }}">
                        <td><input type="text" class="cell-edit cell-name" data-remain="name" data-val="{{ $r['name'] }}" value="{{ $r['name'] }}" placeholder="Nama produk"></td>
                        <td class="num"><input type="number" min="0" step="0.5" class="sack-cell" data-remain="qty" data-val="{{ $r['qty'] }}" value="{{ $r['qty'] }}" placeholder="kg"></td>
                        <td class="num"><input type="text" inputmode="numeric" min="0" class="sack-cell" data-remain="price" data-money data-val="{{ number_format($r['price'], 0, ',', '.') }}" value="{{ number_format($r['price'], 0, ',', '.') }}" placeholder="0"></td>
                        <td class="num" data-remain-sub>{{ rupiah($r['qty'] * $r['price']) }}</td>
                        <td>
                            <form method="POST" action="{{ route('pos.hapusRemain', $r['id']) }}" style="display:inline;" data-confirm="Hapus data ini?">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="summary-line total"><span>Total sisa barang hari ini</span><span class="val" id="remainGrand">{{ rupiah($remainGrand) }}</span></div>
</div>

{{-- 3. HUTANG PRIBADI --}}
<div class="section-header">
    <h2>3. Hutang Pribadi</h2>
</div>

<div class="card">
    <h2>Catat hutang pribadi</h2>
    <form method="POST" action="{{ route('pos.simpanHutangPribadi', $qParams) }}">
        @csrf
        <div class="field-row">
            <div class="field"><label for="hprName">Nama</label><input type="text" id="hprName" name="hprName" value="{{ old('hprName') }}">
                @error('hprName') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field"><label for="hprAmount">Jumlah (Rp)</label><input type="text" inputmode="numeric" id="hprAmount" name="hprAmount" data-money min="0" value="{{ old('hprAmount') }}">
                @error('hprAmount') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
    </form>
</div>
<div class="card">
    <h2>Daftar hutang pribadi (hari ini)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th class="num">Jumlah</th><th></th></tr></thead>
            <tbody>
                @forelse ($hprRows as $r)
                    <tr data-hpr-row="{{ $r['id'] }}">
                        <td><input type="text" class="cell-edit cell-name" data-hpr="name" data-val="{{ $r['name'] }}" value="{{ $r['name'] }}" placeholder="Nama"></td>
                        <td class="num"><input type="text" inputmode="numeric" min="0" class="sack-cell" data-hpr="amount" data-money data-val="{{ number_format($r['amount'], 0, ',', '.') }}" value="{{ number_format($r['amount'], 0, ',', '.') }}" placeholder="0"></td>
                        <td>
                            <form method="POST" action="{{ route('pos.hapusPersonalLedger', $r['id']) }}" style="display:inline;" data-confirm="Hapus hutang pribadi ini?">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada catatan hutang pribadi hari ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="summary-line total"><span>Total hutang pribadi hari ini</span><span class="val" id="hprGrand">{{ rupiah($hprGrand) }}</span></div>
</div>

{{-- 4. PENGURANGAN SALDO --}}
<div class="section-header">
    <h2>4. Pengurangan Saldo</h2>
</div>

<div class="card">
    <h2>Pengurangan saldo</h2>
    <form method="POST" action="{{ route('pos.simpanSaldo', $qParams) }}">
        @csrf
        <div class="field-row">
        <div class="field"><label for="saldoA">Angka A</label><input type="number" id="saldoA" name="saldoA" step="1" value="{{ old('saldoA', $saldoTerakhir?->a) }}">@error('saldoA') <div class="field-error">{{ $message }}</div> @enderror</div>
        <div class="field"><label for="saldoB">Angka B</label><input type="number" id="saldoB" name="saldoB" step="1" value="{{ old('saldoB', $saldoTerakhir?->b) }}">@error('saldoB') <div class="field-error">{{ $message }}</div> @enderror</div>
            <div class="field" style="flex:0; align-self:flex-end;"><button type="submit" class="btn btn-primary">Simpan</button></div>
        </div>
    </form>
    <div class="summary-line total" style="margin-top:12px;">
        <span>Hasil pengurangan (A&minus;B)</span>
        <span class="val">{{ $saldoLogs->isNotEmpty() ? number_format($saldoHari, 0, ',', '.') : 'Belum ada' }}</span>
    </div>
    @if($saldoLogs->isNotEmpty())
        <form method="POST" action="{{ route('pos.hapusSaldo', $qParams) }}" style="margin-top:8px;" data-confirm="Hapus hasil pengurangan saldo tanggal ini?">
            @csrf
            <button type="submit" class="btn btn-outline-danger">Hapus</button>
        </form>
    @endif
</div>

{{-- RINGKASAN HARI INI — baris, tanpa card, paling bawah, lebar penuh (rata kanan kiri) --}}
<div class="section-header" style="margin-top:24px;">
    <h2>Ringkasan Hari Ini</h2>
</div>

<div>
    <div class="summary-line"><span>Stok minyak</span><span class="val">{{ rupiah($oilStocks->sum(fn ($o) => $o->qty * $o->price)) }}</span></div>
    <div class="summary-line"><span>Manajemen stok barang</span><span class="val">{{ rupiah($stockMgmts->sum(fn ($o) => $o->subtotal())) }}</span></div>
    <div class="summary-line"><span>Sisa barang</span><span class="val">{{ rupiah($stockRemainings->sum(fn ($o) => $o->qty * $o->price)) }}</span></div>
    <div class="summary-line"><span>Hutang pelanggan (piutang toko)</span><span class="val">{{ rupiah($hpGrandTotal) }}</span></div>
    <div class="summary-line"><span>Hutang pribadi</span><span class="val">{{ rupiah($hprHari) }}</span></div>
    <div class="summary-line"><span>Pengurangan saldo</span><span class="val">{{ number_format($saldoHari, 0, ',', '.') }}</span></div>
    <div class="summary-line total" title="Stok minyak + total hutang pelanggan + stok barang + sisa barang">
        <span>Total aset hari ini</span>
        <span class="val">{{ rupiah($asetHari) }}</span>
    </div>
    <div class="summary-line total" title="Total aset hari ini &minus; total hutang pribadi">
        <span>Saldo (Total aset - Hutang pribadi)</span>
        <span class="val">{{ rupiah($asetHari - $hprHari) }}</span>
    </div>
    <div class="summary-line total" title="Total aset hari ini &minus; pengurangan saldo &minus; hutang pribadi">
        <span>Total (Total aset - Pengurangan saldo)</span>
        <span class="val">{{ rupiah($asetHari - $saldoHari - $hprHari) }}</span>
    </div>
</div>

<script>
// — Format angka (pasangan PHP: rupiah()/fmtKg() di app/Support/helpers.php) —
function fmtKgJs(value) {
    const n = parseFloat(value) || 0;
    return n % 1 === 0 ? String(n) : String(n).replace('.', ',');
}
function rupiahJs(value) {
    return 'Rp' + Math.round(parseFloat(value) || 0).toLocaleString('id-ID');
}
function parseAmount(v) {
    return parseFloat(String(v == null ? '' : v).replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
}
// Nominal uang: digit-only (pasangan parseRupiah di PHP) — "15.000" -> 15000.
// Qty kg tetap pakai parseAmount (koma desimal "2,5").
function parseMoney(v) {
    return parseFloat(String(v == null ? '' : v).replace(/\D+/g, '')) || 0;
}
function moneyFmtJs(v) {
    return window.posMoney ? window.posMoney.fmt(v) : String(v);
}

// — Stok minyak: subtotal live (pasangan OilStock::subtotal()) —
(function () {
    const q = document.getElementById('oilQty');
    const p = document.getElementById('oilPrice');
    const out = document.getElementById('oilCalc');
    if (!q || !p || !out) return;
    const initial = out.getAttribute('data-initial') || 'Belum ada';
    function calc() {
        if (q.value === '' || p.value === '') { out.textContent = initial; return; }
        out.textContent = 'Rp' + Math.round((parseFloat(q.value) || 0) * parseMoney(p.value)).toLocaleString('id-ID');
    }
    q.addEventListener('input', calc);
    p.addEventListener('input', calc);
})();

// — Form karung: tambah/hapus baris + submit sebagai JSON (stockMgmtSacks) —
(function () {
    const form = document.getElementById('stockMgmtFormEl');
    const rowsBox = document.getElementById('sackRows');
    if (!form || !rowsBox) return;
    const addBtn = rowsBox.querySelector('[data-sack-add]');
    function addRow() {
        const div = document.createElement('div');
        div.className = 'sack-row';
        div.setAttribute('data-sack-row', '');
        div.innerHTML = '<input type="number" min="0" step="0.5" class="sack-qty-input" placeholder="kg karung">'
            + '<button type="button" class="btn btn-outline-danger btn-sm sack-remove-btn" data-sack-remove>Hapus</button>';
        rowsBox.insertBefore(div, addBtn);
        div.querySelector('input').focus();
    }
    rowsBox.addEventListener('click', function (e) {
        if (e.target.closest('[data-sack-add]')) { addRow(); return; }
        if (e.target.closest('[data-sack-remove]')) {
            const row = e.target.closest('[data-sack-row]');
            const all = rowsBox.querySelectorAll('[data-sack-row]');
            if (all.length <= 1) row.querySelector('input').value = '';
            else row.remove();
        }
    });
    form.addEventListener('submit', function () {
        const vals = Array.prototype.map
            .call(rowsBox.querySelectorAll('[data-sack-row] input'), function (i) { return i.value; })
            .filter(function (v) { return parseFloat(v) > 0; });
        document.getElementById('stockMgmtSacks').value = JSON.stringify(vals);
    });
})();

// — Tabel Manajemen stok: auto-save sel, + Sak, grand total —
function mgmtRecountGrand() {
    let sum = 0;
    document.querySelectorAll('[data-mgmt-sub]').forEach(function (td) {
        sum += parseFloat(String(td.textContent).replace(/\D+/g, '')) || 0;
    });
    const g = document.getElementById('mgmtGrand');
    if (g) g.textContent = rupiahJs(sum);
}
function mgmtRevert(tr) {
    tr.querySelectorAll('input[data-sack-input]').forEach(function (inp) {
        if (inp.dataset.val !== undefined) inp.value = inp.dataset.val;
    });
}
function mgmtSave(tr) {
    const id = tr.getAttribute('data-mgmt-row');
    const inputs = Array.prototype.slice.call(tr.querySelectorAll('input[data-sack-input]'));
    const payload = inputs.map(function (i) { return i.value; })
        .filter(function (v) { return v !== '' && v != null && parseFloat(v) > 0; });
    posJson('/pos/stock-mgmt/' + id + '/sacks', { sacks: payload })
        .then(function (data) {
            if (!data || !data.ok) throw new Error('bad response');
            const sacks = data.sacks || [];
            const cells = Array.prototype.slice.call(tr.querySelectorAll('td[data-mgmt-cell]'));
            const totalTd = tr.querySelector('[data-mgmt-total]');
            while (cells.length < sacks.length) {
                const td = document.createElement('td');
                td.className = 'num';
                td.setAttribute('data-mgmt-cell', '');
                td.innerHTML = '<input type="number" min="0" step="0.5" class="sack-cell" data-sack-input placeholder="kg">';
                tr.insertBefore(td, totalTd);
                cells.push(td);
            }
            cells.forEach(function (td, si) {
                const inp = td.querySelector('input');
                const v = sacks[si];
                inp.value = (v === undefined || v === null) ? '' : v;
                inp.dataset.val = inp.value;
            });
            // Kolom Sak tanpa isi dihapus — jumlah kolom = jumlah karung terisi.
            while (cells.length > sacks.length) {
                tr.removeChild(cells.pop());
            }
            mgmtSyncHeaders();
            tr.querySelector('[data-mgmt-total]').textContent = fmtKgJs(data.totalQty) + ' kg';
            tr.querySelector('[data-mgmt-price]').textContent = rupiahJs(data.price);
            tr.querySelector('[data-mgmt-sub]').textContent = rupiahJs(data.subtotal);
            mgmtRecountGrand();
        })
        .catch(function () {
            mgmtRevert(tr);
            if (window.posToast) posToast('Gagal menyimpan stok manajemen.');
        });
}
function mgmtAddSack(tr) {
    const totalTd = tr.querySelector('[data-mgmt-total]');
    const td = document.createElement('td');
    td.className = 'num';
    td.setAttribute('data-mgmt-cell', '');
    td.innerHTML = '<input type="number" min="0" step="0.5" class="sack-cell" data-sack-input placeholder="kg">';
    tr.insertBefore(td, totalTd);
    mgmtSyncHeaders();
    td.querySelector('input').focus();
}
// Samakan jumlah header "Sak N" dengan kolom sak terlebar di seluruh baris.
function mgmtSyncHeaders() {
    let maxCells = 0;
    document.querySelectorAll('tr[data-mgmt-row]').forEach(function (r) {
        maxCells = Math.max(maxCells, r.querySelectorAll('td[data-mgmt-cell]').length);
    });
    let ths = document.querySelectorAll('thead [data-sak-th]');
    while (ths.length > maxCells) {
        ths[ths.length - 1].remove();
        ths = document.querySelectorAll('thead [data-sak-th]');
    }
    const tr = document.querySelector('thead tr');
    while (ths.length < maxCells) {
        const th = document.createElement('th');
        th.className = 'num';
        th.setAttribute('data-sak-th', '');
        th.textContent = 'Sak ' + (ths.length + 1);
        // sisipkan setelah header terakhir saat ini (atau setelah "Nama" bila belum ada)
        const anchor = ths.length ? ths[ths.length - 1] : tr.children[0];
        anchor.insertAdjacentElement('afterend', th);
        ths = document.querySelectorAll('thead [data-sak-th]');
    }
}

// — Tabel Sisa barang: auto-save + grand —
function remainRecountGrand() {
    let sum = 0;
    document.querySelectorAll('tr[data-remain-row]').forEach(function (tr) {
        const qty = parseAmount(tr.querySelector('input[data-remain="qty"]').value);
        const price = parseMoney(tr.querySelector('input[data-remain="price"]').value);
        sum += qty * price;
    });
    const g = document.getElementById('remainGrand');
    if (g) g.textContent = rupiahJs(sum);
}
function remainSave(tr) {
    const id = tr.getAttribute('data-remain-row');
    const fields = {};
    ['name', 'qty', 'price'].forEach(function (k) {
        const inp = tr.querySelector('input[data-remain="' + k + '"]');
        // price (uang) dikirim digit-only; qty/name apa adanya
        fields[k] = inp ? (k === 'price' ? (window.posMoney ? posMoney.digits(inp.value) : inp.value) : inp.value) : '';
    });
    posJson('/pos/remain/' + id + '/update', fields)
        .then(function (data) {
            if (!data || !data.ok) throw new Error('bad response');
            ['name', 'qty', 'price'].forEach(function (k) {
                const inp = tr.querySelector('input[data-remain="' + k + '"]');
                if (data[k] !== undefined && data[k] !== null && data[k] !== '') {
                    inp.value = k === 'price' ? moneyFmtJs(data[k]) : data[k];
                }
                inp.dataset.val = inp.value;
            });
            tr.querySelector('[data-remain-sub]').textContent =
                rupiahJs((parseFloat(data.qty) || 0) * (parseFloat(data.price) || 0));
            remainRecountGrand();
        })
        .catch(function () {
            tr.querySelectorAll('input[data-remain]').forEach(function (inp) {
                if (inp.dataset.val !== undefined) inp.value = inp.dataset.val;
            });
            if (window.posToast) posToast('Gagal menyimpan sisa barang.');
        });
}

// — Tabel Hutang pribadi: auto-save + grand —
function hprRecountGrand() {
    let sum = 0;
    document.querySelectorAll('tr[data-hpr-row]').forEach(function (tr) {
        sum += parseMoney(tr.querySelector('input[data-hpr="amount"]').value);
    });
    const g = document.getElementById('hprGrand');
    if (g) g.textContent = rupiahJs(sum);
}
function hprSave(tr) {
    const id = tr.getAttribute('data-hpr-row');
    const fields = {};
    ['name', 'amount'].forEach(function (k) {
        const inp = tr.querySelector('input[data-hpr="' + k + '"]');
        // amount (uang) dikirim digit-only; name apa adanya
        fields[k] = inp ? (k === 'amount' ? (window.posMoney ? posMoney.digits(inp.value) : inp.value) : inp.value) : '';
    });
    posJson('/pos/hpr/' + id + '/update', fields)
        .then(function (data) {
            if (!data || !data.ok) throw new Error('bad response');
            ['name', 'amount'].forEach(function (k) {
                const inp = tr.querySelector('input[data-hpr="' + k + '"]');
                if (data[k] !== undefined && data[k] !== null && data[k] !== '') {
                    inp.value = k === 'amount' ? moneyFmtJs(data[k]) : data[k];
                }
                inp.dataset.val = inp.value;
            });
            hprRecountGrand();
        })
        .catch(function () {
            tr.querySelectorAll('input[data-hpr]').forEach(function (inp) {
                if (inp.dataset.val !== undefined) inp.value = inp.dataset.val;
            });
            if (window.posToast) posToast('Gagal menyimpan hutang pribadi.');
        });
}

// — Edit inline rekap hutang pelanggan (grid Excel #hpSummaryTable): fetch ke
//   route /pos/* (HutangPelangganController::adjustDebtCell/adjustTotalDebt/hapusLedgerEntry) —
const HP_TX = @js($txDate);
function hpPost(url, fields) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' },
        body: new URLSearchParams(fields),
    }).then(res => res.json());
}
function hpSyncRow(tr, totalSisa) {
    tr.dataset.total = totalSisa;
    const cell = tr.querySelector('.hp-total-cell .hp-amt');
    if (cell) cell.textContent = rupiahJs(totalSisa);
    let sum = 0;
    document.querySelectorAll('#hpSummaryTable tbody tr[data-total]').forEach(r => { sum += parseFloat(r.dataset.total) || 0; });
    const grand = document.getElementById('hpGrandTotal');
    if (grand) grand.textContent = rupiahJs(sum);
}
function hpCellInner(d) {
    const v = moneyFmtJs(d.remaining);
    return '<span class="hp-amt" title="Klik untuk edit — ' + d.date + '">' + rupiahJs(d.remaining) + '</span>'
        + '<input class="hp-inp" type="text" inputmode="numeric" data-money value="' + v + '" data-val="' + v + '">';
}
function hpRenderCells(tr, debts) {
    const tds = Array.from(tr.querySelectorAll('td.hp-cell')).filter(td => !td.classList.contains('hp-total-cell'));
    if (debts.length > tds.length) { location.reload(); return false; }
    tds.forEach((td, i) => {
        const d = debts[i];
        if (d) {
            td.className = 'num hp-cell hp-debt-cell';
            td.dataset.debtId = d.id;
            td.innerHTML = hpCellInner(d);
        } else {
            td.className = 'num hp-cell empty-cell';
            delete td.dataset.debtId;
            td.innerHTML = '&ndash;';
        }
    });
    return true;
}
function hpApply(tr, data) {
    if (!data || !data.ok) return false;
    const ok = hpRenderCells(tr, data.activeDebts || []);
    if (ok) hpSyncRow(tr, data.totalSisa);
    return ok;
}
function hpOpenInput(td) {
    const inp = td.querySelector('.hp-inp');
    if (!inp || td.classList.contains('editing')) return;
    td.classList.add('editing');
    inp.focus();
    if (typeof inp.setSelectionRange === 'function') inp.setSelectionRange(inp.value.length, inp.value.length);
}
function hpCloseInput(td) { td.classList.remove('editing'); }

// Delegasi event: sel tabel auto-save & tombol aksi (semua via data-*, tanpa Alpine)
document.addEventListener('click', function (e) {
    const amt = e.target.closest('#hpSummaryTable .hp-amt');
    if (amt) { hpOpenInput(amt.closest('td')); return; }
    const nm = e.target.closest('#hpSummaryTable .hp-name');
    if (nm) { hpOpenInput(nm.closest('td')); return; }
    const addSack = e.target.closest('[data-mgmt-add-sack]');
    if (addSack) { mgmtAddSack(addSack.closest('tr')); }
});
document.addEventListener('change', function (e) {
    if (e.target.matches('input[data-sack-input]')) { mgmtSave(e.target.closest('tr')); return; }
    if (e.target.matches('input[data-remain]')) { remainSave(e.target.closest('tr')); return; }
    if (e.target.matches('input[data-hpr]')) { hprSave(e.target.closest('tr')); }
});
document.addEventListener('focusout', function (e) {
    const inp = e.target;
    if (!inp.matches || !inp.matches('#hpSummaryTable .hp-inp')) return;
    const td = inp.closest('td');
    if (td.dataset.hpCancel === '1') { td.dataset.hpCancel = '0'; hpCloseInput(td); inp.value = inp.dataset.val !== undefined ? inp.dataset.val : inp.value; return; }
    const old = inp.dataset.val !== undefined ? inp.dataset.val : inp.value;
    const val = inp.value.trim();
    hpCloseInput(td);
    if (val === String(old)) { inp.value = old; return; }

    if (td.classList.contains('hp-name-cell')) {
        if (val === '') { inp.value = old; return; }
        const tr = td.closest('tr');
        hpPost('/pos/hp/rename', { old_name: tr.dataset.name, new_name: val })
            .then(data => {
                if (!data.ok) { if (window.posToast) posToast(data.message || 'Gagal mengubah nama.'); inp.value = old; return; }
                tr.dataset.name = data.name;
                td.querySelector('.hp-name').textContent = data.name;
                inp.dataset.val = data.name;
            })
            .catch(() => { inp.value = old; if (window.posToast) posToast('Gagal mengubah nama.'); });
        return;
    }
    if (td.classList.contains('hp-total-cell')) {
        if (val === '') { inp.value = old; return; }
        hpPost('/pos/hp/adjust-total', { name: td.closest('tr').dataset.name, new_total: (window.posMoney ? posMoney.digits(val) : val), tx_date: HP_TX })
            .then(data => {
                if (!data.ok) { inp.value = old; if (window.posToast) posToast('Gagal mengubah total.'); return; }
                location.reload();
            })
            .catch(() => { inp.value = old; if (window.posToast) posToast('Gagal mengubah total.'); });
        return;
    }
    const id = td.dataset.debtId;
    if (!id) return;
    if (val === '') { inp.value = old; return; }
    hpPost('/pos/hp/adjust-cell', { debt_id: id, new_remaining: (window.posMoney ? posMoney.digits(val) : val), tx_date: HP_TX })
        .then(data => { if (!hpApply(td.closest('tr'), data) && window.posToast) posToast('Gagal menyimpan sel hutang.'); })
        .catch(() => { if (window.posToast) posToast('Gagal menyimpan sel hutang.'); });
});
document.addEventListener('keydown', function (e) {
    const inp = e.target;
    if (e.key !== 'Enter') return;
    if (inp.matches && inp.matches('#hpSummaryTable .hp-inp')) { e.preventDefault(); inp.blur(); return; }
    if (inp.matches && inp.matches('input[data-sack-input], input[data-remain], input[data-hpr]')) { e.preventDefault(); inp.blur(); }
});
// Setelah pos-ui.js memformat [data-money] saat load, samakan data-val (cadangan
// edit inline) dengan nilai tampil berformat supaya perbandingan blur tidak salah.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#hpSummaryTable .hp-inp[data-money], input[data-remain][data-money], input[data-hpr][data-money]')
        .forEach(function (inp) { inp.dataset.val = inp.value; });
});
</script>
