{{-- Bagian 1: payload window.POS_PAY (data transaksi + sisa hutang) — dibaca pos-pay.js
     lalu didaftarkan sebagai Alpine.store('pay') sebelum Alpine CDN start. --}}
@php
    // payload utk modal bayar: data tiap transaksi hari ini + sisa hutang pelanggan (dari sumber yang sama dgn tab hasil)
    $payPayload = [];
    foreach ($sales as $s) {
        $p = $hpProcessed[$s->name] ?? null;
        $debtsDesc = array_values(array_reverse($p['activeDebts'] ?? [])); // terbaru -> terlama
        $payPayload[$s->id] = [
            'name' => $s->name,
            'date' => $s->date->format('Y-m-d'),
            'total' => (int) $s->rounded_total,
            'paid' => (int) $s->paid,
            'paid_kemarin' => (int) $s->paid_kemarin,
            'debts' => array_map(fn ($d) => [
                'id' => $d['id'],
                'date' => $d['date'],
                'amount' => (int) $d['amount'],
                'remaining' => (int) $d['remaining'],
            ], $debtsDesc),
        ];
    }
@endphp
<script>
    window.POS_PAY = {!! json_encode($payPayload, JSON_UNESCAPED_SLASHES) !!};
</script>

{{-- Bagian 2: markup modal Bayar (tombol/field dikendalikan store $store.pay) --}}
<div class="modal-backdrop" id="payModal" :class="{ 'open': $store.pay.openId !== null }" @click="$store.pay.close()" x-cloak>
    <div class="pay-confirm-modal" @click.stop role="dialog" aria-modal="true" aria-label="Modal pembayaran">
        <div class="pay-confirm-head">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                <div>
                    <h2>Bayar &mdash; <span x-text="$store.pay.sale ? $store.pay.sale.name : ''"></span></h2>
                    <p x-show="$store.pay.sale">
                        Tagihan <b x-text="$store.pay.rp($store.pay.sale ? $store.pay.sale.total : 0)"></b>
                        &middot; Sudah dibayar <b x-text="$store.pay.rp($store.pay.sale ? $store.pay.sale.paid : 0)"></b>
                        <template x-if="$store.pay.sale && $store.pay.sale.paid_kemarin > 0">
                            <span>&middot; Bayar kemarin <b x-text="$store.pay.rp($store.pay.sale.paid_kemarin)"></b></span>
                        </template>
                    </p>
                </div>
                <button type="button" class="pay-x" @click="$store.pay.close()" aria-label="Tutup modal">&times;</button>
            </div>
        </div>

        <div class="pay-confirm-body">
            <div class="pay-debt-title">
                Hutang pelanggan ini <span class="dim">&mdash; terbaru &rarr; terlama</span>
            </div>
            <ul class="pay-debt-list" x-show="$store.pay.sale && $store.pay.sale.debts.length > 0">
                <template x-for="(d, i) in ($store.pay.sale ? $store.pay.sale.debts : [])" :key="d.id">
                    <li :class="i === 0 ? 'last' : ''">
                        <span class="pd-date" x-text="d.date"></span>
                        <span class="pd-amt" x-text="$store.pay.rp(d.remaining)"></span>
                    </li>
                </template>
            </ul>
            <div class="empty" x-show="$store.pay.sale && $store.pay.sale.debts.length === 0" style="padding:12px;font-size:15px;">
                Tidak ada catatan hutang.
            </div>

            <div class="pay-field" style="margin-top:14px;">
                <label class="ck-kemarin" :class="{ 'on': $store.pay.kemarin[$store.pay.openId] }" style="margin:0;">
                    <input type="checkbox" id="payKemarinChk"
                           :checked="!!$store.pay.kemarin[$store.pay.openId]"
                           @change="$store.pay.toggleKemarin($event.target.checked)">
                    <span>Bayar kemarin (tampilkan form kedua)</span>
                </label>
            </div>

            <template x-if="$store.pay.kemarin[$store.pay.openId]">
                <div class="pay-field">
                    <label for="payFormK">Jumlah bayar kemarin</label>
                    <input type="text" id="payFormK" inputmode="numeric" x-model="$store.pay.formK" placeholder="0">
                    <div class="pay-hint" x-show="$store.pay.sale && $store.pay.sale.paid_kemarin > 0">
                        Sudah dicatat &rarr; <span x-text="$store.pay.sale ? $store.pay.rp($store.pay.sale.paid_kemarin) : ''"></span> &mdash; nilai terbaru menggantikan yang lama.
                    </div>
                </div>
            </template>

            <div class="pay-field">
                <label for="payFormH">Jumlah bayar hari ini</label>
                <input type="text" id="payFormH" inputmode="numeric" x-model="$store.pay.formH" placeholder="0">
                <div class="pay-hint" x-show="$store.pay.sale && $store.pay.sale.total > 0">
                    Sisa tagihan hari ini: <span x-text="$store.pay.rp($store.pay.sisaTagihan)"></span>
                </div>
                <div class="pay-hint">Isi total transaksi ini &mdash; nilai terbaru menggantikan yang lama (boleh 0).</div>
            </div>

            <div class="pay-summary" x-show="$store.pay.sale">
                <div class="ps-row"><span>Pembayaran baru</span><b x-text="$store.pay.rp($store.pay.newMoney)"></b></div>
            </div>

            <div class="modal-actions">
                <form method="POST" :action="'{{ url('/pos/transaksi') }}/' + $store.pay.openId + '/bayar'"
                      x-show="$store.pay.sale && $store.pay.sale.total > 0" style="display:inline;">
                    @csrf
                    <input type="hidden" name="lunas" value="1">
                    <button type="submit" class="primary" style="background:var(--paid);">Bayar lunas</button>
                </form>
                <form method="POST" :action="'{{ url('/pos/transaksi') }}/' + $store.pay.openId + '/bayar'"
                      style="display:inline;">
                    @csrf
                    <input type="hidden" name="pakai_kemarin" :value="$store.pay.kemarin[$store.pay.openId] ? 1 : 0">
                    <input type="hidden" name="bayar_kemarin" :value="$store.pay.payK">
                    <input type="hidden" name="bayar_hari_ini" :value="$store.pay.payH">
                    <button type="submit" class="primary">Bayar</button>
                </form>
            </div>
        </div>
    </div>
</div>
