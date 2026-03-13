/**
 * customer/booking.js — Customer Booking Form JavaScript
 * Handles: Interactive multi-day booking calendar (date range picker),
 *          package selection with multi-day pricing, and availability checking.
 */
document.addEventListener('DOMContentLoaded', function () {

    // ─── Elements ─────────────────────────────────────────────────────────────
    const pkgRadios       = document.querySelectorAll('input[name="package_id"]');
    const totalEl         = document.getElementById('price-total');
    const advanceEl       = document.getElementById('price-advance');
    const balanceEl       = document.getElementById('price-balance');
    const daysEl          = document.getElementById('price-days');
    const perDayEl        = document.getElementById('price-per-day');
    const summaryBox      = document.getElementById('price-summary-box');
    const pricePlaceholder = document.getElementById('price-placeholder');
    const availBox        = document.getElementById('avail-indicator');
    const startInput      = document.getElementById('start_time');
    const endInput        = document.getElementById('end_time');
    const startDateHidden = document.getElementById('start_date');
    const endDateHidden   = document.getElementById('end_date');
    const calContainer    = document.getElementById('booking-calendar');

    const ADVANCE_PCT = 0.30;

    // ─── State ────────────────────────────────────────────────────────────────
    const todayObj = new Date();
    todayObj.setHours(0, 0, 0, 0);

    let calYear  = todayObj.getFullYear();
    let calMonth = todayObj.getMonth();

    // Restore from POST re-submit (error case)
    let selectedStart = (typeof INIT_START_DATE !== 'undefined' && INIT_START_DATE) ? INIT_START_DATE : null;
    let selectedEnd   = (typeof INIT_END_DATE   !== 'undefined' && INIT_END_DATE)   ? INIT_END_DATE   : null;
    let pickState     = selectedStart ? (selectedEnd ? 'start' : 'end') : 'start';
    let bookedRanges  = [];

    const MONTH_NAMES = ['January','February','March','April','May','June',
                         'July','August','September','October','November','December'];
    const DOW_LABELS  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    // Navigate to selected start date on restore
    if (selectedStart) {
        const d = parseLocal(selectedStart);
        calYear  = d.getFullYear();
        calMonth = d.getMonth();
    }

    // ─── Date Utilities ───────────────────────────────────────────────────────
    function pad(n) { return String(n).padStart(2, '0'); }

    function toDateStr(y, m, d) {
        return `${y}-${pad(m + 1)}-${pad(d)}`;
    }

    function parseLocal(str) {
        const parts = str.split('-').map(Number);
        const dt = new Date(parts[0], parts[1] - 1, parts[2]);
        dt.setHours(0, 0, 0, 0);
        return dt;
    }

    function daysBetween(s, e) {
        return Math.round((parseLocal(e) - parseLocal(s)) / 86400000) + 1;
    }

    function todayStr() {
        return toDateStr(todayObj.getFullYear(), todayObj.getMonth(), todayObj.getDate());
    }

    function isPastDate(dateStr) {
        return parseLocal(dateStr) < todayObj;
    }

    function isBookedDate(dateStr) {
        if (!bookedRanges.length) return false;
        const d = parseLocal(dateStr);
        return bookedRanges.some(function (r) {
            return d >= parseLocal(r.start) && d <= parseLocal(r.end);
        });
    }

    // Returns true if any date within [s..e] is already booked
    function rangeHasBookedDate(s, e) {
        let cur = parseLocal(s);
        const end = parseLocal(e);
        while (cur <= end) {
            const ds = toDateStr(cur.getFullYear(), cur.getMonth(), cur.getDate());
            if (isBookedDate(ds)) return true;
            cur.setDate(cur.getDate() + 1);
        }
        return false;
    }

    // ─── Formatting ───────────────────────────────────────────────────────────
    function formatLKR(amount) {
        return 'LKR ' + Number(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    function formatReadable(str) {
        if (!str) return '';
        return parseLocal(str).toLocaleDateString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric'
        });
    }

    // ─── Package Helpers ──────────────────────────────────────────────────────
    function getSelectedPkgId() {
        const el = document.querySelector('input[name="package_id"]:checked');
        return el ? el.value : null;
    }

    function getSelectedPkgPrice() {
        const el = document.querySelector('input[name="package_id"]:checked');
        return el ? (parseFloat(el.dataset.price) || 0) : 0;
    }

    // ─── Price Summary ────────────────────────────────────────────────────────
    function updatePriceSummary() {
        const price = getSelectedPkgPrice();
        if (!totalEl || price <= 0 || !selectedStart) {
            if (summaryBox)      summaryBox.style.display = 'none';
            if (pricePlaceholder) pricePlaceholder.style.display = '';
            return;
        }
        const days    = (selectedEnd && selectedEnd >= selectedStart) ? daysBetween(selectedStart, selectedEnd) : 1;
        const total   = price * days;
        const advance = total * ADVANCE_PCT;
        const balance = total - advance;

        if (perDayEl)  perDayEl.textContent  = formatLKR(price) + ' / day';
        if (daysEl)    daysEl.textContent    = days + (days === 1 ? ' day' : ' days');
        if (totalEl)   totalEl.textContent   = formatLKR(total);
        if (advanceEl) advanceEl.textContent = formatLKR(advance);
        if (balanceEl) balanceEl.textContent = formatLKR(balance);

        if (summaryBox)       summaryBox.style.display = 'block';
        if (pricePlaceholder) pricePlaceholder.style.display = 'none';
    }

    // ─── Availability Check (AJAX) ────────────────────────────────────────────
    function runAvailabilityCheck() {
        if (!availBox) return;
        const startD = selectedStart;
        const endD   = selectedEnd || selectedStart;
        const start  = startInput ? startInput.value : '';
        const end    = endInput   ? endInput.value   : '';
        const pkgId  = getSelectedPkgId();

        if (!startD || !start || !end || !pkgId) {
            availBox.style.display = 'none';
            return;
        }
        if (start >= end) {
            availBox.className = 'avail-indicator show busy';
            availBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> End time must be after start time';
            return;
        }

        availBox.className = 'avail-indicator show';
        availBox.style.background = 'var(--primary-light)';
        availBox.style.color = 'var(--primary)';
        availBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking availability…';

        const params = new URLSearchParams({
            action: 'check', date: startD, end_date: endD, start: start, end: end, pkg_id: pkgId
        });

        fetch(`${BASE_URL}/customer/bookings/check_availability.php?` + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.available) {
                availBox.className = 'avail-indicator show free';
                availBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> This slot is available!';
            } else {
                availBox.className = 'avail-indicator show busy';
                availBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> These dates are already booked. Please choose different dates.';
            }
        })
        .catch(function () { availBox.style.display = 'none'; });
    }

    if (startInput) startInput.addEventListener('change', runAvailabilityCheck);
    if (endInput)   endInput.addEventListener('change', runAvailabilityCheck);

    // ─── Fetch Booked Dates for a Package ────────────────────────────────────
    function fetchBookedDates(pkgId) {
        if (!pkgId) {
            bookedRanges = [];
            renderCalendar();
            return;
        }
        fetch(`${BASE_URL}/customer/bookings/get_booked_dates.php?pkg_id=${pkgId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            bookedRanges = data.ranges || [];
            renderCalendar();
        })
        .catch(function () {
            bookedRanges = [];
            renderCalendar();
        });
    }

    // ─── Package Radio Change ─────────────────────────────────────────────────
    pkgRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.package-option').forEach(function (opt) {
                opt.classList.remove('selected');
            });
            const lbl = document.querySelector(`label[for="${this.id}"]`);
            if (lbl) lbl.closest('.package-option').classList.add('selected');
            fetchBookedDates(this.value);
            updatePriceSummary();
        });
    });

    // ─── Calendar Render ──────────────────────────────────────────────────────
    function renderCalendar() {
        if (!calContainer) return;
        const tStr = todayStr();

        calContainer.innerHTML = `
            <div class="booking-cal-wrap">
                <div class="booking-cal-header">
                    <button type="button" class="booking-cal-nav" id="bk-cal-prev" aria-label="Previous month">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="booking-cal-month-title">${MONTH_NAMES[calMonth]} ${calYear}</span>
                    <button type="button" class="booking-cal-nav" id="bk-cal-next" aria-label="Next month">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
                <div class="booking-cal-legend">
                    <span class="booking-cal-legend-item"><span class="booking-cal-legend-dot available"></span>Available</span>
                    <span class="booking-cal-legend-item"><span class="booking-cal-legend-dot booked"></span>Booked/Unavailable</span>
                    <span class="booking-cal-legend-item"><span class="booking-cal-legend-dot selected"></span>Selected</span>
                    <span class="booking-cal-legend-item"><span class="booking-cal-legend-dot today-dot"></span>Today</span>
                </div>
                <div class="booking-cal-dow-row">
                    ${DOW_LABELS.map(function (d) { return '<div class="booking-cal-dow">' + d + '</div>'; }).join('')}
                </div>
                <div class="booking-cal-grid" id="bk-cal-grid"></div>
                <div class="booking-range-info" id="bk-range-info"></div>
            </div>
        `;

        document.getElementById('bk-cal-prev').addEventListener('click', function () {
            calMonth--;
            if (calMonth < 0) { calMonth = 11; calYear--; }
            renderCalendar();
        });
        document.getElementById('bk-cal-next').addEventListener('click', function () {
            calMonth++;
            if (calMonth > 11) { calMonth = 0; calYear++; }
            renderCalendar();
        });

        const grid      = document.getElementById('bk-cal-grid');
        const firstDay  = new Date(calYear, calMonth, 1);
        const lastDay   = new Date(calYear, calMonth + 1, 0);
        const startDow  = firstDay.getDay();
        const totalDays = lastDay.getDate();
        const prevLast  = new Date(calYear, calMonth, 0).getDate();

        // Prev month fillers
        for (let i = startDow - 1; i >= 0; i--) {
            const cell = document.createElement('div');
            cell.className = 'booking-cal-cell other-month';
            cell.textContent = prevLast - i;
            grid.appendChild(cell);
        }

        // Current month days
        for (let d = 1; d <= totalDays; d++) {
            const dateStr  = toDateStr(calYear, calMonth, d);
            const isPast   = isPastDate(dateStr);
            const isBooked = isBookedDate(dateStr);
            const isToday  = (dateStr === tStr);

            let cls = 'booking-cal-cell';
            if (isPast)        cls += ' past';
            else if (isBooked) cls += ' booked';
            else               cls += ' available';
            if (isToday)       cls += ' today';

            // Range highlight classes
            if (selectedStart && selectedEnd && selectedStart <= selectedEnd) {
                if (dateStr === selectedStart && dateStr === selectedEnd) {
                    cls += ' selected-start selected-end';
                } else if (dateStr === selectedStart) {
                    cls += ' selected-start';
                } else if (dateStr === selectedEnd) {
                    cls += ' selected-end';
                } else if (dateStr > selectedStart && dateStr < selectedEnd && !isBooked) {
                    cls += ' in-range';
                }
            } else if (selectedStart && !selectedEnd && dateStr === selectedStart) {
                cls += ' selected-start';
            }

            const cell = document.createElement('div');
            cell.className = cls;
            cell.textContent = d;
            cell.dataset.date = dateStr;

            if (!isPast && !isBooked) {
                cell.addEventListener('click', function () {
                    handleDateClick(this.dataset.date);
                });
            }

            grid.appendChild(cell);
        }

        // Next month fillers
        const rem = grid.children.length % 7;
        if (rem !== 0) {
            for (let i = 1; i <= 7 - rem; i++) {
                const cell = document.createElement('div');
                cell.className = 'booking-cal-cell other-month';
                cell.textContent = i;
                grid.appendChild(cell);
            }
        }

        updateRangeInfo();
    }

    // ─── Date Click Handler ───────────────────────────────────────────────────
    function handleDateClick(dateStr) {
        if (pickState === 'start') {
            selectedStart = dateStr;
            selectedEnd   = null;
            pickState     = 'end';
        } else {
            if (dateStr < selectedStart) {
                // Clicked before start — begin a new selection
                selectedStart = dateStr;
                selectedEnd   = null;
                // stay in 'end' state — user needs to pick the end
            } else {
                // Valid end date — check for booked dates in the range
                if (dateStr !== selectedStart && rangeHasBookedDate(selectedStart, dateStr)) {
                    showRangeConflict();
                    return;
                }
                selectedEnd = dateStr;
                pickState   = 'start'; // reset for next selection
            }
        }

        // Update hidden form fields
        if (startDateHidden) startDateHidden.value = selectedStart || '';
        if (endDateHidden)   endDateHidden.value   = selectedEnd   || '';

        renderCalendar();
        updatePriceSummary();
        runAvailabilityCheck();
    }

    function showRangeConflict() {
        const info = document.getElementById('bk-range-info');
        if (info) {
            info.innerHTML =
                '<i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);margin-right:6px;"></i>' +
                '<span style="color:var(--danger);font-weight:600;">Your selected range includes booked dates. Please choose a different range.</span>';
        }
    }

    // ─── Range Info Bar ───────────────────────────────────────────────────────
    function updateRangeInfo() {
        const info = document.getElementById('bk-range-info');
        if (!info) return;

        if (!selectedStart) {
            info.innerHTML =
                '<span class="booking-range-step">' +
                '<i class="fa-regular fa-hand-pointer"></i>&nbsp; Click a date to set your event <strong>start date</strong>' +
                '</span>';
            return;
        }
        if (!selectedEnd) {
            info.innerHTML =
                '<i class="fa-solid fa-calendar-day"></i>&nbsp; Start: <strong>' + formatReadable(selectedStart) + '</strong>' +
                '&nbsp;&mdash;&nbsp;' +
                '<span class="booking-range-step">Now click an <strong>end date</strong> (or the same date for a single-day event)</span>';
            return;
        }
        const days = daysBetween(selectedStart, selectedEnd);
        info.innerHTML =
            '<i class="fa-solid fa-calendar-days"></i>&nbsp; ' +
            '<strong>' + formatReadable(selectedStart) + '</strong> &rarr; <strong>' + formatReadable(selectedEnd) + '</strong>' +
            '&nbsp;&nbsp;<span class="booking-range-badge">' + days + ' day' + (days > 1 ? 's' : '') + '</span>';
    }

    // ─── Form Submit Validation ───────────────────────────────────────────────
    const form = document.getElementById('booking-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!getSelectedPkgId()) {
                e.preventDefault();
                alert('Please select a package to continue.');
                return;
            }
            if (!selectedStart) {
                e.preventDefault();
                alert('Please select an event date on the calendar.');
                return;
            }
            // Default end to start if only one date chosen (single-day)
            if (!selectedEnd) {
                selectedEnd = selectedStart;
                if (endDateHidden) endDateHidden.value = selectedEnd;
            }
            const start = startInput ? startInput.value : '';
            const end   = endInput   ? endInput.value   : '';
            if (!start || !end) {
                e.preventDefault();
                alert('Please select daily start and end times.');
                return;
            }
            if (start >= end) {
                e.preventDefault();
                alert('End time must be after start time.');
                return;
            }
        });
    }

    // ─── Init ─────────────────────────────────────────────────────────────────
    const preSelected = document.querySelector('input[name="package_id"]:checked');
    if (preSelected) {
        fetchBookedDates(preSelected.value); // also calls renderCalendar() + updatePriceSummary inside
    } else {
        renderCalendar();
    }
    updatePriceSummary();
});
