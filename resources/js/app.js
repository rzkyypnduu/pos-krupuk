import { Modal, Collapse, Toast } from 'bootstrap';

// Komponen JS Bootstrap diekspor ke global (window.bootstrap.Modal dll.)
// supaya modul POS lain bisa memakainya tanpa import berulang.
window.bootstrap = { Modal, Collapse, Toast };

import './pos/antarmuka.js';
import './pos/pembayaran.js';
import './pos/transaksi.js';
import './pos/suara.js';
import './pos/hasil/minyak.js';
import './pos/hasil/manajemen-stok.js';
import './pos/hasil/sisa-stok.js';
import './pos/hasil/hutang-pelanggan.js';
import './pos/hasil/hutang-pribadi.js';

// Chart.js dan pengurutan pelanggan hanya dimuat saat tab Ringkasan punya canvas grafik.
if (document.getElementById('chartHarian')) {
    import('./pos/grafik.js');
    import('./pos/ringkasan.js');
}
