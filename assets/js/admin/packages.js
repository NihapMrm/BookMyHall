/**
 * packages.js — Admin Package Management JS
 * Module 2 – Riffna
 * Handles: type toggle (main/sub), parent field visibility, delete confirm
 */

document.addEventListener('DOMContentLoaded', () => {

    /* ── Type Toggle: show/hide parent-package selector ───────────────── */
    const typeRadios     = document.querySelectorAll('input[name="type"]');
    const parentField    = document.querySelector('.pkg-parent-field');

    function updateParentVisibility() {
        const selected = document.querySelector('input[name="type"]:checked');
        if (!parentField) return;
        if (selected && selected.value === 'sub') {
            parentField.classList.remove('hidden');
            const sel = parentField.querySelector('select');
            if (sel) sel.required = true;
        } else {
            parentField.classList.add('hidden');
            const sel = parentField.querySelector('select');
            if (sel) { sel.required = false; sel.value = ''; }
        }
    }

    typeRadios.forEach(r => r.addEventListener('change', updateParentVisibility));
    updateParentVisibility(); // run on page load

    /* ── Main-package Collapse/Expand ────────────────────────────────── */
    document.querySelectorAll('.main-pkg-header').forEach(header => {
        header.addEventListener('click', () => {
            header.classList.toggle('collapsed');
            const list = header.nextElementSibling;
            if (list) list.classList.toggle('hidden');
        });
    });

    /* ── Delete Confirm Dialog ───────────────────────────────────────── */
    document.querySelectorAll('[data-delete-pkg]').forEach(btn => {
        btn.addEventListener('click', e => {
            const name = btn.dataset.deletePkg || 'this package';
            if (!confirm(`Delete "${name}"? This cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });

    /* ── Service Checkbox Keyboard Accessibility ─────────────────────── */
    document.querySelectorAll('.service-item').forEach(item => {
        item.addEventListener('keydown', e => {
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                const cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = !cb.checked;
            }
        });
        item.setAttribute('tabindex', '0');
        item.setAttribute('role', 'checkbox');
    });

    /* ── Edit Page: live price preview ──────────────────────────────── */
    const priceInput = document.getElementById('price');
    const pricePreview = document.getElementById('pricePreview');
    if (priceInput && pricePreview) {
        priceInput.addEventListener('input', () => {
            const val = parseFloat(priceInput.value) || 0;
            pricePreview.textContent = 'LKR ' + val.toLocaleString('en-US', { minimumFractionDigits: 2 });
        });
    }

});
