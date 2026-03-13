/**
 * customer/calendar.js — Customer Booking Calendar
 * Renders a monthly calendar showing the customer's own bookings
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof CUSTOMER_BOOKINGS !== 'undefined') {
        initCustomerCalendar(CUSTOMER_BOOKINGS);
    }
});

function initCustomerCalendar(bookings) {
    const container = document.getElementById('customer-calendar');
    if (!container) return;

    const today = new Date();
    let currentYear  = today.getFullYear();
    let currentMonth = today.getMonth();

    const MONTH_NAMES = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ];
    const DOW_LABELS  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    function pad(n) { return String(n).padStart(2, '0'); }

    function toDateStr(y, m, d) {
        return `${y}-${pad(m + 1)}-${pad(d)}`;
    }

    function isToday(y, m, d) {
        return y === today.getFullYear() && m === today.getMonth() && d === today.getDate();
    }

    function buildIndex(bks) {
        const idx = {};
        bks.forEach(function (b) {
            if (!idx[b.event_date]) idx[b.event_date] = [];
            idx[b.event_date].push(b);
        });
        return idx;
    }

    function render() {
        const idx = buildIndex(bookings);

        container.innerHTML = `
            <div class="c-cal-header">
                <button class="c-cal-nav" id="c-cal-prev" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span class="c-cal-month-title">${MONTH_NAMES[currentMonth]} ${currentYear}</span>
                <button class="c-cal-nav" id="c-cal-next" aria-label="Next month">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
            <div class="c-cal-legend">
                <span class="c-legend-item"><span class="c-legend-dot pending"></span>Pending</span>
                <span class="c-legend-item"><span class="c-legend-dot approved"></span>Approved</span>
                <span class="c-legend-item"><span class="c-legend-dot completed"></span>Completed</span>
                <span class="c-legend-item"><span class="c-legend-dot cancelled"></span>Cancelled/Rejected</span>
            </div>
            <div class="c-cal-dow-row">
                ${DOW_LABELS.map(d => `<div class="c-cal-dow">${d}</div>`).join('')}
            </div>
            <div class="c-cal-grid" id="c-cal-grid"></div>
        `;

        document.getElementById('c-cal-prev').addEventListener('click', function () {
            currentMonth--;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            render();
        });
        document.getElementById('c-cal-next').addEventListener('click', function () {
            currentMonth++;
            if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            render();
        });

        const grid      = document.getElementById('c-cal-grid');
        const firstDay  = new Date(currentYear, currentMonth, 1);
        const lastDay   = new Date(currentYear, currentMonth + 1, 0);
        const startDow  = firstDay.getDay();
        const totalDays = lastDay.getDate();
        const prevLast  = new Date(currentYear, currentMonth, 0).getDate();

        // Prev month fill
        for (let i = startDow - 1; i >= 0; i--) {
            grid.appendChild(buildCell(null, prevLast - i, 'other-month', false, []));
        }

        // Current month
        for (let d = 1; d <= totalDays; d++) {
            const dateStr = toDateStr(currentYear, currentMonth, d);
            const dayBks  = idx[dateStr] || [];
            grid.appendChild(buildCell(dateStr, d, '', isToday(currentYear, currentMonth, d), dayBks));
        }

        // Next month fill
        const totalCells = grid.children.length;
        const remaining  = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let i = 1; i <= remaining; i++) {
            grid.appendChild(buildCell(null, i, 'other-month', false, []));
        }
    }

    function buildCell(dateStr, dayNum, extraClass, isTodayFlag, bks) {
        const cell = document.createElement('div');
        cell.className = `c-cal-cell ${extraClass} ${isTodayFlag ? 'today' : ''}`.trim();

        const numSpan = document.createElement('span');
        numSpan.className = 'c-cal-day-num';
        numSpan.textContent = dayNum;
        cell.appendChild(numSpan);

        if (bks.length > 0 && dateStr) {
            const evDiv = document.createElement('div');
            evDiv.className = 'c-cal-events';

            bks.slice(0, 3).forEach(function (bk) {
                const ev = document.createElement('div');
                ev.className = `c-cal-event ${bk.status}`;
                ev.textContent = bk.package_name || bk.event_type || 'Booking';
                ev.title = `${bk.package_name} · ${bk.start_time}–${bk.end_time} · ${bk.status}`;
                ev.addEventListener('click', function (e) {
                    e.stopPropagation();
                    window.location.href = bk.detail_url;
                });
                evDiv.appendChild(ev);
            });

            if (bks.length > 3) {
                const more = document.createElement('div');
                more.className = 'c-cal-event approved';
                more.style.cursor = 'pointer';
                more.textContent = `+${bks.length - 3} more`;
                evDiv.appendChild(more);
            }

            cell.appendChild(evDiv);
        }

        return cell;
    }

    render();
}
