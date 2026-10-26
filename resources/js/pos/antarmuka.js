/**
 * Helper UI global POS (JavaScript vanilla):
 * 1. Konfirmasi hapus aman-XSS lewat atribut data-confirm.
 * 2. Tombol qty +/- pada kartu produk.
 * 3. Notifikasi Bootstrap Toast.
 * 4. Format input uang [data-money] dan helper format bersama.
 * 5. Pemulihan posisi scroll per tab.
 */

import { digitUang, formatUang } from './format.js';
import { posJson } from './http.js';

(function () {
    'use strict';

    document.addEventListener('submit', function (e) {
        const form = e.target.closest('[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) e.preventDefault();
    });

    document.addEventListener('click', function (e) {
        const tombol = e.target.closest('[data-qty-step]');
        if (!tombol) return;
        const masukan = document.getElementById(tombol.dataset.qtyStep);
        if (!masukan) return;
        const selisih = parseFloat(tombol.dataset.delta) || 0;
        let nilai = parseFloat(String(masukan.value).replace(',', '.')) || 0;
        nilai = selisih < 0 ? Math.max(0, nilai - Math.abs(selisih)) : nilai + selisih;
        masukan.value = nilai === 0 ? '' : String(nilai).replace('.', ',');
        masukan.dispatchEvent(new Event('input', { bubbles: true }));
    });

    window.posJson = posJson;

    /** Format input uang saat mengetik; posisi kursor dihitung dari digit di kiri kursor. */
    function formatMasukanUang(input) {
        const posisiKursor = input.selectionStart == null
            ? null
            : input.value.slice(0, input.selectionStart).replace(/\D+/g, '').length;
        const terformat = formatUang(input.value);
        input.value = terformat;
        if (posisiKursor != null && typeof input.setSelectionRange === 'function') {
            let indeks = 0;
            let terlihat = 0;
            while (indeks < terformat.length && terlihat < posisiKursor) {
                if (terformat[indeks] >= '0' && terformat[indeks] <= '9') terlihat++;
                indeks++;
            }
            input.setSelectionRange(indeks, indeks);
        }
    }

    window.posMoney = { digits: digitUang, fmt: formatUang, formatInput: formatMasukanUang };

    document.addEventListener('input', function (e) {
        const target = e.target;
        if (target && target.matches && target.matches('[data-money]')) formatMasukanUang(target);
    });

    // Saat form POST dikirim: ubah nilai [data-money] menjadi digit saja agar validasi integer lolos.
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || !form.querySelectorAll) return;
        form.querySelectorAll('[data-money]').forEach(function (input) {
            input.value = digitUang(input.value);
        });
    });

    function inisialisasiUang() {
        document.querySelectorAll('[data-money]').forEach(formatMasukanUang);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inisialisasiUang);
    } else {
        inisialisasiUang();
    }

    window.posToast = function (pesan, jenis) {
        const wadah = document.getElementById('posToastHost');
        if (!wadah || !window.bootstrap || !window.bootstrap.Toast) {
            if (!window.bootstrap) window.alert(pesan);
            return;
        }
        const pembungkus = document.createElement('div');
        const warna = jenis === 'success' ? 'text-bg-success' : 'text-bg-danger';
        pembungkus.className = 'toast align-items-start ' + warna + ' border-0';
        pembungkus.setAttribute('role', 'alert');
        pembungkus.setAttribute('aria-live', 'assertive');
        pembungkus.setAttribute('aria-atomic', 'true');
        pembungkus.innerHTML = '<div class="d-flex"><div class="toast-body"></div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button></div>';
        pembungkus.querySelector('.toast-body').textContent = pesan;
        wadah.appendChild(pembungkus);
        window.bootstrap.Toast.getOrCreateInstance(pembungkus, { delay: 4000 }).show();
        pembungkus.addEventListener('hidden.bs.toast', function () { pembungkus.remove(); });
    };

    // Pertahankan posisi scroll halaman per tab.
    const tabAktif = new URLSearchParams(location.search).get('tab') || 'transaksi';
    const kunci = 'posScroll:' + tabAktif;

    window.addEventListener('pagehide', function () {
        try {
            sessionStorage.setItem(kunci, String(window.scrollY));
        } catch (e) {
            // storage penuh/nonaktif: abaikan
        }
    });

    window.addEventListener('load', function () {
        let posisiTersimpan = null;
        try {
            posisiTersimpan = sessionStorage.getItem(kunci);
            sessionStorage.removeItem(kunci);
        } catch (e) {
            return;
        }
        if (posisiTersimpan === null || location.hash) return;
        const atas = parseInt(posisiTersimpan, 10) || 0;
        if (atas <= 0) return;

        let percobaan = 5;
        (function pulihkan() {
            window.scrollTo(0, atas);
            requestAnimationFrame(function () {
                if (Math.abs(window.scrollY - atas) <= 2) return;
                if (--percobaan > 0) setTimeout(pulihkan, 250);
            });
        })();
    });
})();
