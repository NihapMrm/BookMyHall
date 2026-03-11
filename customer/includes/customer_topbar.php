<?php
/**
 * customer/includes/customer_topbar.php
 * Fixed top header bar for the customer sidebar layout.
 * Set $pageTitle and $pageSubtitle before including this file.
 */
$pageT     = $pageTitle    ?? 'Welcome';
$pageS     = $pageSubtitle ?? 'Lee Maridean Banquet Hall';
$isLoggedIn    = isset($_SESSION['customer_id']);
$customerName  = htmlspecialchars($_SESSION['customer_name'] ?? '');

$initials = '';
if ($customerName) {
    $parts    = array_filter(explode(' ', trim($customerName)));
    $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice($parts, 0, 2))));
}
?>

<header class="customer-topbar">
    <div class="ctopbar__title-group">
        <h1 class="ctopbar__title"><?= htmlspecialchars($pageT) ?></h1>
        <p class="ctopbar__subtitle"><?= htmlspecialchars($pageS) ?></p>
    </div>

    <div class="ctopbar__right">
        <?php if ($isLoggedIn): ?>
            <span style="font-size:13px; color:var(--text-muted);">
                Hi, <strong><?= $customerName ?></strong>
            </span>
            <a href="<?= BASE_URL ?>/customer/profile/profile.php" class="btn btn-sm btn-outline">
                <i class="fa-solid fa-user"></i> Profile
            </a>
            <a href="<?= BASE_URL ?>/customer/auth/customer_logout.php"
               class="btn btn-sm btn-danger"
               data-confirm="Are you sure you want to log out?">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/customer/auth/customer_login.php" class="btn btn-sm btn-outline">
                Sign In
            </a>
            <a href="<?= BASE_URL ?>/customer/auth/register.php" class="btn btn-sm btn-primary">
                Register
            </a>
        <?php endif; ?>
    </div>
</header>
