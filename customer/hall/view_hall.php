<?php
/**
 * view_hall.php — Customer: Hall Detail Page
 * Module 2 – Riffna
 * Public (no login required) — shows gallery, description, amenities, key stats
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['customer_id']);
$hall       = null;
$images     = [];

try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
    if ($hall) {
        $stmt = $pdo->prepare(
            "SELECT * FROM hall_images WHERE hall_id = ? ORDER BY sort_order ASC, image_id ASC"
        );
        $stmt->execute([$hall['hall_id']]);
        $images = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("view_hall: " . $e->getMessage());
}

$features = [];
if ($hall && !empty($hall['features'])) {
    $decoded = json_decode($hall['features'], true);
    if (is_array($decoded)) $features = $decoded;
}

$amenityDefs = [
    'ac'           => ['label' => 'Air Conditioning', 'icon' => 'fa-snowflake'],
    'stage'        => ['label' => 'Grand Stage',       'icon' => 'fa-star'],
    'parking'      => ['label' => 'Parking',           'icon' => 'fa-square-parking'],
    'sound_system' => ['label' => 'Sound System',      'icon' => 'fa-music'],
    'catering'     => ['label' => 'Catering',          'icon' => 'fa-utensils'],
    'wifi'         => ['label' => 'Wi-Fi',             'icon' => 'fa-wifi'],
    'bridal_suite' => ['label' => 'Bridal Suite',      'icon' => 'fa-heart'],
    'projector'    => ['label' => 'Projector / AV',    'icon' => 'fa-display'],
];

$firstImage = !empty($images) ? $images[0] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($hall['name'] ?? 'Hall') ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/hall.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php
$pageTitle    = htmlspecialchars($hall['name'] ?? 'The Hall');
$pageSubtitle = 'Gallery, amenities and venue details';
include __DIR__ . '/../includes/customer_topbar.php';
?>

<div class="c-content-wrapper">
<div class="customer-content">

    <?php if (!$hall): ?>
    <div style="text-align:center; padding:80px 20px;">
        <i class="fa-solid fa-building-columns" style="font-size:56px; color:#c9d0fd;"></i>
        <h2 style="margin-top:16px;">Hall information coming soon.</h2>
    </div>

    <?php else: ?>

    <!-- Hero -->
    <div class="hall-hero">
        <?php if ($firstImage): ?>
            <img src="<?= BASE_URL ?>/assets/images/hall/<?= htmlspecialchars($firstImage['filename']) ?>"
                 alt="<?= htmlspecialchars($hall['name']) ?>">
        <?php endif; ?>
        <div class="hall-hero-overlay">
            <h1><?= htmlspecialchars($hall['name']) ?></h1>
            <div class="hall-hero-meta">
                <?php if ($hall['location']): ?>
                <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($hall['location']) ?></span>
                <?php endif; ?>
                <span><i class="fa-solid fa-users"></i> Up to <?= number_format($hall['capacity']) ?> guests</span>
                <?php if ($hall['size_sqft']): ?>
                <span><i class="fa-solid fa-vector-square"></i> <?= number_format($hall['size_sqft']) ?> sq ft</span>
                <?php endif; ?>
                <span>
                    <i class="fa-solid <?= $hall['status'] === 'available' ? 'fa-circle-check' : 'fa-circle-pause' ?>"></i>
                    <?= ucfirst($hall['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Gallery -->
    <?php if (count($images) > 1): ?>
    <div class="gallery-section">
        <h2>Gallery</h2>
        <div class="gallery-grid">
            <?php foreach ($images as $img): ?>
            <div class="gallery-thumb">
                <img src="<?= BASE_URL ?>/assets/images/hall/<?= htmlspecialchars($img['filename']) ?>"
                     alt="Hall photo" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info Grid -->
    <div class="hall-info-grid">
        <!-- Description -->
        <div class="hall-description-card">
            <h2>About the Hall</h2>
            <p><?= nl2br(htmlspecialchars($hall['description'] ?? 'No description available.')) ?></p>
        </div>

        <!-- Key Stats -->
        <div class="hall-stats-card">
            <div class="hall-stat-item">
                <div class="hsi-icon"><i class="fa-solid fa-users"></i></div>
                <div class="hsi-text">
                    <div class="hsi-val"><?= number_format($hall['capacity']) ?>+</div>
                    <div class="hsi-lbl">Guest Capacity</div>
                </div>
            </div>
            <?php if ($hall['size_sqft']): ?>
            <div class="hall-stat-item">
                <div class="hsi-icon"><i class="fa-solid fa-vector-square"></i></div>
                <div class="hsi-text">
                    <div class="hsi-val"><?= number_format($hall['size_sqft']) ?></div>
                    <div class="hsi-lbl">Square Feet</div>
                </div>
            </div>
            <?php endif; ?>
            <div class="hall-stat-item">
                <div class="hsi-icon"><i class="fa-solid fa-tag"></i></div>
                <div class="hsi-text">
                    <div class="hsi-val"><?= formatCurrency($hall['base_price']) ?></div>
                    <div class="hsi-lbl">Base Price</div>
                </div>
            </div>
            <div class="hall-stat-item">
                <div class="hsi-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="hsi-text">
                    <div class="hsi-val"><?= ucfirst($hall['status']) ?></div>
                    <div class="hsi-lbl">Current Status</div>
                </div>
            </div>
            <div style="margin-top:8px;">
                <a href="<?= BASE_URL ?>/customer/hall/view_packages.php" class="btn btn-primary" style="width:100%; justify-content:center;">
                    <i class="fa-solid fa-box-open"></i> View Packages &amp; Book
                </a>
            </div>
        </div>
    </div>

    <!-- Amenities -->
    <?php if (!empty($features)): ?>
    <div class="amenities-section">
        <h2>Amenities &amp; Features</h2>
        <div class="amenities-grid">
            <?php foreach ($amenityDefs as $key => $def): ?>
                <?php $available = in_array($key, $features); ?>
                <div class="amenity-card <?= $available ? '' : 'unavailable' ?>">
                    <i class="fa-solid <?= $def['icon'] ?>"></i>
                    <?= $def['label'] ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- CTA -->
    <div style="text-align:center; padding:40px 0 60px;">
        <h2 style="font-size:24px; font-weight:700; margin-bottom:12px;">Ready to Book?</h2>
        <p style="color:#6c6f83; margin-bottom:24px;">Browse our packages and secure your date today.</p>
        <?php if ($isLoggedIn): ?>
            <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php" class="btn btn-primary" style="font-size:16px; padding:14px 36px;">
                <i class="fa-solid fa-calendar-plus"></i> Book Now
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/customer/auth/register.php" class="btn btn-primary" style="font-size:16px; padding:14px 36px;">
                <i class="fa-solid fa-user-plus"></i> Register &amp; Book
            </a>
            <a href="<?= BASE_URL ?>/customer/auth/customer_login.php" class="btn btn-outline" style="margin-left:12px; font-size:16px; padding:14px 36px;">
                Sign In
            </a>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>

</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
