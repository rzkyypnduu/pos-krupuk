@if($ringkasanData)
    @php
        $grafikHarian = $ringkasanData['grafikHarian'];
        $produkTerlaris = $ringkasanData['produkTerlaris'];
        $pelangganAktif = $ringkasanData['pelangganAktif'];
    @endphp
    {{-- Bagian 1: kartu tanggal & neraca (angka Saldo/Total = rumus sama dengan tab Hasil) --}}
    <div class="card">
        <div style="margin-bottom:14px;"><h2 style="margin-bottom:4px;">Ringkasan neraca &amp; stok</h2></div>
        <h3 style="margin-bottom:14px;">Tanggal: <span>{{ $ringkasanData['txDate'] }}</span></h3>
        <div class="stat-grid">
            <div class="stat-box"><div class="label">Total stok minyak</div><div class="value">{{ rupiah($ringkasanData['totalOil']) }}</div></div>
            <div class="stat-box"><div class="label">Total manajemen stok barang</div><div class="value">{{ rupiah($ringkasanData['totalStockMgmt']) }}</div></div>
            <div class="stat-box"><div class="label">Total sisa barang</div><div class="value">{{ rupiah($ringkasanData['totalRemain']) }}</div></div>
            <div class="stat-box"><div class="label">Total hutang pelanggan</div><div class="value">{{ rupiah($ringkasanData['totalHutangPel']) }}</div></div>
            <div class="stat-box"><div class="label">Total hutang pribadi</div><div class="value">{{ rupiah($ringkasanData['totalHutangPri']) }}</div></div>
            <div class="stat-box"><div class="label">Pengurangan saldo (A&minus;B)</div><div class="value">{{ number_format($ringkasanData['totalSaldo'], 0, ',', '.') }}</div></div>
        </div>
        <div class="stat-box highlight" style="margin-top:14px;" title="Total stok minyak + total manajemen stok barang + total hutang pelanggan + total sisa barang">
            <div class="label">Total keseluruhan aset</div>
            <div class="value">{{ rupiah($ringkasanData['grand']) }}</div>
        </div>
        <div class="stat-box highlight" style="margin-top:8px;" title="Total keseluruhan aset &minus; total hutang pribadi">
            <div class="label">Saldo</div>
            <div class="value">{{ rupiah($ringkasanData['grand'] - $ringkasanData['totalHutangPri']) }}</div>
        </div>
        <div class="stat-box highlight" style="margin-top:8px;" title="Total keseluruhan aset &minus; pengurangan saldo &minus; total hutang pribadi">
            <div class="label">Total</div>
            <div class="value">{{ rupiah($ringkasanData['grand'] - $ringkasanData['totalSaldo'] - $ringkasanData['totalHutangPri']) }}</div>
        </div>
    </div>

    {{-- Bagian 2: grafik harian — data grafik disuntik window.POS_GRAFIK,
         dirender chart-init.js (Chart.js via bundle Vite). --}}
    <div class="card">
        <h2>Grafik penjualan harian</h2>
        <div class="chart-wrap"><canvas id="chartHarian"></canvas></div>
    </div>

    {{-- Bagian 3: produk terlaris top-5 (ranking qty dari bulan berjalan) --}}
    <div class="card">
        <h2>Produk terlaris</h2>
        @if($produkTerlaris->isEmpty())
            <p class="note">Belum ada penjualan bulan ini.</p>
        @else
            @php $topQty = max((float) $produkTerlaris->max('qty'), 1); @endphp
            <div class="rank-list">
                @foreach ($produkTerlaris as $i => $p)
                    @php
                        $qtyTxt = rtrim(rtrim(number_format((float) $p->qty, 2, ',', '.'), '0'), ',');
                    @endphp
                    <div class="rank-row">
                        <span class="rank-no">{{ $i + 1 }}</span>
                        <span class="rank-name">{{ $p->name }}</span>
                        <span class="rank-track"><span class="rank-bar" style="width: {{ max(2, round((float) $p->qty / $topQty * 100)) }}%"></span></span>
                        <span class="rank-val">{{ $qtyTxt }} kg</span>
                        <span class="rank-val">{{ rupiah($p->nilai) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Bagian 4: pelanggan teraktif — render server, urut ulang di klien (vanilla JS) --}}
    <div class="card" id="pelangganAktifCard">
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
                    @forelse ($pelangganAktif as $c)
                        <tr data-bayar="{{ $c['bayar'] }}" data-trx="{{ $c['trx'] }}">
                            <td class="rank-i"></td>
                            <td><strong>{{ $c['name'] }}</strong></td>
                            <td class="num pelanggan-bayar"></td>
                            <td class="num">{{ $c['trx'] }}</td>
                        </tr>
                    @empty
                        <tr class="pelanggan-empty"><td colspan="4" class="empty">Belum ada transaksi bulan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bagian 5: kelola data — reset bulan (PosController::resetMonth) & reset semua --}}
    <div class="card">
        <h2>Kelola data</h2>
        @error('bulan') <div class="field-error" style="margin-bottom:10px;">{{ $message }}</div> @enderror
        <form method="POST" action="{{ route('pos.resetMonth') }}" style="display:inline;" data-confirm="Hapus semua data bulan {{ $monthLabel }}? Tindakan ini tidak bisa dibatalkan.">
            @csrf
            <input type="hidden" name="bulan" value="{{ $activeMonth }}">
            <button type="submit" class="btn btn-outline-danger">Hapus data bulan ini saja</button>
        </form>
        <form method="POST" action="{{ route('pos.resetAll') }}" style="display:inline;" data-confirm="Yakin hapus SEMUA data POS (semua bulan)? Tindakan ini tidak bisa dibatalkan.">
            @csrf
            <button type="submit" class="btn btn-outline-danger" style="margin-left:8px;">Hapus SEMUA data (semua bulan)</button>
        </form>
    </div>

    <script>
        // Data grafik harian — dibaca chart-init.js (bundle Vite, Chart.js).
        window.POS_GRAFIK = @json($grafikHarian);
        window.POS_MONTH_LABEL = @json($monthLabel);

        // — Pelanggan teraktif: urut ulang klien (tanpa Alpine) —
        (function () {
            const body = document.getElementById('pelangganAktifBody');
            if (!body) return;
            let sort = 'bayar';

            function rupiahLocal(v) {
                return 'Rp' + Math.round(parseFloat(v) || 0).toLocaleString('id-ID');
            }

            function render() {
                const rows = Array.from(body.querySelectorAll('tr[data-bayar]'));
                rows.sort((a, b) => {
                    const aBayar = +a.dataset.bayar, bBayar = +b.dataset.bayar;
                    const aTrx = +a.dataset.trx, bTrx = +b.dataset.trx;
                    if (sort === 'trx') return (bTrx - aTrx) || (bBayar - aBayar);
                    return (bBayar - aBayar) || (bTrx - aTrx);
                });
                rows.forEach((tr, i) => {
                    tr.querySelector('.rank-i').textContent = i + 1;
                    tr.querySelector('.pelanggan-bayar').textContent = rupiahLocal(tr.dataset.bayar);
                    body.appendChild(tr);
                });
                const empty = body.querySelector('.pelanggan-empty');
                if (empty) body.appendChild(empty);
            }

            document.querySelectorAll('.pelanggan-sort').forEach(btn => {
                btn.addEventListener('click', () => {
                    sort = btn.dataset.sort;
                    document.querySelectorAll('.pelanggan-sort').forEach(b => {
                        b.classList.toggle('btn-primary', b === btn);
                        b.classList.toggle('btn-outline-secondary', b !== btn);
                    });
                    render();
                });
            });

            render();
        })();
    </script>
@endif
