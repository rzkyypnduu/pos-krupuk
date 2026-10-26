/**
 * Rekap hutang pelanggan: edit inline sel hutang dan nama lewat fetch JSON.
 * Total hanya ditampilkan. Sel hutang yang dikosongkan dianggap 0; jumlah
 * kolom mengikuti pelanggan dengan hutang aktif terbanyak.
 */

import { posFormPost } from '../http.js';
import { digitUang, formatUang, tampilkanRupiahJs } from '../format.js';

const tabel = document.getElementById('hpSummaryTable');
if (tabel) {
    const tanggalHutang = tabel.dataset.tanggal || '';

    function syncGrand() {
        let total = 0;
        tabel.querySelectorAll('tbody tr[data-total]').forEach(function (r) {
            total += parseFloat(r.dataset.total) || 0;
        });
        const grand = document.getElementById('hpGrandTotal');
        if (grand) grand.textContent = tampilkanRupiahJs(total);
    }

    function syncRow(baris, totalSisa) {
        baris.dataset.total = totalSisa;
        const sel = baris.querySelector('.hp-total-cell .hp-total-amt');
        if (sel) sel.textContent = tampilkanRupiahJs(totalSisa);
        syncGrand();
    }

    function syncColumns() {
        const semuaBaris = Array.from(tabel.querySelectorAll('tbody tr[data-total]'));
        if (!semuaBaris.length) {
            location.reload();
            return;
        }

        let maks = 1;
        semuaBaris.forEach(function (baris) {
            maks = Math.max(maks, baris.querySelectorAll('td.hp-debt-cell').length);
        });

        semuaBaris.forEach(function (baris) {
            const sel = Array.from(baris.querySelectorAll('td.hp-cell')).filter(function (td) {
                return !td.classList.contains('hp-total-cell');
            });
            while (sel.length > maks) {
                sel.pop().remove();
            }
        });

        const header = tabel.querySelector('thead th[colspan]');
        if (header) header.colSpan = maks;
    }

    function cellInner(data) {
        const nilai = formatUang(data.remaining);
        return '<span class="hp-amt" title="Klik untuk edit - ' + data.date + '">' + tampilkanRupiahJs(data.remaining) + '</span>'
            + '<input class="hp-inp" type="text" inputmode="numeric" data-money value="' + nilai + '" data-val="' + nilai + '">';
    }

    function renderCells(baris, daftarHutang) {
        const sel = Array.from(baris.querySelectorAll('td.hp-cell')).filter(function (td) {
            return !td.classList.contains('hp-total-cell');
        });
        if (daftarHutang.length > sel.length) {
            location.reload();
            return false;
        }
        sel.forEach(function (td, indeks) {
            const hutang = daftarHutang[indeks];
            if (hutang) {
                td.className = 'num hp-cell hp-debt-cell';
                td.dataset.debtId = hutang.id;
                td.innerHTML = cellInner(hutang);
            } else {
                td.className = 'num hp-cell empty-cell';
                delete td.dataset.debtId;
                td.innerHTML = '&ndash;';
            }
        });
        return true;
    }

    function apply(baris, data) {
        if (!data || !data.ok) return false;

        if ((Number(data.totalSisa) || 0) <= 0) {
            baris.remove();
            syncGrand();
            syncColumns();
            return true;
        }

        const berhasil = renderCells(baris, data.activeDebts || []);
        if (berhasil) {
            syncRow(baris, Number(data.totalSisa) || 0);
            syncColumns();
        }
        return berhasil;
    }

    function openInput(td) {
        const input = td.querySelector('.hp-inp');
        if (!input || td.classList.contains('editing')) return;
        td.classList.add('editing');
        input.focus();
        if (typeof input.select === 'function') {
            input.select();
        }
    }

    function closeInput(td) {
        td.classList.remove('editing');
    }

    tabel.addEventListener('click', function (e) {
        const nominal = e.target.closest('.hp-amt');
        if (nominal) {
            openInput(nominal.closest('td'));
            return;
        }
        const nama = e.target.closest('.hp-name');
        if (nama) openInput(nama.closest('td'));
    });

    tabel.addEventListener('focusout', function (e) {
        const input = e.target;
        if (!input.matches || !input.matches('.hp-inp')) return;
        const td = input.closest('td');

        if (td.dataset.hpCancel === '1') {
            td.dataset.hpCancel = '0';
            closeInput(td);
            input.value = input.dataset.val !== undefined ? input.dataset.val : input.value;
            return;
        }

        const lama = input.dataset.val !== undefined ? input.dataset.val : input.value;
        const nilai = input.value.trim();
        closeInput(td);
        if (nilai === String(lama)) {
            input.value = lama;
            return;
        }

        if (td.classList.contains('hp-name-cell')) {
            if (nilai === '') {
                input.value = lama;
                return;
            }
            const baris = td.closest('tr');
            posFormPost('/pos/hp/rename', { old_name: baris.dataset.name, new_name: nilai })
                .then(function (data) {
                    if (!data.ok) {
                        if (window.posToast) window.posToast(data.message || 'Gagal mengubah nama.');
                        input.value = lama;
                        return;
                    }
                    baris.dataset.name = data.name;
                    td.querySelector('.hp-name').textContent = data.name;
                    input.dataset.val = data.name;
                })
                .catch(function () {
                    input.value = lama;
                    if (window.posToast) window.posToast('Gagal mengubah nama.');
                });
            return;
        }

        const id = td.dataset.debtId;
        if (!id) return;
        posFormPost('/pos/hp/adjust-cell', {
            debt_id: id,
            new_remaining: nilai === '' ? '0' : digitUang(nilai),
            tx_date: tanggalHutang,
        })
            .then(function (data) {
                if (!apply(td.closest('tr'), data) && window.posToast) {
                    window.posToast('Gagal menyimpan sel hutang.');
                }
            })
            .catch(function () {
                if (window.posToast) window.posToast('Gagal menyimpan sel hutang.');
            });
    });

    tabel.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches || !e.target.matches('.hp-inp')) return;
        e.preventDefault();
        e.target.blur();
    });

    function sinkronkanNilai() {
        tabel.querySelectorAll('.hp-inp[data-money]').forEach(function (input) {
            input.dataset.val = input.value;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sinkronkanNilai);
    } else {
        sinkronkanNilai();
    }
}
