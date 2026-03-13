<?php
/**
 * manage_payments.php — Admin: All Payments list with search, filter & summary cards
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Filters ──────────────────────────────────────────────────────────────────
$search       = sanitizeInput($_GET['q']       ?? '');
$statusFilter = sanitizeInput($_GET['status']  ?? '');
$methodFilter = sanitizeInput($_GET['method']  ?? '');
$dateFrom     = sanitizeInput($_GET['from']    ?? '');
$dateTo       = sanitizeInput($_GET['to']      ?? '');
$perPage      = 15;
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$validStatuses = ['pending', 'paid', 'refunded', 'failed'];
$validMethods  = ['cash', 'bank_transfer', 'card', 'online'];

// ─── Summary Stats ────────────────────────────────────────────────────────────
$summary = ['total_revenue' => 0, 'pending_count' => 0, 'paid_count' => 0, 'refunded_count' => 0];
try {
    $row = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS total_revenue,
            SUM(status = 'pending')  AS pending_count,
            SUM(status = 'paid')     AS paid_count,
            SUM(status = 'refunded') AS refunded_count
         FROM payments"
    )->fetch();
    if ($row) $summary = $row;
} catch (PDOException $e) { error_log('manage_payments stats: ' . $e->getMessage()); }

// ─── Build query ──────────────────────────────────────────────────────────────
$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR p.reference LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $conditions[] = "p.status = ?";
    $params[]     = $statusFilter;
}

if ($methodFilter !== '' && in_array($methodFilter, $validMethods, true)) {
    $conditions[] = "p.method = ?";
    $params[]     = $methodFilter;
}

if ($dateFrom !== '') {
    $conditions[] = "DATE(p.created_at) >= ?";
    $params[]     = $dateFrom;
}

if ($dateTo !== '') {
    $conditions[] = "DATE(p.created_at) <= ?";
    $params[]     = $dateTo;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countSql = "SELECT COUNT(*) FROM payments p
             JOIN bookings b ON b.booking_id = p.booking_id
             JOIN users u    ON u.user_id    = b.customer_id
             $where";

$dataSql = "SELECT p.*, b.booking_id AS bk_id, b.event_date,
                   u.full_name AS customer_name, u.email AS customer_email
            FROM payments p
            JOIN bookings b ON b.booking_id = p.booking_id
            JOIN users u    ON u.user_id    = b.customer_id
            $where
            ORDER BY p.created_at DESC
            LIMIT $perPage OFFSET $offset";

$totalRows = 0;
$payments  = [];

try {
    $cStmt = $pdo->prepare($countSql);
    $cStmt->execute($params);
    $totalRows = (int) $cStmt->fetchColumn();

    $dStmt = $pdo->prepare($dataSql);
    $dStmt->execute($params);
    $payments = $dStmt->fetchAll();
} catch (PDOException $e) { error_log('manage_payments query: ' . $e->getMessage()); }

$totalPages = $totalRows > 0 ? (int) ceil($totalRows / $perPage) : 1;

$flash = getFlash();

$pageTitle    = 'Manage Payments';
$pageSubtitle = 'View, search and track all payment records';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/payments.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Payments</h1>
            <p class="page-subtitle">All recorded payment transactions</p>
        </div>
        <div class="page-header-actions">
            <a href="<?= BASE_URL ?>/admin/payments/add_payment.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Record Payment
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="payment-stats">
        <div class="pstat-card">
            <div class="pstat-icon revenue"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="pstat-info">
                <div class="pstat-value"><?= formatCurrency((float)$summary['total_revenue']) ?></div>
                <div class="pstat-label">Total Revenue Collected</div>
            </div>
        </div>
        <div class="pstat-card">
            <div class="pstat-icon pending"><i class="fa-solid fa-clock"></i></div>
            <div class="pstat-info">
                <div class="pstat-value"><?= (int)$summary['pending_count'] ?></div>
                <div class="pstat-label">Pending Payments</div>
            </div>
        </div>
        <div class="pstat-card">
            <div class="pstat-icon paid"><i class="fa-solid fa-circle-check"></i></div>
            <div class="pstat-info">
                <div class="pstat-value"><?= (int)$summary['paid_count'] ?></div>
                <div class="pstat-label">Completed Payments</div>
            </div>
        </div>
        <div class="pstat-card">
            <div class="pstat-icon refunded"><i class="fa-solid fa-rotate-left"></i></div>
            <div class="pstat-info">
                <div class="pstat-value"><?= (int)$summary['refunded_count'] ?></div>
                <div class="pstat-label">Refunds Issued</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form id="filterForm" method="GET" action="">
        <div class="payments-toolbar">
            <div class="toolbar-left">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="q" placeholder="Search customer, email, reference…"
                           value="<?= htmlspecialchars($search) ?>" autocomplete="off"/>
                </div>
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending','paid','refunded','failed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="method" class="filter-select">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="card" <?= $methodFilter === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="online" <?= $methodFilter === 'online' ? 'selected' : '' ?>>Online</option>
                </select>
                <input type="date" name="from" class="filter-select" value="<?= htmlspecialchars($dateFrom) ?>" title="Date from">
                <input type="date" name="to"   class="filter-select" value="<?= htmlspecialchars($dateTo) ?>"   title="Date to">
            </div>
            <div class="toolbar-right">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="<?= BASE_URL ?>/admin/payments/manage_payments.php" class="btn btn-outline btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="card">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Customer</th>
                        <th>Booking</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">
                            <i class="fa-solid fa-receipt" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.35;"></i>
                            No payment records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--primary);">#<?= $p['payment_id'] ?></td>
                        <td>
                            <div style="font-weight:500;"><?= htmlspecialchars($p['customer_name']) ?></div>
                            <div style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($p['customer_email']) ?></div>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $p['bk_id'] ?>"
                               style="color:var(--primary);font-weight:500;">
                                #<?= $p['bk_id'] ?>
                            </a>
                            <div style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars(formatDateReadable($p['event_date'])) ?></div>
                        </td>
                        <td><span class="badge-type <?= htmlspecialchars($p['payment_type']) ?>"><?= ucfirst($p['payment_type']) ?></span></td>
                        <td style="text-transform:capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $p['method'])) ?></td>
                        <td class="amount-col"><?= formatCurrency((float)$p['amount']) ?></td>
                        <td><span class="badge-payment <?= htmlspecialchars($p['status']) ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td style="font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars(date('d M Y', strtotime($p['created_at']))) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/admin/payments/payment_details.php?id=<?= $p['payment_id'] ?>"
                               class="btn btn-outline btn-sm">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a>
            <?php endif; ?>
        </div>
        <div style="text-align:center;font-size:.8rem;color:var(--text-muted);padding:8px 0 16px;">
            Showing <?= count($payments) ?> of <?= $totalRows ?> records
        </div>
        <?php endif; ?>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/payments.js"></script>
</body>
</html>
