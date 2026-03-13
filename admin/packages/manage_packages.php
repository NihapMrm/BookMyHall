<?php
/**
 * manage_packages.php — Admin: List All Packages
 * Module 2 – Riffna
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$pageTitle    = 'Packages';
$pageSubtitle = 'Manage event packages for the hall';

$hall     = null;
$packages = [];
$stats    = ['total' => 0, 'active' => 0, 'bookings_this_month' => 0];

try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();

    if ($hall) {
        $stmt = $pdo->prepare(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM bookings b
                     WHERE b.package_id = p.package_id
                       AND b.is_deleted = 0
                       AND MONTH(b.created_at) = MONTH(NOW())
                       AND YEAR(b.created_at)  = YEAR(NOW())
                    ) AS bookings_this_month
             FROM packages p
             WHERE p.hall_id = ?
             ORDER BY p.price ASC"
        );
        $stmt->execute([$hall['hall_id']]);
        $packages = $stmt->fetchAll();

        foreach ($packages as $pkg) {
            $stats['total']++;
            if ($pkg['is_active']) $stats['active']++;
            $stats['bookings_this_month'] += (int)$pkg['bookings_this_month'];
        }
    }
} catch (PDOException $e) {
    error_log("manage_packages: " . $e->getMessage());
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Packages — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/packages.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">
        <div class="page-header">
            <div>
                <h1 class="page-title">Packages</h1>
                <p class="page-subtitle">Manage event packages for <?= htmlspecialchars($hall['name'] ?? 'the hall') ?>.</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/packages/add_package.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add Package
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Bar -->
        <div class="pkg-stats-bar">
            <div class="pkg-stat-card">
                <div class="ps-icon blue"><i class="fa-solid fa-box-open"></i></div>
                <div class="ps-info">
                    <div class="ps-val"><?= $stats['total'] ?></div>
                    <div class="ps-lbl">Total Packages</div>
                </div>
            </div>
            <div class="pkg-stat-card">
                <div class="ps-icon green"><i class="fa-solid fa-circle-check"></i></div>
                <div class="ps-info">
                    <div class="ps-val"><?= $stats['active'] ?></div>
                    <div class="ps-lbl">Active</div>
                </div>
            </div>
            <div class="pkg-stat-card">
                <div class="ps-icon orange"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="ps-info">
                    <div class="ps-val"><?= $stats['bookings_this_month'] ?></div>
                    <div class="ps-lbl">Bookings This Month</div>
                </div>
            </div>
        </div>

        <!-- Package List -->
        <?php if (empty($packages)): ?>
            <div class="section-card" style="text-align:center; padding:60px 30px;">
                <i class="fa-solid fa-box-open" style="font-size:48px; color:#c9d0fd;"></i>
                <h3 style="margin:16px 0 8px;">No Packages Yet</h3>
                <p style="color:var(--text-muted);">Add your first package to get started.</p>
                <a href="<?= BASE_URL ?>/admin/packages/add_package.php" class="btn btn-primary" style="margin-top:16px;">
                    <i class="fa-solid fa-plus"></i> Add Package
                </a>
            </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Package Name</th>
                    <th>Price</th>
                    <th>Capacity</th>
                    <th>Services</th>
                    <th>Bookings (Month)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($packages as $pkg): ?>
                <?php
                    $services = [];
                    if (!empty($pkg['services'])) {
                        $decoded = json_decode($pkg['services'], true);
                        if (is_array($decoded)) $services = $decoded;
                    }
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($pkg['name']) ?></div>
                        <?php if ($pkg['description']): ?>
                        <div style="font-size:.76rem; color:var(--text-muted); margin-top:2px;">
                            <?= htmlspecialchars(mb_strimwidth($pkg['description'], 0, 60, '...')) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><?= formatCurrency($pkg['price']) ?></td>
                    <td>
                        <?php if ($pkg['seat_capacity']): ?>
                        <span style="font-size:.82rem;"><i class="fa-solid fa-users" style="color:var(--primary);"></i> <?= number_format($pkg['seat_capacity']) ?></span>
                        <?php endif; ?>
                        <?php if ($pkg['parking_capacity']): ?>
                        <span style="font-size:.82rem; margin-left:6px;"><i class="fa-solid fa-car" style="color:var(--text-muted);"></i> <?= number_format($pkg['parking_capacity']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                        <?php foreach ($services as $svc): ?>
                            <span style="font-size:.72rem;background:var(--primary-light);color:var(--primary);padding:2px 8px;border-radius:20px;">
                                <?= htmlspecialchars(ucfirst($svc)) ?>
                            </span>
                        <?php endforeach; ?>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($pkg['bookings_this_month'] > 0): ?>
                        <span style="font-size:.82rem;color:var(--info);"><?= (int)$pkg['bookings_this_month'] ?></span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-status <?= $pkg['is_active'] ? 'approved' : 'cancelled' ?>">
                            <?= $pkg['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex;gap: 10px;">
                              <a href="<?= BASE_URL ?>/admin/packages/edit_package.php?id=<?= $pkg['package_id'] ?>" class="btn btn-sm btn-outline">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/packages/delete_package.php?id=<?= $pkg['package_id'] ?>" class="btn btn-sm btn-danger">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>  
                        </div>
                    
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/packages.js"></script>
</body>
</html>