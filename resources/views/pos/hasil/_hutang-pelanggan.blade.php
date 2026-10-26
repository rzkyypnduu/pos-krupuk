{{-- Tabel hutang pelanggan: daftar per nama, total, tambah/edit/hapus entri. --}}
<div class="section-header">
    <h2>1. Rekap Hutang Pelanggan</h2>
</div>

<div class="card">
    <h2>Rekap hutang per pelanggan</h2>
    <div class="hp-toolbar">
        <a href="{{ route('pos', array_merge($parameterQuery, ['debt_modal' => 1, 'debt_name' => ''])) }}" class="btn btn-primary btn-plus">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Tambah hutang
        </a>
    </div>

    <div class="table-wrap">
        <table id="hpSummaryTable" class="hp-grid" style="white-space: nowrap;" data-tanggal="{{ $tanggalTransaksi }}">
            <thead>
                <tr>
                    <th class="hp-head">Nama</th>
                    <th class="hp-head num" colspan="{{ max((int) $maxCols, 1) }}">Hutang</th>
                    <th class="hp-head num">Total</th>
                    <th class="hp-head"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarNamaHutangPelanggan as $nama)
                    @php $dataHutangPelanggan = $hutangPelangganDiproses[$nama]; @endphp
                    <tr data-name="{{ $nama }}" data-total="{{ $dataHutangPelanggan['totalSisa'] }}">
                        <td class="hp-name-cell">
                            <span class="hp-name" title="Klik untuk ubah nama">{{ $nama }}</span>
                            <input class="hp-inp hp-name-inp" type="text" value="{{ $nama }}" data-val="{{ $nama }}" maxlength="255">
                        </td>
                        @for ($i = 0; $i < max((int) $maxCols, 1); $i++)
                            @if (isset($dataHutangPelanggan['activeDebts'][$i]))
                                @php $dataHutangAktif = $dataHutangPelanggan['activeDebts'][$i]; @endphp
                                <td class="num hp-cell hp-debt-cell" data-debt-id="{{ $dataHutangAktif['id'] }}">
                                    <span class="hp-amt" title="Klik untuk edit — {{ $dataHutangAktif['date'] }}">{{ rupiah($dataHutangAktif['remaining']) }}</span>
                                    <input class="hp-inp" type="text" inputmode="numeric" data-money value="{{ number_format($dataHutangAktif['remaining'], 0, ',', '.') }}" data-val="{{ number_format($dataHutangAktif['remaining'], 0, ',', '.') }}">
                                </td>
                            @else
                                <td class="num hp-cell empty-cell">&ndash;</td>
                            @endif
                        @endfor
                        <td class="num hp-total-cell">
                            <span class="hp-total-amt">{{ rupiah($dataHutangPelanggan['totalSisa']) }}</span>
                        </td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end; align-items:center;">
                                <a class="btn-icon" href="{{ route('pos', array_merge($parameterQuery, ['debt_modal' => 1, 'debt_name' => $nama])) }}"
                                   title="Tambah hutang baru untuk {{ $nama }}" aria-label="Tambah hutang baru">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </a>
                                <form method="POST" action="{{ route('pos.hapusCustomerLedger', array_merge($parameterQuery, ['name' => $nama])) }}" style="display:inline;" data-confirm="Hapus semua riwayat hutang pelanggan ini di tanggal ini? Tindakan ini tidak bisa dibatalkan.">
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
    <div class="summary-line total"><span>Total hutang pelanggan (piutang toko)</span><span class="val" id="hpGrandTotal">{{ rupiah($totalHutangPelanggan) }}</span></div>
</div>

@if($namaDetailHutangPelanggan && $entriHutangPelanggan)
    <div class="card">
        <h2>Detail hutang &mdash; {{ $namaDetailHutangPelanggan }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Jenis</th><th class="num">Jumlah</th><th class="num">Dibayar</th><th class="num">Saldo berjalan</th><th>Catatan</th><th></th></tr></thead>
                <tbody>
                    @forelse ($entriHutangPelanggan as $entri)
                        <tr>
                            <td>{!! $entri->jenis === 'tambah' ? '<span style="color:var(--debt);">+ Tambah hutang</span>' : '<span style="color:var(--paid);">&minus; Bayar hutang</span>' !!}</td>
                            <td class="num">{{ $entri->jenis === 'tambah' ? '+' : '-' }}{{ rupiah($entri->jumlah) }}</td>
                            <td class="num">{{ $entri->penjualan_id && $entri->dibayar !== null ? rupiah($entri->dibayar) : '-' }}</td>
                            <td class="num">{!! $entri->running > 0 ? rupiah($entri->running) : ($entri->running < 0 ? '&minus;'.rupiah(-$entri->running).' (deposit)' : 'Lunas (Rp0)') !!}</td>
                            <td>{{ $entri->catatan ?? '' }}</td>
                            <td>
                                <div class="row-actions">
                                @if($entri->penjualan_id)
                                    <form method="POST" action="{{ route('pos.loadForPayment', $entri->penjualan_id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary">Lihat Transaksi</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('pos.hapusLedgerEntry', $entri->id) }}" style="display:inline;" data-confirm="Hapus entri ini?">
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
        <a href="{{ route('pos', array_merge($parameterQuery)) }}" class="btn btn-outline-secondary" style="margin-top:8px;">Tutup detail</a>
    </div>
@endif
