<?php
/**
 * customer_report.php — Admin: Customer activity statistics
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Date filter ───────────────────────────────────────────────────────────────
$dateFrom = sanitizeInput($_GET['date_from'] ?? date('Y-01-01'));
$dateTo   = sanitizeInput($_GET['date_to']   ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');

// ─── Summary stats ─────────────────────────────────────────────────────────────
$totalCustomers = 0; $newThisMonth = 0; $active = 0; $blocked = 0;
try {
    $csStmt = $pdo->prepare(
        "SELECT
             COUNT(*)                                           AS total,
             SUM(YEAR(created_at) = YEAR(CURDATE())
                 AND MONTH(created_at) = MONTH(CURDATE()))    AS new_this_month,
             SUM(status = 'active')                            AS active,
             SUM(status = 'blocked')                           AS blocked
         FROM users WHERE role = 'customer'"
    );
    $csStmt->execute();
    $csRow = $csStmt->fetch();
    $totalCustomers = (int)$csRow['total'];
    $newThisMonth   = (int)$csRow['new_this_month'];
    $active         = (int)$csRow['active'];
    $blocked        = (int)$csRow['blocked'];
} catch (PDOException $e) { error_log('customer_report stats: ' . $e->getMessage()); }

// ─── New customer registrations per month (last 12 months through dateTo) ─────
$newCustMonths = [];
try {
    $ncStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at,'%b %Y') AS label,
                DATE_FORMAT(created_at,'%Y-%m') AS ym,
                COUNT(*) AS cnt
         FROM users
         WHERE role = 'customer'
           AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY ym
         ORDER BY ym ASC"
    );
    $ncStmt->execute([$dateFrom, $dateTo]);
    $newCustMonths = $ncStmt->fetchAll();
} catch (PDOException $e) { error_log('customer_report new_cust: ' . $e->getMessage()); }

$chartLabels = array_column($newCustMonths, 'label');
$chartValues = array_map('intval', array_column($newCustMonths, 'cnt'));

// ─── Top customers by spending ────────────────────────────────────────────────
$topCustomers = [];
try {
    $tcStmt = $pdo->prepare(
        "SELECT u.user_id, u.full_name, u.email, u.phone,
                COUNT(DISTINCT b.booking_id)  AS booking_count,
                COALESCE(SUM(p.amount), 0)    AS total_paid
         FROM users u
         JOIN bookings b  ON b.customer_id = u.user_id AND b.is_deleted = 0
         LEFT JOIN payments p ON p.booking_id = b.booking_id AND p.status = 'paid'
         WHERE u.role = 'customer'
           AND b.event_date BETWEEN ? AND ?
         GROUP BY u.user_id
         ORDER BY total_paid DESC
         LIMIT 10"
    );
    $tcStmt->execute([$dateFrom, $dateTo]);
    $topCustomers = $tcStmt->fetchAll();
} catch (PDOException $e) { error_log('customer_report top: ' . $e->getMessage()); }

// ─── Repeat bookers (> 1 booking) ─────────────────────────────────────────────
$repeatBookers = 0; $oneTimeBookers = 0;
try {
    $rbStmt = $pdo->prepare(
        "SELECT booking_count, COUNT(*) AS cust_count
         FROM (
             SELECT customer_id, COUNT(*) AS booking_count
             FROM bookings
             WHERE is_deleted = 0 AND event_date BETWEEN ? AND ?
             GROUP BY customer_id
         ) t
         GROUP BY booking_count > 1"
    );
    $rbStmt->execute([$dateFrom, $dateTo]);
    foreach ($rbStmt->fetchAll() as $rb) {
        // 'booking_count > 1' is 0 or 1
        if ($rb['booking_count > 1']) $repeatBookers  += (int)$rb['cust_count'];
        else                          $oneTimeBookers += (int)$rb['cust_count'];
    }
} catch (PDOException $e) { error_log('customer_report repeat: ' . $e->getMessage()); }

// ─── Customer list with booking counts in period ──────────────────────────────
$customerList = [];
try {
    $clStmt = $pdo->prepare(
        "SELECT u.user_id, u.full_name, u.email, u.phone, u.status,
                u.created_at,
                COUNT(DISTINCT b.booking_id)                      AS bookings_in_period,
                COALESCE(SUM(CASE WHEN p.status='paid' THEN p.amount END),0) AS paid_amount
         FROM users u
         LEFT JOIN bookings b  ON b.customer_id = u.user_id
                               AND b.is_deleted = 0
                               AND b.event_date BETWEEN ? AND ?
         LEFT JOIN payments p  ON p.booking_id = b.booking_id
         WHERE u.role = 'customer'
         GROUP BY u.user_id
         ORDER BY bookings_in_period DESC, u.full_name ASC"
    );
    $clStmt->execute([$dateFrom, $dateTo]);
    $customerList = $clStmt->fetchAll();
} catch (PDOException $e) { error_log('customer_report list: ' . $e->getMessage()); }

$pageTitle    = 'Customer Report';
$pageSubtitle = 'Customer registrations, activity, and spending statistics';
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

    <div class="print-header">
        <h1><?= SITE_NAME ?> — Customer Report</h1>
        <p>Period: <?= formatDateReadable($dateFrom) ?> to <?= formatDateReadable($dateTo) ?></p>
    </div>

    <!-- Report Nav -->
    <nav class="report-nav">
        <a href="<?= BASE_URL ?>/admin/reports/booking_report.php"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
        <a href="<?= BASE_URL ?>/admin/reports/income_report.php"><i class="fa-solid fa-sack-dollar"></i> Income</a>
        <a href="<?= BASE_URL ?>/admin/reports/monthly_report.php"><i class="fa-solid fa-table"></i> Monthly</a>
        <a href="<?= BASE_URL ?>/admin/reports/customer_report.php" class="active"><i class="fa-solid fa-users"></i> Customers</a>
    </nav>

    <!-- Filters -->
    <form id="filterForm" method="GET" action="">
        <div class="report-filters">
            <div class="form-group">
                <label>From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div style="display:flex;gap:10px;align-items:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
                <button type="button" id="printBtn" class="btn btn-outline no-print">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>
        </div>
    </form>

    <!-- Summary Stats -->
    <div class="report-stats">
        <div class="rstat-card">
            <div class="rstat-label">Total Customers</div>
            <div class="rstat-value"><?= $totalCustomers ?></div>
            <div class="rstat-sub"><?= $active ?> active · <?= $blocked ?> blocked</div>
        </div>
        <div class="rstat-card success">
            <div class="rstat-label">New This Month</div>
            <div class="rstat-value"><?= $newThisMonth ?></div>
        </div>
        <div class="rstat-card info">
            <div class="rstat-label">Repeat Bookers</div>
            <div class="rstat-value"><?= $repeatBookers ?></div>
            <div class="rstat-sub">in selected period</div>
        </div>
        <div class="rstat-card warning">
            <div class="rstat-label">One-Time Bookers</div>
            <div class="rstat-value"><?= $oneTimeBookers ?></div>
            <div class="rstat-sub">in selected period</div>
        </div>
    </div>

    <?php if (!empty($newCustMonths)): ?>
    <!-- New Registrations Chart -->
    <div class="chart-card">
        <h2><i class="fa-solid fa-user-plus"></i> New Customer Registrations</h2>
        <div class="chart-wrapper">
            <canvas id="custBar"
                data-labels='<?= htmlspecialchars(json_encode($chartLabels), ENT_QUOTES) ?>'
                data-values='<?= htmlspecialchars(json_encode($chartValues), ENT_QUOTES) ?>'></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Customers -->
    <?php if (!empty($topCustomers)): ?>
    <div class="report-table-card" style="margin-bottom:24px;">
        <h2><i class="fa-solid fa-trophy" style="color:var(--warning);margin-right:8px;"></i> Top Customers by Spending</h2>
        <?php foreach ($topCustomers as $i => $tc): ?>
        <div class="top-customer-row">
            <div class="top-customer-rank"><?= $i + 1 ?></div>
            <div class="top-customer-info">
                <div class="top-customer-name"><?= htmlspecialchars($tc['full_name']) ?></div>
                <div class="top-customer-meta"><?= htmlspecialchars($tc['email']) ?> · <?= htmlspecialchars($tc['phone'] ?? '—') ?></div>
            </div>
            <div class="top-customer-stat">
                <span class="top-stat-value"><?= formatCurrency($tc['total_paid']) ?></span>
                <span class="top-stat-label"><?= $tc['booking_count'] ?> booking<?= $tc['booking_count'] != 1 ? 's' : '' ?></span>
            </div>
            <a href="<?= BASE_URL ?>/admin/customers/customer_details.php?id=<?= $tc['user_id'] ?>" class="btn btn-sm btn-outline no-print">View</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Full Customer List -->
    <div class="report-table-card">
        <h2><i class="fa-solid fa-users" style="color:var(--primary);margin-right:8px;"></i> All Customers</h2>
        <?php if (empty($customerList)): ?>
        <div class="report-empty"><i class="fa-solid fa-users-slash"></i><br>No customers found.</div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Registered</th>
                        <th style="text-align:center;">Bookings in Period</th>
                        <th style="text-align:right;">Amount Paid</th>
                        <th style="text-align:center;">Status</th>
                        <th class="no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($customerList as $c): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($c['full_name']) ?></td>
                    <td style="color:var(--text-muted);font-size:.85rem;">
                        <?= htmlspecialchars($c['email']) ?><br>
                        <?= htmlspecialchars($c['phone'] ?? '—') ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:.85rem;">
                        <?= formatDateReadable($c['created_at']) ?>
                    </td>
                    <td style="text-align:center;font-weight:700;">
                        <?= (int)$c['bookings_in_period'] ?>
                    </td>
                    <td style="text-align:right;font-weight:600;">
                        <?= $c['paid_amount'] > 0 ? formatCurrency($c['paid_amount']) : '<span style="color:var(--text-muted);">—</span>' ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status <?= $c['status'] === 'active' ? 'approved' : 'rejected' ?>">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </td>
                    <td class="no-print">
                        <a href="<?= BASE_URL ?>/admin/customers/customer_details.php?id=<?= $c['user_id'] ?>"
                           class="btn btn-sm btn-outline">View</a>
                    </td>
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
