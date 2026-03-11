<?php
// Determine current page to highlight active link
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function sidebarLink(string $href, string $icon, string $label, string $currentPage, array $matchFiles = []): string
{
    $isActive = in_array($currentPage, $matchFiles) ? 'is-active' : '';
    return <<<HTML
        <li>
            <a class="sidebar__link {$isActive}" href="{$href}">
                <span class="sidebar__icon"><i class="{$icon}"></i></span>
                <span>{$label}</span>
            </a>
        </li>
    HTML;
}
?>

<aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar__brand">
        <button class="sidebar__menu-toggle" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
        </button>
        <span class="sidebar__brand-icon">
            <i class="fa-solid fa-building-columns"></i>
        </span>
        <span class="sidebar__logo">BookMyHall</span>
    </div>

    <nav class="sidebar__nav" aria-label="Admin navigation">

        <!-- Main -->
        <ul class="sidebar__section">
            <li class="sidebar__title">Main</li>
            <?= sidebarLink(BASE_URL . '/admin/dashboard/dashboard.php', 'fa-solid fa-table-columns', 'Dashboard',  $currentPage, ['dashboard.php']) ?>
            <?= sidebarLink(BASE_URL . '/admin/hall/manage_hall.php',    'fa-solid fa-building',      'Hall',       $currentPage, ['manage_hall.php','edit_hall.php','manage_images.php']) ?>
            <?= sidebarLink(BASE_URL . '/admin/packages/manage_packages.php', 'fa-solid fa-box-open', 'Packages',  $currentPage, ['manage_packages.php','add_package.php','edit_package.php']) ?>
            <?= sidebarLink(BASE_URL . '/admin/bookings/manage_bookings.php', 'fa-solid fa-calendar-check', 'Bookings', $currentPage, ['manage_bookings.php','booking_details.php','add_booking.php']) ?>
            <?= sidebarLink(BASE_URL . '/admin/customers/manage_customers.php', 'fa-solid fa-users', 'Customers', $currentPage, ['manage_customers.php','customer_details.php','block_customer.php']) ?>
            <?= sidebarLink(BASE_URL . '/admin/feedback/manage_feedback.php', 'fa-solid fa-comments', 'Feedback',  $currentPage, ['manage_feedback.php','view_feedback.php']) ?>
        </ul>

        <!-- Pages -->
        <ul class="sidebar__section">
            <li class="sidebar__title">Finance</li>
            <?= sidebarLink(BASE_URL . '/admin/payments/manage_payments.php', 'fa-solid fa-credit-card', 'Payments', $currentPage, ['manage_payments.php','payment_details.php','add_payment.php']) ?>
            <?= sidebarLink(BASE_URL . '/admin/reports/booking_report.php',   'fa-solid fa-chart-line',  'Reports',  $currentPage, ['booking_report.php','income_report.php','monthly_report.php','utilization_report.php']) ?>
        </ul>

        <!-- Footer -->
        <ul class="sidebar__section sidebar__section--footer">
            <li class="sidebar__title">Account</li>
            <?= sidebarLink(BASE_URL . '/admin/dashboard/admin_profile.php', 'fa-solid fa-user-gear', 'My Profile', $currentPage, ['admin_profile.php']) ?>
            <li>
                <a class="sidebar__link danger" href="<?= BASE_URL ?>/admin/auth/logout.php"
                   data-confirm="Are you sure you want to log out?">
                    <span class="sidebar__icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span>Logout</span>
                </a>
            </li>
        </ul>

    </nav>
</aside>
