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
@endphp

{{-- 1. HUTANG PELANGGAN --}}
<div class="section-header">
    <h2>1. Rekap Hutang Pelanggan</h2>
    <p>Rekap menampilkan posisi hutang pelanggan pada <strong>tanggal aktif</strong> &mdash; bersifat kumulatif dalam lembar tanggal itu. Seperti Excel: <strong>klik angka</strong> untuk mengedit langsung di selnya, tombol <strong>&times;</strong> pada sel untuk menghapus entri itu, klik nama/total untuk mengubahnya, dan tombol <strong>+</strong> paling kanan baris untuk menambah hutang pelanggan itu. Transaksi &amp; pembayaran di tab Transaksi <strong>tidak</strong> menambah/mengurangi hutang di tabel ini secara otomatis.</p>
</div>

<div class="card">
    <h2>Rekap hutang per pelanggan</h2>
    <div class="hp-toolbar">
        <a href="{{ route('pos', array_merge($qParams, ['debt_modal' => 1, 'debt_name' => ''])) }}" class="primary btn-plus">
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
                                    <button type="button" class="cell-del" data-id="{{ $d['id'] }}" title="Hapus entri ini">&times;</button>
                                    <input class="hp-inp" type="number" min="0" step="500" value="{{ $d['remaining'] }}" data-val="{{ $d['remaining'] }}">
                                </td>
                            @else
                                <td class="num hp-cell empty-cell">&ndash;</td>
                            @endif
                        @endfor
                        <td class="num hp-cell hp-total-cell">
                            <span class="hp-amt hp-total-amt" title="Klik untuk ubah total">{{ rupiah($p['totalSisa']) }}</span>
                            <input class="hp-inp" type="number" min="0" step="500" value="{{ $p['totalSisa'] }}" data-val="{{ $p['totalSisa'] }}">
                        </td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end; align-items:center;">
                                <a class="btn-icon" href="{{ route('pos', array_merge($qParams, ['debt_modal' => 1, 'debt_name' => $name])) }}"
                                   title="Tambah hutang baru untuk {{ $name }}" aria-label="Tambah hutang baru">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </a>
                                <form method="POST" action="{{ route('pos.hapusCustomerLedger', array_merge($qParams, ['name' => $name])) }}" style="display:inline;" onsubmit="return confirm('Hapus semua riwayat hutang pelanggan ini di tanggal ini? Tindakan ini tidak bisa dibatalkan.')">
                                    @csrf
                                    <button type="submit" class="ghost danger">Hapus</button>
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
                <thead><tr><th>Tanggal</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Dibayar</th><th class="num">Saldo berjalan</th><th>Catatan</th><th></th></tr></thead>
                <tbody>
                    @forelse ($hpEntries as $entry)
                        <tr>
                            <td>{{ $entry->date }}</td>
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
                                        <button type="submit" class="ghost">Lihat Transaksi</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('pos.hapusLedgerEntry', $entry->id) }}" style="display:inline;" onsubmit="return confirm('Hapus entri ini?')">
                                    @csrf
                                    <button type="submit" class="ghost danger">Hapus</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <a href="{{ route('pos', array_merge($qParams)) }}" class="ghost" style="margin-top:8px;text-decoration:none;">Tutup detail</a>
    </div>
@endif

{{-- 2. STOK BARANG --}}
<div class="section-header">
    <h2>2. Stok Barang</h2>
    <p>Data pada bagian ini menampilkan data <strong>tanggal aktif</strong> yang dipilih di bagian atas halaman.</p>
</div>

<div class="card sub-section" x-data="{ qty: '{{ old('oilQty', $oilSaved->qty ?? '') }}', price: '{{ old('oilPrice', $oilSaved->price ?? '') }}' }">
    <h3>Stok minyak</h3>
    <form method="POST" action="{{ route('pos.simpanOil', $qParams) }}">
        @csrf
        <div class="field-row">
            <div class="field"><label for="oilQty">Jumlah (liter/kg)</label><input type="number" id="oilQty" name="oilQty" min="0" step="0.5" x-model="qty"></div>
            <div class="field"><label for="oilPrice">Harga satuan (Rp)</label><input type="number" id="oilPrice" name="oilPrice" min="0" step="500" x-model="price"></div>
            <div class="field" style="flex:0; align-self:flex-end;"><button type="submit" class="primary">Simpan</button></div>
        </div>
    </form>
    @error('oilQty') <div class="field-error">{{ $message }}</div> @enderror
    <div class="summary-line total" style="margin-top:12px;">
        <span>Hasil perkalian (jumlah &times; harga)</span>
        <span class="val" x-text="(qty !== '' && price !== '') ? 'Rp' + Math.round(qty * price).toLocaleString('id-ID') : '{{ $oilSaved ? rupiah($oilSaved->subtotal()) : 'Belum ada' }}'"></span>
    </div>
    @if($oilSaved)
        <form method="POST" action="{{ route('pos.hapusOil', $qParams) }}" style="margin-top:8px;" onsubmit="return confirm('Hapus stok minyak tanggal ini?')">
            @csrf
            <button type="submit" class="ghost danger">Hapus</button>
        </form>
    @endif
</div>

<div class="card sub-section" x-data="mgmtTable({{ json_encode($mgmtRows) }})">
    <h3>Manajemen stok barang (per pemegang / gudang)</h3>
    <form method="POST" action="{{ route('pos.simpanStockMgmt', $qParams) }}" id="stockMgmtFormEl" x-data="stockMgmtForm()">
        @csrf
        <input type="hidden" name="stockMgmtSacks" :value="JSON.stringify(sacks.filter(v => parseFloat(v) > 0))">
        <div class="field-row">
            <div class="field"><label for="stockMgmtName">Nama</label><input type="text" id="stockMgmtName" name="stockMgmtName" value="{{ $stockMgmtName }}" placeholder="Contoh: gun, imam" list="stockHolderNames"></div>
            <datalist id="stockHolderNames">
                @foreach ($stockMgmtHolderNames as $n)
                    <option value="{{ $n }}"></option>
                @endforeach
            </datalist>
            <div class="field"><label for="stockMgmtPrice">Harga per kg (Rp)</label><input type="number" id="stockMgmtPrice" name="stockMgmtPrice" value="{{ $stockMgmtPrice }}" min="0" step="500"></div>
        </div>
        @error('stockMgmtName') <div class="field-error">{{ $message }}</div> @enderror
        <div class="sub-section" style="margin-top:10px;">
            <div class="form-group-label">Kg per karung (boleh lebih dari satu karung)</div>
            <template x-for="(sack, index) in sacks" :key="index">
                <div class="sack-row">
                    <input type="number" min="0" step="0.5" x-model="sacks[index]" :id="'sackQty' + index" class="sack-qty-input" placeholder="kg karung">
                    <button type="button" class="ghost danger sack-remove-btn" @click="sacks.splice(index,1); if(sacks.length===0) sacks.push('')">Hapus</button>
                </div>
            </template>
            <button type="button" class="ghost" style="margin-top:4px;" @click="sacks.push('')">+ Tambah karung</button>
        </div>
        @error('stockMgmtSacks') <div class="field-error">{{ $message }}</div> @enderror
        <button type="submit" class="primary" style="margin-top:8px;">Tambah ke stok</button>
    </form>

    <div class="table-wrap" style="margin-top:14px;">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama</th>
                    <template x-for="i in maxSak" :key="'h' + i">
                        <th class="num" x-text="'Sak ' + i"></th>
                    </template>
                    <th class="num">Total (kg)</th>
                    <th class="num">Harga</th>
                    <th class="num">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(r, ri) in rows" :key="r.id">
                    <tr>
                        <td x-text="r.date"></td>
                        <td><strong x-text="r.name"></strong></td>
                        <template x-for="(v, si) in maxSak" :key="r.id + '-c' + si">
                            <td class="num">
                                <input type="number" min="0" step="0.5" class="sack-cell"
                                       :data-mgmt-row="r.id"
                                       :value="(r.sacks[si] ?? '') === '' ? '' : r.sacks[si]"
                                       placeholder="kg"
                                       @change="setSack(r, si, $event.target.value)"
                                       @keydown.enter="$event.target.blur()">
                            </td>
                        </template>
                        <td class="num" x-text="fmtKgJs(r.totalQty) + ' kg'"></td>
                        <td class="num" x-text="rupiahJs(r.price)"></td>
                        <td class="num" x-text="rupiahJs(r.subtotal)"></td>
                        <td>
                            <div class="row-actions">
                                <button type="button" class="ghost" @click="addSack(r)" title="Tambah sak baru">+ Sak</button>
                                <form method="POST" :action="'{{ url('/pos/stock-mgmt') }}/' + r.id + '/hapus'" style="display:inline;" onsubmit="return confirm('Hapus data ini?')">
                                    @csrf
                                    <button type="submit" class="ghost danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0"><td colspan="99" class="empty">Belum ada data.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="summary-line total" style="margin-top:10px;"><span>Total manajemen stok barang</span><span class="val" x-text="rupiahJs(grand)"></span></div>
</div>

<div class="card sub-section" x-data="remainTable({{ json_encode($remainRows) }})">
    <h3>Sisa barang hari ini</h3>
    <form method="POST" action="{{ route('pos.simpanRemain', $qParams) }}">
        @csrf
        <div class="field-row">
            <div class="field">
                <label for="remainName">Produk</label>
                <input type="text" id="remainName" name="remainName" value="{{ $remainName }}" placeholder="Nama produk bebas">
            </div>
            <div class="field"><label for="remainQty">Jumlah (kg)</label><input type="number" id="remainQty" name="remainQty" min="0" step="0.5" value="{{ $remainQty }}"></div>
            <div class="field"><label for="remainPrice">Harga (Rp)</label><input type="number" id="remainPrice" name="remainPrice" min="0" step="500" value="{{ $remainPrice }}"></div>
        </div>
        @error('remainName') <div class="field-error">{{ $message }}</div> @enderror
        @error('remainQty') <div class="field-error">{{ $message }}</div> @enderror
        <button type="submit" class="primary">Tambah</button>
    </form>
    <div class="table-wrap" style="margin-top:10px;">
        <table>
            <thead><tr><th>Tanggal</th><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th><th></th></tr></thead>
            <tbody>
                <template x-for="(r, ri) in rows" :key="r.id">
                    <tr>
                        <td x-text="r.date"></td>
                        <td><input type="text" class="cell-edit cell-name" :value="r.name" placeholder="Nama produk"
                                   @change="r.name = $event.target.value; save(r)"
                                   @keydown.enter="$event.target.blur()"></td>
                        <td class="num"><input type="number" min="0" step="0.5" class="sack-cell" :value="r.qty" placeholder="kg"
                                   @change="r.qty = $event.target.value; save(r)"
                                   @keydown.enter="$event.target.blur()"></td>
                        <td class="num"><input type="number" min="0" step="500" class="sack-cell" :value="r.price" placeholder="0"
                                   @change="r.price = $event.target.value; save(r)"
                                   @keydown.enter="$event.target.blur()"></td>
                        <td class="num" x-text="rupiahJs(r.qty * r.price)"></td>
                        <td>
                            <form method="POST" :action="'{{ url('/pos/remain') }}/' + r.id + '/hapus'" style="display:inline;" onsubmit="return confirm('Hapus data ini?')">
                                @csrf
                                <button type="submit" class="ghost danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0"><td colspan="6" class="empty">Belum ada data.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="summary-line total"><span>Total sisa barang hari ini</span><span class="val" x-text="rupiahJs(grand)"></span></div>
</div>

{{-- 3. HUTANG PRIBADI --}}
<div class="section-header">
    <h2>3. Hutang Pribadi</h2>
    <p>Data hutang pribadi mengikuti <strong>tanggal aktif</strong> yang dipilih di bagian atas halaman.</p>
</div>

<div class="card">
    <h2>Catat hutang pribadi</h2>
    <form method="POST" action="{{ route('pos.simpanHutangPribadi', $qParams) }}">
        @csrf
        <div class="field-row">
            <div class="field"><label for="hprName">Nama</label><input type="text" id="hprName" name="hprName" value="{{ old('hprName') }}">
                @error('hprName') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field"><label for="hprAmount">Jumlah (Rp)</label><input type="number" id="hprAmount" name="hprAmount" min="0" step="500" value="{{ old('hprAmount') }}">
                @error('hprAmount') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <button type="submit" class="primary">Simpan</button>
    </form>
</div>
<div class="card" x-data="hprTable({{ json_encode($hprRows) }})">
    <h2>Daftar hutang pribadi (hari ini)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tanggal</th><th>Nama</th><th class="num">Jumlah</th><th></th></tr></thead>
            <tbody>
                <template x-for="(r, ri) in rows" :key="r.id">
                    <tr>
                        <td x-text="r.date"></td>
                        <td><input type="text" class="cell-edit cell-name" :value="r.name" placeholder="Nama"
                                   @change="r.name = $event.target.value; save(r)"
                                   @keydown.enter="$event.target.blur()"></td>
                        <td class="num"><input type="number" min="0" step="500" class="sack-cell" :value="r.amount" placeholder="0"
                                   @change="r.amount = $event.target.value; save(r)"
                                   @keydown.enter="$event.target.blur()"></td>
                        <td>
                            <form method="POST" :action="'{{ url('/pos/hpr') }}/' + r.id + '/hapus'" style="display:inline;" onsubmit="return confirm('Hapus hutang pribadi ini?')">
                                @csrf
                                <button type="submit" class="ghost danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0"><td colspan="4" class="empty">Belum ada catatan hutang pribadi hari ini.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="summary-line total"><span>Total hutang pribadi hari ini</span><span class="val" x-text="rupiahJs(grand)"></span></div>
</div>

{{-- 4. PENGURANGAN SALDO --}}
<div class="section-header">
    <h2>4. Pengurangan Saldo</h2>
    <p>Isi Angka A dan Angka B &mdash; hanya <strong>hasil pengurangan (A&minus;B)</strong> yang ditampilkan, tersimpan satu nilai untuk tanggal aktif (simpan ulang menimpa nilai lama).</p>
</div>

<div class="card">
    <h2>Pengurangan saldo</h2>
    <form method="POST" action="{{ route('pos.simpanSaldo', $qParams) }}">
        @csrf
        <div class="field-row">
            <div class="field"><label for="saldoA">Angka A</label><input type="number" id="saldoA" name="saldoA" step="1" value="{{ old('saldoA', $saldoTerakhir?->a) }}"></div>
            <div class="field"><label for="saldoB">Angka B</label><input type="number" id="saldoB" name="saldoB" step="1" value="{{ old('saldoB', $saldoTerakhir?->b) }}"></div>
            <div class="field" style="flex:0; align-self:flex-end;"><button type="submit" class="primary">Simpan</button></div>
        </div>
    </form>
    <div class="summary-line total" style="margin-top:12px;">
        <span>Hasil pengurangan (A&minus;B)</span>
        <span class="val">{{ $saldoLogs->isNotEmpty() ? number_format($saldoHari, 0, ',', '.') : 'Belum ada' }}</span>
    </div>
    @if($saldoLogs->isNotEmpty())
        <form method="POST" action="{{ route('pos.hapusSaldo', $qParams) }}" style="margin-top:8px;" onsubmit="return confirm('Hapus hasil pengurangan saldo tanggal ini?')">
            @csrf
            <button type="submit" class="ghost danger">Hapus</button>
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
        <span>Saldo</span>
        <span class="val">{{ rupiah($asetHari - $hprHari) }}</span>
    </div>
    <div class="summary-line total" title="Total aset hari ini &minus; pengurangan saldo &minus; hutang pribadi">
        <span>Total</span>
        <span class="val">{{ rupiah($asetHari - $saldoHari - $hprHari) }}</span>
    </div>
</div>

<script>
function fmtKgJs(value) {
    const n = parseFloat(value) || 0;
    return n % 1 === 0 ? String(n) : String(n).replace('.', ',');
}
function rupiahJs(value) {
    return 'Rp' + Math.round(parseFloat(value) || 0).toLocaleString('id-ID');
}
function stockMgmtForm() {
    return {
        sacks: [''],
    };
}
function mgmtTable(rows) {
    return {
        rows: rows || [],
        get maxSak() {
            return Math.max(1, ...this.rows.map(r => r.sacks.length));
        },
        get grand() {
            return this.rows.reduce((sum, r) => sum + (parseFloat(r.subtotal) || 0), 0);
        },
        setSack(r, si, value) {
            while (r.sacks.length < si) r.sacks.push('');
            r.sacks[si] = value;
            this.save(r);
        },
        addSack(r) {
            r.sacks.push('');
            this.$nextTick(() => {
                const inputs = document.querySelectorAll('input[data-mgmt-row="' + r.id + '"]');
                if (inputs.length) inputs[inputs.length - 1].focus();
            });
        },
        save(r) {
            const payload = r.sacks.filter(v => v !== '' && v !== null && parseFloat(v) > 0);
            fetch('/pos/stock-mgmt/' + r.id + '/sacks', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ sacks: payload }),
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.ok) return;
                    r.sacks = data.sacks;
                    r.totalQty = data.totalQty;
                    r.subtotal = data.subtotal;
                })
                .catch(() => {});
        },
    };
}
function remainTable(rows) {
    return {
        rows: rows || [],
        get grand() {
            return this.rows.reduce((sum, r) => sum + (parseFloat(r.qty) || 0) * (parseFloat(r.price) || 0), 0);
        },
        save(r) {
            fetch('/pos/remain/' + r.id + '/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ name: r.name, qty: r.qty, price: r.price }),
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.ok) return;
                    r.name = data.name;
                    r.qty = data.qty;
                    r.price = data.price;
                })
                .catch(() => {});
        },
    };
}
function hprTable(rows) {
    return {
        rows: rows || [],
        get grand() {
            return this.rows.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);
        },
        save(r) {
            fetch('/pos/hpr/' + r.id + '/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ name: r.name, amount: r.amount }),
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.ok) return;
                    r.name = data.name;
                    r.amount = data.amount;
                })
                .catch(() => {});
        },
    };
}
const HP_TX = '{{ $txDate }}';
function hpPost(url, fields) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
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
    return '<span class="hp-amt" title="Klik untuk edit — ' + d.date + '">' + rupiahJs(d.remaining) + '</span>'
        + '<button type="button" class="cell-del" data-id="' + d.id + '" title="Hapus entri ini">&times;</button>'
        + '<input class="hp-inp" type="number" min="0" step="500" value="' + d.remaining + '" data-val="' + d.remaining + '">';
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
    if (inp.type === 'number') inp.select();
    else inp.setSelectionRange(inp.value.length, inp.value.length);
}
function hpCloseInput(td) { td.classList.remove('editing'); }
function hpDelEntry(id, tr, ask) {
    if (ask && !confirm('Hapus entri hutang ini dari tabel?')) return;
    hpPost('/pos/hp/' + id + '/hapus', { tx_date: HP_TX })
        .then(data => hpApply(tr, data))
        .catch(() => {});
}

document.addEventListener('click', function (e) {
    const del = e.target.closest('#hpSummaryTable .cell-del');
    if (del) {
        e.stopPropagation();
        hpDelEntry(del.dataset.id, del.closest('tr'), true);
        return;
    }
    const amt = e.target.closest('#hpSummaryTable .hp-amt');
    if (amt) { hpOpenInput(amt.closest('td')); return; }
    const nm = e.target.closest('#hpSummaryTable .hp-name');
    if (nm) { hpOpenInput(nm.closest('td')); }
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
                if (!data.ok) { alert(data.message || 'Gagal mengubah nama.'); inp.value = old; return; }
                tr.dataset.name = data.name;
                td.querySelector('.hp-name').textContent = data.name;
                inp.dataset.val = data.name;
            })
            .catch(() => {});
        return;
    }
    if (td.classList.contains('hp-total-cell')) {
        if (val === '') { inp.value = old; return; }
        hpPost('/pos/hp/adjust-total', { name: td.closest('tr').dataset.name, new_total: val, tx_date: HP_TX })
            .then(data => {
                if (!data.ok) { inp.value = old; return; }
                location.reload();
            })
            .catch(() => {});
        return;
    }
    const id = td.dataset.debtId;
    if (!id) return;
    if (val === '') { hpDelEntry(id, td.closest('tr'), false); return; }
    hpPost('/pos/hp/adjust-cell', { debt_id: id, new_remaining: val, tx_date: HP_TX })
        .then(data => hpApply(td.closest('tr'), data))
        .catch(() => {});
});
document.addEventListener('keydown', function (e) {
    const inp = e.target;
    if (!inp.matches || !inp.matches('#hpSummaryTable .hp-inp')) return;
    if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
    else if (e.key === 'Escape') { const td = inp.closest('td'); td.dataset.hpCancel = '1'; hpCloseInput(td); inp.blur(); }
});
</script>
