<?php
/**
 * profile.php — Customer: View own profile
 * Module 3 – Nishtha
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

$customerId = (int)$_SESSION['customer_id'];

// ─── Fetch customer ───────────────────────────────────────────────────────
$customer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
} catch (PDOException $e) {
    error_log("profile fetch: " . $e->getMessage());
}

if (!$customer) {
    redirect(BASE_URL . '/customer/auth/customer_logout.php');
}

// ─── Booking summary stats ─────────────────────────────────────────────────
$bookingStats = ['total' => 0, 'completed' => 0, 'upcoming' => 0];
try {
    $s = $pdo->prepare(
        "SELECT
            COUNT(*)                                             AS total,
            SUM(status = 'completed')                           AS completed,
            SUM(status IN ('pending','approved') AND event_date >= CURDATE()) AS upcoming
         FROM bookings
         WHERE customer_id = ? AND is_deleted = 0"
    );
    $s->execute([$customerId]);
    $bookingStats = $s->fetch();
} catch (PDOException $e) {
    error_log("profile stats: " . $e->getMessage());
}

// ─── Recent bookings ──────────────────────────────────────────────────────
$recentBookings = [];
try {
    $s = $pdo->prepare(
        "SELECT b.booking_id, b.event_date, b.status, b.total_amount, p.name AS package_name
         FROM bookings b
         LEFT JOIN packages p ON p.package_id = b.sub_package_id
         WHERE b.customer_id = ? AND b.is_deleted = 0
         ORDER BY b.created_at DESC
         LIMIT 5"
    );
    $s->execute([$customerId]);
    $recentBookings = $s->fetchAll();
} catch (PDOException $e) {
    error_log("profile recent bookings: " . $e->getMessage());
}

$flash = getFlash();
$initials = strtoupper(implode('', array_map(fn($w) => $w[0],
    array_slice(explode(' ', $customer['full_name']), 0, 2))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Profile — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/profile.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php
$pageTitle    = 'My Profile';
$pageSubtitle = 'Your account details and booking history';
include __DIR__ . '/../includes/customer_topbar.php';
?>

<div class="c-content-wrapper">
<div class="customer-content">

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- ── Stats Row ─────────────────────────────────────────────────────── -->
    <div class="profile-stats-row">
        <div class="profile-stat-card">
            <div class="ps-val"><?= (int)$bookingStats['total'] ?></div>
            <div class="ps-lbl">Total Bookings</div>
        </div>
        <div class="profile-stat-card">
            <div class="ps-val"><?= (int)$bookingStats['completed'] ?></div>
            <div class="ps-lbl">Completed Events</div>
        </div>
        <div class="profile-stat-card">
            <div class="ps-val"><?= (int)$bookingStats['upcoming'] ?></div>
            <div class="ps-lbl">Upcoming Events</div>
        </div>
    </div>

    <!-- ── Profile Layout ────────────────────────────────────────────────── -->
    <div class="profile-layout">

        <!-- Left: profile card -->
        <aside class="profile-card">
            <div class="profile-avatar-wrap">
                <div class="profile-avatar">
                    <?php if (!empty($customer['profile_picture'])): ?>
                        <img src="<?= BASE_URL ?>/assets/images/profiles/<?= htmlspecialchars($customer['profile_picture']) ?>" alt="Profile picture">
                    <?php else: ?>
                        <?= htmlspecialchars($initials) ?>
                    <?php endif; ?>
                </div>
            </div>

            <p class="profile-name"><?= htmlspecialchars($customer['full_name']) ?></p>
            <span class="profile-role-badge">Customer</span>

            <div class="profile-detail-row">
                <i class="fa-solid fa-envelope"></i>
                <span><?= htmlspecialchars($customer['email']) ?></span>
            </div>

            <?php if ($customer['phone']): ?>
            <div class="profile-detail-row">
                <i class="fa-solid fa-phone"></i>
                <span><?= htmlspecialchars($customer['phone']) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($customer['address']): ?>
            <div class="profile-detail-row">
                <i class="fa-solid fa-location-dot"></i>
                <span><?= htmlspecialchars($customer['address']) ?></span>
            </div>
            <?php endif; ?>

            <div class="profile-detail-row">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Member since <?= formatDateReadable($customer['created_at']) ?></span>
            </div>

            <hr class="profile-divider">

            <div class="profile-card-actions">
                <a href="<?= BASE_URL ?>/customer/profile/edit_profile.php" class="btn btn-primary btn-full">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile
                </a>
                <a href="<?= BASE_URL ?>/customer/profile/change_password.php" class="btn btn-outline btn-full">
                    <i class="fa-solid fa-lock"></i> Change Password
                </a>
            </div>
        </aside>

        <!-- Right: recent bookings -->
        <div>
            <div class="profile-recent-card">
                <div class="prc-header">
                    <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Bookings</h3>
                    <a href="<?= BASE_URL ?>/customer/bookings/booking_history.php"
                       class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Event Date</th>
                                <th>Package</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentBookings)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted);">
                                    <i class="fa-solid fa-calendar" style="font-size:28px;display:block;margin-bottom:8px;color:#c9d0fd;"></i>
                                    You haven't made any bookings yet.
                                    <br>
                                    <a href="<?= BASE_URL ?>/customer/hall/view_packages.php"
                                       class="btn btn-primary btn-sm" style="margin-top:12px;">
                                        Browse Packages
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentBookings as $b): ?>
                            <tr>
                                <td style="font-size:12px;color:var(--text-muted);">#<?= $b['booking_id'] ?></td>
                                <td style="font-size:13px;"><?= formatDateReadable($b['event_date']) ?></td>
                                <td style="font-size:13px;"><?= htmlspecialchars($b['package_name'] ?? '—') ?></td>
                                <td style="font-size:13px;font-weight:600;"><?= formatCurrency((float)$b['total_amount']) ?></td>
                                <td><span class="badge-status <?= htmlspecialchars($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /.profile-layout -->

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
