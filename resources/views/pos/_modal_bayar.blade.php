{{-- Payload window.POS_PAY (data transaksi + sisa hutang) dibaca
     resources/js/pos/pembayaran.js untuk mengisi modal Bayar.
     Modal memakai Bootstrap Modal (window.bootstrap). --}}
@php
    // Payload modal bayar: data tiap transaksi hari ini + sisa hutang pelanggan
    // (sumber sama dengan tab Hasil).
    $muatanPembayaran = [];
    foreach ($daftarPenjualan as $penjualan) {
        $dataHutangPelanggan = $hutangPelangganDiproses[$penjualan->nama] ?? null;
        $hutangTerurut = array_values(array_reverse($dataHutangPelanggan['activeDebts'] ?? [])); // terbaru ke terlama
        $muatanPembayaran[$penjualan->id] = [
            'name' => $penjualan->nama,
            'date' => $penjualan->tanggal->format('Y-m-d'),
            'total' => (int) $penjualan->total_dibulatkan,
            'paid' => (int) $penjualan->dibayar,
            'paid_kemarin' => (int) $penjualan->dibayar_kemarin,
            'kemarin_active' => (bool) $penjualan->kemarin_aktif,
            'debts' => array_map(fn ($dataHutangAktif) => [
                'id' => $dataHutangAktif['id'],
                'date' => $dataHutangAktif['date'],
                'amount' => (int) $dataHutangAktif['amount'],
                'remaining' => (int) $dataHutangAktif['remaining'],
            ], $hutangTerurut),
        ];
    }
@endphp
<script>
    window.POS_PAY = {{ Illuminate\Support\Js::from($muatanPembayaran) }};
    window.POS_BAYAR_URL = @js(url('/pos/transaksi'));
</script>

{{-- Markup modal Bayar; nilai dan daftar hutang diisi pembayaran.js saat dibuka. --}}
<div class="modal fade" id="payModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="payModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="pay-confirm-head">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                    <div>
                        <h2 id="payModalTitle">Bayar &mdash; <span id="payCustName"></span></h2>
                        <p id="payTotalsLine" hidden>
                            Tagihan <b id="payTagihan"></b>
                            &middot; Sudah dibayar <b id="paySudah"></b>
                            <span id="payKemarinWrap" hidden>&middot; Bayar kemarin <b id="payKemarinAmt"></b></span>
                        </p>
                    </div>
                    <button type="button" class="pay-x" data-bs-dismiss="modal" aria-label="Tutup modal">&times;</button>
                </div>
            </div>

            <div class="pay-confirm-body">
                        <div class="pay-debt-title">
                            Hutang pelanggan ini
                        </div>
                <ul class="pay-debt-list" id="payDebtList" hidden></ul>
                <div class="empty" id="payDebtEmpty" style="padding:12px;font-size:15px;">
                    Tidak ada catatan hutang.
                </div>

                <div class="pay-field" style="margin-top:14px;">
                    <label class="ck-kemarin" id="payKemarinLabel" style="margin:0;">
                        <input type="checkbox" id="payKemarinChk">
                        <span>Bayar kemarin (tampilkan form kedua)</span>
                    </label>
                </div>

                <div class="pay-field" id="payFieldK" hidden>
                    <label for="payFormK">Jumlah bayar kemarin</label>
                    <input type="text" id="payFormK" inputmode="numeric" data-money value="0" placeholder="0">
                    <div class="pay-hint" id="payHintK" hidden>
                        Sudah dicatat &rarr; <span id="payHintKAmt"></span> &mdash; nilai terbaru menggantikan yang lama.
                    </div>
                </div>

                <div class="pay-field">
                    <label for="payFormH">Jumlah bayar hari ini</label>
                    <input type="text" id="payFormH" inputmode="numeric" data-money value="0" placeholder="0">
                    <div class="pay-hint" id="payHintSisa">Sisa tagihan hari ini: <span id="paySisaAmt"></span></div>
                </div>

                <div class="pay-summary" id="paySummary" hidden>
                    <div class="ps-row"><span>Pembayaran baru</span><b id="payNewMoney">Rp0</b></div>
                </div>

                <div class="modal-actions">
                    <form method="POST" id="payFormLunas" hidden style="display:inline;">
                        @csrf
                        <input type="hidden" name="lunas" value="1">
                        <button type="submit" class="btn btn-success">Bayar lunas</button>
                    </form>
                    <form method="POST" id="payFormPartial" style="display:inline;">
                        @csrf
                        <input type="hidden" name="pakai_kemarin" id="payPakaiKemarin" value="0">
                        <input type="hidden" name="bayar_kemarin" id="payBayarKemarin" value="0">
                        <input type="hidden" name="bayar_hari_ini" id="payBayarHariIni" value="0">
                        <button type="submit" class="btn btn-primary">Bayar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
