/**
 * reports.js — Admin Reports Module JS (Chart.js integration)
 * Module 5 – Nihap
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Confirm before print ─────────────────────────────────────────────────
    const printBtn = document.getElementById('printBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }

    // ─── Auto-submit date filter on change ────────────────────────────────────
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        const dateInputs = filterForm.querySelectorAll('input[type="date"]');
        dateInputs.forEach(function (inp) {
            inp.addEventListener('change', function () {
                // Only auto-submit if both dates are set
                const allFilled = Array.from(dateInputs).every(function (i) { return i.value; });
                if (allFilled) filterForm.submit();
            });
        });
    }

    // ─── Revenue Line Chart (income_report) ──────────────────────────────────
    const revenueChartEl = document.getElementById('revenueChart');
    if (revenueChartEl && typeof Chart !== 'undefined') {
        const labels = JSON.parse(revenueChartEl.dataset.labels || '[]');
        const values = JSON.parse(revenueChartEl.dataset.values || '[]');

        new Chart(revenueChartEl, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: values,
                    borderColor: '#4d5dfb',
                    backgroundColor: 'rgba(77,93,251,0.08)',
                    borderWidth: 2.5,
                    tension: 0.35,
                    pointBackgroundColor: '#4d5dfb',
                    pointRadius: 4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' LKR ' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (v) {
                                return 'LKR ' + v.toLocaleString();
                            },
                            color: '#6c6f83',
                            font: { family: 'Poppins', size: 11 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        ticks: { color: '#6c6f83', font: { family: 'Poppins', size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // ─── Payment Method Doughnut Chart (income_report) ───────────────────────
    const methodChartEl = document.getElementById('methodChart');
    if (methodChartEl && typeof Chart !== 'undefined') {
        const labels = JSON.parse(methodChartEl.dataset.labels || '[]');
        const values = JSON.parse(methodChartEl.dataset.values || '[]');

        new Chart(methodChartEl, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#4d5dfb', '#2ecc71', '#f39c12', '#3498db'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: 'Poppins', size: 12 }, padding: 16 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' LKR ' + ctx.parsed.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                cutout: '65%',
            }
        });
    }

    // ─── Booking Status Doughnut (booking_report) ────────────────────────────
    const statusChartEl = document.getElementById('statusChart');
    if (statusChartEl && typeof Chart !== 'undefined') {
        const labels = JSON.parse(statusChartEl.dataset.labels || '[]');
        const values = JSON.parse(statusChartEl.dataset.values || '[]');

        new Chart(statusChartEl, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#f39c12', '#2ecc71', '#e74c3c', '#6c6f83', '#3498db'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: 'Poppins', size: 12 }, padding: 16 }
                    }
                },
                cutout: '60%',
            }
        });
    }

    // ─── Monthly Bookings Bar Chart (monthly_report) ─────────────────────────
    const monthlyBarEl = document.getElementById('monthlyBar');
    if (monthlyBarEl && typeof Chart !== 'undefined') {
        const labels = JSON.parse(monthlyBarEl.dataset.labels || '[]');
        const bookings = JSON.parse(monthlyBarEl.dataset.bookings || '[]');
        const revenue  = JSON.parse(monthlyBarEl.dataset.revenue  || '[]');

        new Chart(monthlyBarEl, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Bookings',
                        data: bookings,
                        backgroundColor: 'rgba(77,93,251,0.7)',
                        borderRadius: 6,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Revenue (LKR)',
                        data: revenue,
                        type: 'line',
                        borderColor: '#2ecc71',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 4,
                        pointBackgroundColor: '#2ecc71',
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Poppins', size: 12 }, padding: 16 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#6c6f83', font: { family: 'Poppins', size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        title: { display: true, text: 'Bookings', color: '#4d5dfb', font: { family: 'Poppins' } }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        ticks: {
                            callback: function (v) { return 'LKR ' + v.toLocaleString(); },
                            color: '#6c6f83', font: { family: 'Poppins', size: 11 }
                        },
                        grid: { display: false },
                        title: { display: true, text: 'Revenue', color: '#2ecc71', font: { family: 'Poppins' } }
                    },
                    x: {
                        ticks: { color: '#6c6f83', font: { family: 'Poppins', size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // ─── Customer registrations bar (customer_report) ────────────────────────
    const custBarEl = document.getElementById('custBar');
    if (custBarEl && typeof Chart !== 'undefined') {
        const labels = JSON.parse(custBarEl.dataset.labels || '[]');
        const values = JSON.parse(custBarEl.dataset.values || '[]');

        new Chart(custBarEl, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Customers',
                    data: values,
                    backgroundColor: 'rgba(77,93,251,0.7)',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#6c6f83', font: { family: 'Poppins', size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        ticks: { color: '#6c6f83', font: { family: 'Poppins', size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

});
