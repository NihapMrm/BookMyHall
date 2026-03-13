<?php
/**
 * export_report.php — Admin: Report export / print hub
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// Quick stats for reference on the export page
$quickStats = ['total_bookings'=>0,'total_revenue'=>0,'customers'=>0];
try {
    $sRow = $pdo->query(
        "SELECT
             (SELECT COUNT(*) FROM bookings WHERE is_deleted=0)  AS total_bookings,
             (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid') AS total_revenue,
             (SELECT COUNT(*) FROM users WHERE role='customer')  AS customers"
    )->fetch();
    $quickStats = $sRow;
} catch (PDOException $e) { error_log('export_report stats: ' . $e->getMessage()); }

$pageTitle    = 'Export Reports';
$pageSubtitle = 'Open any report for print-to-PDF saving';
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

    <!-- Report Nav -->
    <nav class="report-nav">
        <a href="<?= BASE_URL ?>/admin/reports/booking_report.php"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
        <a href="<?= BASE_URL ?>/admin/reports/income_report.php"><i class="fa-solid fa-sack-dollar"></i> Income</a>
        <a href="<?= BASE_URL ?>/admin/reports/monthly_report.php"><i class="fa-solid fa-table"></i> Monthly</a>
        <a href="<?= BASE_URL ?>/admin/reports/utilization_report.php"><i class="fa-solid fa-gauge-high"></i> Utilization</a>
        <a href="<?= BASE_URL ?>/admin/reports/customer_report.php"><i class="fa-solid fa-users"></i> Customers</a>
        <a href="<?= BASE_URL ?>/admin/reports/export_report.php" class="active"><i class="fa-solid fa-file-export"></i> Export</a>
    </nav>

    <!-- Info -->
    <div class="alert alert-info" style="margin-bottom:24px;">
        <i class="fa-solid fa-circle-info"></i>
        To save any report as a PDF: click <strong>Open for Print</strong>, then in your browser press <kbd>Ctrl+P</kbd> (or <kbd>Cmd+P</kbd> on Mac) and choose <strong>Save as PDF</strong>.
    </div>

    <!-- Quick Stats -->
    <div class="report-stats" style="margin-bottom:32px;">
        <div class="rstat-card">
            <div class="rstat-label">All-Time Bookings</div>
            <div class="rstat-value"><?= number_format($quickStats['total_bookings']) ?></div>
        </div>
        <div class="rstat-card success">
            <div class="rstat-label">Total Revenue</div>
            <div class="rstat-value"><?= formatCurrency($quickStats['total_revenue']) ?></div>
        </div>
        <div class="rstat-card info">
            <div class="rstat-label">Registered Customers</div>
            <div class="rstat-value"><?= number_format($quickStats['customers']) ?></div>
        </div>
    </div>

    <!-- Date range for export links -->
    <div class="card" style="margin-bottom:32px;">
        <div class="card-header" style="border-bottom:1px solid #eaedf7;padding-bottom:12px;margin-bottom:16px;">
            <h2 style="margin:0;font-size:1rem;font-weight:700;color:var(--text-main);">
                <i class="fa-solid fa-calendar-range" style="color:var(--primary);margin-right:8px;"></i>
                Set Date Range for Export
            </h2>
        </div>
        <form id="exportDateForm" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
            <div class="form-group" style="min-width:180px;">
                <label>From</label>
                <input type="date" id="expFrom" name="date_from" class="form-control" value="<?= date('Y-01-01') ?>">
            </div>
            <div class="form-group" style="min-width:180px;">
                <label>To</label>
                <input type="date" id="expTo" name="date_to" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </form>
    </div>

    <!-- Export Cards -->
    <div class="export-options">

        <div class="export-card">
            <div class="export-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <h3>Booking Report</h3>
            <p>Complete list of bookings with status breakdown, event types, and peak booking analysis.</p>
            <button type="button" class="btn btn-primary btn-full open-report"
                    data-url="<?= BASE_URL ?>/admin/reports/booking_report.php">
                <i class="fa-solid fa-print"></i> Open for Print
            </button>
        </div>

        <div class="export-card">
            <div class="export-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <h3>Income Report</h3>
            <p>Revenue breakdown by payment method and date, with trend charts and all payment records.</p>
            <button type="button" class="btn btn-primary btn-full open-report"
                    data-url="<?= BASE_URL ?>/admin/reports/income_report.php">
                <i class="fa-solid fa-print"></i> Open for Print
            </button>
        </div>

        <div class="export-card">
            <div class="export-icon"><i class="fa-solid fa-table"></i></div>
            <h3>Monthly Report</h3>
            <p>Month-by-month performance table for a selected year — bookings, revenue, and growth trends.</p>
            <button type="button" class="btn btn-primary btn-full" id="openMonthly">
                <i class="fa-solid fa-print"></i> Open for Print
            </button>
        </div>

        <div class="export-card">
            <div class="export-icon"><i class="fa-solid fa-gauge-high"></i></div>
            <h3>Utilization Report</h3>
            <p>Hall booking frequency heatmap by day of week and time slot to identify peak periods.</p>
            <button type="button" class="btn btn-primary btn-full open-report"
                    data-url="<?= BASE_URL ?>/admin/reports/utilization_report.php">
                <i class="fa-solid fa-print"></i> Open for Print
            </button>
        </div>

        <div class="export-card">
            <div class="export-icon"><i class="fa-solid fa-users"></i></div>
            <h3>Customer Report</h3>
            <p>Customer activity, top spenders, repeat vs one-time bookers, and registration trends.</p>
            <button type="button" class="btn btn-primary btn-full open-report"
                    data-url="<?= BASE_URL ?>/admin/reports/customer_report.php">
                <i class="fa-solid fa-print"></i> Open for Print
            </button>
        </div>

        <div class="export-card">
            <div class="export-icon"><i class="fa-solid fa-credit-card"></i></div>
            <h3>Payments List</h3>
            <p>Full payment transaction log with status, method, and amounts for the selected period.</p>
            <button type="button" class="btn btn-outline btn-full"
                    onclick="window.open('<?= BASE_URL ?>/admin/payments/manage_payments.php','_blank')">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Payments
            </button>
        </div>

    </div>

    <!-- How to print instructions -->
    <div class="card" style="margin-top:32px;background:var(--primary-light);border:1.5px solid #c7ceff;">
        <h3 style="margin:0 0 12px;font-size:.95rem;font-weight:700;color:var(--primary);">
            <i class="fa-solid fa-circle-question"></i> How to Save as PDF
        </h3>
        <ol style="margin:0;padding-left:20px;color:var(--text-main);font-size:.88rem;line-height:1.8;">
            <li>Set your desired date range in the form above, then click <strong>Open for Print</strong>.</li>
            <li>The report opens in a new browser tab with the print-ready layout.</li>
            <li>Press <kbd>Ctrl+P</kbd> (Windows) or <kbd>Cmd+P</kbd> (Mac).</li>
            <li>In the print dialog, change the <strong>Destination</strong> to <strong>Save as PDF</strong>.</li>
            <li>Click <strong>Save</strong> to download the PDF.</li>
        </ol>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Build query string from date inputs
    function getParams() {
        var from = document.getElementById('expFrom').value;
        var to   = document.getElementById('expTo').value;
        return '?date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to);
    }

    // Open report buttons with date filter attached
    document.querySelectorAll('.open-report').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.open(btn.dataset.url + getParams(), '_blank');
        });
    });

    // Monthly report uses year filter — open with current year
    document.getElementById('openMonthly').addEventListener('click', function () {
        var year = new Date(document.getElementById('expFrom').value).getFullYear();
        window.open('<?= BASE_URL ?>/admin/reports/monthly_report.php?year=' + year, '_blank');
    });
});
</script>
</body>
</html>
