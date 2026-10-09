/* Counts remain visible as accessible text even if the chart library fails. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('canvas[data-chart-type]').forEach(function (canvas) {
            if (!window.Chart) { canvas.closest('.chart-box').hidden = true; return; }
            try {
                const labels = JSON.parse(canvas.dataset.chartLabels);
                const values = JSON.parse(canvas.dataset.chartCounts).map(Number);
                const total = values.reduce((sum, value) => sum + value, 0);
                const pie = canvas.dataset.chartType === 'doughnut';
                new window.Chart(canvas, {
                    type: canvas.dataset.chartType,
                    data: { labels, datasets: [{ label: 'Records', data: values,
                        backgroundColor: pie ? ['#1d3557', '#cb2035'] : '#cb2035', borderWidth: 2 }] },
                    options: { responsive: true, maintainAspectRatio: false,
                        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 350 },
                        plugins: { legend: { display: pie, position: 'bottom' },
                            tooltip: { callbacks: { label: function (item) {
                                const count = values[item.dataIndex];
                                return labels[item.dataIndex] + ': ' + count + ' (' +
                                    (total ? (100 * count / total).toFixed(1) : '0') + '%)';
                            } } } },
                        ...(pie ? {} : { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } })
                    }
                });
            } catch (error) { canvas.closest('.chart-box').hidden = true; }
        });
    });
}());
