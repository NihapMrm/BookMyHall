/**
 * customers.js — Admin Customer Management Scripts
 * Module 3 – Nishtha
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Auto-dismiss alerts ────────────────────────────────────────────────
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 4000);
    });

    // ── Confirm block/unblock ─────────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ── Search form: clear button ─────────────────────────────────────────
    const searchInput = document.querySelector('.filter-bar input[name="q"]');
    if (searchInput) {
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                searchInput.value = '';
                searchInput.closest('form').submit();
            }
        });
    }

    // ── Status filter auto-submit ─────────────────────────────────────────
    const statusSelect = document.querySelector('.filter-bar select[name="status"]');
    if (statusSelect) {
        statusSelect.addEventListener('change', () => {
            statusSelect.closest('form').submit();
        });
    }

    // ── Row click → detail page ───────────────────────────────────────────
    document.querySelectorAll('.data-table tbody tr[data-href]').forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', e => {
            // Ignore clicks on action buttons
            if (e.target.closest('a, button, form')) return;
            window.location.href = row.dataset.href;
        });
    });

});
