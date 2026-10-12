/**
 * pos-pay.js — state pembayaran (vanilla JS, tanpa Alpine).
 * Sumber data: window.POS_PAY (disuntik halaman _modal_bayar) — diubah jadi
 * window.POSPAY. "Bayar hari ini" = TOTAL pembayaran transaksi ini (input terakhir
 * menang); uang baru yang benar-benar masuk = max(0, (payH+payK) - (paid+paid_kemarin)).
 * Field default 0; ceklis "Bayar kemarin" ON bila transaksi sudah punya
 * paid_kemarin > 0 (mencerminkan data tersimpan — tetap ON setelah reload).
 * Ceklis OFF (di tabel / di modal) = pindah alokasi uang ke "bayar hari ini",
 * live tanpa reload, dan tersimpan ke server.
 * Modal memakai Bootstrap Modal (window.bootstrap dari bundle Vite); baris tabel
 * dirender server oleh Blade, lalu diperbarui renderRow() saat state berubah.
 */
(function () {
    'use strict';

    var rows = window.POS_PAY || {};
    var posUrl = window.POS_BAYAR_URL || '/pos/transaksi';

    // Helper format uang dari pos-ui.js (window.posMoney) — state simpan digit,
    // tampilan berformat titik ribuan (15000 -> "15.000").
    var money = window.posMoney || {
        digits: function (v) { return String(v == null ? '' : v).replace(/\D+/g, ''); },
        fmt: function (v) { var d = this.digits(v); return d ? Number(d).toLocaleString('id-ID') : ''; },
    };

    var state = {
        openId: null,
        kemarin: {},
        formK: '0',
        formH: '0',
    };

    // seed ceklis dari DB: ON kalau paid_kemarin sudah tercatat
    Object.keys(rows).forEach(function (id) {
        state.kemarin[id] = (rows[id].paid_kemarin || 0) > 0;
    });

    function el(id) { return document.getElementById(id); }
    function rp(n) { return 'Rp' + Number(n || 0).toLocaleString('id-ID'); }
    function parse(str) {
        var digits = String(str == null ? '' : str).replace(/\D+/g, '');
        return digits ? parseInt(digits, 10) : 0;
    }
    function sale() { return state.openId != null ? (rows[state.openId] || null) : null; }
    function payK() { return state.kemarin[state.openId] ? parse(state.formK) : 0; }
    function payH() { return parse(state.formH); }

    /**
     * Uang baru = kenaikan TOTAL uang transaksi ini (paid + paid_kemarin)
     * terhadap yang sudah tercatat. Pindah alokasi (toggle OFF/ON) bukan uang baru.
     */
    function newMoney() {
        var s = sale();
        if (!s) return 0;
        return Math.max(0, (payH() + payK()) - ((s.paid || 0) + (s.paid_kemarin || 0)));
    }

    function sisaTagihan() {
        var s = sale();
        if (!s) return 0;
        return Math.max(0, s.total - payH());
    }

    /** Uang yang mengurangi tagihan transaksi ini:
     *  ceklis ON -> hanya paid (paid_kemarin dialokasikan ke bayar kemarin);
     *  ceklis OFF -> paid + paid_kemarin (semua uang untuk transaksi ini). */
    function effPaid(id) {
        var r = rows[id];
        if (!r) return 0;
        return state.kemarin[id] ? (r.paid || 0) : (r.paid || 0) + (r.paid_kemarin || 0);
    }

    function rowDiff(id) {
        var r = rows[id];
        if (!r) return 0;
        return (r.total || 0) - effPaid(id);
    }

    function statusText(id) {
        var d = rowDiff(id);
        if (d > 0) return 'Kurang ' + rp(d);
        if (d < 0) return 'Lebih ' + rp(-d);
        return 'Lunas';
    }

    function statusClass(id) {
        var d = rowDiff(id);
        return d > 0 ? 'debt' : (d < 0 ? 'paid' : 'zero');
    }

    function dibayarTitle(id) {
        var r = rows[id];
        if (!r) return '';
        var pk = (state.kemarin[id] ? (r.paid_kemarin || 0) : 0);
        var ph = effPaid(id) - pk;
        return 'Bayar kemarin ' + rp(pk) + ' + hari ini ' + rp(ph);
    }

    /* ---- baris tabel (live tanpa reload) ---- */

    function rowEl(id) { return document.querySelector('[data-pay-row="' + id + '"]'); }

    function renderRow(id) {
        var row = rowEl(id);
        if (!row) return;
        var r = rows[id] || {};
        var paid = row.querySelector('[data-pay-paid]');
        if (paid) {
            paid.textContent = rp((r.paid || 0) + (r.paid_kemarin || 0));
            paid.setAttribute('title', dibayarTitle(id));
        }
        var badge = row.querySelector('[data-pay-badge]');
        if (badge) {
            badge.className = 'badge ' + statusClass(id);
            badge.textContent = statusText(id);
        }
        var kb = row.querySelector('[data-pay-kbadge]');
        if (kb) {
            // badge tampil selama ceklis aktif, berapa pun nilainya (termasuk Rp0)
            var show = !!state.kemarin[id];
            kb.classList.toggle('d-none', !show);
            if (show) kb.textContent = 'Bayar kemarin ' + rp(r.paid_kemarin || 0);
        }
        var ck = row.querySelector('[data-pay-ck]');
        if (ck) ck.checked = !!state.kemarin[id];
        var lb = row.querySelector('.ck-kemarin');
        if (lb) lb.classList.toggle('on', !!state.kemarin[id]);
    }

    function renderAllRows() {
        Object.keys(rows).forEach(function (id) { renderRow(id); });
    }

    /* ---- modal Bayar ---- */

    function renderMoney() {
        var s = sale();
        if (!s) return;
        el('paySisaAmt').textContent = rp(sisaTagihan());
        el('payNewMoney').textContent = rp(newMoney());
        el('payBayarKemarin').value = payK();
        el('payBayarHariIni').value = payH();
        el('payPakaiKemarin').value = state.kemarin[state.openId] ? 1 : 0;
    }

    function renderModal() {
        var s = sale();
        el('payCustName').textContent = s ? s.name : '';
        el('payTotalsLine').hidden = !s;
        if (s) {
            el('payTagihan').textContent = rp(s.total);
            el('paySudah').textContent = rp(s.paid);
            var showKWrap = (s.paid_kemarin || 0) > 0;
            el('payKemarinWrap').hidden = !showKWrap;
            if (showKWrap) el('payKemarinAmt').textContent = rp(s.paid_kemarin);
        }

        // daftar hutang (terbaru -> terlama, urutan dari payload)
        var list = el('payDebtList');
        list.innerHTML = '';
        var debts = s ? (s.debts || []) : [];
        debts.forEach(function (d, i) {
            var li = document.createElement('li');
            var date = document.createElement('span');
            date.className = 'pd-date';
            date.textContent = d.date;
            var amt = document.createElement('span');
            amt.className = 'pd-amt';
            amt.textContent = rp(d.remaining);
            li.appendChild(date);
            li.appendChild(amt);
            list.appendChild(li);
        });
        list.hidden = debts.length === 0;
        el('payDebtEmpty').hidden = debts.length > 0;

        // ceklis "bayar kemarin" + form kedua
        var on = state.kemarin[state.openId] === true;
        el('payKemarinChk').checked = on;
        el('payKemarinLabel').classList.toggle('on', on);
        el('payFieldK').hidden = !on;
        var showHintK = on && s && (s.paid_kemarin || 0) > 0;
        el('payHintK').hidden = !showHintK;
        if (showHintK) el('payHintKAmt').textContent = rp(s.paid_kemarin);

        el('payFormK').value = money.fmt(state.formK) || '0';
        el('payFormH').value = money.fmt(state.formH) || '0';
        el('payHintSisa').hidden = !(s && s.total > 0);
        el('paySummary').hidden = !s;
        el('payFormLunas').hidden = !(s && s.total > 0);

        if (state.openId != null) {
            var base = posUrl + '/' + state.openId + '/bayar';
            el('payFormLunas').action = base;
            el('payFormPartial').action = base;
        }
        renderMoney();
    }

    /** Ceklis ON (di modal) = buka form + kembalikan uang dari "hari ini" ke "kemarin" (live, simetris dgn OFF). */
    function toggleKemarin(checked) {
        var s = sale();
        if (!s) return;
        state.kemarin[state.openId] = checked;
        if (checked) {
            // pindah alokasi balik: uang dari "bayar hari ini" kembali ke "bayar kemarin" (live)
            var prev = s.paid_kemarin || 0;
            state.formK = String(prev);
            state.formH = String(Math.max(0, parse(state.formH) - prev));
        } else {
            // pindah alokasi: uang kemarin masuk ke form "bayar hari ini" (live)
            state.formH = String(parse(state.formH) + (s.paid_kemarin || 0));
            state.formK = '0';
        }
        renderModal();
        renderRow(state.openId);
    }

    function open(id) {
        state.openId = Number(id);
        var s = rows[state.openId] || null;
        // field kemarin ikut nilai terakhir yang tercatat (bila ceklis ON), bukan akumulasi
        state.formK = (state.kemarin[state.openId] && s && s.paid_kemarin > 0) ? String(s.paid_kemarin) : '0';
        state.formH = '0';
        renderModal();
        var modalEl = el('payModal');
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    function close() {
        var modalEl = el('payModal');
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        } else {
            modalEl.classList.remove('show');
            modalEl.style.display = '';
        }
        state.openId = null;
    }

    // ditutup lewat tombol X / backdrop / Escape oleh Bootstrap -> state ikut bersih
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target && e.target.id === 'payModal') state.openId = null;
    });

    /**
     * Toggle ceklis di baris tabel: OFF langsung memindahkan uang ke
     * "bayar hari ini" di server (persisten), dengan update optimistik
     * supaya Status/Dibayar berubah live tanpa reload.
     */
    function toggleRow(id, active) {
        var r = rows[id];
        if (!r) return;

        state.kemarin[id] = active;

        var before = { paid: r.paid, paid_kemarin: r.paid_kemarin, diff: r.diff };
        var moved = false;
        if (!active && (r.paid_kemarin || 0) > 0) {
            // optimistik: uang pindah alokasi sebelum server merespons
            r.paid = (r.paid || 0) + r.paid_kemarin;
            r.paid_kemarin = 0;
            r.diff = (r.total || 0) - r.paid;
            moved = true;
        }
        renderRow(id);

        fetch(posUrl + '/' + id + '/toggle-kemarin', {
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
                renderRow(id);
            })
            .catch(function () {
                // gagal -> kembalikan state seperti semula + beri tahu user
                if (moved) {
                    r.paid = before.paid;
                    r.paid_kemarin = before.paid_kemarin;
                    r.diff = before.diff;
                }
                state.kemarin[id] = !active;
                renderRow(id);
                if (window.posToast) window.posToast('Gagal menyimpan perubahan "bayar kemarin".');
            });
    }

    /* ---- bindings (event delegation; markup tanpa atribut JS inline) ---- */

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pay-open]');
        if (btn) open(btn.getAttribute('data-pay-open'));
    });

    document.addEventListener('change', function (e) {
        var ck = e.target.closest('[data-pay-ck]');
        if (ck) toggleRow(Number(ck.getAttribute('data-pay-ck')), ck.checked);
    });

    if (el('payFormK')) el('payFormK').addEventListener('input', function () { state.formK = money.digits(this.value) || '0'; renderMoney(); });
    if (el('payFormH')) el('payFormH').addEventListener('input', function () { state.formH = money.digits(this.value) || '0'; renderMoney(); });
    if (el('payKemarinChk')) el('payKemarinChk').addEventListener('change', function () { toggleKemarin(this.checked); });

    renderAllRows();
})();
