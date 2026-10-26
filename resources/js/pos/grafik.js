import Chart from 'chart.js/auto';

// Grafik penjualan harian tab Ringkasan. Data dibaca dari atribut canvas
// (array {label, diterima, bersih}); chart hanya dimuat saat canvas tersedia.
(function () {
    'use strict';

    const kanvas = document.getElementById('chartHarian');
    if (!kanvas) return;

    let dataGrafik = [];
    try {
        dataGrafik = JSON.parse(kanvas.dataset.grafik || '[]');
    } catch (error) {
        return;
    }

    if (!Array.isArray(dataGrafik) || !dataGrafik.length) return;
    const labelBulan = kanvas.dataset.labelBulan || '';

    const juta = nilai => {
        const n = nilai / 1000000;
        return (Math.round(n * 100) / 100).toLocaleString('id-ID') + ' jt';
    };

    new Chart(kanvas, {
        type: 'line',
        data: {
            labels: dataGrafik.map(hari => hari.label),
            datasets: [
                {
                    label: 'Diterima',
                    data: dataGrafik.map(hari => hari.diterima),
                    borderColor: '#C1272D',
                    backgroundColor: 'rgba(193, 39, 45, 0.10)',
                    fill: true,
                    tension: 0.25,
                    pointRadius: 2.5,
                    borderWidth: 2,
                },
                {
                    label: 'Kas bersih',
                    data: dataGrafik.map(hari => hari.bersih),
                    borderColor: '#2E7D46',
                    backgroundColor: 'rgba(46, 125, 70, 0.10)',
                    fill: true,
                    tension: 0.25,
                    pointRadius: 2.5,
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': Rp' + Math.round(ctx.parsed.y).toLocaleString('id-ID') + ' (' + juta(ctx.parsed.y) + ')',
                    },
                },
            },
            scales: {
                y: {
                    ticks: { callback: nilai => juta(nilai) },
                    grid: { color: 'rgba(41, 35, 32, 0.07)' },
                },
                x: {
                    title: { display: true, text: 'Tanggal — ' + labelBulan },
                },
            },
        },
    });
})();
