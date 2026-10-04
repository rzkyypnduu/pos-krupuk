@if($ringkasanData)
    @php
        $grafikHarian = $ringkasanData['grafikHarian'];
        $produkTerlaris = $ringkasanData['produkTerlaris'];
        $pelangganAktif = $ringkasanData['pelangganAktif'];
    @endphp
    <div class="card">
        <div style="margin-bottom:14px;"><h2 style="margin-bottom:4px;">Ringkasan neraca &amp; stok</h2><div class="hint" style="margin:0;">Ringkasan harian (stok, hutang, saldo) pindah ke tab Hasil. Neraca di bawah mengikuti tanggal aktif di bagian atas halaman.</div></div>
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
        <p class="note">Rumus mengikuti catatan: total aset = total stok minyak + total manajemen stok barang + total hutang pelanggan + total sisa barang; Saldo = total aset &minus; hutang pribadi; Total = total aset &minus; pengurangan saldo &minus; hutang pribadi (sama dengan Ringkasan Hari Ini di tab Hasil).</p>
    </div>

    <div class="card">
        <h2>Grafik penjualan harian</h2>
        <div class="hint" style="margin:0 0 10px;">Periode: bulan {{ $monthLabel }}. <strong>Diterima</strong> = uang masuk (Dibayar + Bayar kemarin); <strong>Kas bersih</strong> = Diterima &minus; Pengeluaran. Sumbu vertikal dalam juta rupiah.</div>
        <div class="chart-wrap"><canvas id="chartHarian"></canvas></div>
    </div>

    <div class="card">
        <h2>Produk terlaris</h2>
        <div class="hint" style="margin:0 0 10px;">Peringkat berdasarkan jumlah terjual (kg) bulan {{ $monthLabel }}.</div>
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

    <div class="card" x-data="pelangganAktif({{ json_encode($pelangganAktif) }})">
        <h2>Pelanggan teraktif</h2>
        <div class="hint" style="margin:0 0 10px;">Transaksi bulan {{ $monthLabel }} — pilih cara urut:</div>
        <div class="filter-btns">
            <button type="button" class="ghost" :class="sort === 'bayar' ? 'primary' : ''" @click="sort = 'bayar'">Urut: Total Dibayar</button>
            <button type="button" class="ghost" :class="sort === 'trx' ? 'primary' : ''" @click="sort = 'trx'">Urut: Jumlah Transaksi</button>
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
                <tbody>
                    <template x-for="(c, i) in sorted" :key="c.name">
                        <tr>
                            <td x-text="i + 1"></td>
                            <td><strong x-text="c.name"></strong></td>
                            <td class="num" x-text="rupiahJs(c.bayar)"></td>
                            <td class="num" x-text="c.trx"></td>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0"><td colspan="4" class="empty">Belum ada transaksi bulan ini.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Kelola data</h2>
        <form method="POST" action="{{ route('pos.resetMonth') }}" style="display:inline;" onsubmit="return confirm('Hapus semua data bulan {{ $monthLabel }}? Tindakan ini tidak bisa dibatalkan.')">
            @csrf
            <input type="hidden" name="bulan" value="{{ $activeMonth }}">
            <button type="submit" class="ghost danger">Hapus data bulan ini saja</button>
        </form>
        <form method="POST" action="{{ route('pos.resetAll') }}" style="display:inline;" onsubmit="return confirm('Yakin hapus SEMUA data POS (semua bulan)? Tindakan ini tidak bisa dibatalkan.')" style="margin-left:8px;">
            @csrf
            <button type="submit" class="ghost danger" style="margin-left:8px;">Hapus SEMUA data (semua bulan)</button>
        </form>
    </div>

    <script src="{{ asset('js/chart.umd.min.js') }}"></script>
    <script>
        function pelangganAktif(rows) {
            return {
                rows: rows || [],
                sort: 'bayar',
                get sorted() {
                    const arr = this.rows.slice();
                    if (this.sort === 'trx') {
                        arr.sort((a, b) => (b.trx - a.trx) || (b.bayar - a.bayar));
                    } else {
                        arr.sort((a, b) => (b.bayar - a.bayar) || (b.trx - a.trx));
                    }
                    return arr;
                },
            };
        }

        (function () {
            const canvas = document.getElementById('chartHarian');
            if (!canvas || typeof Chart === 'undefined') return;
            const data = @json($grafikHarian);
            const jt = v => {
                const n = v / 1000000;
                return (Math.round(n * 100) / 100).toLocaleString('id-ID') + ' jt';
            };
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [
                        {
                            label: 'Diterima',
                            data: data.map(d => d.diterima),
                            borderColor: '#C06014',
                            backgroundColor: 'rgba(192, 96, 20, 0.12)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 2.5,
                            borderWidth: 2,
                        },
                        {
                            label: 'Kas bersih',
                            data: data.map(d => d.bersih),
                            borderColor: '#006600',
                            backgroundColor: 'rgba(0, 102, 0, 0.10)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 2.5,
                            borderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': Rp' + Math.round(ctx.parsed.y).toLocaleString('id-ID') + ' (' + jt(ctx.parsed.y) + ')',
                            },
                        },
                    },
                    scales: {
                        y: {
                            ticks: { callback: v => jt(v) },
                            grid: { color: 'rgba(0, 0, 0, 0.06)' },
                        },
                        x: {
                            title: { display: true, text: 'Tanggal — {{ $monthLabel }}' },
                        },
                    },
                },
            });
        })();
    </script>
@endif
