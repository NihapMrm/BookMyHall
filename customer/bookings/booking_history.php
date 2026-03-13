<?php
/**
 * booking_history.php — Customer: booking list (table view)
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

$customerId = (int)$_SESSION['customer_id'];
$flash = getFlash();

// Filters
$filterStatus = sanitizeInput($_GET['status'] ?? '');
$page         = max(1, (int)($_GET['page']   ?? 1));
$perPage      = 8;
$offset       = ($page - 1) * $perPage;

// Build WHERE clause
$where = 'WHERE b.customer_id = ? AND b.is_deleted = 0';
$params = [$customerId];
if (in_array($filterStatus, ['pending','approved','rejected','cancelled','completed'])) {
    $where  .= ' AND b.status = ?';
    $params[] = $filterStatus;
}

try {
    // Count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    // Bookings
    $dataParams = array_merge($params, [$perPage, $offset]);
    $stmt = $pdo->prepare(
        "SELECT b.booking_id, b.event_date, COALESCE(b.end_date, b.event_date) AS end_date, b.start_time, b.end_time,
                b.event_type, b.guest_count, b.status,
                b.total_amount, b.advance_amount, b.balance_amount, b.created_at,
                p.name AS package_name
         FROM bookings b
         JOIN packages p ON p.package_id = b.package_id
         $where
         ORDER BY b.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($dataParams);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('booking_history: ' . $e->getMessage());
    $bookings = [];
    $total    = 0;
}

// Fetch booking IDs that already have feedback (so we can show the action button correctly)
$feedbackBookingIds = [];
try {
    $fbStmt = $pdo->prepare('SELECT booking_id FROM feedback WHERE customer_id = ?');
    $fbStmt->execute([$customerId]);
    $feedbackBookingIds = $fbStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('booking_history feedback fetch: ' . $e->getMessage());
}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

$pageTitle    = 'Booking History';
$pageSubtitle = 'View and manage your reservations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $pageTitle ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/bookings.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php include __DIR__ . '/../includes/customer_topbar.php'; ?>

<div class="c-content-wrapper">
<div class="customer-content">

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" style="margin-bottom:20px;">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Header actions -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <div>
            <a href="<?= BASE_URL ?>/customer/bookings/customer_bookings.php"
               class="btn btn-outline" style="margin-right:8px;">
                <i class="fa-solid fa-calendar"></i> Calendar View
            </a>
            <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> New Booking
            </a>
        </div>
        <!-- Status filter -->
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="status" class="form-control" style="width:auto;padding:8px 14px;"
                    onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['pending','approved','rejected','cancelled','completed'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Booking table -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
            <tr>
                <th>#ID</th>
                <th>Package</th>
                <th>Event Date</th>
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
                <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">
                    <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;margin-bottom:8px;display:block;"></i>
                    No bookings found.
                    <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php" style="color:var(--primary);display:block;margin-top:8px;">
                        Make your first booking →
                    </a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($bookings as $bk): ?>
            <tr>
                <td><span style="font-size:.75rem;color:var(--text-muted);">#</span><?= (int)$bk['booking_id'] ?></td>
                <td>
                    <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($bk['package_name']) ?></div>
                    <?php if (!empty($bk['event_type'])): ?>
                    <div style="font-size:.77rem;color:var(--text-muted);"><?= htmlspecialchars($bk['event_type']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $endD = $bk['end_date'] ?? null;
                    if ($endD && $endD !== $bk['event_date']):
                        $nd = (int)round((strtotime($endD) - strtotime($bk['event_date'])) / 86400) + 1;
                    ?>
                    <div style="font-size:.84rem;font-weight:600;"><?= htmlspecialchars(formatDateReadable($bk['event_date'])) ?></div>
                    <div style="font-size:.74rem;color:var(--text-muted);">to <?= htmlspecialchars(formatDateReadable($endD)) ?></div>
                    <div><span style="background:var(--primary-light);color:var(--primary);border-radius:10px;padding:1px 8px;font-size:.7rem;font-weight:700;"><?= $nd ?> days</span></div>
                    <?php else: ?>
                    <?= htmlspecialchars(formatDateReadable($bk['event_date'])) ?>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;font-size:.84rem;">
                    <?= htmlspecialchars(date('g:i A', strtotime($bk['start_time']))) ?>
                    —
                    <?= htmlspecialchars(date('g:i A', strtotime($bk['end_time']))) ?>
                </td>
                <td><?= (int)$bk['guest_count'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars(formatCurrency((float)$bk['total_amount'])) ?></td>
                <td>
                    <span class="badge-status <?= htmlspecialchars($bk['status']) ?>">
                        <?= ucfirst($bk['status']) ?>
                    </span>
                </td>
                <td>
                    <?php
                    $hasFeedback = in_array($bk['booking_id'], $feedbackBookingIds, true);
                    $canLeaveFeedback = !$hasFeedback && in_array($bk['status'], ['completed','approved'], true);
                    ?>

                    <a href="<?= BASE_URL ?>/customer/bookings/booking_details.php?id=<?= (int)$bk['booking_id'] ?>"
                       class="btn btn-outline btn-sm">View</a>

                    <?php if ($canLeaveFeedback): ?>
                    <a href="<?= BASE_URL ?>/customer/feedback/submit_feedback.php?booking_id=<?= (int)$bk['booking_id'] ?>"
                       class="btn btn-primary btn-sm" style="margin-top:6px;display:inline-flex;">
                        <i class="fa-solid fa-star"></i> Leave Feedback
                    </a>
                    <?php endif; ?>

                    <?php if ($bk['status'] === 'pending'): ?>
                    <a href="<?= BASE_URL ?>/customer/bookings/cancel_booking.php?id=<?= (int)$bk['booking_id'] ?>"
                       class="btn btn-danger btn-sm">Cancel</a>
                    <?php endif; ?>
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
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>" class="page-link">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>"
           class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>" class="page-link">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
