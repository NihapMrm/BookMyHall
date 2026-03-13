/**
 * payments.js — Admin Payment Module JS
 * Module 5 – Nihap
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Live search / filter form auto-submit ───────────────────────────────
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        const selects = filterForm.querySelectorAll('select');
        selects.forEach(function (sel) {
            sel.addEventListener('change', function () {
                filterForm.submit();
            });
        });
    }

    // ─── Confirm destructive actions ─────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ─── Booking selector on add_payment page ────────────────────────────────
    const bookingSelect = document.getElementById('booking_id');
    const amountInput   = document.getElementById('amount');
    const typeSelect    = document.getElementById('payment_type');
    const infoBox       = document.getElementById('bookingInfo');

    if (bookingSelect && infoBox) {
        bookingSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) {
                infoBox.style.display = 'none';
                return;
            }
            const total      = parseFloat(opt.dataset.total   || 0);
            const advance    = parseFloat(opt.dataset.advance  || 0);
            const balance    = parseFloat(opt.dataset.balance  || 0);
            const paid       = parseFloat(opt.dataset.paid     || 0);
            const paidAdv    = parseFloat(opt.dataset.paidAdv  || 0);
            const paidBal    = parseFloat(opt.dataset.paidBal  || 0);

            document.getElementById('infoTotal').textContent   = formatLKR(total);
            document.getElementById('infoAdvance').textContent = formatLKR(advance);
            document.getElementById('infoBalance').textContent = formatLKR(balance);
            document.getElementById('infoPaid').textContent    = formatLKR(paid);
            infoBox.style.display = 'block';

            // Pre-fill amount based on payment type
            updateAmountSuggestion(typeSelect ? typeSelect.value : 'advance', advance, balance, paidAdv, paidBal);
        });

        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                const opt = bookingSelect.options[bookingSelect.selectedIndex];
                if (!opt || !opt.value) return;
                const advance = parseFloat(opt.dataset.advance || 0);
                const balance = parseFloat(opt.dataset.balance || 0);
                const paidAdv = parseFloat(opt.dataset.paidAdv || 0);
                const paidBal = parseFloat(opt.dataset.paidBal || 0);
                updateAmountSuggestion(this.value, advance, balance, paidAdv, paidBal);
            });
        }
    }

    function updateAmountSuggestion(type, advance, balance, paidAdv, paidBal) {
        if (!amountInput) return;
        if (type === 'advance') {
            amountInput.value = Math.max(0, advance - paidAdv).toFixed(2);
        } else if (type === 'balance') {
            amountInput.value = Math.max(0, balance - paidBal).toFixed(2);
        } else if (type === 'full') {
            amountInput.value = (Math.max(0, advance - paidAdv) + Math.max(0, balance - paidBal)).toFixed(2);
        }
    }

    function formatLKR(n) {
        return 'LKR ' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

});
