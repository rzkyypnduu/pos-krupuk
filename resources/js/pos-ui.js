/**
 * pos-ui.js — helper UI vanilla (tanpa Alpine).
 * 1. Konfirmasi hapus aman-XSS lewat atribut data-confirm (pesan di-escape Blade,
 *    dibaca dari dataset — bukan disisipkan ke string JS inline).
 * 2. Tombol qty +/- pada kartu produk: <button data-qty-step="idInput" data-delta="0.5">.
 * 3. Notifikasi gagal/sukses via Bootstrap Toast (posToast).
 * 4. Input uang [data-money]: format titik ribuan live (15000 -> "15.000"),
 *    strip digit-only sebelum form POST; qty kg TIDAK pakai atribut ini.
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) e.preventDefault();
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-qty-step]');
        if (!btn) return;
        var input = document.getElementById(btn.dataset.qtyStep);
        if (!input) return;
        var delta = parseFloat(btn.dataset.delta) || 0;
        var v = parseFloat(String(input.value).replace(',', '.')) || 0;
        v = delta < 0 ? Math.max(0, v - Math.abs(delta)) : v + delta;
        input.value = v === 0 ? '' : String(v).replace('.', ',');
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    /** POST JSON (endpoint tabel auto-save menerima payload JSON). */
    window.posJson = function (url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
            },
            body: JSON.stringify(payload),
        }).then(function (res) { return res.json(); });
    };

    /* ---- format uang live [data-money] — pasangan parseRupiah() di PHP ---- */

    function moneyDigits(v) {
        return String(v == null ? '' : v).replace(/\D+/g, '');
    }

    function moneyFmt(v) {
        var d = moneyDigits(v);
        return d ? Number(d).toLocaleString('id-ID') : '';
    }

    /** Format input uang saat mengetik; kursor dipertahankan via jumlah digit sebelumnya. */
    function moneyFormatInput(inp) {
        var caret = inp.selectionStart == null
            ? null
            : inp.value.slice(0, inp.selectionStart).replace(/\D+/g, '').length;
        var formatted = moneyFmt(inp.value);
        inp.value = formatted;
        if (caret != null && typeof inp.setSelectionRange === 'function') {
            var i = 0, seen = 0;
            while (i < formatted.length && seen < caret) {
                if (formatted[i] >= '0' && formatted[i] <= '9') seen++;
                i++;
            }
            inp.setSelectionRange(i, i);
        }
    }

    window.posMoney = { digits: moneyDigits, fmt: moneyFmt, formatInput: moneyFormatInput };

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (t && t.matches && t.matches('[data-money]')) moneyFormatInput(t);
    });

    // Sebelum form POST biasa dikirim: kirim digit-only supaya validasi integer lolos.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.querySelectorAll) return;
        form.querySelectorAll('[data-money]').forEach(function (inp) { inp.value = moneyDigits(inp.value); });
    });

    // Format nilai awal yang sudah ter-render server (old()/DB) saat load.
    function moneyInit() {
        document.querySelectorAll('[data-money]').forEach(moneyFormatInput);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', moneyInit);
    else moneyInit();

    window.posToast = function (message, type) {
        var host = document.getElementById('posToastHost');
        if (!host || !window.bootstrap || !window.bootstrap.Toast) {
            if (!window.bootstrap) window.alert(message);
            return;
        }
        var wrap = document.createElement('div');
        var color = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
        wrap.className = 'toast align-items-start ' + color + ' border-0';
        wrap.setAttribute('role', 'alert');
        wrap.setAttribute('aria-live', 'assertive');
        wrap.setAttribute('aria-atomic', 'true');
        wrap.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button></div>';
        wrap.querySelector('.toast-body').textContent = message;
        host.appendChild(wrap);
        window.bootstrap.Toast.getOrCreateInstance(wrap, { delay: 4000 }).show();
        wrap.addEventListener('hidden.bs.toast', function () { wrap.remove(); });
    };
})();
