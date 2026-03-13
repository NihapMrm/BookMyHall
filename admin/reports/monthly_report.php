<?php
/**
 * monthly_report.php — Admin: Monthly performance breakdown table + bar chart
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Year filter ───────────────────────────────────────────────────────────────
$year = (int)sanitizeInput($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

// ─── Monthly bookings data ─────────────────────────────────────────────────────
$monthlyBookings = [];
try {
    $mbStmt = $pdo->prepare(
        "SELECT
             MONTH(event_date)                   AS month_num,
             COUNT(*)                             AS total_bookings,
             SUM(status = 'completed')            AS completed,
             SUM(status IN ('cancelled','rejected')) AS cancelled,
             SUM(status = 'pending')              AS pending,
             SUM(status = 'approved')             AS approved,
             COALESCE(SUM(total_amount), 0)       AS booking_value
         FROM bookings
         WHERE YEAR(event_date) = ? AND is_deleted = 0
         GROUP BY MONTH(event_date)
         ORDER BY month_num ASC"
    );
    $mbStmt->execute([$year]);
    $rawRows = $mbStmt->fetchAll();
    // Key by month number
    $byMonth = [];
    foreach ($rawRows as $r) $byMonth[(int)$r['month_num']] = $r;
    for ($m = 1; $m <= 12; $m++) {
        $monthlyBookings[$m] = $byMonth[$m] ?? [
            'month_num'=>$m,'total_bookings'=>0,'completed'=>0,'cancelled'=>0,
            'pending'=>0,'approved'=>0,'booking_value'=>0
        ];
    }
} catch (PDOException $e) { error_log('monthly_report bookings: ' . $e->getMessage()); }

// ─── Monthly revenue data ──────────────────────────────────────────────────────
$monthlyRevenue = array_fill(1, 12, 0.0);
try {
    $mrStmt = $pdo->prepare(
        "SELECT MONTH(created_at) AS month_num, SUM(amount) AS revenue
         FROM payments
         WHERE status = 'paid' AND YEAR(created_at) = ?
         GROUP BY MONTH(created_at)"
    );
    $mrStmt->execute([$year]);
    foreach ($mrStmt->fetchAll() as $r) {
        $monthlyRevenue[(int)$r['month_num']] = (float)$r['revenue'];
    }
} catch (PDOException $e) { error_log('monthly_report revenue: ' . $e->getMessage()); }

// ─── Build chart data ──────────────────────────────────────────────────────────
$monthNames    = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chartLabels   = $monthNames;
$chartBookings = [];
$chartRevenue  = [];
for ($m = 1; $m <= 12; $m++) {
    $chartBookings[] = (int)$monthlyBookings[$m]['total_bookings'];
    $chartRevenue[]  = $monthlyRevenue[$m];
}

// ─── Growth calculation vs previous year ──────────────────────────────────────
$prevYearTotal = 0;
$currYearTotal = array_sum($chartBookings);
try {
    $prevRow = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE YEAR(event_date) = ? AND is_deleted = 0");
    $prevRow->execute([$year - 1]);
    $prevYearTotal = (int)$prevRow->fetchColumn();
} catch (PDOException $e) {}
$growthPct = $prevYearTotal > 0 ? round(($currYearTotal - $prevYearTotal) / $prevYearTotal * 100, 1) : null;

// ─── Available years ────────────────────────────────────────────────────────────
$availableYears = [];
try {
    $yrStmt = $pdo->query("SELECT DISTINCT YEAR(event_date) AS y FROM bookings WHERE is_deleted=0 ORDER BY y DESC");
    $availableYears = array_column($yrStmt->fetchAll(), 'y');
} catch (PDOException $e) {}
if (empty($availableYears)) $availableYears = [(int)date('Y')];

$pageTitle    = 'Monthly Performance';
$pageSubtitle = 'Month-by-month booking and revenue statistics';
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
        <h1><?= SITE_NAME ?> — Monthly Performance Report</h1>
        <p>Year: <?= $year ?></p>
    </div>

    <!-- Report Nav -->
    <nav class="report-nav">
        <a href="<?= BASE_URL ?>/admin/reports/booking_report.php"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
        <a href="<?= BASE_URL ?>/admin/reports/income_report.php"><i class="fa-solid fa-sack-dollar"></i> Income</a>
        <a href="<?= BASE_URL ?>/admin/reports/monthly_report.php?year=<?= $year ?>" class="active"><i class="fa-solid fa-table"></i> Monthly</a>
        <a href="<?= BASE_URL ?>/admin/reports/utilization_report.php"><i class="fa-solid fa-gauge-high"></i> Utilization</a>
        <a href="<?= BASE_URL ?>/admin/reports/customer_report.php"><i class="fa-solid fa-users"></i> Customers</a>
        <a href="<?= BASE_URL ?>/admin/reports/export_report.php" class="no-print"><i class="fa-solid fa-file-export"></i> Export</a>
    </nav>

    <!-- Year Filter -->
    <form id="filterForm" method="GET" action="">
        <div class="report-filters">
            <div class="form-group">
                <label>Year</label>
                <select name="year" class="form-control">
                    <?php foreach ($availableYears as $y): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
                <button type="button" id="printBtn" class="btn btn-outline no-print">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="report-stats">
        <div class="rstat-card">
            <div class="rstat-label">Year <?= $year ?> — Total Bookings</div>
            <div class="rstat-value"><?= $currYearTotal ?></div>
            <?php if ($growthPct !== null): ?>
            <div class="rstat-sub">
                <span class="growth-badge <?= $growthPct >= 0 ? 'up' : 'down' ?>">
                    <i class="fa-solid fa-arrow-<?= $growthPct >= 0 ? 'up' : 'down' ?>"></i>
                    <?= abs($growthPct) ?>% vs <?= $year - 1 ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <div class="rstat-card success">
            <div class="rstat-label">Total Revenue <?= $year ?></div>
            <div class="rstat-value"><?= formatCurrency(array_sum($monthlyRevenue)) ?></div>
        </div>
        <div class="rstat-card info">
            <div class="rstat-label">Avg Bookings / Month</div>
            <div class="rstat-value"><?= round($currYearTotal / 12, 1) ?></div>
        </div>
        <div class="rstat-card warning">
            <div class="rstat-label">Best Month</div>
            <?php
            $bestMonth = array_search(max($chartBookings), $chartBookings);
            ?>
            <div class="rstat-value"><?= $monthNames[$bestMonth] ?></div>
            <div class="rstat-sub"><?= $chartBookings[$bestMonth] ?> bookings</div>
        </div>
    </div>

    <!-- Chart -->
    <div class="chart-card">
        <h2><i class="fa-solid fa-chart-bar"></i> Bookings & Revenue — <?= $year ?></h2>
        <div class="chart-wrapper">
            <canvas id="monthlyBar"
                data-labels='<?= json_encode($chartLabels) ?>'
                data-bookings='<?= json_encode($chartBookings) ?>'
                data-revenue='<?= json_encode(array_values($monthlyRevenue)) ?>'></canvas>
        </div>
    </div>

    <!-- Monthly Table -->
    <div class="report-table-card">
        <h2><i class="fa-solid fa-table" style="color:var(--primary);margin-right:8px;"></i> Monthly Breakdown — <?= $year ?></h2>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Completed</th>
                        <th style="text-align:center;">Pending/Approved</th>
                        <th style="text-align:center;">Cancelled/Rejected</th>
                        <th style="text-align:right;">Revenue Collected</th>
                        <th style="text-align:right;">Avg Value</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $totals = ['total'=>0,'completed'=>0,'pending_approved'=>0,'cancelled'=>0,'revenue'=>0.0];
                for ($m = 1; $m <= 12; $m++):
                    $row = $monthlyBookings[$m];
                    $rev = $monthlyRevenue[$m];
                    $pa  = (int)$row['pending'] + (int)$row['approved'];
                    $avg = (int)$row['total_bookings'] > 0
                           ? formatCurrency((float)$row['booking_value'] / (int)$row['total_bookings'])
                           : '—';
                    $totals['total']           += (int)$row['total_bookings'];
                    $totals['completed']        += (int)$row['completed'];
                    $totals['pending_approved'] += $pa;
                    $totals['cancelled']        += (int)$row['cancelled'];
                    $totals['revenue']          += $rev;
                    $isCurrent = ($m == (int)date('m') && $year == (int)date('Y'));
                ?>
                <tr <?= $isCurrent ? 'style="background:var(--primary-light);"' : '' ?>>
                    <td style="font-weight:<?= $isCurrent ? '700' : '500' ?>;">
                        <?= date('F', mktime(0,0,0,$m,1)) ?>
                        <?php if ($isCurrent): ?><span style="font-size:.72rem;color:var(--primary);font-weight:600;margin-left:6px;">← Current</span><?php endif; ?>
                    </td>
                    <td style="text-align:center;font-weight:700;"><?= (int)$row['total_bookings'] ?></td>
                    <td style="text-align:center;color:var(--success);font-weight:600;"><?= (int)$row['completed'] ?></td>
                    <td style="text-align:center;color:var(--warning);font-weight:600;"><?= $pa ?></td>
                    <td style="text-align:center;color:var(--danger);font-weight:600;"><?= (int)$row['cancelled'] ?></td>
                    <td style="text-align:right;font-weight:600;"><?= $rev > 0 ? formatCurrency($rev) : '<span style="color:var(--text-muted);">—</span>' ?></td>
                    <td style="text-align:right;color:var(--text-muted);font-size:.85rem;"><?= $avg ?></td>
                </tr>
                <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--primary-light);font-weight:700;">
                        <td>TOTAL</td>
                        <td style="text-align:center;"><?= $totals['total'] ?></td>
                        <td style="text-align:center;color:var(--success);"><?= $totals['completed'] ?></td>
                        <td style="text-align:center;color:var(--warning);"><?= $totals['pending_approved'] ?></td>
                        <td style="text-align:center;color:var(--danger);"><?= $totals['cancelled'] ?></td>
                        <td style="text-align:right;"><?= formatCurrency($totals['revenue']) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/reports.js"></script>
</body>
</html>
