<?php
/**
 * my_feedback.php — Customer: view all submitted feedback
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

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
    error_log('my_feedback migration: ' . $e->getMessage());
}

$customerId = (int)$_SESSION['customer_id'];
$flash = getFlash();

$feedbacks = [];
try {
    $stmt = $pdo->prepare(
        "SELECT f.feedback_id, f.rating, f.comment, f.is_visible, f.created_at,
                b.booking_id, b.event_date, b.event_type,
                p.name AS package_name
         FROM feedback f
         JOIN bookings b ON b.booking_id = f.booking_id
         JOIN packages p ON p.package_id = b.sub_package_id
         WHERE f.customer_id = ?
         ORDER BY f.created_at DESC"
    );
    $stmt->execute([$customerId]);
    $feedbacks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('my_feedback fetch: ' . $e->getMessage());
}

// Fetch completed bookings that don't have feedback yet (eligible for leaving feedback)
$pendingFeedbackBookings = [];
try {
    $pendingStmt = $pdo->prepare(
        "SELECT b.booking_id, b.event_date, b.event_type,
                p.name AS package_name, h.name AS hall_name
         FROM bookings b
         JOIN packages p ON p.package_id = b.sub_package_id
         JOIN hall h ON h.hall_id = b.hall_id
         LEFT JOIN feedback f ON f.booking_id = b.booking_id
         WHERE b.customer_id = ?
           AND b.is_deleted = 0
           AND f.booking_id IS NULL
           AND b.status IN ('completed','approved')
         ORDER BY b.event_date DESC"
    );
    $pendingStmt->execute([$customerId]);
    $pendingFeedbackBookings = $pendingStmt->fetchAll();
} catch (PDOException $e) {
    error_log('my_feedback pending fetch: ' . $e->getMessage());
}

$pageTitle    = 'My Feedback';
$pageSubtitle = 'Your reviews and ratings';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/feedback.css"/>
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

    <?php if (empty($feedbacks) && empty($pendingFeedbackBookings)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
        <i class="fa-solid fa-star" style="font-size:3rem;color:var(--warning);margin-bottom:14px;display:block;opacity:.4;"></i>
        <p style="font-size:1.05rem;margin-bottom:18px;">You haven't submitted any feedback yet.</p>
        <a href="<?= BASE_URL ?>/customer/bookings/booking_history.php" class="btn btn-primary">
            <i class="fa-solid fa-calendar"></i> View My Bookings
        </a>
    </div>

    <?php else: ?>

    <?php if (!empty($pendingFeedbackBookings)): ?>
    <div class="feedback-pending-section" style="margin-bottom:32px;">
        <h2 style="margin-bottom:16px;">Leave Feedback</h2>
        <div class="feedback-item-list">
            <?php foreach ($pendingFeedbackBookings as $pb): ?>
            <div class="feedback-item-card">
                <div class="feedback-item-body">
                    <div class="feedback-item-meta">
                        <span class="feedback-item-pkg"><?= htmlspecialchars($pb['package_name']) ?></span>
                        <span class="feedback-item-date">
                            <i class="fa-solid fa-calendar-day"></i>
                            <?= htmlspecialchars(formatDateReadable($pb['event_date'])) ?>
                            <?php if ($pb['event_type']): ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars($pb['event_type']) ?>
                            <?php endif; ?>
                        </span>
                        <span class="feedback-item-location">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= htmlspecialchars($pb['hall_name']) ?>
                        </span>
                    </div>
                </div>
                <div class="feedback-item-actions">
                    <a href="<?= BASE_URL ?>/customer/feedback/submit_feedback.php?booking_id=<?= (int)$pb['booking_id'] ?>"
                       class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-star"></i> Leave Feedback
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($feedbacks)): ?>

    <div class="feedback-item-list">
        <?php foreach ($feedbacks as $fb): ?>
        <div class="feedback-item-card">

            <!-- Left: rating number + stars -->
            <div class="feedback-item-rating">
                <div class="feedback-item-big-num"><?= (int)$fb['rating'] ?></div>
                <div class="feedback-item-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa-<?= $i <= (int)$fb['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                    <?php endfor; ?>
                </div>
                <div class="feedback-item-label">
                    <?= ['','Poor','Fair','Good','Very Good','Excellent'][(int)$fb['rating']] ?>
                </div>
            </div>

            <!-- Middle: booking info + comment -->
            <div class="feedback-item-body">
                <div class="feedback-item-meta">
                    <span class="feedback-item-pkg"><?= htmlspecialchars($fb['package_name']) ?></span>
                    <span class="feedback-item-date">
                        <i class="fa-solid fa-calendar-day"></i>
                        <?= htmlspecialchars(formatDateReadable($fb['created_at'])) ?>
                        <?php if ($fb['event_type']): ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($fb['event_type']) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="feedback-item-comment">
                    <?= nl2br(htmlspecialchars($fb['comment'] ?? '')) ?>
                </div>
                <div class="feedback-item-submitted">
                    Submitted on <?= htmlspecialchars(date('M d, Y', strtotime($fb['created_at']))) ?>
                </div>
            </div>

            <!-- Right: visibility + booking link -->
            <div class="feedback-item-actions">
                <?php if ($fb['is_visible']): ?>
                <span class="visibility-notice visible">
                    <i class="fa-solid fa-eye"></i> Visible
                </span>
                <?php else: ?>
                <span class="visibility-notice hidden">
                    <i class="fa-solid fa-eye-slash"></i> Hidden
                </span>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/customer/bookings/booking_details.php?id=<?= (int)$fb['booking_id'] ?>"
                   class="btn btn-outline btn-sm" style="margin-top:8px;">
                    <i class="fa-solid fa-calendar"></i> View Booking
                </a>
            </div>

        </div><!-- /.feedback-item-card -->
        <?php endforeach; ?>
    </div><!-- /.feedback-item-list -->

    <?php endif; ?>

    <?php endif; ?>

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
