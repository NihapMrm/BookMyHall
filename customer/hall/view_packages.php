<?php
/**
 * view_packages.php — Customer: Browse Active Packages
 * Module 2 – Riffna
 * Shows all active packages as a flat grid.
 * Public (no login required); "Book Now" requires login.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['customer_id']);
$hall       = null;
$packages   = [];

try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
    if ($hall) {
        $packages = getPackagesByHall($pdo, (int)$hall['hall_id']);
    }
} catch (PDOException $e) {
    error_log("view_packages: " . $e->getMessage());
}

$serviceIconMap = [
    'catering'   => ['label' => 'Catering',    'icon' => 'fa-utensils'],
    'ac'         => ['label' => 'AC',          'icon' => 'fa-snowflake'],
    'decoration' => ['label' => 'Decoration',  'icon' => 'fa-wand-magic-sparkles'],
    'wifi'       => ['label' => 'Wi-Fi',       'icon' => 'fa-wifi'],
    'parking'    => ['label' => 'Parking',     'icon' => 'fa-square-parking'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Packages — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/hall.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php
$pageTitle    = 'Event Packages';
$pageSubtitle = 'Choose from our curated packages for ' . htmlspecialchars($hall['name'] ?? 'the hall');
include __DIR__ . '/../includes/customer_topbar.php';
?>

<div class="c-content-wrapper">
<div class="customer-content">

    <?php if (!$hall || empty($packages)): ?>
    <!-- No packages yet -->
    <div style="text-align:center; padding:80px 20px;">
        <i class="fa-solid fa-box-open" style="font-size:56px; color:#c9d0fd;"></i>
        <h2 style="margin:16px 0 8px;">No Packages Available</h2>
        <p style="color:#6c6f83;">Check back soon — packages are being set up.</p>
        <a href="<?= BASE_URL ?>/" class="btn btn-outline" style="margin-top:16px;">Back to Home</a>
    </div>

    <?php else: ?>

    <div class="packages-section">
        <?php foreach ($packages as $pkg): ?>
        <div class="sub-pkg-card">
            <div class="spkg-name"><?= htmlspecialchars($pkg['name']) ?></div>

            <?php if ($pkg['description']): ?>
            <div class="spkg-desc"><?= htmlspecialchars($pkg['description']) ?></div>
            <?php endif; ?>

            <?php if ($pkg['seat_capacity'] || $pkg['parking_capacity']): ?>
            <div class="spkg-meta">
                <?php if ($pkg['seat_capacity']): ?>
                <span><i class="fa-solid fa-users"></i> <?= number_format($pkg['seat_capacity']) ?> guests</span>
                <?php endif; ?>
                <?php if ($pkg['parking_capacity']): ?>
                <span><i class="fa-solid fa-square-parking"></i> <?= number_format($pkg['parking_capacity']) ?> parking</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($pkg['services_arr'])): ?>
            <div class="spkg-services">
                <?php foreach ($pkg['services_arr'] as $svc): ?>
                    <?php if (isset($serviceIconMap[$svc])): ?>
                    <span class="svc-badge">
                        <i class="fa-solid <?= $serviceIconMap[$svc]['icon'] ?>"></i>
                        <?= $serviceIconMap[$svc]['label'] ?>
                    </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($pkg['inclusions']): ?>
            <div style="font-size:12px; color:#6c6f83; line-height:1.6; border-top:1px solid #eaedf7; padding-top:8px;">
                <?= nl2br(htmlspecialchars($pkg['inclusions'])) ?>
            </div>
            <?php endif; ?>

            <div class="spkg-footer">
                <div class="spkg-price">
                    <?= formatCurrency($pkg['price']) ?><br>
                    <small>per event</small>
                </div>
                <?php if ($hall['status'] === 'available'): ?>
                    <?php if ($isLoggedIn): ?>
                    <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php?package=<?= $pkg['package_id'] ?>"
                       class="btn btn-primary btn-sm">
                        Book Now <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <?php else: ?>
                    <a href="<?= BASE_URL ?>/customer/auth/customer_login.php" class="btn btn-primary btn-sm">
                        Sign In to Book
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                <span class="badge-status cancelled" style="font-size:11px;">Unavailable</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Bottom CTA -->
    <?php if (!$isLoggedIn): ?>
    <div style="background:#eef1ff; border-radius:20px; padding:32px 28px; text-align:center; margin-bottom:48px;">
        <h3 style="margin:0 0 8px; font-size:20px;">Ready to book your event?</h3>
        <p style="color:#6c6f83; margin:0 0 20px; font-size:15px;">
            Create a free account to check availability and confirm your reservation.
        </p>
        <a href="<?= BASE_URL ?>/customer/auth/register.php" class="btn btn-primary" style="font-size:15px; padding:12px 32px;">
            <i class="fa-solid fa-user-plus"></i> Create Free Account
        </a>
        <a href="<?= BASE_URL ?>/customer/hall/view_hall.php" class="btn btn-outline" style="margin-left:12px; font-size:15px; padding:12px 32px;">
            View Hall Details
        </a>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>

</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
