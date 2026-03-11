<?php
/**
 * customer/includes/customer_sidebar.php
 * Fixed left sidebar for the customer-facing layout.
 * Mirrors the admin sidebar visual style.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['customer_id']);

function csideLink(string $href, string $icon, string $label, string $currentPage, array $match = []): string
{
    $active = in_array($currentPage, $match) ? 'is-active' : '';
    return <<<HTML
        <li>
            <a class="cside__link {$active}" href="{$href}">
                <span class="cside__icon"><i class="{$icon}"></i></span>
                <span>{$label}</span>
            </a>
        </li>
    HTML;
}
?>

<aside class="customer-sidebar" aria-label="Customer navigation">

    <!-- Brand -->
    <div class="cside__brand">
        <span class="cside__brand-icon">
            <i class="fa-solid fa-building-columns"></i>
        </span>
        <span class="cside__logo">BookMyHall</span>
    </div>

    <nav class="cside__nav">

        <!-- Browse -->
        <ul class="cside__section">
            <li class="cside__title">Browse</li>
            <?= csideLink(BASE_URL . '/index.php',
                'fa-solid fa-house', 'Home', $currentPage, ['index.php']) ?>
            <?= csideLink(BASE_URL . '/customer/hall/view_hall.php',
                'fa-solid fa-building', 'The Hall', $currentPage, ['view_hall.php']) ?>
            <?= csideLink(BASE_URL . '/customer/hall/view_packages.php',
                'fa-solid fa-box-open', 'Packages', $currentPage, ['view_packages.php']) ?>
        </ul>

        <!-- Bookings — logged-in only -->
        <?php if ($isLoggedIn): ?>
        <ul class="cside__section">
            <li class="cside__title">My Bookings</li>
            <?= csideLink(BASE_URL . '/customer/bookings/book_hall.php',
                'fa-solid fa-calendar-plus', 'Book Now', $currentPage, ['book_hall.php']) ?>
            <?= csideLink(BASE_URL . '/customer/bookings/booking_history.php',
                'fa-solid fa-clock-rotate-left', 'Booking History', $currentPage,
                ['booking_history.php', 'customer_bookings.php', 'booking_details.php']) ?>
            <?= csideLink(BASE_URL . '/customer/feedback/my_feedback.php',
                'fa-regular fa-star', 'My Feedback', $currentPage,
                ['my_feedback.php', 'submit_feedback.php']) ?>
        </ul>
        <?php endif; ?>

        <!-- Account -->
        <ul class="cside__section cside__section--footer">
            <li class="cside__title">Account</li>
            <?php if ($isLoggedIn): ?>
                <?= csideLink(BASE_URL . '/customer/profile/profile.php',
                    'fa-solid fa-user-gear', 'My Profile', $currentPage,
                    ['profile.php', 'edit_profile.php', 'change_password.php']) ?>
                <li>
                    <a class="cside__link" href="<?= BASE_URL ?>/customer/auth/customer_logout.php"
                       data-confirm="Are you sure you want to log out?">
                        <span class="cside__icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                        <span>Logout</span>
                    </a>
                </li>
            <?php else: ?>
                <?= csideLink(BASE_URL . '/customer/auth/customer_login.php',
                    'fa-solid fa-arrow-right-to-bracket', 'Sign In', $currentPage, ['customer_login.php']) ?>
                <?= csideLink(BASE_URL . '/customer/auth/register.php',
                    'fa-solid fa-user-plus', 'Register', $currentPage, ['register.php']) ?>
            <?php endif; ?>
        </ul>

    </nav>
</aside>
