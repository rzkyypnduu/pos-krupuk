/**
 * Hutang pribadi: edit inline nama/jumlah dan grand total.
 */

import { posJson } from '../http.js';
import { digitUang, formatUang, tampilkanRupiahJs, uraiUang } from '../format.js';

const akar = document.getElementById('hutangPribadiCard');
if (akar) {
    function recountGrand() {
        let total = 0;
        akar.querySelectorAll('tr[data-hpr-row]').forEach(function (baris) {
            total += uraiUang(baris.querySelector('input[data-hpr="amount"]').value);
        });
        const grand = akar.querySelector('#hprGrand');
        if (grand) grand.textContent = tampilkanRupiahJs(total);
    }

    function simpan(baris) {
        const id = baris.getAttribute('data-hpr-row');
        const fields = {};
        ['name', 'amount'].forEach(function (kunci) {
            const input = baris.querySelector('input[data-hpr="' + kunci + '"]');
            fields[kunci] = input
                ? (kunci === 'amount' ? digitUang(input.value) : input.value)
                : '';
        });

        posJson('/pos/hpr/' + id + '/update', fields)
            .then(function (data) {
                if (!data || !data.ok) throw new Error('bad response');
                ['name', 'amount'].forEach(function (kunci) {
                    const input = baris.querySelector('input[data-hpr="' + kunci + '"]');
                    if (data[kunci] !== undefined && data[kunci] !== null && data[kunci] !== '') {
                        input.value = kunci === 'amount' ? formatUang(data[kunci]) : data[kunci];
                    }
                    input.dataset.val = input.value;
                });
                recountGrand();
            })
            .catch(function () {
                baris.querySelectorAll('input[data-hpr]').forEach(function (input) {
                    if (input.dataset.val !== undefined) input.value = input.dataset.val;
                });
                if (window.posToast) window.posToast('Gagal menyimpan hutang pribadi.');
            });
    }

    akar.addEventListener('change', function (e) {
        if (e.target.matches('input[data-hpr]')) simpan(e.target.closest('tr'));
    });

    akar.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches || !e.target.matches('input[data-hpr]')) return;
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
