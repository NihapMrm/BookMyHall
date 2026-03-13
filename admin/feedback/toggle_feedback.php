<?php
/**
 * toggle_feedback.php — Admin: Toggle feedback visibility (show/hide)
 * POST-only action.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// Auto-migrate: create feedback table if missing
try {
    $tableCheck = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'feedback'"
    );
    if ((int)$tableCheck->fetchColumn() === 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `feedback` (
                `feedback_id`  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `booking_id`   INT UNSIGNED  NOT NULL,
                `customer_id`  INT UNSIGNED  NOT NULL,
                `rating`       TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1–5 stars',
                `comment`      TEXT          DEFAULT NULL,
                `is_visible`   TINYINT(1)    NOT NULL DEFAULT 1,
                `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`feedback_id`),
                UNIQUE KEY `uq_booking_feedback` (`booking_id`),
                FOREIGN KEY (`booking_id`)  REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE,
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`user_id`)       ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
} catch (PDOException $e) {
    error_log('toggle_feedback migration: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/feedback/manage_feedback.php');
}

$feedbackId = (int)($_POST['feedback_id'] ?? 0);
if ($feedbackId <= 0) {
    setFlash('error', 'Invalid feedback reference.');
    redirect(BASE_URL . '/admin/feedback/manage_feedback.php');
}

try {
    // Toggle is_visible
    $stmt = $pdo->prepare(
        "UPDATE feedback SET is_visible = (1 - is_visible) WHERE feedback_id = ?"
    );
    $stmt->execute([$feedbackId]);

    // Read new state for flash message
    $cur = $pdo->prepare("SELECT is_visible FROM feedback WHERE feedback_id = ?");
    $cur->execute([$feedbackId]);
    $row = $cur->fetch();
    $state = ($row && $row['is_visible']) ? 'visible' : 'hidden';

    setFlash('success', 'Feedback #' . $feedbackId . ' is now ' . $state . '.');
} catch (PDOException $e) {
    error_log('toggle_feedback error: ' . $e->getMessage());
    setFlash('error', 'An error occurred. Please try again.');
}

$redirect = $_POST['redirect'] ?? BASE_URL . '/admin/feedback/manage_feedback.php';
// Validate redirect is a relative URL within this app to prevent open redirect
if (!str_starts_with($redirect, BASE_URL)) {
    $redirect = BASE_URL . '/admin/feedback/manage_feedback.php';
}
redirect($redirect);
