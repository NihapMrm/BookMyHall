<?php
/**
 * manage_bookings.php — Admin: All Bookings (Calendar + Table view)
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Filters (table view) ──────────────────────────────────────────────────
$statusFilter = sanitizeInput($_GET['status']    ?? '');
$dateFrom     = sanitizeInput($_GET['date_from'] ?? '');
$dateTo       = sanitizeInput($_GET['date_to']   ?? '');
$search       = sanitizeInput($_GET['q']         ?? '');
$perPage      = 10;
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$validStatuses = ['pending','approved','rejected','cancelled','completed'];

// ─── Stats ─────────────────────────────────────────────────────────────────
$stats = ['total'=>0,'pending'=>0,'approved'=>0,'completed'=>0,'cancelled'=>0];
try {
    $row = $pdo->query(
        "SELECT
            COUNT(*)                            AS total,
            SUM(status='pending')               AS pending,
            SUM(status='approved')              AS approved,
            SUM(status='completed')             AS completed,
            SUM(status IN ('cancelled','rejected')) AS cancelled
         FROM bookings WHERE is_deleted = 0"
    )->fetch();
    if ($row) $stats = array_map('intval', $row);
} catch (PDOException $e) { error_log('manage_bookings stats: ' . $e->getMessage()); }

// ─── Calendar data: ALL non-deleted bookings ───────────────────────────────
$calendarBookings = [];
try {
    $calStmt = $pdo->query(
        "SELECT b.booking_id, b.event_date, b.end_date, b.start_time, b.end_time,
                b.status, b.total_amount,
                u.full_name AS customer_name,
                p.name AS package_name
         FROM bookings b
         JOIN users u ON u.user_id = b.customer_id
         JOIN packages p ON p.package_id = b.package_id
         WHERE b.is_deleted = 0
         ORDER BY b.event_date ASC"
    );
    $rawBks = $calStmt->fetchAll();
    foreach ($rawBks as $bk) {
        $calendarBookings[] = [
            'booking_id'    => $bk['booking_id'],
            'event_date'    => $bk['event_date'],
            'end_date'      => $bk['end_date'] ?: $bk['event_date'],
            'start_time'    => substr($bk['start_time'], 0, 5),
            'end_time'      => substr($bk['end_time'], 0, 5),
            'status'        => $bk['status'],
            'customer_name' => $bk['customer_name'],
            'package_name'  => $bk['package_name'],
            'total_amount'  => formatCurrency((float)$bk['total_amount']),
            'detail_url'    => BASE_URL . '/admin/bookings/booking_details.php?id=' . $bk['booking_id'],
        ];
    }
} catch (PDOException $e) { error_log('manage_bookings calendar: ' . $e->getMessage()); }

// ─── Table data: filtered + paginated ──────────────────────────────────────
$where  = "WHERE b.is_deleted = 0";
$params = [];

if (in_array($statusFilter, $validStatuses)) {
    $where   .= " AND b.status = ?";
    $params[] = $statusFilter;
}
if ($dateFrom !== '') {
    $where   .= " AND b.event_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where   .= " AND b.event_date <= ?";
    $params[] = $dateTo;
}
if ($search !== '') {
    $where   .= " AND (u.full_name LIKE ? OR b.event_type LIKE ? OR b.booking_id = ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = (int)$search;
}

$totalRows = 0;
$bookings  = [];
try {
    $countSql = "SELECT COUNT(*) FROM bookings b
                 JOIN users u ON u.user_id = b.customer_id
                 $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);

    $sql = "SELECT b.booking_id, b.event_date, COALESCE(b.end_date, b.event_date) AS end_date, b.start_time, b.end_time,
                   b.event_type, b.guest_count, b.status,
                   b.total_amount, b.advance_amount, b.balance_amount, b.created_at,
                   u.full_name AS customer_name, u.email AS customer_email,
                   p.name AS package_name
            FROM bookings b
            JOIN users u ON u.user_id = b.customer_id
            JOIN packages p ON p.package_id = b.package_id
            $where
            ORDER BY b.created_at DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) { error_log('manage_bookings table: ' . $e->getMessage()); }

$totalPages = isset($totalPages) ? $totalPages : 1;
$flash      = getFlash();
$pageTitle    = 'Bookings';
$pageSubtitle = 'Manage reservations — calendar and list view';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/bookings.css"/>
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

    <!-- Stats row -->
    <div class="booking-stats">
        <div class="bstat-card">
            <div class="bstat-icon total"><i class="fa-solid fa-calendar-days"></i></div>
            <div class="bstat-info">
                <div class="bstat-value"><?= $stats['total'] ?></div>
                <div class="bstat-label">Total Bookings</div>
            </div>
        </div>
        <div class="bstat-card">
            <div class="bstat-icon pending"><i class="fa-solid fa-clock"></i></div>
            <div class="bstat-info">
                <div class="bstat-value"><?= $stats['pending'] ?></div>
                <div class="bstat-label">Pending</div>
            </div>
        </div>
        <div class="bstat-card">
            <div class="bstat-icon approved"><i class="fa-solid fa-circle-check"></i></div>
            <div class="bstat-info">
                <div class="bstat-value"><?= $stats['approved'] ?></div>
                <div class="bstat-label">Approved</div>
            </div>
        </div>
        <div class="bstat-card">
            <div class="bstat-icon completed"><i class="fa-solid fa-flag-checkered"></i></div>
            <div class="bstat-info">
                <div class="bstat-value"><?= $stats['completed'] ?></div>
                <div class="bstat-label">Completed</div>
            </div>
        </div>
        <div class="bstat-card">
            <div class="bstat-icon cancelled"><i class="fa-solid fa-ban"></i></div>
            <div class="bstat-info">
                <div class="bstat-value"><?= $stats['cancelled'] ?></div>
                <div class="bstat-label">Cancelled/Rejected</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="bookings-toolbar">
        <div class="toolbar-left">
            <!-- View toggle -->
            <div class="view-toggle">
                <button class="view-toggle-btn active" id="btn-view-cal">
                    <i class="fa-solid fa-calendar-days"></i> Calendar
                </button>
                <button class="view-toggle-btn" id="btn-view-table">
                    <i class="fa-solid fa-table-list"></i> Table
                </button>
            </div>

            <!-- Calendar status filter (JS only) -->
            <div id="cal-filter-wrap">
                <select id="cal-status-filter" class="filter-bar" style="height:38px;padding:0 12px;border:1.5px solid #e0e4f0;border-radius:var(--radius-sm);font-family:inherit;font-size:.82rem;color:var(--text-main);">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="toolbar-right">
            <a href="<?= BASE_URL ?>/admin/bookings/add_booking.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add Booking
            </a>
        </div>
    </div>

    <!-- ─── Calendar View ─────────────────────────────────── -->
    <div id="cal-view">
        <div class="booking-calendar" id="admin-calendar"></div>
    </div>

    <!-- ─── Table View ────────────────────────────────────── -->
    <div id="table-view" style="display:none;">

        <!-- Table filters -->
        <form method="GET" id="filter-form">
            <div class="filter-bar" style="margin-bottom:20px; background:var(--card-bg); padding:16px 20px; border-radius:var(--radius-md); box-shadow:var(--shadow-card);">
                <input type="search" name="q" placeholder="Search customer, event type, ID…"
                       value="<?= htmlspecialchars($search) ?>" style="min-width:210px; height:38px; padding:0 12px; border:1.5px solid #e0e4f0; border-radius:var(--radius-sm); font-family:inherit; font-size:.82rem; color:var(--text-main);"/>
                <select name="status" style="height:38px;padding:0 12px;border:1.5px solid #e0e4f0;border-radius:var(--radius-sm);font-family:inherit;font-size:.82rem;color:var(--text-main);">
                    <option value="">All statuses</option>
                    <?php foreach ($validStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                       title="From date" style="height:38px;padding:0 12px;border:1.5px solid #e0e4f0;border-radius:var(--radius-sm);font-family:inherit;font-size:.82rem;color:var(--text-main);"/>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                       title="To date" style="height:38px;padding:0 12px;border:1.5px solid #e0e4f0;border-radius:var(--radius-sm);font-family:inherit;font-size:.82rem;color:var(--text-main);"/>
                <a href="<?= BASE_URL ?>/admin/bookings/manage_bookings.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
            </div>
        </form>

        <div class="bookings-card">
            <div class="card-header">
                <span class="card-title">
                    All Bookings
                    <span style="font-size:.8rem;font-weight:500;color:var(--text-muted);margin-left:8px;">
                        (<?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?>)
                    </span>
                </span>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Customer</th>
                            <th>Package</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Time</th>
                            <th>Guests</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">
                                <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.4;"></i>
                                No bookings found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $bk): ?>
                        <tr>
                            <td><strong>#<?= $bk['booking_id'] ?></strong></td>
                            <td>
                                <div style="font-weight:600;font-size:.88rem;"><?= htmlspecialchars($bk['customer_name']) ?></div>
                                <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($bk['customer_email']) ?></div>
                            </td>
                            <td style="font-size:.85rem;"><?= htmlspecialchars($bk['package_name']) ?></td>
                            <td style="font-size:.85rem;white-space:nowrap;">
                                <?= htmlspecialchars(formatDateReadable($bk['event_date'])) ?>
                            </td>
                            <td style="font-size:.85rem;white-space:nowrap;">
                                <?= htmlspecialchars(formatDateReadable($bk['end_date'])) ?>
                            </td>
                            <td style="font-size:.82rem;white-space:nowrap;color:var(--text-muted);">
                                <?= htmlspecialchars(substr($bk['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($bk['end_time'],0,5)) ?>
                            </td>
                            <td style="font-size:.85rem;"><?= (int)$bk['guest_count'] ?></td>
                            <td style="font-size:.85rem;font-weight:700;"><?= htmlspecialchars(formatCurrency((float)$bk['total_amount'])) ?></td>
                            <td>
                                <span class="badge-status <?= htmlspecialchars($bk['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($bk['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-cell">
                                    <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $bk['booking_id'] ?>"
                                       class="btn btn-sm btn-outline" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php if ($bk['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/bookings/approve_booking.php" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= $bk['booking_id'] ?>"/>
                                        <button type="submit" class="btn btn-sm btn-success"
                                                data-confirm="Approve booking #<?= $bk['booking_id'] ?>?"
                                                title="Approve">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </form>
                                    <a href="<?= BASE_URL ?>/admin/bookings/reject_booking.php?booking_id=<?= $bk['booking_id'] ?>"
                                       class="btn btn-sm btn-danger" title="Reject">
                                        <i class="fa-solid fa-ban"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($bk['status'] === 'approved'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/bookings/complete_booking.php" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= $bk['booking_id'] ?>"/>
                                        <button type="submit" class="btn btn-sm btn-primary"
                                                data-confirm="Mark booking #<?= $bk['booking_id'] ?> as completed?"
                                                title="Mark Completed">
                                            <i class="fa-solid fa-flag-checkered"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $qs = http_build_query(array_filter(['q'=>$search,'status'=>$statusFilter,'date_from'=>$dateFrom,'date_to'=>$dateTo]));
                $base = BASE_URL . '/admin/bookings/manage_bookings.php?' . ($qs ? $qs . '&' : '');
                ?>
                <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>"
                   href="<?= $base ?>page=<?= $page - 1 ?>">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="page-link <?= $p === $page ? 'active' : '' ?>"
                   href="<?= $base ?>page=<?= $p ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>"
                   href="<?= $base ?>page=<?= $page + 1 ?>">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div><!-- /.bookings-card -->

    </div><!-- /#table-view -->

</main>

<!-- Day modal -->
<div class="bk-modal-overlay" id="bk-modal-overlay">
    <div class="bk-modal">
        <div class="bk-modal-header">
            <span class="bk-modal-title" id="bk-modal-date-title"></span>
            <button class="bk-modal-close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="bk-modal-body">
            <ul class="bk-modal-list" id="bk-modal-list"></ul>
        </div>
    </div>
</div>

<script>
    const BOOKING_DATA = <?= json_encode($calendarBookings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const BASE_URL     = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/bookings.js"></script>
</body>
</html>
