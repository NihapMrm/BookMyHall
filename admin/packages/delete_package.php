<?php
/**
 * delete_package.php — Admin: Confirm & Delete a Package
 * Module 2 – Riffna
 * Blocks deletion if the package has active bookings.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$packageId = (int) ($_GET['id'] ?? 0);

if ($packageId <= 0) {
    setFlash('danger', 'Invalid package.');
    redirect(BASE_URL . '/admin/packages/manage_packages.php');
}

$package        = null;
$activeBookings = 0;

try {
    $hall = $pdo->query("SELECT hall_id FROM hall LIMIT 1")->fetch();
    if ($hall) {
        $stmt = $pdo->prepare("SELECT * FROM packages WHERE package_id = ? AND hall_id = ?");
        $stmt->execute([$packageId, $hall['hall_id']]);
        $package = $stmt->fetch();

        if ($package) {
            $b = $pdo->prepare(
                "SELECT COUNT(*) FROM bookings
                 WHERE package_id = ?
                   AND status IN ('pending','approved')
                   AND is_deleted = 0"
            );
            $b->execute([$packageId]);
            $activeBookings = (int) $b->fetchColumn();
        }
    }
} catch (PDOException $e) {
    error_log("delete_package load: " . $e->getMessage());
}

if (!$package) {
    setFlash('danger', 'Package not found.');
    redirect(BASE_URL . '/admin/packages/manage_packages.php');
}

// ── POST: Confirmed Delete ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if ($activeBookings > 0) {
        setFlash('danger', 'Cannot delete: this package has active bookings.');
        redirect(BASE_URL . '/admin/packages/manage_packages.php');
    }
    try {
        $pdo->prepare("DELETE FROM packages WHERE package_id = ?")->execute([$packageId]);
        setFlash('success', 'Package "' . $package['name'] . '" deleted successfully.');
        redirect(BASE_URL . '/admin/packages/manage_packages.php');
    } catch (PDOException $e) {
        error_log("delete_package delete: " . $e->getMessage());
        setFlash('danger', 'Failed to delete package. Please try again.');
        redirect(BASE_URL . '/admin/packages/manage_packages.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Delete Package — <?= SITE_NAME ?></title>
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
                <h1 class="page-title">Delete Package</h1>
                <p class="page-subtitle">Review the impact before confirming deletion.</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/packages/manage_packages.php" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>

        <div class="delete-confirm-card">
            <?php if ($activeBookings > 0): ?>
                <div class="dc-icon" style="color:var(--warning);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h2>Cannot Delete This Package</h2>
                <p style="color:var(--text-muted); font-size:15px;">
                    <strong><?= $activeBookings ?> active booking<?= $activeBookings !== 1 ? 's' : '' ?></strong>
                    are using this package. You must cancel or reject those bookings first.
                </p>
                <div class="pkg-summary">
                    <div class="ps-row"><span>Package</span><span><?= htmlspecialchars($package['name']) ?></span></div>
                    <div class="ps-row"><span>Active Bookings</span><span style="color:var(--danger);"><?= $activeBookings ?></span></div>
                </div>
                <div class="dc-actions">
                    <a href="<?= BASE_URL ?>/admin/packages/manage_packages.php" class="btn btn-primary">
                        Back to Packages
                    </a>
                    <a href="<?= BASE_URL ?>/admin/bookings/manage_bookings.php" class="btn btn-outline">
                        View Bookings
                    </a>
                </div>

            <?php else: ?>
                <div class="dc-icon">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <h2>Confirm Deletion</h2>
                <p style="color:var(--text-muted); font-size:15px;">
                    This action is permanent and cannot be undone.
                </p>

                <div class="pkg-summary">
                    <div class="ps-row"><span>Package Name</span><span><?= htmlspecialchars($package['name']) ?></span></div>
                    <div class="ps-row"><span>Price</span><span><?= formatCurrency($package['price']) ?></span></div>
                    <div class="ps-row"><span>Active Bookings</span><span style="color:var(--success);">None</span></div>
                </div>

                <form method="POST" action="">
                    <div class="dc-actions">
                        <a href="<?= BASE_URL ?>/admin/packages/manage_packages.php" class="btn btn-outline">
                            Cancel
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            <i class="fa-solid fa-trash-can"></i> Yes, Delete
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
