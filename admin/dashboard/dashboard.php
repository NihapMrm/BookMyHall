<?php
/**
 * admin/dashboard/dashboard.php — Main admin dashboard.
 * Module 1 – Sahani
 */
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

// ─── Fetch dashboard stats (graceful fallback if tables not yet created) ──────
$stats = getDashboardStats($pdo);

// ─── Recent bookings (last 6) ─────────────────────────────────────────────────
$recentBookings = [];
try {
    $stmt = $pdo->query(
        "SELECT b.booking_id, u.full_name, u.email, b.event_date,
                b.event_type, b.total_amount, b.status, b.created_at
         FROM bookings b
         JOIN users u ON b.customer_id = u.user_id
         WHERE b.is_deleted = 0
         ORDER BY b.created_at DESC
         LIMIT 6"
    );
    $recentBookings = $stmt->fetchAll();
} catch (PDOException $e) { /* bookings table not yet created */ }

// ─── Upcoming events (next 5 approved/pending bookings) ───────────────────────
$upcomingBookings = [];
try {
    $stmt = $pdo->query(
        "SELECT b.booking_id, u.full_name, b.event_date, b.start_time,
                b.event_type, b.status
         FROM bookings b
         JOIN users u ON b.customer_id = u.user_id
         WHERE b.event_date >= CURDATE()
           AND b.status IN ('pending','approved')
           AND b.is_deleted = 0
         ORDER BY b.event_date ASC
         LIMIT 5"
    );
    $upcomingBookings = $stmt->fetchAll();
} catch (PDOException $e) { /* bookings table not yet created */ }

// ─── Flash message ────────────────────────────────────────────────────────────
$flash = getFlash();

// ─── Page meta ────────────────────────────────────────────────────────────────
$pageTitle    = 'Dashboard';
$pageSubtitle = '';   // header.php generates greeting from session
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | BookMyHall Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/dashboard.css">
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-auto-dismiss>
            <i class="fa-solid fa-circle-info"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- ── Stat cards ─────────────────────────────────────────────────────── -->
    <div class="stats-grid">

        <article class="stat-card">
            <div class="stat-card__header">
                <div>
                    <p class="stat-card__title">Total Bookings</p>
                    <p class="stat-card__value"><?= number_format($stats['total_bookings']) ?></p>
                </div>
                <span class="stat-card__icon icon-primary">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>
            </div>
            <span class="stat-card__trend trend-neutral">All time</span>
        </article>

        <article class="stat-card">
            <div class="stat-card__header">
                <div>
                    <p class="stat-card__title">Pending Approvals</p>
                    <p class="stat-card__value"><?= number_format($stats['pending_approvals']) ?></p>
                </div>
                <span class="stat-card__icon icon-warning">
                    <i class="fa-solid fa-clock"></i>
                </span>
            </div>
            <span class="stat-card__trend <?= $stats['pending_approvals'] > 0 ? 'trend-down' : 'trend-neutral' ?>">
                <?= $stats['pending_approvals'] > 0 ? 'Needs attention' : 'All clear' ?>
            </span>
        </article>

        <article class="stat-card">
            <div class="stat-card__header">
                <div>
                    <p class="stat-card__title">Total Revenue</p>
                    <p class="stat-card__value"><?= formatCurrency((float) $stats['total_revenue']) ?></p>
                </div>
                <span class="stat-card__icon icon-success">
                    <i class="fa-solid fa-sack-dollar"></i>
                </span>
            </div>
            <span class="stat-card__trend trend-neutral">Paid payments</span>
        </article>

        <article class="stat-card">
            <div class="stat-card__header">
                <div>
                    <p class="stat-card__title">New Customers</p>
                    <p class="stat-card__value"><?= number_format($stats['new_customers']) ?></p>
                </div>
                <span class="stat-card__icon icon-info">
                    <i class="fa-solid fa-users"></i>
                </span>
            </div>
            <span class="stat-card__trend trend-neutral">This month</span>
        </article>

    </div><!-- /.stats-grid -->

    <!-- ── Bottom two-column grid ─────────────────────────────────────────── -->
    <div style="display:grid; grid-template-columns: 1fr 340px; gap:24px;">

        <!-- Recent bookings table -->
        <section class="section-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-list" style="color:var(--primary);margin-right:8px;"></i>Recent Bookings</h3>
                <a href="<?= BASE_URL ?>/admin/bookings/manage_bookings.php" class="btn btn-sm btn-outline">View All</a>
            </div>

            <?php if (empty($recentBookings)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <p>No bookings yet. They will appear here once customers start booking.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Customer</th>
                                <th scope="col">Event Date</th>
                                <th scope="col">Type</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $b): ?>
                            <tr>
                                <td>
                                    <div class="customer-cell">
                                        <div class="cust-avatar">
                                            <?= strtoupper($b['full_name'][0]) ?>
                                        </div>
                                        <div>
                                            <span class="cust-name"><?= htmlspecialchars($b['full_name']) ?></span>
                                            <span class="cust-email"><?= htmlspecialchars($b['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= formatDateReadable($b['event_date']) ?></td>
                                <td><?= htmlspecialchars($b['event_type'] ?? '—') ?></td>
                                <td><?= formatCurrency((float) $b['total_amount']) ?></td>
                                <td><span class="badge-status badge-<?= htmlspecialchars($b['status']) ?>"><?= ucfirst(htmlspecialchars($b['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Upcoming events panel -->
        <section class="section-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-calendar-days" style="color:var(--primary);margin-right:8px;"></i>Upcoming</h3>
                <a href="<?= BASE_URL ?>/admin/bookings/manage_bookings.php" class="btn btn-sm btn-outline">All</a>
            </div>

            <?php if (empty($upcomingBookings)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-calendar"></i>
                    <p>No upcoming bookings scheduled.</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcomingBookings as $u): ?>
                    <div class="upcoming-item">
                        <div class="upcoming-date">
                            <span class="day"><?= date('d', strtotime($u['event_date'])) ?></span>
                            <span class="month"><?= date('M', strtotime($u['event_date'])) ?></span>
                        </div>
                        <div class="upcoming-info">
                            <p class="upcoming-name"><?= htmlspecialchars($u['full_name']) ?></p>
                            <p class="upcoming-meta">
                                <?= htmlspecialchars($u['event_type'] ?? 'Event') ?> ·
                                <?= date('g:i A', strtotime($u['start_time'])) ?>
                            </p>
                        </div>
                        <span class="badge-status badge-<?= htmlspecialchars($u['status']) ?>">
                            <?= ucfirst(htmlspecialchars($u['status'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </div><!-- /.two-col-grid -->

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/dashboard.js"></script>

</body>
</html>
