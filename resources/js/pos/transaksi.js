/**
 * Logika form transaksi: status pembayaran dan hitung ulang total saat jumlah berubah.
 * Data awal dibaca dari atribut data pada #txForm.
 */

import { tampilkanKilogramJs, tampilkanRupiahJs, uraiUang } from './format.js';

(function () {
    'use strict';

    const form = document.getElementById('txForm');
    if (!form) return;

    const totalDibulatkan = Number(form.dataset.totalDibulatkan) || 0;
    // Harga produk dikirim Blade sebagai JSON; data yang tidak valid dianggap kosong.
    let hargaProduk = {};
    try {
        hargaProduk = JSON.parse(form.dataset.hargaProduk || '{}');
    } catch (error) {
        hargaProduk = {};
    }

    const statusBox = document.getElementById('statusBox');
    const inputDibayar = document.getElementById('txPaid');
    const inputTouched = document.getElementById('txPaidTouched');

    function perbaruiStatus() {
        const angkaDibayar = Math.trunc(uraiUang(inputDibayar ? inputDibayar.value : ''));
        const selisih = totalDibulatkan - angkaDibayar;
        if (!statusBox) return;

        if (selisih > 0) {
            statusBox.className = 'status-box debt';
            statusBox.textContent = 'Kurang ' + tampilkanRupiahJs(selisih);
        } else if (selisih < 0) {
            statusBox.className = 'status-box paid';
            statusBox.textContent = 'Lebih ' + tampilkanRupiahJs(-selisih);
        } else {
            statusBox.className = 'status-box zero';
            statusBox.innerHTML = 'Lunas &#10003;';
        }
    }

    /**
     * Menghitung total kg, total kotor, dan total dibulatkan dari input qty.
     * Aturan pembulatan mengikuti bulatkanTotal() di app/Support/helpers.php.
     */
    function hitungUlangTotal() {
        let totalKilogram = 0;
        let totalKotor = 0;

        form.querySelectorAll('[data-product-id]').forEach(function (masukan) {
            const nilai = parseFloat(String(masukan.value).replace(',', '.')) || 0;
            if (nilai > 0) {
                totalKilogram += nilai;
                totalKotor += nilai * (Number(hargaProduk[masukan.dataset.productId]) || 0);
            }
        });

        totalKotor = Math.round(totalKotor);
        const ribuan = Math.floor(totalKotor / 1000) * 1000;
        const sisa = totalKotor - ribuan;
        const dibulatkan = sisa < 500 ? ribuan : ribuan + 1000;

        const totalKgDisplay = document.getElementById('totalKgDisplay');
        const rawTotalDisplay = document.getElementById('rawTotalDisplay');
        const roundedTotalDisplay = document.getElementById('roundedTotalDisplay');

        if (totalKgDisplay) totalKgDisplay.textContent = tampilkanKilogramJs(totalKilogram) + ' kg';
        if (rawTotalDisplay) rawTotalDisplay.textContent = tampilkanRupiahJs(totalKotor);
        if (roundedTotalDisplay) roundedTotalDisplay.textContent = tampilkanRupiahJs(dibulatkan);

        if (statusBox) {
            statusBox.innerHTML = 'Belum dibayar: ' + tampilkanRupiahJs(dibulatkan);
            statusBox.className = 'status-box debt';
        }
    }

    form.addEventListener('input', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        if (target.matches('[data-product-id]')) {
            const kartu = target.closest('.product-card');
            if (kartu) {
                const nilai = parseFloat(String(target.value).replace(',', '.')) || 0;
                kartu.classList.toggle('has-qty', nilai > 0);
            }
            hitungUlangTotal();
            return;
        }

        if (target.id === 'txName') {
            if (inputTouched) inputTouched.value = '1';
            return;
        }

        if (target.id === 'txPaid') {
            if (inputTouched) inputTouched.value = '1';
            perbaruiStatus();
        }
    });

    form.addEventListener('click', function (event) {
        const tombol = event.target.closest('[data-pas]');
        if (!tombol || !inputDibayar) return;

        inputDibayar.value = String(totalDibulatkan);
        inputDibayar.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // Dipanggil resources/js/pos/suara.js setelah qty hasil ucapan diterapkan.
    window.hitungUlangTotal = hitungUlangTotal;
})();
