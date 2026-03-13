<?php
/**
 * booking_details.php — Admin: Full booking record view
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
}

// ─── Fetch booking ─────────────────────────────────────────────────────────
$booking = null;
try {
    $stmt = $pdo->prepare(
        "SELECT b.*,
                u.full_name AS customer_name, u.email AS customer_email,
                u.phone AS customer_phone,
                h.name AS hall_name,
                p.name AS package_name, p.price AS package_price
         FROM bookings b
         JOIN users u    ON u.user_id    = b.customer_id
         JOIN hall h     ON h.hall_id    = b.hall_id
         JOIN packages p ON p.package_id = b.package_id
         WHERE b.booking_id = ? AND b.is_deleted = 0"
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
    error_log('booking_details fetch: ' . $e->getMessage());
}

if (!$booking) {
    setFlash('error', 'Booking not found.');
    redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
}

// ─── Fetch payments ────────────────────────────────────────────────────────
$payments = [];
try {
    $pStmt = $pdo->prepare(
        "SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at ASC"
    );
    $pStmt->execute([$bookingId]);
    $payments = $pStmt->fetchAll();
} catch (PDOException $e) { error_log('booking_details payments: ' . $e->getMessage()); }

// ─── Fetch feedback ────────────────────────────────────────────────────────
$feedback = null;
try {
    $fStmt = $pdo->prepare("SELECT * FROM feedback WHERE booking_id = ?");
    $fStmt->execute([$bookingId]);
    $feedback = $fStmt->fetch();
} catch (PDOException $e) { error_log('booking_details feedback: ' . $e->getMessage()); }

$flash = getFlash();

$statusColors = [
    'pending'   => '#f39c12',
    'approved'  => '#2ecc71',
    'rejected'  => '#e74c3c',
    'cancelled' => '#6c6f83',
    'completed' => '#3498db',
];

$pageTitle    = 'Booking #' . $bookingId;
$pageSubtitle = 'Full booking record and actions';
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

    <!-- Back + actions bar -->
    <div class="page-header" style="margin-bottom:24px;">
        <div>
            <a href="<?= BASE_URL ?>/admin/bookings/manage_bookings.php"
               style="font-size:.82rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:6px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Bookings
            </a>
            <h1 class="page-title">Booking #<?= $bookingId ?></h1>
            <p class="page-subtitle">
                <span class="badge-status <?= htmlspecialchars($booking['status']) ?>">
                    <?= ucfirst(htmlspecialchars($booking['status'])) ?>
                </span>
                &nbsp; Created <?= htmlspecialchars(formatDateReadable($booking['created_at'])) ?>
            </p>
        </div>
        <div class="booking-actions-bar">
            <?php if ($booking['status'] === 'pending'): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/bookings/approve_booking.php" style="display:inline;">
                <input type="hidden" name="booking_id" value="<?= $bookingId ?>"/>
                <button type="submit" class="btn btn-success"
                        data-confirm="Approve this booking?">
                    <i class="fa-solid fa-check"></i> Approve
                </button>
            </form>
            <a href="<?= BASE_URL ?>/admin/bookings/reject_booking.php?booking_id=<?= $bookingId ?>"
               class="btn btn-danger">
                <i class="fa-solid fa-ban"></i> Reject
            </a>
            <?php endif; ?>
            <?php if ($booking['status'] === 'approved'): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/bookings/complete_booking.php" style="display:inline;">
                <input type="hidden" name="booking_id" value="<?= $bookingId ?>"/>
                <button type="submit" class="btn btn-primary"
                        data-confirm="Mark this booking as completed?">
                    <i class="fa-solid fa-flag-checkered"></i> Mark Completed
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Rejection reason notice -->
    <?php if ($booking['status'] === 'rejected' && $booking['rejection_reason']): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-ban"></i>
        <strong>Rejection Reason:</strong> <?= htmlspecialchars($booking['rejection_reason']) ?>
    </div>
    <?php endif; ?>

    <!-- Detail grid -->
    <div class="booking-detail-grid">

        <!-- Customer Info -->
        <div class="detail-card">
            <div class="detail-card-title"><i class="fa-solid fa-user"></i> &nbsp;Customer</div>
            <div class="detail-row">
                <span class="detail-label">Name</span>
                <span class="detail-value"><?= htmlspecialchars($booking['customer_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($booking['customer_email']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($booking['customer_phone'] ?: '—') ?></span>
            </div>
        </div>

        <!-- Event Info -->
        <div class="detail-card">
            <div class="detail-card-title"><i class="fa-solid fa-calendar-days"></i> &nbsp;Event</div>
            <div class="detail-row">
                <span class="detail-label">Event Type</span>
                <span class="detail-value"><?= htmlspecialchars($booking['event_type'] ?: '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date</span>
                <span class="detail-value">
                    <?php
                    $endDate = $booking['end_date'] ?: null;
                    if ($endDate && $endDate !== $booking['event_date']):
                    ?>
                        <?= htmlspecialchars(formatDateReadable($booking['event_date'])) ?>
                        &rarr; <?= htmlspecialchars(formatDateReadable($endDate)) ?>
                        <?php
                        $numDays = (int)round((strtotime($endDate) - strtotime($booking['event_date'])) / 86400) + 1;
                        echo ' <span style="background:var(--primary-light);color:var(--primary);border-radius:10px;padding:2px 8px;font-size:.75rem;font-weight:700;">' . $numDays . ' days</span>';
                        ?>
                    <?php else: ?>
                        <?= htmlspecialchars(formatDateReadable($booking['event_date'])) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time</span>
                <span class="detail-value">
                    <?= htmlspecialchars(substr($booking['start_time'],0,5)) ?> –
                    <?= htmlspecialchars(substr($booking['end_time'],0,5)) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Guests</span>
                <span class="detail-value"><?= (int)$booking['guest_count'] ?> people</span>
            </div>
        </div>

        <!-- Package & Hall -->
        <div class="detail-card">
            <div class="detail-card-title"><i class="fa-solid fa-box-open"></i> &nbsp;Package & Hall</div>
            <div class="detail-row">
                <span class="detail-label">Hall</span>
                <span class="detail-value"><?= htmlspecialchars($booking['hall_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Package</span>
                <span class="detail-value"><?= htmlspecialchars($booking['package_name']) ?></span>
            </div>
        </div>

        <!-- Financial -->
        <div class="detail-card">
            <div class="detail-card-title"><i class="fa-solid fa-receipt"></i> &nbsp;Financial Summary</div>
            <div class="detail-row">
                <span class="detail-label">Total Amount</span>
                <span class="detail-value amount-highlight"><?= htmlspecialchars(formatCurrency((float)$booking['total_amount'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Advance (30%)</span>
                <span class="detail-value" style="color:var(--success);"><?= htmlspecialchars(formatCurrency((float)$booking['advance_amount'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Balance Due</span>
                <span class="detail-value" style="color:var(--warning);"><?= htmlspecialchars(formatCurrency((float)$booking['balance_amount'])) ?></span>
            </div>
        </div>

        <?php if (!empty($booking['special_requests'])): ?>
        <!-- Special Requests -->
        <div class="detail-card" style="grid-column:1/-1;">
            <div class="detail-card-title"><i class="fa-solid fa-comment-dots"></i> &nbsp;Special Requests</div>
            <p style="margin:0;font-size:.88rem;color:var(--text-muted);line-height:1.7;">
                <?= nl2br(htmlspecialchars($booking['special_requests'])) ?>
            </p>
        </div>
        <?php endif; ?>

    </div><!-- /.booking-detail-grid -->

    <!-- Payments -->
    <div class="detail-card" style="margin-top:24px;">
        <div class="detail-card-title" style="margin-bottom:16px;">
            <i class="fa-solid fa-credit-card"></i> &nbsp;Payments
            <a href="<?= BASE_URL ?>/admin/payments/add_payment.php?booking_id=<?= $bookingId ?>"
               class="btn btn-sm btn-primary" style="float:right;font-size:.75rem;">
                <i class="fa-solid fa-plus"></i> Record Payment
            </a>
        </div>
        <?php if (empty($payments)): ?>
            <p style="color:var(--text-muted);font-size:.85rem;">No payments recorded yet.</p>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th><th>Type</th><th>Amount</th>
                    <th>Method</th><th>Reference</th><th>Status</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $pmt): ?>
            <tr>
                <td>#<?= $pmt['payment_id'] ?></td>
                <td><?= ucfirst(htmlspecialchars($pmt['payment_type'])) ?></td>
                <td><strong><?= htmlspecialchars(formatCurrency((float)$pmt['amount'])) ?></strong></td>
                <td><?= ucfirst(htmlspecialchars(str_replace('_',' ',$pmt['method']))) ?></td>
                <td><?= htmlspecialchars($pmt['reference'] ?: '—') ?></td>
                <td><span class="badge-status <?= htmlspecialchars($pmt['status']) ?>"><?= ucfirst(htmlspecialchars($pmt['status'])) ?></span></td>
                <td style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars(formatDateReadable($pmt['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Feedback -->
    <?php if ($booking['status'] === 'completed'): ?>
    <div class="detail-card" style="margin-top:24px;">
        <div class="detail-card-title">
            <i class="fa-solid fa-star"></i> &nbsp;Customer Feedback
        </div>
        <?php if ($feedback): ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                <span style="font-size:2rem;font-weight:800;color:var(--warning);"><?= $feedback['rating'] ?></span>
                <div>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa-solid fa-star" style="color:<?= $i <= $feedback['rating'] ? 'var(--warning)' : '#ddd' ?>;"></i>
                    <?php endfor; ?>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px;">
                        Submitted <?= htmlspecialchars(formatDateReadable($feedback['created_at'])) ?>
                    </div>
                </div>
                <span style="margin-left:auto;">
                    <form method="POST" action="<?= BASE_URL ?>/admin/feedback/toggle_feedback.php" style="display:inline;">
                        <input type="hidden" name="feedback_id" value="<?= $feedback['feedback_id'] ?>"/>
                        <input type="hidden" name="redirect" value="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $bookingId ?>"/>
                        <button type="submit" class="btn btn-sm btn-outline"
                                data-confirm="Toggle feedback visibility?">
                            <?= $feedback['is_visible'] ? '<i class="fa-solid fa-eye-slash"></i> Hide' : '<i class="fa-solid fa-eye"></i> Show' ?>
                        </button>
                    </form>
                </span>
            </div>
            <?php if ($feedback['comment']): ?>
            <p style="font-size:.88rem;color:var(--text-muted);line-height:1.7;margin:0;">
                "<?= nl2br(htmlspecialchars($feedback['comment'])) ?>"
            </p>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:var(--text-muted);font-size:.85rem;">No feedback submitted yet for this booking.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/bookings.js"></script>
</body>
</html>
