/**
 * Stok minyak: hitung subtotal (jumlah x harga) secara live.
 */

import { uraiUang } from '../format.js';

const akar = document.getElementById('stokMinyakCard');
if (akar) {
    const jumlah = document.getElementById('oilQty');
    const harga = document.getElementById('oilPrice');
    const hasil = document.getElementById('oilCalc');

    if (jumlah && harga && hasil) {
        const awal = hasil.getAttribute('data-initial') || 'Belum ada';

        function hitung() {
            if (jumlah.value === '' || harga.value === '') {
                hasil.textContent = awal;
                return;
            }
            hasil.textContent = 'Rp' + Math.round((parseFloat(jumlah.value) || 0) * uraiUang(harga.value)).toLocaleString('id-ID');
        }

        jumlah.addEventListener('input', hitung);
        harga.addEventListener('input', hitung);
    }
}
