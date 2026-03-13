/**
 * admin/bookings.js — Admin Booking Management JavaScript
 * Handles: Calendar view, table view toggle, filters, booking modal
 */

/* ─── View Toggle (Calendar ↔ Table) ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {

    const calView   = document.getElementById('cal-view');
    const tableView = document.getElementById('table-view');
    const btnCal    = document.getElementById('btn-view-cal');
    const btnTable  = document.getElementById('btn-view-table');

    const calFilterWrap = document.getElementById('cal-filter-wrap');

    if (btnCal && btnTable) {
        btnCal.addEventListener('click', function () {
            calView.style.display   = 'block';
            tableView.style.display = 'none';
            btnCal.classList.add('active');
            btnTable.classList.remove('active');
            if (calFilterWrap) calFilterWrap.style.display = 'block';
            localStorage.setItem('bk_view', 'calendar');
        });

        btnTable.addEventListener('click', function () {
            calView.style.display   = 'none';
            tableView.style.display = 'block';
            btnTable.classList.add('active');
            btnCal.classList.remove('active');
            if (calFilterWrap) calFilterWrap.style.display = 'none';
            localStorage.setItem('bk_view', 'table');
        });

        // Restore last used view
        const savedView = localStorage.getItem('bk_view') || 'calendar';
        if (savedView === 'table') btnTable.click();
        else btnCal.click();
    }

    /* ─── Calendar ──────────────────────────────────────────────────────────── */
    if (typeof BOOKING_DATA !== 'undefined') {
        initAdminCalendar(BOOKING_DATA);
    }

    /* ─── Auto-submit table filters ───────────────────────────────────────── */
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        // Debounced submit for text search
        const searchInput = filterForm.querySelector('input[name="q"]');
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () { filterForm.submit(); }, 500);
            });
        }
        // Immediate submit for selects and date inputs
        filterForm.querySelectorAll('select, input[type="date"]').forEach(function (el) {
            el.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    /* ─── Confirm dialogs for approve/reject/complete actions ───────────────── */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });
});

/* ─── Admin Calendar ─────────────────────────────────────────────────────────── */
function initAdminCalendar(bookings) {
    const container = document.getElementById('admin-calendar');
    if (!container) return;

    const today = new Date();
    let currentYear  = today.getFullYear();
    let currentMonth = today.getMonth(); // 0-indexed

    const MONTH_NAMES = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ];
    const DOW_LABELS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    // Index bookings by date for fast lookup
    function buildIndex(bks) {
        const idx = {};
        bks.forEach(function (b) {
            const start = b.event_date;
            const end   = b.end_date || start;

            // Parse date strings as local dates (avoid Date("YYYY-MM-DD") UTC parsing).
            const [sy, sm, sd] = start.split('-').map(Number);
            const [ey, em, ed] = end.split('-').map(Number);
            const cur = new Date(sy, sm - 1, sd);
            const endDt = new Date(ey, em - 1, ed);

            while (cur <= endDt) {
                const ds = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-${String(cur.getDate()).padStart(2, '0')}`;
                if (!idx[ds]) idx[ds] = [];
                idx[ds].push(b);
                cur.setDate(cur.getDate() + 1);
            }
        });
        return idx;
    }

    function pad(n) { return String(n).padStart(2, '0'); }

    function toDateStr(y, m, d) {
        return `${y}-${pad(m + 1)}-${pad(d)}`;
    }

    function isToday(y, m, d) {
        return y === today.getFullYear() && m === today.getMonth() && d === today.getDate();
    }

    function render() {
        const idx = buildIndex(bookings);

        // Header
        container.innerHTML = `
            <div class="cal-header">
                <button class="cal-nav-btn" id="cal-prev" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span class="cal-month-title">${MONTH_NAMES[currentMonth]} ${currentYear}</span>
                <button class="cal-nav-btn" id="cal-next" aria-label="Next month">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
            <div class="cal-legend">
                <span class="legend-item"><span class="legend-dot pending"></span>Pending</span>
                <span class="legend-item"><span class="legend-dot approved"></span>Approved</span>
                <span class="legend-item"><span class="legend-dot completed"></span>Completed</span>
                <span class="legend-item"><span class="legend-dot cancelled"></span>Cancelled</span>
                <span class="legend-item"><span class="legend-dot rejected"></span>Rejected</span>
            </div>
            <div class="cal-dow-row">
                ${DOW_LABELS.map(d => `<div class="cal-dow-cell">${d}</div>`).join('')}
            </div>
            <div class="cal-grid" id="cal-grid-cells"></div>
        `;

        // Navigation listeners
        document.getElementById('cal-prev').addEventListener('click', function () {
            currentMonth--;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            render();
        });
        document.getElementById('cal-next').addEventListener('click', function () {
            currentMonth++;
            if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            render();
        });

        // Build grid
        const grid      = document.getElementById('cal-grid-cells');
        const firstDay  = new Date(currentYear, currentMonth, 1);
        const lastDay   = new Date(currentYear, currentMonth + 1, 0);
        const startDow  = firstDay.getDay();
        const totalDays = lastDay.getDate();

        // Previous month fill
        const prevLastDay = new Date(currentYear, currentMonth, 0).getDate();
        for (let i = startDow - 1; i >= 0; i--) {
            const cell = buildCell(null, prevLastDay - i, 'other-month', false, []);
            grid.appendChild(cell);
        }

        // Current month
        for (let d = 1; d <= totalDays; d++) {
            const dateStr = toDateStr(currentYear, currentMonth, d);
            const dayBks  = idx[dateStr] || [];
            const cell    = buildCell(dateStr, d, '', isToday(currentYear, currentMonth, d), dayBks);
            grid.appendChild(cell);
        }

        // Next month fill
        const totalCells = grid.children.length;
        const remaining  = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let i = 1; i <= remaining; i++) {
            const cell = buildCell(null, i, 'other-month', false, []);
            grid.appendChild(cell);
        }
    }

    function buildCell(dateStr, dayNum, extraClass, isTodayFlag, bks) {
        const cell = document.createElement('div');
        cell.className = `cal-cell ${extraClass} ${isTodayFlag ? 'today' : ''}`.trim();

        const numSpan = document.createElement('span');
        numSpan.className = 'cal-day-num';
        numSpan.textContent = dayNum;
        cell.appendChild(numSpan);

        if (bks.length > 0 && dateStr) {
            const eventsDiv = document.createElement('div');
            eventsDiv.className = 'cal-events';

            const maxShow = 3;
            bks.slice(0, maxShow).forEach(function (bk) {
                const ev = document.createElement('div');
                ev.className = `cal-event ${bk.status}`;
                ev.textContent = bk.customer_name;
                ev.title = `${bk.customer_name} · ${bk.package_name} · ${bk.start_time}–${bk.end_time}`;
                ev.addEventListener('click', function (e) {
                    e.stopPropagation();
                    window.location.href = bk.detail_url;
                });
                eventsDiv.appendChild(ev);
            });

            if (bks.length > maxShow) {
                const more = document.createElement('button');
                more.className = 'cal-more-link';
                more.textContent = `+${bks.length - maxShow} more`;
                more.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openDayModal(dateStr, bks);
                });
                eventsDiv.appendChild(more);
            }

            cell.appendChild(eventsDiv);

            // Click entire cell to open modal
            cell.addEventListener('click', function () {
                openDayModal(dateStr, bks);
            });
        }

        return cell;
    }

    /* ─── Day Modal ─────────────────────────────────────────────────────────── */
    function openDayModal(dateStr, bks) {
        const overlay = document.getElementById('bk-modal-overlay');
        const title   = document.getElementById('bk-modal-date-title');
        const list    = document.getElementById('bk-modal-list');
        if (!overlay) return;

        // Format date
        const parts = dateStr.split('-');
        const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        const displayDate = d.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        title.textContent = displayDate;

        list.innerHTML = bks.map(function (bk) {
            return `
                <li class="bk-modal-item" onclick="window.location='${bk.detail_url}'" title="View booking details">
                    <div class="bk-item-status ${bk.status}"></div>
                    <div class="bk-item-info">
                        <div class="bk-item-customer">${escHtml(bk.customer_name)}</div>
                        <div class="bk-item-pkg">${escHtml(bk.package_name)}</div>
                        <div class="bk-item-time"><i class="fa-regular fa-clock"></i> ${escHtml(bk.start_time)} – ${escHtml(bk.end_time)}</div>
                    </div>
                    <div class="bk-item-amount">${escHtml(bk.total_amount)}</div>
                </li>
            `;
        }).join('');

        overlay.classList.add('open');
    }

    // Close modal
    document.addEventListener('click', function (e) {
        const overlay = document.getElementById('bk-modal-overlay');
        if (!overlay) return;
        if (e.target === overlay || e.target.closest('.bk-modal-close')) {
            overlay.classList.remove('open');
        }
    });

    /* ─── Filter bookings for calendar display ───────────────────────────────── */
    const statusFilter = document.getElementById('cal-status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            const val = this.value;
            if (val === '') {
                // Show all
            } else {
                bookings = BOOKING_DATA.filter(b => b.status === val);
            }
            // Reset if needed
            if (val === '') bookings = [...BOOKING_DATA];
            render();
        });
    }

    render();
}

/* ─── Utility ────────────────────────────────────────────────────────────────── */
function escHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
