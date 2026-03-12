/**
 * customer/feedback.js — Star Rating Interaction
 */
document.addEventListener('DOMContentLoaded', function () {

    const labels     = document.querySelectorAll('.star-rating-input label');
    const starLabel  = document.querySelector('.star-label');
    const starTexts  = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    // Update label text on hover
    labels.forEach(function (lbl) {
        lbl.addEventListener('mouseenter', function () {
            const val = parseInt(this.getAttribute('for').replace('star', ''));
            if (starLabel) starLabel.textContent = starTexts[val] || '';
        });
        lbl.addEventListener('mouseleave', function () {
            const checked = document.querySelector('.star-rating-input input:checked');
            if (starLabel) {
                starLabel.textContent = checked ? (starTexts[parseInt(checked.value)] || '') : '';
            }
        });
    });

    // Update label on selection
    document.querySelectorAll('.star-rating-input input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (starLabel) starLabel.textContent = starTexts[parseInt(this.value)] || '';
        });
    });

    // Form validation
    const form = document.getElementById('feedback-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const checked = document.querySelector('.star-rating-input input:checked');
            if (!checked) {
                e.preventDefault();
                alert('Please select a star rating before submitting.');
                return;
            }
            const comment = document.getElementById('comment');
            if (comment && comment.value.trim().length < 10) {
                e.preventDefault();
                alert('Please write at least 10 characters in your comment.');
            }
        });
    }
});
