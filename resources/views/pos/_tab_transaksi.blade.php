@php
    $totalKilogram = collect($jumlahTransaksi)->filter()->sum();
    $totalKotor = 0;
    foreach ($jumlahTransaksi as $idProduk => $jumlah) {
        if ($jumlah && (float) $jumlah > 0) {
            $produk = $daftarProduk->firstWhere('id', $idProduk);
            if ($produk)
                $totalKotor += (float) $jumlah * $produk->harga;
        }
    }
    $totalKotor = (int) round($totalKotor);
    // Nilai awal; resources/js/pos/transaksi.js menghitung ulang saat input berubah.
    $totalDibulatkan = bulatkanTotal($totalKotor);
    $nilaiDibayar = $dibayarTransaksiDisentuh ? uraiRupiah($dibayarTransaksi) : 0;
    $selisih = $totalDibulatkan - $nilaiDibayar;

    $parameterQuery = ['bulan' => $bulanAktif, 'tab' => 'transaksi', 'tx_date' => $tanggalTransaksi];
    $formTerbuka = $tampilkanFormTransaksi || $errors->has('txName') || $errors->has('txQty') || $errors->has('editing_sale_id');
    $parameterForm = array_merge($parameterQuery, request()->has('new') ? ['new' => 1] : []);
    $parameterTransaksiBaru = ['bulan' => $bulanAktif, 'tab' => 'transaksi', 'tx_date' => $tanggalTransaksi, 'new' => 1];
    $labelTanggalTransaksi = \Illuminate\Support\Carbon::parse($tanggalTransaksi)->locale('id')->isoFormat('dddd, D MMMM YYYY');
@endphp

{{-- Bagian 1: header tanggal, tabel transaksi hari ini, & form baru/edit (id #tx-baru) --}}
<div class="tx-page">
    <div class="card tx-section" id="tx-baru">
        <div class="tx-page-head">
            <div>
                <h2>Transaksi Hari Ini</h2>
                <p class="tx-page-date">{{ $labelTanggalTransaksi }}</p>
            </div>
            <div class="tx-page-actions">
                @unless($formTerbuka)
                    <a href="{{ route('pos', $parameterTransaksiBaru) }}#tx-baru" class="btn btn-primary">+ Transaksi Baru</a>
                @endunless
            </div>
        </div>

        @if($formTerbuka)
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
                        <a href="{{ route('pos', $parameterQuery) }}" class="btn btn-outline-light">Tutup</a>
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
                    <form method="POST" action="{{ route('pos.simpanTransaksi', $parameterForm) }}" id="txForm"
                        data-total-dibulatkan="{{ (int) $totalDibulatkan }}"
                        data-harga-produk="{{ $daftarProduk->pluck('harga', 'id')->toJson() }}">
                        @csrf
                        <input type="hidden" name="txDate" value="{{ $tanggalTransaksi }}">
                        <input type="hidden" name="editing_sale_id" value="{{ $idPenjualanDiedit }}">
                        <input type="hidden" name="tx_paid_touched" value="{{ $dibayarTransaksiDisentuh ? 1 : 0 }}" id="txPaidTouched">
                        <input type="hidden" name="input_method" id="inputMethod" value="manual">
                        <input type="hidden" name="input_text" id="inputText" value="">
                        <input type="hidden" name="input_parsed" id="inputParsed" value="">
                        <input type="hidden" name="input_duration_ms" id="inputDurationMs" value="">
                        <input type="hidden" name="input_started_at" id="inputStartedAt" value="">

                        <div class="tx-customer-row">
                            <div class="tx-name-field">
                                <label for="txName">Nama pelanggan</label>
                                <input type="text" id="txName" name="txName" value="{{ $namaTransaksi }}" list="customerNames"
                                    placeholder="Cari nama...">
                                @error('txName')
                                <div class="field-error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div style="margin-top:14px;">
                            <div class="form-group-label"
                                style="font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);">
                                Jumlah per produk (kg)</div>
                            @if ($daftarProduk->isEmpty())
                                <div class="empty">Tambahkan produk dulu di tab Master Produk.</div>
                            @else
                                <div class="tx-product-grid">
                                    @foreach ($daftarProduk as $produk)
                                        @php $nilaiJumlah = $jumlahTransaksi[$produk->id] ?? null; @endphp
                                        <div class="product-card {{ $nilaiJumlah && (float) $nilaiJumlah > 0 ? 'has-qty' : '' }}">
                                            <div class="product-card-name">{{ $produk->nama }}</div>
                                            <div class="product-card-input">
                                                <button type="button" class="qty-btn" data-qty-step="qtyInput{{ $produk->id }}"
                                                    data-delta="-0.5" aria-label="Kurangi">&minus;</button>
                                                <input type="text" inputmode="decimal" step="0.5" id="qtyInput{{ $produk->id }}"
                                                    name="qty[{{ $produk->id }}]"
                                                    value="{{ $nilaiJumlah && (float) $nilaiJumlah > 0 ? str_replace('.', ',', (string) $nilaiJumlah) : '' }}"
                                                    class="qty-input" placeholder="0" data-product-id="{{ $produk->id }}">
                                                <button type="button" class="qty-btn" data-qty-step="qtyInput{{ $produk->id }}"
                                                    data-delta="0.5" aria-label="Tambah">+</button>
                                            </div>
                                            @if($produk->harga && $nilaiJumlah && (float) $nilaiJumlah > 0)
                                                <div class="product-card-subtotal">{{ rupiah($produk->harga * $nilaiJumlah) }}</div>
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
                                <div class="value" id="totalKgDisplay">{{ tampilkanKilogram($totalKilogram) }} kg</div>
                            </div>
                            <div class="tx-total-stat">
                                <div class="label">Belanja (asli)</div>
                                <div class="value" id="rawTotalDisplay">{{ rupiah($totalKotor) }}</div>
                            </div>
                            <div class="tx-total-stat highlight">
                                <div class="label">Tagihan</div>
                                <div class="value" id="roundedTotalDisplay">{{ rupiah($totalDibulatkan) }}</div>
                            </div>
                        </div>

                        <div class="tx-payment-row">
                            <div class="tx-paid-group">
                                <label for="txPaid">Dibayar (Rp)</label>
                                <div class="input-group">
                                    <input type="text" id="txPaid" name="tx_paid" inputmode="numeric" value="{{ $dibayarTransaksi }}"
                                        data-money
                                        placeholder="0">
                                    <button type="button" class="pas-btn" data-pas>Pas</button>
                                </div>
                            </div>
                            <div class="tx-status-group">
                                <div class="form-group-label">Status</div>
                                <div class="status-box {{ !$dibayarTransaksiDisentuh ? 'debt' : ($selisih > 0 ? 'debt' : ($selisih < 0 ? 'paid' : 'zero')) }}"
                                    id="statusBox">
                                    @if(!$dibayarTransaksiDisentuh) Belum dibayar: {{ rupiah($totalDibulatkan) }}
                                    @elseif($selisih > 0) Kurang {{ rupiah($selisih) }}
                                    @elseif($selisih < 0) Lebih {{ rupiah(-$selisih) }}
                                    @else Lunas &#10003; @endif
                                </div>
                            </div>
                        </div>

                        <div class="tx-note" style="margin-top:12px;">
                            <input type="text" name="tx_note" value="{{ $catatanTransaksi }}" placeholder="Catatan (opsional)">
                        </div>

                        @if($idPenjualanDiedit)
                            @php $penjualanDiedit = $daftarPenjualan->firstWhere('id', $idPenjualanDiedit); @endphp
                            @if($penjualanDiedit)
                                <div class="tx-edit-banner">
                                    Mode bayar: <strong>{{ $penjualanDiedit->nama }}</strong> &mdash;
                                    {{ $penjualanDiedit->tanggal->format('Y-m-d') }}
                                    <a href="{{ route('pos', array_merge($parameterQuery, ['tab' => 'transaksi'])) }}"
                                        class="btn btn-outline-secondary" style="margin-left:auto;text-decoration:none;">Batal</a>
                                </div>
                            @endif
                        @endif

                        <div class="tx-actions">
                            <button type="submit"
                                class="btn btn-primary">{{ $idPenjualanDiedit ? 'Simpan pembayaran' : 'Simpan transaksi' }}</button>
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
                    @forelse ($daftarPenjualan as $transaksi)
                        <tr data-pay-row="{{ $transaksi->id }}">
                            <td>{{ $transaksi->nama }}</td>
                            <td style="font-size:19px;line-height:1.6;">
                                @foreach($transaksi->daftarItem as $itemPenjualan)
                                    <div>{{ $itemPenjualan->nama }}: {{ tampilkanKilogram($itemPenjualan->jumlah) }} kg</div>
                                @endforeach
                            </td>
                            <td class="num">{{ tampilkanKilogram($transaksi->daftarItem->sum('jumlah')) }}</td>
                            <td class="num">{{ rupiah($transaksi->total_dibulatkan) }}</td>
                            <td class="num" data-pay-paid
                                title="{{ 'Bayar kemarin ' . rupiah($transaksi->dibayar_kemarin) . ' + hari ini ' . rupiah($transaksi->dibayar) }}">{{ rupiah($transaksi->dibayar + $transaksi->dibayar_kemarin) }}</td>
                            <td>
                                <div class="status-stack">
                                    <span class="badge {{ $transaksi->selisih > 0 ? 'debt' : ($transaksi->selisih < 0 ? 'paid' : 'zero') }}"
                                        data-pay-badge>{{ $transaksi->selisih > 0 ? 'Kurang ' . rupiah($transaksi->selisih) : ($transaksi->selisih < 0 ? 'Lebih ' . rupiah(-$transaksi->selisih) : 'Lunas') }}</span>
                                    <span class="badge paid-outline{{ $transaksi->kemarin_aktif ? '' : ' d-none' }}"
                                        data-pay-kbadge title="Sebagian uang dipakai membayar hutang sebelumnya">Bayar
                                        kemarin {{ rupiah($transaksi->dibayar_kemarin) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button"
                                        class="btn {{ $transaksi->tombol_bayar_diklik ? 'btn-outline-secondary' : 'btn-success' }}"
                                        title="Buka modal pembayaran {{ $transaksi->nama }}"
                                        data-pay-open="{{ $transaksi->id }}">Bayar</button>
                                    <label class="ck-kemarin{{ $transaksi->kemarin_aktif ? ' on' : '' }}"
                                        title="Aktifkan bila pembeli juga membayar hutang sebelumnya">
                                        <input type="checkbox" id="ckKmr{{ $transaksi->id }}" data-pay-ck="{{ $transaksi->id }}"
                                            @checked($transaksi->kemarin_aktif)>
                                        <span>Bayar kemarin</span>
                                    </label>
                                    <form method="POST" action="{{ route('pos.loadForPayment', $transaksi->id) }}"
                                        style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary"
                                            title="Edit transaksi">Edit</button>
                                    </form>
                                    <form method="POST" action="{{ route('pos.hapusTransaksi', $transaksi->id) }}"
                                        style="display:inline;"
                                        data-confirm="Hapus transaksi {{ $transaksi->nama }} ini? Riwayat hutang tidak akan dihapus.">
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
        <form method="POST" action="{{ route('pos.simpanExpense', $parameterQuery) }}" class="tx-expense-form">
            @csrf
            <input type="hidden" name="expDate" value="{{ $tanggalTransaksi }}">
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
                    @forelse ($daftarPengeluaranHari as $pengeluaran)
                        <tr>
                            <td>{{ $pengeluaran->catatan ?? '-' }}</td>
                            <td class="num">{{ rupiah($pengeluaran->jumlah) }}</td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('pos.hapusExpense', $pengeluaran->id) }}" style="display:inline;"
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
                    @forelse ($rekapPerProduk as $namaProduk => $kilogram)
                        <div style="display:flex;justify-content:space-between;padding:2px 0;">
                            <span>{{ $namaProduk }}</span>
                            <span class="num">{{ tampilkanKilogram($kilogram) }} kg</span>
                        </div>
                    @empty
                        <span class="num">0 kg</span>
                    @endforelse
                    <div
                        style="display:flex;justify-content:space-between;padding:2px 0;border-top:1px solid var(--line);margin-top:4px;padding-top:6px;font-weight:700;">
                        <span>Total</span>
                        <span class="num">{{ tampilkanKilogram($totalRekap['kg']) }} kg</span>
                    </div>
                    <div class="tx-summ-row">
                        <span>Diterima</span>
                        <span class="num green">{{ rupiah($totalRekap['dibayar']) }}</span>
                    </div>
                    <div class="tx-summ-row">
                        <span>Pengeluaran</span>
                        <span class="num red">&minus;{{ rupiah($totalRekap['pengeluaran']) }}</span>
                    </div>
                    <div class="tx-summ-row kas">
                        <span>Kas bersih</span>
                        <span class="num green">{{ rupiah($totalRekap['kas_bersih']) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bagian 4: rekap bulan berjalan --}}
    <div class="card">
        <button type="button" class="recap-toggle" data-bs-toggle="collapse" data-bs-target="#recapBulan"
            aria-expanded="true" aria-controls="recapBulan">
            <h2 style="margin-bottom:0;">Ringkasan Bulan ({{ $labelBulan }})</h2>
            <span style="font-size:13px;color:var(--ink-soft);" aria-hidden="true">&#9650;</span>
        </button>
        <div class="collapse show" id="recapBulan" style="margin-top:14px;">
            <div class="tx-summ-block">
                <div class="lbl">Kg terjual per produk</div>
                <div style="margin-top:6px;font-size:14px;">
                    @forelse ($rekapBulanPerProduk as $namaProduk => $kilogram)
                        <div style="display:flex;justify-content:space-between;padding:2px 0;">
                            <span>{{ $namaProduk }}</span>
                            <span class="num">{{ tampilkanKilogram($kilogram) }} kg</span>
                        </div>
                    @empty
                        <span class="num">0 kg</span>
                    @endforelse
                    <div
                        style="display:flex;justify-content:space-between;padding:2px 0;border-top:1px solid var(--line);margin-top:4px;padding-top:6px;font-weight:700;">
                        <span>Total</span>
                        <span class="num">{{ tampilkanKilogram($totalRekapBulan['kg']) }} kg</span>
                    </div>
                    <div class="tx-summ-row">
                        <span>Diterima</span>
                        <span class="num green">{{ rupiah($totalRekapBulan['dibayar']) }}</span>
                    </div>
                    <div class="tx-summ-row">
                        <span>Pengeluaran</span>
                        <span class="num red">&minus;{{ rupiah($totalRekapBulan['pengeluaran']) }}</span>
                    </div>
                    <div class="tx-summ-row kas">
                        <span>Kas bersih</span>
                        <span class="num green">{{ rupiah($totalRekapBulan['kas_bersih']) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- Status pembayaran dan total dihitung ulang oleh resources/js/pos/transaksi.js
     saat qty atau nominal Dibayar berubah. --}}
{{-- Speech-to-text: browser (resources/js/pos/suara.js di bundle Vite) -> route pos.speech ->
UcapanController::parseSpeech -> PenguraiUcapan (rule-based) -> hasil diisi ke form. --}}