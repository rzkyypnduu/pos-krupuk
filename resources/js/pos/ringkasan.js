/**
 * Mengurutkan ulang tabel pelanggan teraktif pada tab Ringkasan.
 */

import { tampilkanRupiahJs } from './format.js';

(function () {
    'use strict';

    const body = document.getElementById('pelangganAktifBody');
    if (!body) return;

    const tombolUrut = document.querySelectorAll('.pelanggan-sort');
    let urutan = 'bayar';

    function render() {
        const baris = Array.from(body.querySelectorAll('tr[data-bayar]'));

        baris.sort(function (a, b) {
            const aBayar = Number(a.dataset.bayar) || 0;
            const bBayar = Number(b.dataset.bayar) || 0;
            const aTrx = Number(a.dataset.trx) || 0;
            const bTrx = Number(b.dataset.trx) || 0;

            if (urutan === 'trx') return (bTrx - aTrx) || (bBayar - aBayar);
            return (bBayar - aBayar) || (bTrx - aTrx);
        });

        baris.forEach(function (tr, i) {
            const nomor = tr.querySelector('.rank-i');
            const bayar = tr.querySelector('.pelanggan-bayar');

            if (nomor) nomor.textContent = i + 1;
            if (bayar) bayar.textContent = tampilkanRupiahJs(tr.dataset.bayar);
            body.appendChild(tr);
        });

        const kosong = body.querySelector('.pelanggan-empty');
        if (kosong) body.appendChild(kosong);
    }

    tombolUrut.forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            urutan = tombol.dataset.sort;

            tombolUrut.forEach(function (lain) {
                lain.classList.toggle('btn-primary', lain === tombol);
                lain.classList.toggle('btn-outline-secondary', lain !== tombol);
            });

            render();
        });
    });

    render();
})();
