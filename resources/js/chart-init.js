import Chart from 'chart.js/auto';

// Grafik penjualan harian tab Ringkasan — data disuntik Blade sebagai
// window.POS_GRAFIK (array {label, diterima, bersih}) + window.POS_MONTH_LABEL.
// Module script defer: inline window.POS_GRAFIK di _tab_ringkasan dieksekusi
// saat parse HTML (sebelum module ini jalan), jadi datanya selalu siap.
(function () {
    'use strict';

    const canvas = document.getElementById('chartHarian');
    if (!canvas) return;
    const data = window.POS_GRAFIK;
    if (!Array.isArray(data) || !data.length) return;
    const monthLabel = window.POS_MONTH_LABEL || '';

    const jt = v => {
        const n = v / 1000000;
        return (Math.round(n * 100) / 100).toLocaleString('id-ID') + ' jt';
    };

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.map(d => d.label),
            datasets: [
                {
                    label: 'Diterima',
                    data: data.map(d => d.diterima),
                    borderColor: '#C1272D',
                    backgroundColor: 'rgba(193, 39, 45, 0.10)',
                    fill: true,
                    tension: 0.25,
                    pointRadius: 2.5,
                    borderWidth: 2,
                },
                {
                    label: 'Kas bersih',
                    data: data.map(d => d.bersih),
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
                        label: ctx => ctx.dataset.label + ': Rp' + Math.round(ctx.parsed.y).toLocaleString('id-ID') + ' (' + jt(ctx.parsed.y) + ')',
                    },
                },
            },
            scales: {
                y: {
                    ticks: { callback: v => jt(v) },
                    grid: { color: 'rgba(41, 35, 32, 0.07)' },
                },
                x: {
                    title: { display: true, text: 'Tanggal — ' + monthLabel },
                },
            },
        },
    });
})();
