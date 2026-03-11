<?php
/**
 * manage_hall.php — Admin: View Hall Details
 * Module 2 – Riffna
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// Fetch hall record (single-hall system → first row)
$hall = null;
try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
    $images = [];
    if ($hall) {
        $stmt = $pdo->prepare(
            "SELECT * FROM hall_images WHERE hall_id = ? ORDER BY sort_order ASC, image_id ASC"
        );
        $stmt->execute([$hall['hall_id']]);
        $images = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("manage_hall.php: " . $e->getMessage());
}

$flash = getFlash();

// Decode features JSON
$features = [];
if ($hall && !empty($hall['features'])) {
    $decoded = json_decode($hall['features'], true);
    if (is_array($decoded)) $features = $decoded;
}

$amenityLabels = [
    'ac'           => ['label' => 'Air Conditioning', 'icon' => 'fa-snowflake'],
    'stage'        => ['label' => 'Grand Stage',       'icon' => 'fa-star'],
    'parking'      => ['label' => 'Parking',           'icon' => 'fa-square-parking'],
    'sound_system' => ['label' => 'Sound System',      'icon' => 'fa-music'],
    'catering'     => ['label' => 'Catering',          'icon' => 'fa-utensils'],
    'wifi'         => ['label' => 'Wi-Fi',             'icon' => 'fa-wifi'],
    'bridal_suite' => ['label' => 'Bridal Suite',      'icon' => 'fa-heart'],
    'projector'    => ['label' => 'Projector / AV',    'icon' => 'fa-display'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Hall — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/hall.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">
        <div class="page-header">
            <div>
                <h1 class="page-title">Hall Management</h1>
                <p class="page-subtitle">View and manage Lee Maridean Banquet Hall details and gallery.</p>
            </div>
            <?php if ($hall): ?>
            <div class="page-header-actions">
                <a href="<?= BASE_URL ?>/admin/hall/manage_images.php" class="btn btn-outline">
                    <i class="fa-solid fa-images"></i> Manage Gallery
                </a>
                <a href="<?= BASE_URL ?>/admin/hall/edit_hall.php" class="btn btn-primary">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Hall
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!$hall): ?>
        <!-- No hall configured yet -->
        <div class="hall-empty-state">
            <i class="fa-solid fa-building-columns"></i>
            <h3>No Hall Configured</h3>
            <p>The hall record hasn't been set up yet. Create it to start managing bookings.</p>
            <a href="<?= BASE_URL ?>/admin/hall/edit_hall.php?setup=1" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Set Up Hall
            </a>
        </div>

        <?php else: ?>

        <!-- Hall Status Bar -->
        <div class="hall-status-bar">
            <div class="hall-title"><?= htmlspecialchars($hall['name']) ?></div>
            <div class="hall-meta">
                <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($hall['location'] ?? 'N/A') ?></span>
                <span><i class="fa-solid fa-users"></i> <?= htmlspecialchars($hall['capacity']) ?> guests</span>
                <span><i class="fa-solid fa-vector-square"></i> <?= $hall['size_sqft'] ? number_format($hall['size_sqft']) . ' sq ft' : 'N/A' ?></span>
                <span>
                    <span class="hall-status-badge <?= htmlspecialchars($hall['status']) ?>">
                        <i class="fa-solid <?= $hall['status'] === 'available' ? 'fa-circle-check' : ($hall['status'] === 'maintenance' ? 'fa-wrench' : 'fa-circle-xmark') ?>"></i>
                        <?= ucfirst($hall['status']) ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="hall-detail-grid">
            <!-- Left Column: Hall Info -->
            <div class="hall-info-card">
                <h3><i class="fa-solid fa-circle-info"></i> &nbsp;Hall Details</h3>

                <div class="detail-row">
                    <span class="dr-label">Description</span>
                    <span class="dr-value"><?= nl2br(htmlspecialchars($hall['description'] ?? 'No description added.')) ?></span>
                </div>
                <div class="detail-row">
                    <span class="dr-label">Base Price</span>
                    <span class="dr-value"><?= formatCurrency($hall['base_price']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="dr-label">Capacity</span>
                    <span class="dr-value"><?= number_format($hall['capacity']) ?> guests</span>
                </div>
                <div class="detail-row">
                    <span class="dr-label">Size</span>
                    <span class="dr-value"><?= $hall['size_sqft'] ? number_format($hall['size_sqft']) . ' sq ft' : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="dr-label">Location</span>
                    <span class="dr-value"><?= htmlspecialchars($hall['location'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="dr-label">Status</span>
                    <span class="dr-value">
                        <span class="hall-status-badge <?= $hall['status'] ?>">
                            <?= ucfirst($hall['status']) ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="dr-label">Last Updated</span>
                    <span class="dr-value"><?= $hall['updated_at'] ? formatDateReadable($hall['updated_at']) : 'Never' ?></span>
                </div>

                <!-- Amenities -->
                <div class="detail-row">
                    <span class="dr-label">Amenities</span>
                    <span class="dr-value">
                        <div class="amenity-list">
                            <?php foreach ($amenityLabels as $key => $info): ?>
                                <?php $enabled = in_array($key, $features); ?>
                                <span class="amenity-badge <?= $enabled ? '' : 'disabled' ?>">
                                    <i class="fa-solid <?= $info['icon'] ?>"></i>
                                    <?= $info['label'] ?>
                                    <?php if (!$enabled): ?><i class="fa-solid fa-xmark" style="font-size:10px;"></i><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </span>
                </div>
            </div>

            <!-- Right Column: Gallery Preview -->
            <div class="hall-info-card">
                <h3><i class="fa-solid fa-images"></i> &nbsp;Gallery
                    <a href="<?= BASE_URL ?>/admin/hall/manage_images.php"
                       style="font-size:12px; font-weight:600; color:var(--primary); float:right; margin-top:2px;">
                        Manage <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </h3>
                <?php if (empty($images)): ?>
                    <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                        <i class="fa-solid fa-image" style="font-size:40px; color:#c9d0fd;"></i>
                        <p style="margin-top:10px;">No images uploaded yet.</p>
                        <a href="<?= BASE_URL ?>/admin/hall/manage_images.php" class="btn btn-outline btn-sm">
                            Upload Images
                        </a>
                    </div>
                <?php else: ?>
                    <div class="image-grid">
                        <?php foreach ($images as $img): ?>
                        <div class="image-thumb">
                            <img src="<?= BASE_URL ?>/assets/images/hall/<?= htmlspecialchars($img['filename']) ?>"
                                 alt="Hall image" loading="lazy">
                            <span class="img-sort-badge"><?= (int)$img['sort_order'] + 1 ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
