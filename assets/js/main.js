/**
 * main.js — Global scripts loaded on every admin and customer page.
 */

// ─── Flash message auto-dismiss ───────────────────────────────────────────────
(function () {
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    alerts.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });
})();

// ─── Confirm dialogs ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Any link/button with data-confirm="Message" will trigger a native confirm
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});

// ─── Active nav highlight (matches current pathname) ──────────────────────────
(function () {
    const path = window.location.pathname;
    document.querySelectorAll('.sidebar__link, .navbar__links a').forEach(function (link) {
        if (link.getAttribute('href') && path.includes(link.getAttribute('href'))) {
            link.classList.add('is-active');
        }
    });
})();

// ─── Profile dropdown toggle ──────────────────────────────────────────────────
(function () {
    const profile = document.querySelector('.topbar__profile');
    if (!profile) return;
    profile.addEventListener('click', function (e) {
        e.stopPropagation();
        profile.classList.toggle('is-open');
    });
    document.addEventListener('click', function () {
        profile.classList.remove('is-open');
    });
})();

// ─── Mobile sidebar toggle ────────────────────────────────────────────────────
(function () {
    const toggle = document.querySelector('.sidebar__menu-toggle');
    if (!toggle) return;
    toggle.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-open');
    });
})();
