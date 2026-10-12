// Pertahankan posisi scroll halaman per tab.
// Saat meninggalkan halaman (submit form, klik tab, pindah tanggal) scrollY
// disimpan ke sessionStorage per tab; saat halaman dibuka lagi dipulihkan,
// supaya setelah menyimpan sesuatu view tetap di posisi terakhir — tidak
// melompat ke atas/bawah.
// Catatan: Chrome dapat menimpa scroll awal setelah load event, jadi restore
// diulang (verifikasi + retry singkat) sampai posisi benar-benar tercapai.
// URL ber-hash (#tx-baru dkk) dihormati: biarkan browser yang men-scroll ke
// elemen tujuan.
(function () {
    var tab = new URLSearchParams(location.search).get('tab') || 'transaksi';
    var key = 'posScroll:' + tab;

    window.addEventListener('pagehide', function () {
        try {
            sessionStorage.setItem(key, String(window.scrollY));
        } catch (e) { /* storage penuh/nonaktif — abaikan */ }
    });

    window.addEventListener('load', function () {
        var y = null;
        try {
            y = sessionStorage.getItem(key);
            sessionStorage.removeItem(key);
        } catch (e) { return; }
        if (y === null || location.hash) return;
        var top = parseInt(y, 10) || 0;
        if (top <= 0) return;

        var tries = 5;
        (function restore() {
            window.scrollTo(0, top);
            requestAnimationFrame(function () {
                if (Math.abs(window.scrollY - top) <= 2) return;
                if (--tries > 0) setTimeout(restore, 250);
            });
        })();
    });
})();
