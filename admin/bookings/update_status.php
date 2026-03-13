<?php
/**
 * update_status.php — Admin: Inline booking status update (AJAX/JSON)
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$validStatuses = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
$bookingId     = (int)($_POST['booking_id'] ?? 0);
$status        = sanitizeInput($_POST['status'] ?? '');

if (!$bookingId || !in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE bookings SET status = ?, updated_at = NOW() WHERE booking_id = ? AND is_deleted = 0"
    );
    $stmt->execute([$status, $bookingId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('update_status: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
