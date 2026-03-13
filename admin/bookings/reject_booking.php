<?php
/**
 * reject_booking.php — Admin: Reject a pending booking
 * GET: Show rejection reason form. POST: Apply rejection.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$bookingId = (int)($_REQUEST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    setFlash('error', 'Invalid booking reference.');
    redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
}

// Fetch booking
try {
    $stmt = $pdo->prepare(
        "SELECT b.*, u.full_name AS customer_name, p.name AS package_name
         FROM bookings b
         JOIN users u ON u.user_id = b.customer_id
         JOIN packages p ON p.package_id = b.sub_package_id
         WHERE b.booking_id = ? AND b.is_deleted = 0"
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
    error_log('reject_booking fetch: ' . $e->getMessage());
    $booking = null;
}

if (!$booking) {
    setFlash('error', 'Booking not found.');
    redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
}

if ($booking['status'] !== 'pending') {
    setFlash('error', 'Only pending bookings can be rejected.');
    redirect(BASE_URL . '/admin/bookings/booking_details.php?id=' . $bookingId);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['rejection_reason'] ?? '');

    if (strlen($reason) < 5) {
        $error = 'Please provide a rejection reason (at least 5 characters).';
    } else {
        try {
            $upd = $pdo->prepare(
                "UPDATE bookings
                 SET status = 'rejected', rejection_reason = ?, updated_at = NOW()
                 WHERE booking_id = ?"
            );
            $upd->execute([sanitizeInput($reason), $bookingId]);

            setFlash('success', 'Booking #' . $bookingId . ' has been rejected.');
            redirect(BASE_URL . '/admin/bookings/booking_details.php?id=' . $bookingId);
        } catch (PDOException $e) {
            error_log('reject_booking update: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

$pageTitle    = 'Reject Booking';
$pageSubtitle = 'Provide a reason for rejection';
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

    <!-- Back -->
    <div class="page-header">
        <div>
            <p class="page-subtitle">
                <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $bookingId ?>">
                    <i class="fa-solid fa-arrow-left"></i> Back to Booking #<?= $bookingId ?>
                </a>
            </p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="reject-form-card">
        <h2 style="margin:0 0 6px; font-size:1.1rem; color:var(--danger);">
            <i class="fa-solid fa-ban"></i> Reject Booking #<?= $bookingId ?>
        </h2>
        <p style="font-size:.85rem; color:var(--text-muted); margin:0 0 24px;">
            Customer: <strong><?= htmlspecialchars($booking['customer_name']) ?></strong> &nbsp;·&nbsp;
            Package: <strong><?= htmlspecialchars($booking['package_name']) ?></strong> &nbsp;·&nbsp;
            Date: <strong><?= htmlspecialchars(formatDateReadable($booking['event_date'])) ?></strong>
        </p>

        <form method="POST">
            <input type="hidden" name="booking_id" value="<?= $bookingId ?>"/>
            <div class="form-group">
                <label class="form-label" for="rejection_reason">
                    Rejection Reason <span style="color:var(--danger)">*</span>
                </label>
                <textarea class="form-control" id="rejection_reason" name="rejection_reason"
                          rows="5" placeholder="Explain why this booking cannot be approved…"
                          required minlength="5"><?= htmlspecialchars($_POST['rejection_reason'] ?? '') ?></textarea>
                <span class="form-hint">This reason will be visible to the customer in their booking history.</span>
            </div>

            <div style="display:flex; gap:12px; margin-top:24px;">
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-ban"></i> Confirm Rejection
                </button>
                <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $bookingId ?>"
                   class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
