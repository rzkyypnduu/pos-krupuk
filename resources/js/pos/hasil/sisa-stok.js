/**
 * Sisa barang: edit inline (nama, jumlah, harga), subtotal per baris,
 * dan grand total.
 */

import { posJson } from '../http.js';
import { digitUang, formatUang, tampilkanRupiahJs, uraiJumlah, uraiUang } from '../format.js';

const akar = document.getElementById('sisaStokCard');
if (akar) {
    function recountGrand() {
        let total = 0;
        akar.querySelectorAll('tr[data-remain-row]').forEach(function (baris) {
            const jumlah = uraiJumlah(baris.querySelector('input[data-remain="qty"]').value);
            const harga = uraiUang(baris.querySelector('input[data-remain="price"]').value);
            total += jumlah * harga;
        });
        const grand = akar.querySelector('#remainGrand');
        if (grand) grand.textContent = tampilkanRupiahJs(total);
    }

    function simpan(baris) {
        const id = baris.getAttribute('data-remain-row');
        const fields = {};
        ['name', 'qty', 'price'].forEach(function (kunci) {
            const input = baris.querySelector('input[data-remain="' + kunci + '"]');
            fields[kunci] = input
                ? (kunci === 'price' ? digitUang(input.value) : input.value)
                : '';
        });

        posJson('/pos/remain/' + id + '/update', fields)
            .then(function (data) {
                if (!data || !data.ok) throw new Error('bad response');
                ['name', 'qty', 'price'].forEach(function (kunci) {
                    const input = baris.querySelector('input[data-remain="' + kunci + '"]');
                    if (data[kunci] !== undefined && data[kunci] !== null && data[kunci] !== '') {
                        input.value = kunci === 'price' ? formatUang(data[kunci]) : data[kunci];
                    }
                    input.dataset.val = input.value;
                });
                baris.querySelector('[data-remain-sub]').textContent =
                    tampilkanRupiahJs((parseFloat(data.qty) || 0) * (parseFloat(data.price) || 0));
                recountGrand();
            })
            .catch(function () {
                baris.querySelectorAll('input[data-remain]').forEach(function (input) {
                    if (input.dataset.val !== undefined) input.value = input.dataset.val;
                });
                if (window.posToast) window.posToast('Gagal menyimpan sisa barang.');
            });
    }

    akar.addEventListener('change', function (e) {
        if (e.target.matches('input[data-remain]')) simpan(e.target.closest('tr'));
    });

    akar.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches || !e.target.matches('input[data-remain]')) return;
        e.preventDefault();
        e.target.blur();
    });

    function sinkronkanNilai() {
        akar.querySelectorAll('input[data-money]').forEach(function (input) {
            input.dataset.val = input.value;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sinkronkanNilai);
    } else {
        sinkronkanNilai();
    }
}
