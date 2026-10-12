{{-- Bagian 1: payload window.POS_PAY (data transaksi + sisa hutang) — dibaca
     resources/js/pos-pay.js (bundle Vite) untuk mengisi modal Bayar.
     Modal memakai Bootstrap Modal (window.bootstrap). --}}
@php
    // payload utk modal bayar: data tiap transaksi hari ini + sisa hutang pelanggan (dari sumber sama dgn tab hasil)
    $payPayload = [];
    foreach ($sales as $s) {
        $p = $hpProcessed[$s->name] ?? null;
        $payPayload[$s->id] = [
            'name' => $s->name,
            'date' => $s->date->format('Y-m-d'),
            'total' => (int) $s->rounded_total,
            'paid' => (int) $s->paid,
            'paid_kemarin' => (int) $s->paid_kemarin,
            'debt_total' => (int) ($p['totalSisa'] ?? 0),
        ];
    }
@endphp
<script>
    window.POS_PAY = {!! json_encode($payPayload, JSON_UNESCAPED_SLASHES) !!};
    window.POS_BAYAR_URL = @js(url('/pos/transaksi'));
</script>

{{-- Bagian 2: markup modal Bayar (Bootstrap modal; nilai & daftar hutang di-render
     pos-pay.js saat dibuka — tanpa Alpine). --}}
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
                <div class="pay-debt-total" id="payDebtTotal" hidden>Sisa hutang: <b id="payDebtTotalAmt">Rp0</b></div>
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
