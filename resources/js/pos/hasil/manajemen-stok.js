/**
 * Manajemen stok: form karung, edit sel per sak, tambah kolom sak,
 * edit harga per pemegang, dan grand total.
 */

import { posJson } from '../http.js';
import { formatUang, tampilkanKilogramJs, tampilkanRupiahJs } from '../format.js';

const akar = document.getElementById('manajemenStokCard');
if (akar) {
    const form = document.getElementById('stockMgmtFormEl');
    const wadahBaris = document.getElementById('sackRows');

    function recountGrand() {
        let total = 0;
        akar.querySelectorAll('[data-mgmt-sub]').forEach(function (sel) {
            total += parseFloat(String(sel.textContent).replace(/\D+/g, '')) || 0;
        });
        const grand = akar.querySelector('#mgmtGrand');
        if (grand) grand.textContent = tampilkanRupiahJs(total);
    }

    function kembalikan(baris) {
        baris.querySelectorAll('input[data-sack-input]').forEach(function (input) {
            if (input.dataset.val !== undefined) input.value = input.dataset.val;
        });
    }

    function simpan(baris) {
        const id = baris.getAttribute('data-mgmt-row');
        const inputs = Array.prototype.slice.call(baris.querySelectorAll('input[data-sack-input]'));
        const payload = inputs.map(function (input) { return input.value; })
            .filter(function (nilai) { return nilai !== '' && nilai != null && parseFloat(nilai) > 0; });

        posJson('/pos/stock-mgmt/' + id + '/sacks', { sacks: payload })
            .then(function (data) {
                if (!data || !data.ok) throw new Error('bad response');
                const karung = data.sacks || [];
                const sel = Array.prototype.slice.call(baris.querySelectorAll('td[data-mgmt-cell]'));
                const totalSel = baris.querySelector('[data-mgmt-total]');

                while (sel.length < karung.length) {
                    const td = document.createElement('td');
                    td.className = 'num';
                    td.setAttribute('data-mgmt-cell', '');
                    td.innerHTML = '<input type="number" min="0" step="0.5" class="sack-cell" data-sack-input placeholder="kg">';
                    baris.insertBefore(td, totalSel);
                    sel.push(td);
                }

                sel.forEach(function (td, indeks) {
                    const input = td.querySelector('input');
                    const nilai = karung[indeks];
                    input.value = (nilai === undefined || nilai === null) ? '' : nilai;
                    input.dataset.val = input.value;
                });

                while (sel.length > karung.length) {
                    baris.removeChild(sel.pop());
                }

                syncHeaders();
                baris.querySelector('[data-mgmt-total]').textContent = tampilkanKilogramJs(data.totalQty) + ' kg';
                const hargaInput = baris.querySelector('[data-mgmt-price-input]');
                if (hargaInput) {
                    hargaInput.value = formatUang(data.price);
                    hargaInput.dataset.val = hargaInput.value;
                }
                baris.querySelector('[data-mgmt-sub]').textContent = tampilkanRupiahJs(data.subtotal);
                recountGrand();
            })
            .catch(function () {
                kembalikan(baris);
                if (window.posToast) window.posToast('Gagal menyimpan stok manajemen.');
            });
    }

    function simpanHarga(input) {
        const baris = input.closest('tr[data-mgmt-row]');
        if (!baris) return;

        const id = baris.getAttribute('data-mgmt-row');
        const lama = input.dataset.val !== undefined ? input.dataset.val : input.value;

        posJson('/pos/stock-mgmt/' + id + '/harga', { price: input.value })
            .then(function (data) {
                if (!data || !data.ok) throw new Error('bad response');
                input.value = formatUang(data.price);
                input.dataset.val = input.value;
                baris.querySelector('[data-mgmt-sub]').textContent = tampilkanRupiahJs(data.subtotal);
                recountGrand();
            })
            .catch(function () {
                input.value = lama;
                if (window.posToast) window.posToast('Gagal menyimpan harga stok.');
            });
    }

    function tambahSak(baris) {
        const totalSel = baris.querySelector('[data-mgmt-total]');
        const td = document.createElement('td');
        td.className = 'num';
        td.setAttribute('data-mgmt-cell', '');
        td.innerHTML = '<input type="number" min="0" step="0.5" class="sack-cell" data-sack-input placeholder="kg">';
        baris.insertBefore(td, totalSel);
        syncHeaders();
        td.querySelector('input').focus();
    }

    function syncHeaders() {
        let maksKolom = 0;
        akar.querySelectorAll('tr[data-mgmt-row]').forEach(function (baris) {
            maksKolom = Math.max(maksKolom, baris.querySelectorAll('td[data-mgmt-cell]').length);
        });

        let headers = akar.querySelectorAll('thead [data-sak-th]');
        while (headers.length > maksKolom) {
            headers[headers.length - 1].remove();
            headers = akar.querySelectorAll('thead [data-sak-th]');
        }

        const barisHeader = akar.querySelector('thead tr');
        if (!barisHeader) return;
        while (headers.length < maksKolom) {
            const th = document.createElement('th');
            th.className = 'num';
            th.setAttribute('data-sak-th', '');
            th.textContent = 'Sak ' + (headers.length + 1);
            const jangkar = headers.length ? headers[headers.length - 1] : barisHeader.children[0];
            jangkar.insertAdjacentElement('afterend', th);
            headers = akar.querySelectorAll('thead [data-sak-th]');
        }
    }

    if (form && wadahBaris) {
        const tombolTambah = wadahBaris.querySelector('[data-sack-add]');

        function tambahBaris() {
            const div = document.createElement('div');
            div.className = 'sack-row';
            div.setAttribute('data-sack-row', '');
            div.innerHTML = '<input type="number" min="0" step="0.5" class="sack-qty-input" placeholder="kg karung">'
                + '<button type="button" class="btn btn-outline-danger btn-sm sack-remove-btn" data-sack-remove>Hapus</button>';
            wadahBaris.insertBefore(div, tombolTambah);
            div.querySelector('input').focus();
        }

        wadahBaris.addEventListener('click', function (e) {
            if (e.target.closest('[data-sack-add]')) {
                tambahBaris();
                return;
            }
            if (e.target.closest('[data-sack-remove]')) {
                const baris = e.target.closest('[data-sack-row]');
                const semua = wadahBaris.querySelectorAll('[data-sack-row]');
                if (semua.length <= 1) baris.querySelector('input').value = '';
                else baris.remove();
            }
        });

        form.addEventListener('submit', function () {
            const nilai = Array.prototype.map
                .call(wadahBaris.querySelectorAll('[data-sack-row] input'), function (input) { return input.value; })
                .filter(function (v) { return parseFloat(v) > 0; });
            const target = document.getElementById('stockMgmtSacks');
            if (target) target.value = JSON.stringify(nilai);
        });
    }

    akar.addEventListener('click', function (e) {
        const tombol = e.target.closest('[data-mgmt-add-sack]');
        if (tombol) tambahSak(tombol.closest('tr'));
    });

    akar.addEventListener('change', function (e) {
        if (e.target.matches('[data-mgmt-price-input]')) {
            simpanHarga(e.target);
            return;
        }
        if (e.target.matches('input[data-sack-input]')) simpan(e.target.closest('tr'));
    });

    akar.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches || !e.target.matches('input[data-sack-input], [data-mgmt-price-input]')) return;
        e.preventDefault();
        e.target.blur();
    });
}
