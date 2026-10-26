/**
 * Keadaan pembayaran modal Bayar (JavaScript vanilla + Bootstrap).
 * Sumber data: window.POS_PAY dari halaman _modal_bayar.
 * "Bayar hari ini" = total pembayaran transaksi ini; input terakhir menang.
 * Uang baru yang masuk = max(0, (bayarHariIni+bayarKemarin) - (paid+paid_kemarin)).
 * Field default 0; ceklis "Bayar kemarin" ON bila kemarin_active = true.
 * Ceklis ON/OFF hanya mengubah flag kemarin_active; uang tidak dipindahkan
 * antara paid dan paid_kemarin. diff dihitung server:
 *   ON  → diff = rounded_total - paid
 *   OFF → diff = rounded_total - paid - paid_kemarin
 * Baris tabel dirender Blade, lalu diperbarui tampilkanBaris() saat keadaan berubah.
 */
(function () {
    'use strict';

    var barisPembayaran = window.POS_PAY || {};
    var urlPos = window.POS_BAYAR_URL || '/pos/transaksi';

    // Helper format uang dari antarmuka.js (window.posMoney) — keadaan simpan digit,
    // tampilan berformat titik ribuan (15000 -> "15.000").
    var uang = window.posMoney || {
        digits: function (v) { return String(v == null ? '' : v).replace(/\D+/g, ''); },
        fmt: function (v) { var d = this.digits(v); return d ? Number(d).toLocaleString('id-ID') : ''; },
    };

    var keadaan = {
        idTerbuka: null,
        kemarin: {},
        formKemarin: '0',
        formHariIni: '0',
    };

    // seed ceklis dari DB: ON kalau kemarin_active = true
    Object.keys(barisPembayaran).forEach(function (id) {
        keadaan.kemarin[id] = !!barisPembayaran[id].kemarin_active;
    });

    function elemen(id) { return document.getElementById(id); }
    function rupiah(n) { return 'Rp' + Number(n || 0).toLocaleString('id-ID'); }
    function urai(str) {
        var digits = String(str == null ? '' : str).replace(/\D+/g, '');
        return digits ? parseInt(digits, 10) : 0;
    }
    function penjualan() { return keadaan.idTerbuka != null ? (barisPembayaran[keadaan.idTerbuka] || null) : null; }
    function bayarKemarin() { return keadaan.kemarin[keadaan.idTerbuka] ? urai(keadaan.formKemarin) : 0; }
    function bayarHariIni() { return urai(keadaan.formHariIni); }

    /**
     * Uang baru = kenaikan TOTAL uang transaksi ini (paid + paid_kemarin)
     * terhadap yang sudah tercatat. Pindah alokasi (toggle OFF/ON) bukan uang baru.
     */
    function uangBaru() {
        var s = penjualan();
        if (!s) return 0;
        return Math.max(0, (bayarHariIni() + bayarKemarin()) - ((s.paid || 0) + (s.paid_kemarin || 0)));
    }

    function sisaTagihan() {
        var s = penjualan();
        if (!s) return 0;
        return Math.max(0, s.total - bayarHariIni());
    }

    /** Uang yang mengurangi tagihan transaksi ini:
     *  ceklis ON -> hanya paid (paid_kemarin dialokasikan ke bayar kemarin);
     *  ceklis OFF -> paid + paid_kemarin (semua uang untuk transaksi ini). */
    function dibayarEfektif(id) {
        var r = barisPembayaran[id];
        if (!r) return 0;
        return keadaan.kemarin[id] ? (r.paid || 0) : (r.paid || 0) + (r.paid_kemarin || 0);
    }

    function selisihBaris(id) {
        var r = barisPembayaran[id];
        if (!r) return 0;
        return (r.total || 0) - dibayarEfektif(id);
    }

    function teksStatus(id) {
        var d = selisihBaris(id);
        if (d > 0) return 'Kurang ' + rupiah(d);
        if (d < 0) return 'Lebih ' + rupiah(-d);
        return 'Lunas';
    }

    function kelasStatus(id) {
        var d = selisihBaris(id);
        return d > 0 ? 'debt' : (d < 0 ? 'paid' : 'zero');
    }

    function judulDibayar(id) {
        var r = barisPembayaran[id];
        if (!r) return '';
        var pk = (keadaan.kemarin[id] ? (r.paid_kemarin || 0) : 0);
        var ph = dibayarEfektif(id) - pk;
        return 'Bayar kemarin ' + rupiah(pk) + ' + hari ini ' + rupiah(ph);
    }

    /* ---- baris tabel (live tanpa reload) ---- */

    function elemenBaris(id) { return document.querySelector('[data-pay-row="' + id + '"]'); }

    function tampilkanBaris(id) {
        var row = elemenBaris(id);
        if (!row) return;
        var r = barisPembayaran[id] || {};
        var paid = row.querySelector('[data-pay-paid]');
        if (paid) {
            paid.textContent = rupiah((r.paid || 0) + (r.paid_kemarin || 0));
            paid.setAttribute('title', judulDibayar(id));
        }
        var badge = row.querySelector('[data-pay-badge]');
        if (badge) {
            badge.className = 'badge ' + kelasStatus(id);
            badge.textContent = teksStatus(id);
        }
        var kb = row.querySelector('[data-pay-kbadge]');
        if (kb) {
            // badge tampil selama ceklis aktif, berapa pun nilainya (termasuk Rp0)
            var show = !!keadaan.kemarin[id];
            kb.classList.toggle('d-none', !show);
            if (show) kb.textContent = 'Bayar kemarin ' + rupiah(r.paid_kemarin || 0);
        }
        var ck = row.querySelector('[data-pay-ck]');
        if (ck) ck.checked = !!keadaan.kemarin[id];
        var lb = row.querySelector('.ck-kemarin');
        if (lb) lb.classList.toggle('on', !!keadaan.kemarin[id]);
    }

    function tampilkanSemuaBaris() {
        Object.keys(barisPembayaran).forEach(function (id) { tampilkanBaris(id); });
    }

    /* ---- modal Bayar ---- */

    function tampilkanUang() {
        var s = penjualan();
        if (!s) return;
        elemen('paySisaAmt').textContent = rupiah(sisaTagihan());
        elemen('payNewMoney').textContent = rupiah(uangBaru());
        elemen('payBayarKemarin').value = bayarKemarin();
        elemen('payBayarHariIni').value = bayarHariIni();
        elemen('payPakaiKemarin').value = keadaan.kemarin[keadaan.idTerbuka] ? 1 : 0;
    }

    function tampilkanModal() {
        var s = penjualan();
        elemen('payCustName').textContent = s ? s.name : '';
        elemen('payTotalsLine').hidden = !s;
        if (s) {
            elemen('payTagihan').textContent = rupiah(s.total);
            elemen('paySudah').textContent = rupiah(s.paid);
            var showKWrap = (s.paid_kemarin || 0) > 0;
            elemen('payKemarinWrap').hidden = !showKWrap;
            if (showKWrap) elemen('payKemarinAmt').textContent = rupiah(s.paid_kemarin);
        }

        // daftar hutang (terbaru -> terlama, urutan dari payload)
        var list = elemen('payDebtList');
        list.innerHTML = '';
        var debts = s ? (s.debts || []) : [];
        debts.forEach(function (d, i) {
            var li = document.createElement('li');
            var date = document.createElement('span');
            date.className = 'pd-date';
            date.textContent = d.date;
            var amt = document.createElement('span');
            amt.className = 'pd-amt';
            amt.textContent = rupiah(d.remaining);
            li.appendChild(date);
            li.appendChild(amt);
            list.appendChild(li);
        });
        list.hidden = debts.length === 0;
        elemen('payDebtEmpty').hidden = debts.length > 0;

        // ceklis "bayar kemarin" + form kedua
        var on = keadaan.kemarin[keadaan.idTerbuka] === true;
        elemen('payKemarinChk').checked = on;
        elemen('payKemarinLabel').classList.toggle('on', on);
        elemen('payFieldK').hidden = !on;
        var showHintK = on && s && (s.paid_kemarin || 0) > 0;
        elemen('payHintK').hidden = !showHintK;
        if (showHintK) elemen('payHintKAmt').textContent = rupiah(s.paid_kemarin);

        elemen('payFormK').value = uang.fmt(keadaan.formKemarin) || '0';
        elemen('payFormH').value = uang.fmt(keadaan.formHariIni) || '0';
        elemen('payHintSisa').hidden = !(s && s.total > 0);
        elemen('paySummary').hidden = !s;
        elemen('payFormLunas').hidden = !(s && s.total > 0);

        if (keadaan.idTerbuka != null) {
            var base = urlPos + '/' + keadaan.idTerbuka + '/bayar';
            elemen('payFormLunas').action = base;
            elemen('payFormPartial').action = base;
        }
        tampilkanUang();
    }

    /** Ceklis ON (di modal) = buka form kedua; OFF = sembunyikan + reset formKemarin ke 0.
     *  Tanpa memindahkan uang antara formHariIni dan formKemarin (server juga tidak memindahkan). */
    function alihkanKemarin(checked) {
        var s = penjualan();
        if (!s) return;
        keadaan.kemarin[keadaan.idTerbuka] = checked;
        if (checked) {
            keadaan.formKemarin = (s.paid_kemarin > 0) ? String(s.paid_kemarin) : '0';
        } else {
            keadaan.formKemarin = '0';
        }
        tampilkanModal();
        tampilkanBaris(keadaan.idTerbuka);
    }

    function buka(id) {
        keadaan.idTerbuka = Number(id);
        var s = barisPembayaran[keadaan.idTerbuka] || null;
        // field kemarin ikut nilai terakhir yang tercatat (bila ceklis ON), bukan akumulasi
        keadaan.formKemarin = (keadaan.kemarin[keadaan.idTerbuka] && s && s.paid_kemarin > 0) ? String(s.paid_kemarin) : '0';
        keadaan.formHariIni = '0';
        tampilkanModal();
        var modalEl = elemen('payModal');
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    function tutup() {
        var modalEl = elemen('payModal');
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        } else {
            modalEl.classList.remove('show');
            modalEl.style.display = '';
        }
        keadaan.idTerbuka = null;
    }

    // ditutup lewat tombol X / backdrop / Escape oleh Bootstrap -> keadaan ikut bersih
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target && e.target.id === 'payModal') keadaan.idTerbuka = null;
    });

    /**
     * Toggle ceklis di baris tabel: hanya mengubah flag kemarin_active (persisten
     * di server) + update live Status/Dibayar via tampilkanBaris — TANPA memindahkan uang.
     */
    function alihkanBaris(id, active) {
        var r = barisPembayaran[id];
        if (!r) return;

        keadaan.kemarin[id] = active;
        tampilkanBaris(id);

        fetch(urlPos + '/' + id + '/toggle-kemarin', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
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
                tampilkanBaris(id);
            })
            .catch(function () {
                // gagal -> kembalikan keadaan seperti semula + beri tahu user
                keadaan.kemarin[id] = !active;
                tampilkanBaris(id);
                if (window.posToast) window.posToast('Gagal menyimpan perubahan "bayar kemarin".');
            });
    }

    /* ---- bindings (event delegation; markup tanpa atribut JS inline) ---- */

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pay-open]');
        if (btn) buka(btn.getAttribute('data-pay-open'));
    });

    document.addEventListener('change', function (e) {
        var ck = e.target.closest('[data-pay-ck]');
        if (ck) alihkanBaris(Number(ck.getAttribute('data-pay-ck')), ck.checked);
    });

    if (elemen('payFormK')) elemen('payFormK').addEventListener('input', function () { keadaan.formKemarin = uang.digits(this.value) || '0'; tampilkanUang(); });
    if (elemen('payFormH')) elemen('payFormH').addEventListener('input', function () { keadaan.formHariIni = uang.digits(this.value) || '0'; tampilkanUang(); });
    if (elemen('payKemarinChk')) elemen('payKemarinChk').addEventListener('change', function () { alihkanKemarin(this.checked); });

    tampilkanSemuaBaris();
})();
