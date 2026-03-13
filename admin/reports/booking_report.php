<?php
/**
 * booking_report.php — Admin: Booking Summary Report with date range filter
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Filters ──────────────────────────────────────────────────────────────────
$dateFrom = sanitizeInput($_GET['from'] ?? date('Y-m-01'));
$dateTo   = sanitizeInput($_GET['to']   ?? date('Y-m-d'));

// ─── Summary stats ─────────────────────────────────────────────────────────────
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0];
try {
    $row = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status='pending')   AS pending,
                SUM(status='approved')  AS approved,
                SUM(status='completed') AS completed,
                SUM(status='rejected')  AS rejected,
                SUM(status='cancelled') AS cancelled
         FROM bookings
         WHERE is_deleted = 0 AND event_date BETWEEN ? AND ?"
    );
    $row->execute([$dateFrom, $dateTo]);
    $r = $row->fetch();
    if ($r) $stats = array_map('intval', $r);
} catch (PDOException $e) { error_log('booking_report stats: ' . $e->getMessage()); }

// ─── Status breakdown for chart ───────────────────────────────────────────────
$chartLabels = ['Pending','Approved','Completed','Rejected','Cancelled'];
$chartValues = [
    $stats['pending'], $stats['approved'], $stats['completed'],
    $stats['rejected'], $stats['cancelled']
];

// ─── Popular event types ──────────────────────────────────────────────────────
$eventTypes = [];
try {
    $etStmt = $pdo->prepare(
        "SELECT event_type, COUNT(*) AS cnt
         FROM bookings
         WHERE is_deleted = 0 AND event_type IS NOT NULL AND event_date BETWEEN ? AND ?
         GROUP BY event_type ORDER BY cnt DESC LIMIT 10"
    );
    $etStmt->execute([$dateFrom, $dateTo]);
    $eventTypes = $etStmt->fetchAll();
} catch (PDOException $e) { error_log('booking_report event_types: ' . $e->getMessage()); }

// ─── Peak booking dates ────────────────────────────────────────────────────────
$peakDates = [];
try {
    $pdStmt = $pdo->prepare(
        "SELECT event_date, COUNT(*) AS cnt
         FROM bookings
         WHERE is_deleted = 0 AND event_date BETWEEN ? AND ?
         GROUP BY event_date ORDER BY cnt DESC LIMIT 10"
    );
    $pdStmt->execute([$dateFrom, $dateTo]);
    $peakDates = $pdStmt->fetchAll();
} catch (PDOException $e) { error_log('booking_report peak_dates: ' . $e->getMessage()); }

// ─── Booking list ─────────────────────────────────────────────────────────────
$bookings = [];
try {
    $bkStmt = $pdo->prepare(
        "SELECT b.booking_id, b.event_date, b.event_type, b.guest_count,
                b.total_amount, b.status,
                u.full_name  AS customer_name,
                p.name       AS package_name
         FROM bookings b
         JOIN users u    ON u.user_id    = b.customer_id
         JOIN packages p ON p.package_id = b.sub_package_id
         WHERE b.is_deleted = 0 AND b.event_date BETWEEN ? AND ?
         ORDER BY b.event_date ASC"
    );
    $bkStmt->execute([$dateFrom, $dateTo]);
    $bookings = $bkStmt->fetchAll();
} catch (PDOException $e) { error_log('booking_report list: ' . $e->getMessage()); }

$pageTitle    = 'Booking Report';
$pageSubtitle = 'Booking summary for the selected period';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $pageTitle ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/reports.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <!-- Print header (hidden on screen) -->
    <div class="print-header">
        <h1><?= SITE_NAME ?> — Booking Report</h1>
        <p>Period: <?= htmlspecialchars(formatDateReadable($dateFrom)) ?> to <?= htmlspecialchars(formatDateReadable($dateTo)) ?></p>
    </div>

    <!-- Report Nav -->
    <nav class="report-nav">
        <a href="<?= BASE_URL ?>/admin/reports/booking_report.php<?= $_SERVER['QUERY_STRING'] ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" class="active"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
        <a href="<?= BASE_URL ?>/admin/reports/income_report.php"><i class="fa-solid fa-sack-dollar"></i> Income</a>
        <a href="<?= BASE_URL ?>/admin/reports/monthly_report.php"><i class="fa-solid fa-table"></i> Monthly</a>
        <a href="<?= BASE_URL ?>/admin/reports/utilization_report.php"><i class="fa-solid fa-gauge-high"></i> Utilization</a>
        <a href="<?= BASE_URL ?>/admin/reports/customer_report.php"><i class="fa-solid fa-users"></i> Customers</a>
        <a href="<?= BASE_URL ?>/admin/reports/export_report.php" class="no-print"><i class="fa-solid fa-file-export"></i> Export</a>
    </nav>

    <!-- Filters -->
    <form id="filterForm" method="GET" action="">
        <div class="report-filters">
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>"/>
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>"/>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="<?= BASE_URL ?>/admin/reports/booking_report.php" class="btn btn-outline">Reset</a>
                <button type="button" id="printBtn" class="btn btn-outline no-print">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>
        </div>
    </form>

    <!-- Stats Cards -->
    <div class="report-stats">
        <div class="rstat-card">
            <div class="rstat-label">Total Bookings</div>
            <div class="rstat-value"><?= $stats['total'] ?></div>
            <div class="rstat-sub">In selected period</div>
        </div>
        <div class="rstat-card success">
            <div class="rstat-label">Completed</div>
            <div class="rstat-value"><?= $stats['completed'] ?></div>
            <div class="rstat-sub">Events held</div>
        </div>
        <div class="rstat-card warning">
            <div class="rstat-label">Pending / Approved</div>
            <div class="rstat-value"><?= $stats['pending'] + $stats['approved'] ?></div>
            <div class="rstat-sub">Awaiting event date</div>
        </div>
        <div class="rstat-card danger">
            <div class="rstat-label">Rejected / Cancelled</div>
            <div class="rstat-value"><?= $stats['rejected'] + $stats['cancelled'] ?></div>
            <div class="rstat-sub">Did not proceed</div>
        </div>
    </div>

    <!-- Charts row -->
    <div class="chart-grid">
        <div class="chart-card">
            <h2><i class="fa-solid fa-chart-pie"></i> Status Breakdown</h2>
            <div class="chart-wrapper">
                <canvas id="statusChart"
                    data-labels='<?= json_encode($chartLabels) ?>'
                    data-values='<?= json_encode($chartValues) ?>'></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h2><i class="fa-solid fa-star"></i> Top Event Types</h2>
            <?php if (empty($eventTypes)): ?>
                <div class="report-empty"><i class="fa-solid fa-database"></i> No data for this period.</div>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Event Type</th><th style="text-align:right;">Count</th></tr></thead>
                    <tbody>
                    <?php foreach ($eventTypes as $et): ?>
                    <tr>
                        <td><?= htmlspecialchars(ucfirst($et['event_type'])) ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--primary);"><?= (int)$et['cnt'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Peak dates -->
    <?php if (!empty($peakDates)): ?>
    <div class="report-table-card">
        <h2><i class="fa-solid fa-fire" style="color:var(--danger);"></i> Peak Booking Dates</h2>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Day</th><th style="text-align:right;">Bookings</th></tr></thead>
                <tbody>
                <?php foreach ($peakDates as $pd): ?>
                <tr>
                    <td><?= htmlspecialchars(formatDateReadable($pd['event_date'])) ?></td>
                    <td style="color:var(--text-muted);"><?= date('l', strtotime($pd['event_date'])) ?></td>
                    <td style="text-align:right;font-weight:700;color:var(--danger);"><?= (int)$pd['cnt'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Full Booking List -->
    <div class="report-table-card">
        <h2>
            <span><i class="fa-solid fa-list" style="color:var(--primary);margin-right:8px;"></i>All Bookings (<?= count($bookings) ?>)</span>
        </h2>
        <?php if (empty($bookings)): ?>
        <div class="report-empty">
            <i class="fa-solid fa-calendar-xmark"></i>
            No bookings found for this period.
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Event Date</th>
                        <th>Event Type</th>
                        <th>Guests</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $bk): ?>
                <tr>
                    <td style="font-weight:600;color:var(--primary);">#<?= $bk['booking_id'] ?></td>
                    <td><?= htmlspecialchars($bk['customer_name']) ?></td>
                    <td><?= htmlspecialchars($bk['package_name']) ?></td>
                    <td><?= htmlspecialchars(formatDateReadable($bk['event_date'])) ?></td>
                    <td><?= htmlspecialchars($bk['event_type'] ?? '—') ?></td>
                    <td><?= (int)$bk['guest_count'] ?></td>
                    <td style="font-weight:600;"><?= formatCurrency((float)$bk['total_amount']) ?></td>
                    <td><span class="badge-status <?= htmlspecialchars($bk['status']) ?>"><?= ucfirst($bk['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/reports.js"></script>
</body>
</html>
