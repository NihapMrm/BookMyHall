<?php
/**
 * get_booked_dates.php — AJAX: Return booked date ranges for a sub-package
 * Returns JSON: { "ranges": [{"start":"YYYY-MM-DD","end":"YYYY-MM-DD"}, ...] }
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

header('Content-Type: application/json');

$pkgId = (int)($_GET['pkg_id'] ?? 0);

if ($pkgId <= 0) {
    echo json_encode(['ranges' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT event_date AS `start`, COALESCE(end_date, event_date) AS `end`
         FROM bookings
         WHERE package_id = ?
           AND status NOT IN ('rejected','cancelled')
           AND is_deleted = 0
           AND COALESCE(end_date, event_date) >= CURDATE()
         ORDER BY event_date ASC"
    );
    $stmt->execute([$pkgId]);
    $ranges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ranges' => $ranges]);
} catch (PDOException $e) {
    error_log('get_booked_dates: ' . $e->getMessage());
    echo json_encode(['ranges' => []]);
}
