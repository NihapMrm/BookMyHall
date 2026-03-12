<?php
/**
 * complete_booking.php — Admin: Mark an approved booking as completed
 * POST-only action. Only possible for approved bookings whose event date has passed.
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
    $stmt = $pdo->prepare(
        "SELECT booking_id, status, event_date FROM bookings
         WHERE booking_id = ? AND is_deleted = 0"
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlash('error', 'Booking not found.');
        redirect(BASE_URL . '/admin/bookings/manage_bookings.php');
    }

    if ($booking['status'] !== 'approved') {
        setFlash('error', 'Only approved bookings can be marked as completed.');
        redirect(BASE_URL . '/admin/bookings/booking_details.php?id=' . $bookingId);
    }

    $upd = $pdo->prepare(
        "UPDATE bookings
         SET status = 'completed', completed_at = NOW(), updated_at = NOW()
         WHERE booking_id = ?"
    );
    $upd->execute([$bookingId]);

    setFlash('success', 'Booking #' . $bookingId . ' has been marked as completed.');
} catch (PDOException $e) {
    error_log('complete_booking error: ' . $e->getMessage());
    setFlash('error', 'An error occurred. Please try again.');
}

redirect(BASE_URL . '/admin/bookings/booking_details.php?id=' . $bookingId);
