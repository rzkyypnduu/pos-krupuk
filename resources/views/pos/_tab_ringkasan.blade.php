@if($dataRingkasan)
    @php
        $grafikHarian = $dataRingkasan['grafikHarian'];
        $produkTerlaris = $dataRingkasan['produkTerlaris'];
        $pelangganAktif = $dataRingkasan['pelangganAktif'];
    @endphp
    {{-- Neraca harian; angka saldo dan total mengikuti rumus yang sama dengan tab Hasil. --}}
    <div class="card">
        <div style="margin-bottom:14px;"><h2 style="margin-bottom:4px;">Ringkasan neraca &amp; stok</h2></div>
        <h3 style="margin-bottom:14px;">Tanggal: <span>{{ $dataRingkasan['tanggalTransaksi'] }}</span></h3>
        <div class="stat-grid">
            <div class="stat-box"><div class="label">Total stok minyak</div><div class="value">{{ rupiah($dataRingkasan['totalStokMinyak']) }}</div></div>
            <div class="stat-box"><div class="label">Total manajemen stok barang</div><div class="value">{{ rupiah($dataRingkasan['totalManajemenStok']) }}</div></div>
            <div class="stat-box"><div class="label">Total sisa barang</div><div class="value">{{ rupiah($dataRingkasan['totalSisaStok']) }}</div></div>
            <div class="stat-box"><div class="label">Total hutang pelanggan</div><div class="value">{{ rupiah($dataRingkasan['totalHutangPelanggan']) }}</div></div>
            <div class="stat-box"><div class="label">Total hutang pribadi</div><div class="value">{{ rupiah($dataRingkasan['totalHutangPribadi']) }}</div></div>
            <div class="stat-box"><div class="label">Pengurangan saldo (A&minus;B)</div><div class="value">{{ number_format($dataRingkasan['totalSaldo'], 0, ',', '.') }}</div></div>
        </div>
        <div class="stat-box highlight" style="margin-top:14px;" title="Total stok minyak + total manajemen stok barang + total hutang pelanggan + total sisa barang">
            <div class="label">Total keseluruhan aset</div>
            <div class="value">{{ rupiah($dataRingkasan['totalKeseluruhan']) }}</div>
        </div>
        <div class="stat-box highlight" style="margin-top:8px;" title="Total keseluruhan aset &minus; total hutang pribadi">
            <div class="label">Saldo</div>
            <div class="value">{{ rupiah($dataRingkasan['totalKeseluruhan'] - $dataRingkasan['totalHutangPribadi']) }}</div>
        </div>
        <div class="stat-box highlight" style="margin-top:8px;" title="Total keseluruhan aset &minus; pengurangan saldo &minus; total hutang pribadi">
            <div class="label">Total</div>
            <div class="value">{{ rupiah($dataRingkasan['totalKeseluruhan'] - $dataRingkasan['totalSaldo'] - $dataRingkasan['totalHutangPribadi']) }}</div>
        </div>
    </div>

    {{-- Data grafik dibaca resources/js/pos/grafik.js dari atribut canvas. --}}
    <div class="card">
        <h2>Grafik penjualan harian</h2>
        <div class="chart-wrap">
            <canvas id="chartHarian"
                data-label-bulan="{{ $labelBulan }}"
                data-grafik="{{ json_encode($grafikHarian) }}"></canvas>
        </div>
    </div>

    {{-- Produk terlaris berdasarkan jumlah kilogram pada bulan berjalan. --}}
    <div class="card">
        <h2>Produk terlaris</h2>
        @if($produkTerlaris->isEmpty())
            <p class="note">Belum ada penjualan bulan ini.</p>
        @else
            @php $jumlahTeratas = max((float) $produkTerlaris->max('jumlah'), 1); @endphp
            <div class="rank-list">
                @foreach ($produkTerlaris as $i => $dataProduk)
                    @php
                        $teksJumlah = rtrim(rtrim(number_format((float) $dataProduk->jumlah, 2, ',', '.'), '0'), ',');
                    @endphp
                    <div class="rank-row">
                        <span class="rank-no">{{ $i + 1 }}</span>
                        <span class="rank-name">{{ $dataProduk->nama }}</span>
                        <span class="rank-track"><span class="rank-bar" style="width: {{ max(2, round((float) $dataProduk->jumlah / $jumlahTeratas * 100)) }}%"></span></span>
                        <span class="rank-val">{{ $teksJumlah }} kg</span>
                        <span class="rank-val">{{ rupiah($dataProduk->nilai) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Baris dirender server; resources/js/pos/ringkasan.js mengurutkan ulang di klien. --}}
    <div class="card">
        <h2>Pelanggan teraktif</h2>
        <div class="filter-btns">
            <button type="button" class="btn btn-primary pelanggan-sort" data-sort="bayar">Urut: Total Dibayar</button>
            <button type="button" class="btn btn-outline-secondary pelanggan-sort" data-sort="trx">Urut: Jumlah Transaksi</button>
        </div>
        <div class="table-wrap" style="margin-top:10px;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th class="num">Total Dibayar</th>
                        <th class="num">Jumlah Transaksi</th>
                    </tr>
                </thead>
                <tbody id="pelangganAktifBody">
                    @forelse ($pelangganAktif as $dataPelanggan)
                        <tr data-bayar="{{ $dataPelanggan['bayar'] }}" data-trx="{{ $dataPelanggan['trx'] }}">
                            <td class="rank-i"></td>
                            <td><strong>{{ $dataPelanggan['nama'] }}</strong></td>
                            <td class="num pelanggan-bayar"></td>
                            <td class="num">{{ $dataPelanggan['trx'] }}</td>
                        </tr>
                    @empty
                        <tr class="pelanggan-empty"><td colspan="4" class="empty">Belum ada transaksi bulan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Reset data bulan aktif atau seluruh data operasional. --}}
    <div class="card">
        <h2>Kelola data</h2>
        @error('bulan') <div class="field-error" style="margin-bottom:10px;">{{ $message }}</div> @enderror
        <div class="btn-inline">
            <form method="POST" action="{{ route('pos.resetMonth') }}" data-confirm="Hapus semua data bulan {{ $labelBulan }}? Tindakan ini tidak bisa dibatalkan.">
                @csrf
                <input type="hidden" name="bulan" value="{{ $bulanAktif }}">
                <button type="submit" class="btn btn-outline-danger">Hapus data bulan ini saja</button>
            </form>
            <form method="POST" action="{{ route('pos.resetAll') }}" data-confirm="Yakin hapus SEMUA data POS (semua bulan)? Tindakan ini tidak bisa dibatalkan.">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Hapus SEMUA data (semua bulan)</button>
            </form>
        </div>
    </div>
@endif
