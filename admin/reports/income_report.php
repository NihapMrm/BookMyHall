<?php
/**
 * income_report.php — Admin: Revenue breakdown with Chart.js trend line
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Filters ──────────────────────────────────────────────────────────────────
$dateFrom = sanitizeInput($_GET['from'] ?? date('Y-m-01'));
$dateTo   = sanitizeInput($_GET['to']   ?? date('Y-m-d'));

// ─── Summary Stats ─────────────────────────────────────────────────────────────
$summary = ['total' => 0, 'advance' => 0, 'balance' => 0, 'full' => 0, 'refunded' => 0];
try {
    $row = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END), 0) AS total,
            COALESCE(SUM(CASE WHEN status='paid' AND payment_type='advance' THEN amount ELSE 0 END), 0) AS advance,
            COALESCE(SUM(CASE WHEN status='paid' AND payment_type='balance' THEN amount ELSE 0 END), 0) AS balance,
            COALESCE(SUM(CASE WHEN status='paid' AND payment_type='full'    THEN amount ELSE 0 END), 0) AS full,
            COALESCE(SUM(CASE WHEN status='refunded' THEN amount ELSE 0 END), 0) AS refunded
         FROM payments
         WHERE DATE(created_at) BETWEEN ? AND ?"
    );
    $row->execute([$dateFrom, $dateTo]);
    $r = $row->fetch();
    if ($r) $summary = $r;
} catch (PDOException $e) { error_log('income_report stats: ' . $e->getMessage()); }

// ─── Revenue by day (trend chart) ─────────────────────────────────────────────
$trendLabels = [];
$trendValues = [];
try {
    $trStmt = $pdo->prepare(
        "SELECT DATE(created_at) AS d, SUM(amount) AS total
         FROM payments
         WHERE status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY d ASC"
    );
    $trStmt->execute([$dateFrom, $dateTo]);
    foreach ($trStmt->fetchAll() as $r) {
        $trendLabels[] = date('d M', strtotime($r['d']));
        $trendValues[] = (float) $r['total'];
    }
} catch (PDOException $e) { error_log('income_report trend: ' . $e->getMessage()); }

// ─── Revenue by method (doughnut) ─────────────────────────────────────────────
$methodLabels = [];
$methodValues = [];
try {
    $mStmt = $pdo->prepare(
        "SELECT method, SUM(amount) AS total
         FROM payments
         WHERE status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY method ORDER BY total DESC"
    );
    $mStmt->execute([$dateFrom, $dateTo]);
    foreach ($mStmt->fetchAll() as $r) {
        $methodLabels[] = ucwords(str_replace('_', ' ', $r['method']));
        $methodValues[] = (float) $r['total'];
    }
} catch (PDOException $e) { error_log('income_report method: ' . $e->getMessage()); }

// ─── Pending payments outstanding ─────────────────────────────────────────────
$pendingAmount = 0;
try {
    $pendRow = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status='pending' AND DATE(created_at) BETWEEN ? AND ?");
    $pendRow->execute([$dateFrom, $dateTo]);
    $pendingAmount = (float) $pendRow->fetchColumn();
} catch (PDOException $e) { error_log('income_report pending: ' . $e->getMessage()); }

// ─── Payment records list ─────────────────────────────────────────────────────
$paymentsList = [];
try {
    $plStmt = $pdo->prepare(
        "SELECT p.payment_id, p.payment_type, p.amount, p.method, p.status, p.reference, p.created_at,
                b.booking_id AS bk_id, b.event_date,
                u.full_name AS customer_name
         FROM payments p
         JOIN bookings b ON b.booking_id = p.booking_id
         JOIN users u    ON u.user_id    = b.customer_id
         WHERE DATE(p.created_at) BETWEEN ? AND ?
         ORDER BY p.created_at DESC"
    );
    $plStmt->execute([$dateFrom, $dateTo]);
    $paymentsList = $plStmt->fetchAll();
} catch (PDOException $e) { error_log('income_report list: ' . $e->getMessage()); }

$pageTitle    = 'Income Report';
$pageSubtitle = 'Revenue breakdown and trend analysis';
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
        <h1><?= SITE_NAME ?> — Income Report</h1>
        <p>Period: <?= htmlspecialchars(formatDateReadable($dateFrom)) ?> to <?= htmlspecialchars(formatDateReadable($dateTo)) ?></p>
    </div>

    <!-- Report Nav -->
    <nav class="report-nav">
        <a href="<?= BASE_URL ?>/admin/reports/booking_report.php"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
        <a href="<?= BASE_URL ?>/admin/reports/income_report.php<?= $_SERVER['QUERY_STRING'] ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" class="active"><i class="fa-solid fa-sack-dollar"></i> Income</a>
        <a href="<?= BASE_URL ?>/admin/reports/monthly_report.php"><i class="fa-solid fa-table"></i> Monthly</a>
        <a href="<?= BASE_URL ?>/admin/reports/customer_report.php"><i class="fa-solid fa-users"></i> Customers</a>
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
            <div style="display:flex;gap:10px;align-items:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="<?= BASE_URL ?>/admin/reports/income_report.php" class="btn btn-outline">Reset</a>
                <button type="button" id="printBtn" class="btn btn-outline no-print">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>
        </div>
    </form>

    <!-- Stats Cards -->
    <div class="report-stats">
        <div class="rstat-card">
            <div class="rstat-label">Total Revenue</div>
            <div class="rstat-value"><?= formatCurrency((float)$summary['total']) ?></div>
            <div class="rstat-sub">Paid in selected period</div>
        </div>
        <div class="rstat-card info">
            <div class="rstat-label">Advance Collected</div>
            <div class="rstat-value"><?= formatCurrency((float)$summary['advance']) ?></div>
        </div>
        <div class="rstat-card success">
            <div class="rstat-label">Balance Collected</div>
            <div class="rstat-value"><?= formatCurrency((float)$summary['balance']) ?></div>
        </div>
        <div class="rstat-card danger">
            <div class="rstat-label">Refunded</div>
            <div class="rstat-value"><?= formatCurrency((float)$summary['refunded']) ?></div>
        </div>
        <div class="rstat-card warning">
            <div class="rstat-label">Pending Outstanding</div>
            <div class="rstat-value"><?= formatCurrency($pendingAmount) ?></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="chart-card">
        <h2><i class="fa-solid fa-chart-line"></i> Revenue Trend</h2>
        <?php if (empty($trendLabels)): ?>
        <div class="report-empty"><i class="fa-solid fa-chart-line"></i> No paid payment data for this period.</div>
        <?php else: ?>
        <div class="chart-wrapper">
            <canvas id="revenueChart"
                data-labels='<?= json_encode($trendLabels) ?>'
                data-values='<?= json_encode($trendValues) ?>'></canvas>
        </div>
        <?php endif; ?>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h2><i class="fa-solid fa-chart-pie"></i> Revenue by Payment Method</h2>
            <?php if (empty($methodLabels)): ?>
            <div class="report-empty"><i class="fa-solid fa-chart-pie"></i> No data available.</div>
            <?php else: ?>
            <div class="chart-wrapper">
                <canvas id="methodChart"
                    data-labels='<?= json_encode($methodLabels) ?>'
                    data-values='<?= json_encode($methodValues) ?>'></canvas>
            </div>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <h2><i class="fa-solid fa-layer-group"></i> Payment Type Breakdown</h2>
            <div style="display:flex;flex-direction:column;gap:14px;margin-top:8px;">
                <?php
                $typeBreakdown = [
                    ['Advance', $summary['advance'], 'var(--primary)'],
                    ['Balance', $summary['balance'], 'var(--info)'],
                    ['Full',    $summary['full'],    'var(--success)'],
                ];
                $totalPaid = max((float)$summary['total'], 1);
                foreach ($typeBreakdown as [$label, $val, $color]):
                    $pct = round((float)$val / $totalPaid * 100, 1);
                ?>
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-size:.85rem;font-weight:500;"><?= $label ?></span>
                        <span style="font-size:.85rem;color:var(--text-muted);"><?= formatCurrency((float)$val) ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="height:8px;background:#eaedf7;border-radius:4px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:4px;transition:width .4s;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Payment List -->
    <div class="report-table-card">
        <h2>
            <span><i class="fa-solid fa-list" style="color:var(--primary);margin-right:8px;"></i>Payment Records (<?= count($paymentsList) ?>)</span>
        </h2>
        <?php if (empty($paymentsList)): ?>
        <div class="report-empty"><i class="fa-solid fa-receipt"></i> No payments found for this period.</div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th><th>Customer</th><th>Booking</th><th>Type</th>
                        <th>Method</th><th>Reference</th><th>Amount</th><th>Status</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($paymentsList as $p): ?>
                <tr>
                    <td style="font-weight:600;color:var(--primary);">#<?= $p['payment_id'] ?></td>
                    <td><?= htmlspecialchars($p['customer_name']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $p['bk_id'] ?>"
                           style="color:var(--primary);">#<?= $p['bk_id'] ?></a>
                    </td>
                    <td>
                        <?php
                        $tbadge = ['advance'=>'var(--primary)','balance'=>'var(--info)','full'=>'var(--success)'];
                        $tc = $tbadge[$p['payment_type']] ?? 'var(--text-muted)';
                        ?>
                        <span style="font-weight:600;color:<?= $tc ?>;"><?= ucfirst($p['payment_type']) ?></span>
                    </td>
                    <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$p['method']))) ?></td>
                    <td style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($p['reference'] ?? '—') ?></td>
                    <td style="font-weight:600;"><?= formatCurrency((float)$p['amount']) ?></td>
                    <td>
                        <?php
                        $sbadge = ['paid'=>'#1a8a4a','pending'=>'#a06800','refunded'=>'var(--danger)','failed'=>'var(--text-muted)'];
                        $sc = $sbadge[$p['status']] ?? 'var(--text-muted)';
                        ?>
                        <span style="font-weight:600;color:<?= $sc ?>;"><?= ucfirst($p['status']) ?></span>
                    </td>
                    <td style="font-size:.82rem;color:var(--text-muted);"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
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
