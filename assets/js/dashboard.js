document.addEventListener('DOMContentLoaded', function () {
    // 1. Monthly Revenue & Collection Trend Chart
    const revenueCanvas = document.getElementById('revenueChart');
    if (revenueCanvas && window.Chart) {
        const monthsData = JSON.parse(revenueCanvas.getAttribute('data-months') || '["Mar", "Apr", "May", "Jun", "Jul", "Aug"]');
        const collectionsData = JSON.parse(revenueCanvas.getAttribute('data-collections') || '[35000, 42000, 39500, 48000, 52000, 56500]');
        const receivablesData = JSON.parse(revenueCanvas.getAttribute('data-receivables') || '[5000, 8500, 6000, 12000, 7500, 13950]');

        new Chart(revenueCanvas, {
            type: 'line',
            data: {
                labels: monthsData,
                datasets: [
                    {
                        label: 'Collected Payments',
                        data: collectionsData,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#059669',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Uncollected / Dues',
                        data: receivablesData,
                        borderColor: '#dc2626',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        tension: 0.35,
                        pointBackgroundColor: '#dc2626',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: 600 },
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 13 },
                        bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ₱ ' + Number(context.raw).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 }, color: '#64748b' }
                    },
                    y: {
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                            color: '#64748b',
                            callback: function (value) {
                                return '₱ ' + (value >= 1000 ? (value / 1000) + 'k' : value);
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Unit Occupancy Donut Chart
    const occupancyCanvas = document.getElementById('occupancyChart');
    if (occupancyCanvas && window.Chart) {
        const occupied = parseInt(occupancyCanvas.getAttribute('data-occupied') || '3');
        const vacant = parseInt(occupancyCanvas.getAttribute('data-vacant') || '2');
        const maintenance = parseInt(occupancyCanvas.getAttribute('data-maintenance') || '1');
        const reserved = parseInt(occupancyCanvas.getAttribute('data-reserved') || '0');

        new Chart(occupancyCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Vacant', 'Under Maintenance', 'Reserved'],
                datasets: [{
                    data: [occupied, vacant, maintenance, reserved],
                    backgroundColor: ['#2563eb', '#059669', '#d97706', '#0284c7'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: 500 },
                            usePointStyle: true,
                            padding: 12
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        callbacks: {
                            label: function (context) {
                                const total = occupied + vacant + maintenance + reserved;
                                const val = context.raw;
                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return ' ' + context.label + ': ' + val + ' units (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
});
