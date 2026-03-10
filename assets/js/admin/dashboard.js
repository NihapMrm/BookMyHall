/**
 * dashboard.js — Admin dashboard dynamic behaviour.
 * Module 1 (Sahani). Live stat refresh will be wired in Phase 4.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Animate stat card numbers on load ────────────────────────────────────
    document.querySelectorAll('.stat-card__value[data-target]').forEach(function (el) {
        const target = parseFloat(el.dataset.target.replace(/[^0-9.]/g, ''));
        const prefix = el.dataset.prefix || '';
        const suffix = el.dataset.suffix || '';
        const isCurrency = el.dataset.currency === 'true';
        let start = 0;
        const duration = 800;
        const step = (timestamp) => {
            if (!start) start = timestamp;
            const progress = Math.min((timestamp - start) / duration, 1);
            const value = Math.floor(progress * target);
            el.textContent = prefix + (isCurrency
                ? 'LKR ' + value.toLocaleString()
                : value.toLocaleString()) + suffix;
            if (progress < 1) requestAnimationFrame(step);
            else el.textContent = prefix + el.dataset.target + suffix;
        };
        requestAnimationFrame(step);
    });

    // ─── Booking chart placeholder (Chart.js will be wired in Phase 4) ────────
    const canvas = document.getElementById('bookingChart');
    if (canvas && typeof Chart !== 'undefined') {
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                         'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Bookings',
                    data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    borderColor: '#4d5dfb',
                    backgroundColor: 'rgba(77,93,251,0.08)',
                    borderWidth: 2,
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        grid: { color: 'rgba(112,144,176,0.1)' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
