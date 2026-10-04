/**
 * pos-pay.js — state pembayaran (Alpine store).
 * Sumber data: window.POS_PAY (disuntik halaman) — juga disimpan sebagai `rows`
 * (deep-reactive) supaya baris tabel (Dibayar/Status/badge) bisa live-update.
 * "Bayar hari ini" = TOTAL pembayaran transaksi ini (input terakhir menang);
 * uang baru yang benar-benar masuk = max(0, (payH+payK) - (paid+paid_kemarin)).
 * Field default 0; ceklis "Bayar kemarin" ON bila transaksi sudah punya
 * paid_kemarin > 0 (mencerminkan data tersimpan — tetap ON setelah reload).
 * Ceklis OFF (di tabel / di modal) = pindah alokasi uang ke "bayar hari ini",
 * live tanpa reload, dan tersimpan ke server.
 */
document.addEventListener('alpine:init', function () {
    if (!window.POS_PAY) window.POS_PAY = {};

    // seed ceklis dari DB: ON kalau paid_kemarin sudah tercatat
    var kemarinSeed = {};
    Object.keys(window.POS_PAY).forEach(function (id) {
        kemarinSeed[id] = (window.POS_PAY[id].paid_kemarin || 0) > 0;
    });

    var csrfToken = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    };

    Alpine.store('pay', {
        openId: null,
        kemarin: kemarinSeed,
        rows: window.POS_PAY,
        formK: '0',
        formH: '0',

        get sale() {
            return this.openId != null ? (this.rows[this.openId] || null) : null;
        },

        open: function (id) {
            this.openId = id;
            var s = this.rows[id] || null;
            // field kemarin ikut nilai terakhir yang tercatat (bila ceklis ON), bukan akumulasi
            this.formK = (this.kemarin[id] && s && s.paid_kemarin > 0) ? String(s.paid_kemarin) : '0';
            this.formH = '0';
            document.documentElement.style.overflow = 'hidden';
        },

        close: function () {
            this.openId = null;
            document.documentElement.style.overflow = '';
        },

        rp: function (n) {
            return 'Rp' + Number(n || 0).toLocaleString('id-ID');
        },

        parse: function (str) {
            var digits = String(str == null ? '' : str).replace(/\D+/g, '');
            return digits ? parseInt(digits, 10) : 0;
        },

        get payK() {
            return this.kemarin[this.openId] ? this.parse(this.formK) : 0;
        },

        get payH() {
            return this.parse(this.formH);
        },

        /**
         * Uang baru = kenaikan TOTAL uang transaksi ini (paid + paid_kemarin)
         * terhadap yang sudah tercatat. Pindah alokasi (toggle OFF/ON) bukan uang baru.
         */
        get newMoney() {
            var s = this.sale;
            if (!s) return 0;
            return Math.max(0, (this.payH + this.payK) - ((s.paid || 0) + (s.paid_kemarin || 0)));
        },

        get sisaTagihan() {
            var s = this.sale;
            if (!s) return 0;
            return Math.max(0, s.total - this.payH);
        },

        /** Ceklis ON (di modal) = buka form; field kemarin diisi nilai terakhir tercatat. */
        toggleKemarin: function (checked) {
            var s = this.sale;
            if (!s) return;
            this.kemarin[this.openId] = checked;
            if (checked) {
                this.formK = s.paid_kemarin > 0 ? String(s.paid_kemarin) : '0';
            } else {
                // pindah alokasi: uang kemarin masuk ke form "bayar hari ini" (live)
                this.formH = String(this.parse(this.formH) + (s.paid_kemarin || 0));
                this.formK = '0';
            }
        },

        /* ---- baris tabel (live tanpa reload) ---- */

        /** Uang yang mengurangi tagihan transaksi ini:
         *  ceklis ON → hanya paid (paid_kemarin dialokasikan ke bayar kemarin);
         *  ceklis OFF → paid + paid_kemarin (semua uang untuk transaksi ini). */
        effPaid: function (id) {
            var r = this.rows[id];
            if (!r) return 0;
            return this.kemarin[id] ? (r.paid || 0) : (r.paid || 0) + (r.paid_kemarin || 0);
        },

        rowDiff: function (id) {
            var r = this.rows[id];
            if (!r) return 0;
            return (r.total || 0) - this.effPaid(id);
        },

        statusText: function (id) {
            var d = this.rowDiff(id);
            if (d > 0) return 'Kurang ' + this.rp(d);
            if (d < 0) return 'Lebih ' + this.rp(-d);
            return 'Lunas';
        },

        statusClass: function (id) {
            var d = this.rowDiff(id);
            return d > 0 ? 'debt' : (d < 0 ? 'paid' : 'zero');
        },

        dibayarTitle: function (id) {
            var r = this.rows[id];
            if (!r) return '';
            var pk = (this.kemarin[id] ? (r.paid_kemarin || 0) : 0);
            var ph = this.effPaid(id) - pk;
            return 'Bayar kemarin ' + this.rp(pk) + ' + hari ini ' + this.rp(ph);
        },

        /**
         * Toggle ceklis di baris tabel: OFF langsung memindahkan uang ke
         * "bayar hari ini" di server (persisten), dengan update optimistik
         * supaya Status/Dibayar berubah live tanpa reload.
         */
        toggleRow: function (id, active) {
            var self = this;
            var r = this.rows[id];
            if (!r) return;

            this.kemarin[id] = active;

            var before = { paid: r.paid, paid_kemarin: r.paid_kemarin, diff: r.diff };
            var moved = false;
            if (!active && (r.paid_kemarin || 0) > 0) {
                // optimistik: uang pindah alokasi sebelum server merespons
                r.paid = (r.paid || 0) + r.paid_kemarin;
                r.paid_kemarin = 0;
                r.diff = (r.total || 0) - r.paid;
                moved = true;
            }

            fetch('/pos/transaksi/' + id + '/toggle-kemarin', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: 'active=' + (active ? 1 : 0)
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) throw new Error('bad response');
                    // konsolidasi dengan nilai server
                    r.paid = data.paid;
                    r.paid_kemarin = data.paid_kemarin;
                    r.diff = data.diff;
                })
                .catch(function () {
                    // gagal → kembalikan state seperti semula
                    if (moved) {
                        r.paid = before.paid;
                        r.paid_kemarin = before.paid_kemarin;
                        r.diff = before.diff;
                    }
                    self.kemarin[id] = !active;
                });
        }
    });
});
