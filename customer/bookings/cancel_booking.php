<?php
/**
 * cancel_booking.php — Customer: cancel a pending booking
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

$customerId = (int)$_SESSION['customer_id'];
$bookingId  = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);

if ($bookingId <= 0) {
    setFlash('error', 'Invalid booking.');
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

// Fetch and validate ownership + pending status
try {
    $stmt = $pdo->prepare(
        "SELECT b.booking_id, b.status, b.event_date, b.end_date,
                p.name AS package_name
         FROM bookings b
         JOIN packages p ON p.package_id = b.sub_package_id
         WHERE b.booking_id = ? AND b.customer_id = ? AND b.is_deleted = 0
         LIMIT 1"
    );
    $stmt->execute([$bookingId, $customerId]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
    error_log('cancel_booking fetch: ' . $e->getMessage());
    $booking = null;
}

if (!$booking) {
    setFlash('error', 'Booking not found or access denied.');
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

if ($booking['status'] !== 'pending') {
    setFlash('error', 'Only pending bookings can be cancelled.');
    redirect(BASE_URL . '/customer/bookings/booking_details.php?id=' . $bookingId);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = sanitizeInput($_POST['cancellation_reason'] ?? '');

    if (strlen($reason) < 5) {
        $error = 'Please provide a cancellation reason (at least 5 characters).';
    } else {
        try {
            $upd = $pdo->prepare(
                "UPDATE bookings
                 SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW()
                 WHERE booking_id = ? AND customer_id = ? AND status = 'pending'"
            );
            $upd->execute([$reason, $bookingId, $customerId]);

            if ($upd->rowCount() > 0) {
                setFlash('success', 'Your booking has been cancelled successfully.');
                redirect(BASE_URL . '/customer/bookings/booking_history.php');
            } else {
                $error = 'Unable to cancel. The booking may have already been processed.';
            }
        } catch (PDOException $e) {
            error_log('cancel_booking update: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

$pageTitle    = 'Cancel Booking';
$pageSubtitle = 'Cancelling Booking #' . $bookingId;
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

    <div style="max-width:620px;margin:0 auto;">

        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:18px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="c-booking-detail-card" style="padding:28px;">

            <!-- Warning header -->
            <div class="cancel-warning">
                <div class="cancel-warning-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="cancel-warning-title">Cancel this Booking?</div>
                    <div class="cancel-warning-desc">
                        You are about to cancel <strong>Booking #<?= $bookingId ?></strong> for
                        <strong><?= htmlspecialchars($booking['package_name']) ?></strong>
                        on <strong><?= htmlspecialchars(formatDateReadable($booking['event_date'])) ?><?php
                        if (!empty($booking['end_date']) && $booking['end_date'] !== $booking['event_date']):
                            echo ' &rarr; ' . htmlspecialchars(formatDateReadable($booking['end_date']));
                        endif; ?></strong>.
                        This action cannot be undone.
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="booking_id" value="<?= $bookingId ?>"/>
                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label" for="cancellation_reason">
                        Reason for Cancellation <span style="color:var(--danger)">*</span>
                    </label>
                    <textarea class="form-control" id="cancellation_reason" name="cancellation_reason"
                              rows="4" placeholder="Please tell us why you are cancelling…"
                              required><?= htmlspecialchars($_POST['cancellation_reason'] ?? '') ?></textarea>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-ban"></i> Confirm Cancellation
                    </button>
                    <a href="<?= BASE_URL ?>/customer/bookings/booking_details.php?id=<?= $bookingId ?>"
                       class="btn btn-outline">
                        <i class="fa-solid fa-arrow-left"></i> Keep Booking
                    </a>
                </div>
            </form>
        </div>

    </div><!-- /max-width -->

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
