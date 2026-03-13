<?php
// $pageTitle and $pageSubtitle should be set in the including page
$title    = $pageTitle    ?? 'Dashboard';
$subtitle = $pageSubtitle ?? 'Welcome back, ' . ($_SESSION['full_name'] ?? 'Admin');

// Greeting based on hour
$hour = (int) date('H');
$greeting = match(true) {
    $hour < 12 => 'Good morning',
    $hour < 17 => 'Good afternoon',
    default    => 'Good evening',
};
$subtitle = $subtitle ?: "$greeting, " . htmlspecialchars($_SESSION['full_name'] ?? 'Admin');

// Avatar initials fallback
$name     = $_SESSION['full_name'] ?? 'Admin';
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', $name), 0, 2))));

// Pages where the search bar should be hidden
$_hideSearchPages = [
    'dashboard.php',
    'manage_hall.php', 'edit_hall.php', 'manage_images.php',
    'manage_packages.php', 'add_package.php', 'edit_package.php',
    'booking_report.php', 'income_report.php', 'monthly_report.php',
    'utilization_report.php', 'customer_report.php', 'export_report.php',
    'admin_profile.php',
];
$_showSearch = !in_array(basename($_SERVER['PHP_SELF']), $_hideSearchPages, true);
?>

<header class="topbar">
    <div class="topbar__left">
        <div class="topbar__title-group">
            <h1 class="topbar__title"><?= htmlspecialchars($title) ?></h1>
            <p class="topbar__subtitle"><?= htmlspecialchars($subtitle) ?></p>
        </div>

        <?php if ($_showSearch): ?>
        <form class="topbar__search" role="search" action="#" method="GET">
            <label class="sr-only" for="admin-search">Search</label>
            <input id="admin-search" type="search" name="q"
                   placeholder="Search anything…"
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" />
            <button type="submit" aria-label="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="topbar__right">
        <!-- Notifications (badge wired in Phase 4) -->
        <button class="topbar__icon-button" aria-label="Notifications">
            <i class="fa-solid fa-bell"></i>
        </button>

        <!-- Profile dropdown -->
        <div class="topbar__profile" role="button" aria-haspopup="true" aria-expanded="false" tabindex="0">
            <div class="topbar__avatar-placeholder" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
            <div>
                <span class="topbar__profile-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></span>
                <span class="topbar__profile-role">Administrator</span>
            </div>
            <i class="fa-solid fa-angle-down" style="font-size:12px;color:var(--text-muted);"></i>

            <div class="profile-dropdown">
                <a href="<?= BASE_URL ?>/admin/dashboard/admin_profile.php">
                    <i class="fa-solid fa-user-gear"></i> My Profile
                </a>
                <hr>
                <a href="<?= BASE_URL ?>/admin/auth/logout.php" class="danger"
                   data-confirm="Are you sure you want to log out?">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>
