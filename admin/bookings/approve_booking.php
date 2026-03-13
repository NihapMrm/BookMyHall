<?php
/**
 * approve_booking.php — Admin: Approve a pending booking
 * POST-only action. Creates an advance payment record on approval.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    setFlash('error', 'Invalid booking reference.');
    redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
}

try {
    // Fetch booking — must be pending and not deleted
    $stmt = $pdo->prepare(
        "SELECT booking_id, status, advance_amount FROM bookings
         WHERE booking_id = ? AND is_deleted = 0"
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlash('error', 'Booking not found.');
        redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
    }

    if ($booking['status'] !== 'pending') {
        setFlash('error', 'Only pending bookings can be approved.');
        redirect(BASE_URL . '/admin/bookings/booking_details.php?id=' . $bookingId);
    }

    $pdo->beginTransaction();

    // Update booking status
    $upd = $pdo->prepare(
        "UPDATE bookings SET status = 'approved', updated_at = NOW() WHERE booking_id = ?"
    );
    $upd->execute([$bookingId]);

    // Create advance payment record (status = pending until payment is recorded)
    $ins = $pdo->prepare(
        "INSERT INTO payments (booking_id, payment_type, amount, method, status, created_at)
         VALUES (?, 'advance', ?, 'cash', 'pending', NOW())"
    );
    $ins->execute([$bookingId, $booking['advance_amount']]);

    $pdo->commit();

    setFlash('success', 'Booking #' . $bookingId . ' has been approved.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('approve_booking error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while approving the booking. Please try again.');
}

redirect(BASE_URL . '/admin/bookings/booking_details.php?id=' . $bookingId);
