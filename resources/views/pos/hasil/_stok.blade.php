{{-- 2. STOK BARANG --}}
<div class="section-header">
    <h2>2. Stok Barang</h2>
</div>

<div class="card sub-section" id="stokMinyakCard">
    <h3>Stok minyak</h3>
    <form method="POST" action="{{ route('pos.simpanOil', $parameterQuery) }}">
        @csrf
        <div class="field-row">
            <div class="field"><label for="oilQty">Jumlah (liter/kg)</label><input type="number" id="oilQty" name="oilQty" min="0" step="0.5" value="{{ old('oilQty', $stokMinyakTersimpan->jumlah ?? '') }}"></div>
            <div class="field"><label for="oilPrice">Harga satuan (Rp)</label><input type="text" inputmode="numeric" id="oilPrice" name="oilPrice" data-money min="0" value="{{ old('oilPrice', $stokMinyakTersimpan->harga ?? '') }}"></div>
            <div class="field" style="flex:0; align-self:flex-end;"><button type="submit" class="btn btn-primary">Simpan</button></div>
        </div>
    </form>
    @error('oilQty') <div class="field-error">{{ $message }}</div> @enderror
    <div class="summary-line total" style="margin-top:12px;">
        <span>Hasil perkalian (jumlah &times; harga)</span>
        <span class="val" id="oilCalc" data-initial="{{ $stokMinyakTersimpan ? rupiah($stokMinyakTersimpan->subtotal()) : 'Belum ada' }}">{{ $stokMinyakTersimpan ? rupiah($stokMinyakTersimpan->subtotal()) : 'Belum ada' }}</span>
    </div>
    @if($stokMinyakTersimpan)
        <form method="POST" action="{{ route('pos.hapusOil', $parameterQuery) }}" style="margin-top:8px;" data-confirm="Hapus stok minyak tanggal ini?">
            @csrf
            <button type="submit" class="btn btn-outline-danger">Hapus</button>
        </form>
    @endif
</div>

<div class="card sub-section" id="manajemenStokCard">
    <h3>Manajemen stok barang (per pemegang / gudang)</h3>
    <form method="POST" action="{{ route('pos.simpanStockMgmt', $parameterQuery) }}" id="stockMgmtFormEl">
        @csrf
        <input type="hidden" name="stockMgmtSacks" id="stockMgmtSacks" value="">
        <div class="field-row">
            <div class="field"><label for="stockMgmtName">Nama</label><input type="text" id="stockMgmtName" name="stockMgmtName" value="{{ $namaManajemenStok }}" placeholder="Contoh: gun, imam" list="stockHolderNames"></div>
            <datalist id="stockHolderNames">
                @foreach ($namaPemegangManajemenStok as $namaPemegang)
                    <option value="{{ $namaPemegang }}"></option>
                @endforeach
            </datalist>
            <div class="field"><label for="stockMgmtPrice">Harga per kg (Rp)</label><input type="text" inputmode="numeric" id="stockMgmtPrice" name="stockMgmtPrice" data-money min="0" value="{{ $hargaManajemenStok }}"></div>
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
                    @for ($i = 1; $i <= $maksKarungManajemenStok; $i++)
                        <th class="num" data-sak-th>Sak {{ $i }}</th>
                    @endfor
                    <th class="num">Total (kg)</th>
                    <th class="num">Harga (Rp)</th>
                    <th class="num">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($barisManajemenStok as $baris)
                    <tr data-mgmt-row="{{ $baris['id'] }}">
                        <td><strong>{{ $baris['name'] }}</strong></td>
                        @for ($si = 0; $si < $maksKarungManajemenStok; $si++)
                            <td class="num" data-mgmt-cell>
                                <input type="number" min="0" step="0.5" class="sack-cell" data-sack-input
                                       value="{{ $baris['sacks'][$si] ?? '' }}" data-val="{{ $baris['sacks'][$si] ?? '' }}" placeholder="kg">
                            </td>
                        @endfor
                        <td class="num" data-mgmt-total>{{ tampilkanKilogram($baris['totalQty']) }} kg</td>
                        <td class="num" data-mgmt-price>
                            <input type="text" inputmode="numeric" class="sack-cell mgmt-price-input"
                                   data-money data-mgmt-price-input
                                   value="{{ number_format($baris['price'], 0, ',', '.') }}"
                                   data-val="{{ number_format($baris['price'], 0, ',', '.') }}"
                                   aria-label="Harga per kg {{ $baris['name'] }}">
                        </td>
                        <td class="num" data-mgmt-sub>{{ rupiah($baris['subtotal']) }}</td>
                        <td>
                            <div class="row-actions">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-mgmt-add-sack title="Tambah sak baru">+ Sak</button>
                                <form method="POST" action="{{ route('pos.hapusStockMgmt', $baris['id']) }}" style="display:inline;" data-confirm="Hapus data ini?">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $maksKarungManajemenStok + 5 }}" class="empty">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="summary-line total" style="margin-top:10px;"><span>Total manajemen stok barang</span><span class="val" id="mgmtGrand">{{ rupiah($totalManajemenStok) }}</span></div>
</div>

<div class="card sub-section" id="sisaStokCard">
    <h3>Sisa barang hari ini</h3>
    <form method="POST" action="{{ route('pos.simpanRemain', $parameterQuery) }}">
        @csrf
        <div class="field-row">
            <div class="field">
                <label for="remainName">Produk</label>
                <input type="text" id="remainName" name="remainName" value="{{ $namaSisaStok }}" placeholder="Nama produk bebas">
            </div>
            <div class="field"><label for="remainQty">Jumlah (kg)</label><input type="number" id="remainQty" name="remainQty" min="0" step="0.5" value="{{ $jumlahSisaStok }}"></div>
            <div class="field"><label for="remainPrice">Harga (Rp)</label><input type="text" inputmode="numeric" id="remainPrice" name="remainPrice" data-money min="0" value="{{ $hargaSisaStok }}"></div>
        </div>
        @error('remainName') <div class="field-error">{{ $message }}</div> @enderror
        @error('remainQty') <div class="field-error">{{ $message }}</div> @enderror
        <button type="submit" class="btn btn-primary">Tambah</button>
    </form>
    <div class="table-wrap" style="margin-top:10px;">
        <table>
            <thead><tr><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th><th></th></tr></thead>
            <tbody>
                @forelse ($barisSisaStok as $baris)
                    <tr data-remain-row="{{ $baris['id'] }}">
                        <td><input type="text" class="cell-edit cell-name" data-remain="name" data-val="{{ $baris['name'] }}" value="{{ $baris['name'] }}" placeholder="Nama produk"></td>
                        <td class="num"><input type="number" min="0" step="0.5" class="sack-cell" data-remain="qty" data-val="{{ $baris['qty'] }}" value="{{ $baris['qty'] }}" placeholder="kg"></td>
                        <td class="num"><input type="text" inputmode="numeric" min="0" class="sack-cell" data-remain="price" data-money data-val="{{ number_format($baris['price'], 0, ',', '.') }}" value="{{ number_format($baris['price'], 0, ',', '.') }}" placeholder="0"></td>
                        <td class="num" data-remain-sub>{{ rupiah($baris['qty'] * $baris['price']) }}</td>
                        <td>
                            <form method="POST" action="{{ route('pos.hapusRemain', $baris['id']) }}" style="display:inline;" data-confirm="Hapus data ini?">
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
    <div class="summary-line total"><span>Total sisa barang hari ini</span><span class="val" id="remainGrand">{{ rupiah($totalSisaStok) }}</span></div>
</div>
