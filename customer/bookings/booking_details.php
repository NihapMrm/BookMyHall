<?php
/**
 * booking_details.php — Customer: single booking detail (read-only)
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

$customerId = (int)$_SESSION['customer_id'];
$bookingId  = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    setFlash('error', 'Invalid booking.');
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

try {
    $stmt = $pdo->prepare(
        "SELECT b.*,
                p.name  AS package_name,
                p.price AS package_price,
                p.seat_capacity, p.parking_capacity,
                mp.name AS main_package_name,
                h.name  AS hall_name, h.location AS hall_location,
                u.full_name AS customer_name
         FROM bookings b
         JOIN packages p  ON p.package_id = b.sub_package_id
         JOIN packages mp ON mp.package_id = p.parent_package_id
         JOIN hall h      ON h.hall_id = b.hall_id
         JOIN users u     ON u.user_id = b.customer_id
         WHERE b.booking_id = ? AND b.customer_id = ? AND b.is_deleted = 0
         LIMIT 1"
    );
    $stmt->execute([$bookingId, $customerId]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
    error_log('customer booking_details: ' . $e->getMessage());
    $booking = null;
}

if (!$booking) {
    setFlash('error', 'Booking not found or access denied.');
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

// Fetch payments for this booking
$payments = [];
try {
    $pStmt = $pdo->prepare(
        "SELECT * FROM payments WHERE booking_id = ? ORDER BY recorded_at ASC"
    );
    $pStmt->execute([$bookingId]);
    $payments = $pStmt->fetchAll();
} catch (PDOException $e) {
    error_log('customer booking payments: ' . $e->getMessage());
}

// Check if feedback already exists
$feedbackExists = false;
try {
    $fbStmt = $pdo->prepare("SELECT feedback_id FROM feedback WHERE booking_id = ? LIMIT 1");
    $fbStmt->execute([$bookingId]);
    $feedbackExists = (bool)$fbStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('customer booking feedback check: ' . $e->getMessage());
}

$flash      = getFlash();
$pageTitle  = 'Booking #' . $bookingId;
$pageSubtitle = 'Reservation details & status';
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

    <!-- Back + action buttons -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <a href="<?= BASE_URL ?>/customer/bookings/booking_history.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to History
        </a>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($booking['status'] === 'pending'): ?>
            <a href="<?= BASE_URL ?>/customer/bookings/cancel_booking.php?id=<?= $bookingId ?>"
               class="btn btn-danger btn-sm">
                <i class="fa-solid fa-ban"></i> Cancel Booking
            </a>
            <?php endif; ?>
            <?php if ($booking['status'] === 'completed' && !$feedbackExists): ?>
            <a href="<?= BASE_URL ?>/customer/feedback/submit_feedback.php?booking_id=<?= $bookingId ?>"
               class="btn btn-primary btn-sm">
                <i class="fa-solid fa-star"></i> Leave Feedback
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="c-booking-detail-card">

        <!-- Status banner -->
        <div class="c-detail-status-bar status-<?= htmlspecialchars($booking['status']) ?>">
            <div>
                <span class="c-detail-booking-id">Booking #<?= $bookingId ?></span>
                <span class="badge-status <?= htmlspecialchars($booking['status']) ?>">
                    <?= ucfirst($booking['status']) ?>
                </span>
            </div>
            <div class="c-detail-submitted">
                Submitted <?= htmlspecialchars(formatDateReadable($booking['created_at'])) ?>
            </div>
        </div>

        <!-- Main detail grid -->
        <div class="c-detail-grid">

            <!-- Event Info -->
            <div class="c-detail-section">
                <div class="c-detail-section-title"><i class="fa-solid fa-calendar-day"></i> Event Information</div>
                <dl class="c-detail-dl">
                    <dt>Event Date</dt>
                    <dd>
                        <?php
                        $endDate = $booking['end_date'] ?? null;
                        if ($endDate && $endDate !== $booking['event_date']):
                        ?>
                            <?= htmlspecialchars(formatDateReadable($booking['event_date'])) ?>
                            &rarr; <?= htmlspecialchars(formatDateReadable($endDate)) ?>
                            <?php
                            $numDays = (int)round((strtotime($endDate) - strtotime($booking['event_date'])) / 86400) + 1;
                            echo ' <span style="background:var(--primary);color:#fff;border-radius:20px;padding:1px 9px;font-size:.72rem;">' . $numDays . ' days</span>';
                        else:
                        ?>
                            <?= htmlspecialchars(formatDateReadable($booking['event_date'])) ?>
                        <?php endif; ?>
                    </dd>
                    <dt>Time</dt>
                    <dd>
                        <?= htmlspecialchars(date('g:i A', strtotime($booking['start_time']))) ?>
                        &ndash;
                        <?= htmlspecialchars(date('g:i A', strtotime($booking['end_time']))) ?>
                    </dd>
                    <dt>Event Type</dt>
                    <dd><?= $booking['event_type'] ? htmlspecialchars($booking['event_type']) : '<em style="color:var(--text-muted)">—</em>' ?></dd>
                    <dt>Number of Guests</dt>
                    <dd><?= (int)$booking['guest_count'] ?></dd>
                </dl>
            </div>

            <!-- Hall & Package -->
            <div class="c-detail-section">
                <div class="c-detail-section-title"><i class="fa-solid fa-box-open"></i> Hall & Package</div>
                <dl class="c-detail-dl">
                    <dt>Hall</dt>
                    <dd><?= htmlspecialchars($booking['hall_name']) ?></dd>
                    <dt>Location</dt>
                    <dd><?= htmlspecialchars($booking['hall_location'] ?? '—') ?></dd>
                    <dt>Package Group</dt>
                    <dd><?= htmlspecialchars($booking['main_package_name']) ?></dd>
                    <dt>Package Selected</dt>
                    <dd><?= htmlspecialchars($booking['package_name']) ?></dd>
                    <dt>Seat Capacity</dt>
                    <dd><?= (int)$booking['seat_capacity'] ?></dd>
                </dl>
            </div>

            <!-- Financial Summary -->
            <div class="c-detail-section">
                <div class="c-detail-section-title"><i class="fa-solid fa-receipt"></i> Payment Summary</div>
                <div class="c-amount-chips">
                    <div class="c-amount-chip total">
                        <div class="c-chip-label">Total Amount</div>
                        <div class="c-chip-value"><?= htmlspecialchars(formatCurrency((float)$booking['total_amount'])) ?></div>
                    </div>
                    <div class="c-amount-chip advance">
                        <div class="c-chip-label">Advance (30%)</div>
                        <div class="c-chip-value"><?= htmlspecialchars(formatCurrency((float)$booking['advance_amount'])) ?></div>
                    </div>
                    <div class="c-amount-chip balance">
                        <div class="c-chip-label">Balance</div>
                        <div class="c-chip-value"><?= htmlspecialchars(formatCurrency((float)$booking['balance_amount'])) ?></div>
                    </div>
                </div>
            </div>

        </div><!-- /.c-detail-grid -->

        <!-- Rejection reason -->
        <?php if (!empty($booking['rejection_reason'])): ?>
        <div class="alert alert-error" style="margin:20px 24px 0;border-radius:var(--radius-md);">
            <i class="fa-solid fa-circle-exclamation"></i>
            <strong>Rejection Reason:</strong> <?= htmlspecialchars($booking['rejection_reason']) ?>
        </div>
        <?php endif; ?>

        <!-- Special requests -->
        <?php if (!empty($booking['special_requests'])): ?>
        <div class="c-detail-special">
            <div class="c-detail-section-title"><i class="fa-solid fa-note-sticky"></i> Special Requests</div>
            <p><?= nl2br(htmlspecialchars($booking['special_requests'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Payments recorded -->
        <?php if (!empty($payments)): ?>
        <div class="c-detail-special">
            <div class="c-detail-section-title"><i class="fa-solid fa-money-bill-wave"></i> Payment Records</div>
            <div class="table-wrapper" style="margin-top:10px;">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td><?= ucfirst(htmlspecialchars($pay['payment_type'])) ?></td>
                        <td><?= ucfirst(htmlspecialchars($pay['method'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars(formatCurrency((float)$pay['amount'])) ?></td>
                        <td>
                            <span class="badge-status <?= $pay['status'] === 'paid' ? 'approved' : ($pay['status'] === 'failed' ? 'rejected' : 'pending') ?>">
                                <?= ucfirst($pay['status']) ?>
                            </span>
                        </td>
                        <td style="font-size:.82rem;color:var(--text-muted);">
                            <?= htmlspecialchars(date('M d, Y', strtotime($pay['recorded_at']))) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.c-booking-detail-card -->

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
