<?php
/**
 * utilization_report.php — Admin: Hall utilization heatmap by day-of-week × time-slot
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

// ─── Time-slot definitions ─────────────────────────────────────────────────────
$timeSlots = [
    'Morning'   => ['06:00','12:00'],
    'Afternoon' => ['12:00','17:00'],
    'Evening'   => ['17:00','21:00'],
    'Night'     => ['21:00','23:59'],
];
$days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

// ─── Heatmap data: bookings per (day_of_week, time_slot) ──────────────────────
// day_of_week: 1=Sunday … 7=Saturday in MySQL's DAYOFWEEK()
$heatmap = [];
foreach ($days as $d) $heatmap[$d] = array_fill_keys(array_keys($timeSlots), 0);

try {
    $hStmt = $pdo->prepare(
        "SELECT DAYOFWEEK(event_date) AS dow,
                start_time,
                COUNT(*) AS cnt
         FROM bookings
         WHERE event_date BETWEEN ? AND ?
           AND status NOT IN ('rejected','cancelled')
           AND is_deleted = 0
         GROUP BY dow, start_time"
    );
    $hStmt->execute([$dateFrom, $dateTo]);
    while ($row = $hStmt->fetch()) {
        $day  = $days[(int)$row['dow'] - 1];
        $time = $row['start_time'];
        foreach ($timeSlots as $slot => [$slotStart, $slotEnd]) {
            if ($time >= $slotStart && $time < $slotEnd) {
                $heatmap[$day][$slot] += (int)$row['cnt'];
                break;
            }
        }
    }
} catch (PDOException $e) { error_log('utilization_report: ' . $e->getMessage()); }

// ─── Max value for colour scaling ─────────────────────────────────────────────
$allVals = [];
foreach ($heatmap as $d => $slots) foreach ($slots as $v) $allVals[] = $v;
$maxVal = max(1, ...$allVals);

function heatClass(int $val, int $max): string {
    if ($val === 0) return 'zero';
    $ratio = $val / $max;
    if ($ratio <= 0.33) return 'low';
    if ($ratio <= 0.66) return 'medium';
    return 'high';
}

// ─── Summary stats ─────────────────────────────────────────────────────────────
$totalInPeriod = 0; $peakDay = ''; $peakSlot = ''; $peakVal = 0;
foreach ($heatmap as $d => $slots) {
    foreach ($slots as $slot => $v) {
        $totalInPeriod += $v;
        if ($v > $peakVal) { $peakVal = $v; $peakDay = $d; $peakSlot = $slot; }
    }
}

// ─── Day totals & slot totals ──────────────────────────────────────────────────
$dayTotals  = [];
$slotTotals = array_fill_keys(array_keys($timeSlots), 0);
foreach ($heatmap as $d => $slots) {
    $dayTotals[$d] = array_sum($slots);
    foreach ($slots as $slot => $v) $slotTotals[$slot] += $v;
}

// ─── Booking rate by month ─────────────────────────────────────────────────────
$monthlyUtil = [];
try {
    $muStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(event_date,'%Y-%m') AS ym, COUNT(*) AS cnt
         FROM bookings
         WHERE event_date BETWEEN ? AND ?
           AND status NOT IN ('rejected','cancelled')
           AND is_deleted = 0
         GROUP BY ym ORDER BY ym"
    );
    $muStmt->execute([$dateFrom, $dateTo]);
    $monthlyUtil = $muStmt->fetchAll();
} catch (PDOException $e) {}

$pageTitle    = 'Hall Utilization';
$pageSubtitle = 'Booking frequency heatmap by day and time slot';
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
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <div class="print-header">
        <h1><?= SITE_NAME ?> — Hall Utilization Report</h1>
        <p>Period: <?= formatDateReadable($dateFrom) ?> to <?= formatDateReadable($dateTo) ?></p>
    </div>

    <!-- Report Nav -->
    <nav class="report-nav">
        <a href="<?= BASE_URL ?>/admin/reports/booking_report.php"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
        <a href="<?= BASE_URL ?>/admin/reports/income_report.php"><i class="fa-solid fa-sack-dollar"></i> Income</a>
        <a href="<?= BASE_URL ?>/admin/reports/monthly_report.php"><i class="fa-solid fa-table"></i> Monthly</a>
        <a href="<?= BASE_URL ?>/admin/reports/utilization_report.php" class="active"><i class="fa-solid fa-gauge-high"></i> Utilization</a>
        <a href="<?= BASE_URL ?>/admin/reports/customer_report.php"><i class="fa-solid fa-users"></i> Customers</a>
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

    <!-- Summary Cards -->
    <div class="report-stats">
        <div class="rstat-card">
            <div class="rstat-label">Total Bookings in Period</div>
            <div class="rstat-value"><?= $totalInPeriod ?></div>
            <div class="rstat-sub"><?= htmlspecialchars($dateFrom) ?> – <?= htmlspecialchars($dateTo) ?></div>
        </div>
        <div class="rstat-card success">
            <div class="rstat-label">Peak Day</div>
            <div class="rstat-value"><?= $peakDay ?: '—' ?></div>
            <div class="rstat-sub"><?= $dayTotals[$peakDay] ?? 0 ?> bookings total</div>
        </div>
        <div class="rstat-card warning">
            <div class="rstat-label">Peak Time Slot</div>
            <div class="rstat-value"><?= $peakSlot ?: '—' ?></div>
            <div class="rstat-sub"><?= $slotTotals[$peakSlot] ?? 0 ?> bookings total</div>
        </div>
        <div class="rstat-card info">
            <div class="rstat-label">Busiest Combination</div>
            <div class="rstat-value" style="font-size:1.1rem;"><?= $peakDay ? htmlspecialchars("$peakDay $peakSlot") : '—' ?></div>
            <div class="rstat-sub"><?= $peakVal ?> bookings</div>
        </div>
    </div>

    <!-- Heatmap -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <h2 style="margin:0;font-size:1rem;font-weight:600;color:var(--text-main);">
                <i class="fa-solid fa-fire" style="color:var(--primary);margin-right:8px;"></i>
                Booking Frequency Heatmap
            </h2>
            <div class="util-legend no-print">
                <span class="util-cell zero legend-cell">0</span>
                <span class="util-cell low legend-cell">Low</span>
                <span class="util-cell medium legend-cell">Mid</span>
                <span class="util-cell high legend-cell">High</span>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="util-table">
                <thead>
                    <tr>
                        <th>Day / Slot</th>
                        <?php foreach (array_keys($timeSlots) as $slot): ?>
                        <th><?= htmlspecialchars($slot) ?><br>
                            <small style="font-weight:400;color:var(--text-muted);">
                                <?= $timeSlots[$slot][0] ?>–<?= $timeSlots[$slot][1] ?>
                            </small>
                        </th>
                        <?php endforeach; ?>
                        <th>Day Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($days as $day): ?>
                <tr>
                    <td style="font-weight:600;"><?= $day ?></td>
                    <?php foreach (array_keys($timeSlots) as $slot):
                        $v = $heatmap[$day][$slot];
                        $cls = heatClass($v, $maxVal);
                    ?>
                    <td>
                        <div class="util-cell <?= $cls ?>">
                            <?= $v > 0 ? $v : '' ?>
                        </div>
                    </td>
                    <?php endforeach; ?>
                    <td style="font-weight:700;text-align:center;"><?= $dayTotals[$day] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--primary-light);font-weight:700;">
                        <td>Slot Total</td>
                        <?php foreach (array_keys($timeSlots) as $slot): ?>
                        <td style="text-align:center;"><?= $slotTotals[$slot] ?></td>
                        <?php endforeach; ?>
                        <td style="text-align:center;"><?= $totalInPeriod ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Monthly util breakdown -->
    <?php if (!empty($monthlyUtil)): ?>
    <div class="report-table-card">
        <h2><i class="fa-solid fa-calendar-days" style="color:var(--primary);margin-right:8px;"></i> Monthly Booking Counts</h2>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th style="text-align:center;">Bookings</th>
                        <th>Utilization Bar</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $maxMonthly = max(1, ...array_column($monthlyUtil, 'cnt'));
                foreach ($monthlyUtil as $mu):
                    $pct = round($mu['cnt'] / $maxMonthly * 100);
                    $mon = date('F Y', strtotime($mu['ym'] . '-01'));
                ?>
                <tr>
                    <td style="font-weight:500;"><?= htmlspecialchars($mon) ?></td>
                    <td style="text-align:center;font-weight:700;"><?= (int)$mu['cnt'] ?></td>
                    <td>
                        <div style="background:#eaedf7;border-radius:6px;height:16px;position:relative;overflow:hidden;">
                            <div style="width:<?= $pct ?>%;background:var(--primary);height:100%;border-radius:6px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="report-empty"><i class="fa-solid fa-calendar-xmark"></i><br>No data for selected period.</div>
    <?php endif; ?>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/reports.js"></script>
</body>
</html>
