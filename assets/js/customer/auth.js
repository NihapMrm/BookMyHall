/**
 * auth.js — Customer registration & login client-side behaviour.
 * Module 1 (Sahani / Nishtha).
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Password visibility toggles ──────────────────────────────────────────
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const inputId = btn.dataset.target;
            const input   = document.getElementById(inputId);
            const icon    = btn.querySelector('i');
            if (!input) return;
            if (input.type === 'password') {
                input.type     = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                input.type     = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        });
    });

    // ─── Password strength meter ──────────────────────────────────────────────
    const pwdInput  = document.getElementById('password');
    const fillEl    = document.getElementById('strengthFill');
    const labelEl   = document.getElementById('strengthLabel');
    const barEl     = document.getElementById('strengthBar');

    if (pwdInput && fillEl) {
        pwdInput.addEventListener('input', function () {
            const val = pwdInput.value;
            let score = 0;
            if (val.length >= 8)              score++;
            if (/[A-Z]/.test(val))            score++;
            if (/[0-9]/.test(val))            score++;
            if (/[^A-Za-z0-9]/.test(val))     score++;

            const levels = [
                { label: '',        width: '0%',   color: '#eaedf7' },
                { label: 'Weak',    width: '33%',  color: '#e74c3c' },
                { label: 'Fair',    width: '66%',  color: '#f39c12' },
                { label: 'Strong',  width: '100%', color: '#2ecc71' },
            ];

            const level = score === 0 ? 0 : score <= 2 ? 1 : score === 3 ? 2 : 3;
            fillEl.style.width      = levels[level].width;
            fillEl.style.background = levels[level].color;
            if (labelEl) labelEl.textContent = levels[level].label;
        });
    }

    // ─── Confirm password validation ──────────────────────────────────────────
    const confirmInput = document.getElementById('confirm_password');
    const form         = document.querySelector('.auth-form');

    if (form && confirmInput && pwdInput) {
        form.addEventListener('submit', function (e) {
            if (pwdInput.value !== confirmInput.value) {
                e.preventDefault();
                confirmInput.setCustomValidity('Passwords do not match.');
                confirmInput.reportValidity();
            } else {
                confirmInput.setCustomValidity('');
            }
        });

        confirmInput.addEventListener('input', function () {
            if (pwdInput.value !== confirmInput.value) {
                confirmInput.setCustomValidity('Passwords do not match.');
            } else {
                confirmInput.setCustomValidity('');
            }
        });
    }
});
