<?php
/**
 * check_availability.php — AJAX endpoint: check if a slot is available
 * Returns JSON: { "available": true|false }
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

header('Content-Type: application/json');

$date  = sanitizeInput($_GET['date']   ?? '');
$start = sanitizeInput($_GET['start']  ?? '');
$end   = sanitizeInput($_GET['end']    ?? '');
$pkgId = (int)($_GET['pkg_id'] ?? 0);

if (!$date || !$start || !$end || $pkgId <= 0) {
    echo json_encode(['available' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Validate date formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
    !preg_match('/^\d{2}:\d{2}$/', $start) ||
    !preg_match('/^\d{2}:\d{2}$/', $end)) {
    echo json_encode(['available' => false, 'error' => 'Invalid format']);
    exit;
}

// Optional end_date for multi-day bookings
$endDate = sanitizeInput($_GET['end_date'] ?? '');
if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $date; // fall back to single-day if invalid
}

$available = checkAvailability($pdo, $date, $start, $end, $pkgId, $endDate);
echo json_encode(['available' => $available]);
