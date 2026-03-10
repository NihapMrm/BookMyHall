<?php
/**
 * customer/includes/navbar.php — Customer-area top navigation bar.
 * Include at the top of all customer pages (after session check).
 */
$currentPage = basename($_SERVER['PHP_SELF']);

function navLink(string $href, string $label, string $currentPage, array $match = []): string
{
    $active = in_array($currentPage, $match) ? 'is-active' : '';
    return "<li><a class=\"{$active}\" href=\"{$href}\">{$label}</a></li>";
}

$isLoggedIn   = isset($_SESSION['customer_id']);
$customerName = $isLoggedIn ? htmlspecialchars($_SESSION['customer_name'] ?? '') : '';
?>

<nav class="navbar" aria-label="Customer navigation">

    <!-- Brand -->
    <a class="navbar__brand" href="<?= BASE_URL ?>/index.php">
        <span class="navbar__brand-icon"><i class="fa-solid fa-building-columns"></i></span>
        BookMyHall
    </a>

    <!-- Main links -->
    <ul class="navbar__links">
        <?= navLink(BASE_URL . '/index.php',                          'Home',         $currentPage, ['index.php']) ?>
        <?= navLink(BASE_URL . '/customer/hall/view_hall.php',        'The Hall',     $currentPage, ['view_hall.php']) ?>
        <?= navLink(BASE_URL . '/customer/hall/view_packages.php',    'Packages',     $currentPage, ['view_packages.php']) ?>
        <?php if ($isLoggedIn): ?>
            <?= navLink(BASE_URL . '/customer/bookings/book_hall.php',     'Book Now',     $currentPage, ['book_hall.php']) ?>
            <?= navLink(BASE_URL . '/customer/bookings/booking_history.php','My Bookings',  $currentPage, ['booking_history.php','customer_bookings.php','booking_details.php']) ?>
        <?php endif; ?>
    </ul>

    <!-- Right actions -->
    <div class="navbar__actions">
        <?php if ($isLoggedIn): ?>
            <span style="font-size:14px; color:var(--text-muted);">Hi, <strong><?= $customerName ?></strong></span>
            <a href="<?= BASE_URL ?>/customer/profile/profile.php" class="btn btn-sm btn-outline">
                <i class="fa-solid fa-user"></i> Profile
            </a>
            <a href="<?= BASE_URL ?>/customer/auth/customer_logout.php"
               class="btn btn-sm btn-danger"
               data-confirm="Are you sure you want to log out?">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/customer/auth/customer_login.php" class="btn btn-sm btn-outline">Sign In</a>
            <a href="<?= BASE_URL ?>/customer/auth/register.php"       class="btn btn-sm btn-primary">Register</a>
        <?php endif; ?>
    </div>

</nav>
