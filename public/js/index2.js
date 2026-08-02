(function () {
    'use strict';
    if (typeof Chart === 'undefined' || !window.INDEX2_CHARTS) {
        return;
    }

    var data = window.INDEX2_CHARTS;
    var palette = [
        'rgba(54, 162, 235, 0.7)',
        'rgba(255, 99, 132, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(255, 206, 86, 0.7)',
        'rgba(153, 102, 255, 0.7)',
        'rgba(255, 159, 64, 0.7)',
        'rgba(199, 199, 199, 0.7)',
        'rgba(83, 102, 255, 0.7)',
        'rgba(40, 167, 69, 0.7)',
        'rgba(220, 53, 69, 0.7)'
    ];

    function toGB(bytes) {
        return Math.round(bytes / (1024 * 1024 * 1024));
    }

    function createDoughnut(canvasId, labels, values, colors) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return;
        var c = colors || palette;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: c,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    function createLine(canvasId, labels, values, datasetLabel, color) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: datasetLabel,
                    data: values,
                    borderColor: color,
                    backgroundColor: color.replace('1)', '0.2)').replace(/0\.7\)/, '0.2)'),
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    createDoughnut('chart-class', data.class_labels, data.class_values, palette);
    createDoughnut(
        'chart-seeders',
        ['Seeders', 'Leechers'],
        [data.seeders, data.leechers],
        ['rgba(47, 173, 47, 0.7)', 'rgba(208, 72, 72, 0.7)']
    );

    if (data.monthly_users) {
        createLine(
            'chart-monthly-users',
            Object.keys(data.monthly_users),
            Object.values(data.monthly_users),
            'New users',
            'rgba(54, 162, 235, 0.7)'
        );
    }

    if (data.monthly_torrents) {
        createLine(
            'chart-monthly-torrents',
            Object.keys(data.monthly_torrents),
            Object.values(data.monthly_torrents),
            'New torrents',
            'rgba(255, 159, 64, 0.7)'
        );
    }

    if (data.total_uploaded !== undefined && data.total_downloaded !== undefined) {
        createDoughnut(
            'chart-traffic',
            ['Uploaded', 'Downloaded'],
            [toGB(data.total_uploaded), toGB(data.total_downloaded)],
            ['rgba(47, 173, 47, 0.7)', 'rgba(208, 72, 72, 0.7)']
        );
    }
})();
