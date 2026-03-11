<?php
/**
 * manage_packages.php — Admin: List All Packages
 * Module 2 – Riffna
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$hall    = null;
$mainPkgs = [];
$stats   = ['total' => 0, 'active' => 0, 'main' => 0, 'sub' => 0];

try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();

    if ($hall) {
        // Fetch all packages ordered by main first, then sub
        $stmt = $pdo->prepare(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM bookings b
                     WHERE b.sub_package_id = p.package_id
                       AND b.is_deleted = 0
                       AND MONTH(b.created_at) = MONTH(NOW())
                       AND YEAR(b.created_at)  = YEAR(NOW())
                    ) AS bookings_this_month
             FROM packages p
             WHERE p.hall_id = ?
             ORDER BY p.type DESC, p.parent_package_id ASC, p.package_id ASC"
        );
        $stmt->execute([$hall['hall_id']]);
        $allPkgs = $stmt->fetchAll();

        // Group: main packages + their subs
        $subs = [];
        foreach ($allPkgs as $pkg) {
            $stats['total']++;
            if ($pkg['is_active']) $stats['active']++;
            if ($pkg['type'] === 'main') {
                $stats['main']++;
                $mainPkgs[$pkg['package_id']] = array_merge($pkg, ['sub_packages' => []]);
            } else {
                $stats['sub']++;
                $subs[] = $pkg;
            }
        }
        foreach ($subs as $sub) {
            $pid = $sub['parent_package_id'];
            if ($pid && isset($mainPkgs[$pid])) {
                $mainPkgs[$pid]['sub_packages'][] = $sub;
            }
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
    <title>Manage Packages — <?= SITE_NAME ?></title>
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
                <p class="page-subtitle">Manage main packages and sub-packages for <?= htmlspecialchars($hall['name'] ?? 'the hall') ?>.</p>
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
                <div class="ps-icon orange"><i class="fa-solid fa-layer-group"></i></div>
                <div class="ps-info">
                    <div class="ps-val"><?= $stats['main'] ?></div>
                    <div class="ps-lbl">Main Packages</div>
                </div>
            </div>
            <div class="pkg-stat-card">
                <div class="ps-icon red"><i class="fa-solid fa-diagram-subtask"></i></div>
                <div class="ps-info">
                    <div class="ps-val"><?= $stats['sub'] ?></div>
                    <div class="ps-lbl">Sub-Packages</div>
                </div>
            </div>
        </div>

        <!-- Package Tree -->
        <?php if (empty($mainPkgs)): ?>
            <div class="section-card" style="text-align:center; padding:60px 30px;">
                <i class="fa-solid fa-box-open" style="font-size:48px; color:#c9d0fd;"></i>
                <h3 style="margin:16px 0 8px;">No Packages Yet</h3>
                <p style="color:var(--text-muted);">Add your first main package to get started.</p>
                <a href="<?= BASE_URL ?>/admin/packages/add_package.php" class="btn btn-primary" style="margin-top:16px;">
                    <i class="fa-solid fa-plus"></i> Add Package
                </a>
            </div>
        <?php else: ?>
        <div class="pkg-tree">
            <?php foreach ($mainPkgs as $main): ?>
            <div class="main-pkg-block">
                <div class="main-pkg-header">
                    <div class="mpkg-icon"><i class="fa-solid fa-layer-group"></i></div>
                    <div class="mpkg-name"><?= htmlspecialchars($main['name']) ?></div>
                    <div class="mpkg-meta">
                        <?php if ($main['description']): ?>
                        <span style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars(substr($main['description'], 0, 60)) ?>...
                        </span>
                        <?php endif; ?>
                        <span class="sub-count"><?= count($main['sub_packages']) ?> sub-package<?= count($main['sub_packages']) !== 1 ? 's' : '' ?></span>
                        <span class="badge-status <?= $main['is_active'] ? 'active' : 'cancelled' ?>">
                            <?= $main['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                        <a href="<?= BASE_URL ?>/admin/packages/edit_package.php?id=<?= $main['package_id'] ?>"
                           class="btn btn-sm btn-outline" onclick="event.stopPropagation()">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/packages/delete_package.php?id=<?= $main['package_id'] ?>"
                           class="btn btn-sm btn-danger" onclick="event.stopPropagation()">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                        <i class="fa-solid fa-chevron-down toggle-icon"></i>
                    </div>
                </div>

                <div class="sub-pkg-list">
                    <?php if (empty($main['sub_packages'])): ?>
                        <div class="no-sub">
                            No sub-packages yet —
                            <a href="<?= BASE_URL ?>/admin/packages/add_package.php?parent=<?= $main['package_id'] ?>">
                                Add one
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($main['sub_packages'] as $sub): ?>
                        <div class="sub-pkg-row">
                            <div class="tree-line"></div>
                            <div class="spkg-name"><?= htmlspecialchars($sub['name']) ?></div>
                            <div class="spkg-cap">
                                <i class="fa-solid fa-users" style="font-size:12px;"></i>
                                <?= $sub['seat_capacity'] ? number_format($sub['seat_capacity']) . ' pax' : '—' ?>
                            </div>
                            <div class="spkg-price"><?= formatCurrency($sub['price']) ?></div>
                            <span class="badge-status <?= $sub['is_active'] ? 'active' : 'cancelled' ?>">
                                <?= $sub['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <?php if ($sub['bookings_this_month'] > 0): ?>
                            <span style="font-size:12px; color:var(--info);">
                                <i class="fa-solid fa-calendar-check"></i> <?= $sub['bookings_this_month'] ?> this month
                            </span>
                            <?php endif; ?>
                            <div class="spkg-actions">
                                <a href="<?= BASE_URL ?>/admin/packages/edit_package.php?id=<?= $sub['package_id'] ?>"
                                   class="btn btn-sm btn-outline">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/admin/packages/delete_package.php?id=<?= $sub['package_id'] ?>"
                                   class="btn btn-sm btn-danger">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div style="padding:10px 0; text-align:right;">
                            <a href="<?= BASE_URL ?>/admin/packages/add_package.php?parent=<?= $main['package_id'] ?>"
                               class="btn btn-sm btn-outline">
                                <i class="fa-solid fa-plus"></i> Add Sub-Package
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/packages.js"></script>
</body>
</html>
