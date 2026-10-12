import { Modal, Collapse, Toast } from 'bootstrap';

// Komponen JS Bootstrap diekspor ke global (window.bootstrap.Modal dll.)
// supaya modul POS lain bisa memakainya tanpa import berulang.
window.bootstrap = { Modal, Collapse, Toast };

// Modul POS — sebelumnya file statis di public/js/, kini masuk bundle Vite.
import './pos-ui.js';
import './pos-pay.js';
import './pos-scroll.js';
import './pos-speech.js';
import './chart-init.js';
