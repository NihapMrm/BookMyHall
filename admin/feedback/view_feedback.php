<?php
/**
 * view_feedback.php — Admin: Single feedback detail
 * Module 4 – Afrina
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
    error_log('view_feedback migration: ' . $e->getMessage());
}

$feedbackId = (int)($_GET['id'] ?? 0);
if ($feedbackId <= 0) {
    redirect(BASE_URL . '/admin/feedback/manage_feedback.php');
}

$feedback = null;
try {
    $stmt = $pdo->prepare(
        "SELECT f.*, u.full_name AS customer_name, u.email AS customer_email,
                b.booking_id, b.event_date, b.event_type, b.guest_count,
                b.total_amount, b.status AS booking_status,
                p.name AS package_name
         FROM feedback f
         JOIN users u    ON u.user_id    = f.customer_id
         JOIN bookings b ON b.booking_id = f.booking_id
         JOIN packages p ON p.package_id = b.sub_package_id
         WHERE f.feedback_id = ?"
    );
    $stmt->execute([$feedbackId]);
    $feedback = $stmt->fetch();
} catch (PDOException $e) {
    error_log('view_feedback fetch: ' . $e->getMessage());
}

if (!$feedback) {
    setFlash('error', 'Feedback not found.');
    redirect(BASE_URL . '/admin/feedback/manage_feedback.php');
}

$flash = getFlash();
$pageTitle    = 'Feedback #' . $feedbackId;
$pageSubtitle = 'Customer review detail';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/feedback.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <div style="margin-bottom:20px;">
        <a href="<?= BASE_URL ?>/admin/feedback/manage_feedback.php"
           style="font-size:.82rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Feedback
        </a>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <div class="feedback-detail-layout">

        <!-- Main feedback card -->
        <div class="feedback-main-card">
            <div class="feedback-rating-block">
                <span class="feedback-big-rating"><?= (int)$feedback['rating'] ?></span>
                <div>
                    <div class="feedback-stars-large">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fa-solid fa-star <?= $i <= (int)$feedback['rating'] ? 'filled' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:6px;">
                        out of 5 stars
                    </div>
                </div>
                <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
                    <span class="visibility-badge <?= $feedback['is_visible'] ? 'visible' : 'hidden' ?>">
                        <i class="fa-solid <?= $feedback['is_visible'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                        <?= $feedback['is_visible'] ? 'Visible to customers' : 'Hidden from customers' ?>
                    </span>
                    <form method="POST" action="<?= BASE_URL ?>/admin/feedback/toggle_feedback.php" style="display:inline;">
                        <input type="hidden" name="feedback_id" value="<?= $feedbackId ?>"/>
                        <input type="hidden" name="redirect"
                               value="<?= htmlspecialchars(BASE_URL . '/admin/feedback/view_feedback.php?id=' . $feedbackId) ?>"/>
                        <button type="submit" class="btn btn-sm <?= $feedback['is_visible'] ? 'btn-outline' : 'btn-success' ?>"
                                data-confirm="Toggle visibility of this feedback?">
                            <?= $feedback['is_visible'] ? '<i class="fa-solid fa-eye-slash"></i> Hide' : '<i class="fa-solid fa-eye"></i> Show' ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($feedback['comment']): ?>
            <p class="feedback-comment-full"><?= nl2br(htmlspecialchars($feedback['comment'])) ?></p>
            <?php else: ?>
            <p style="color:var(--text-muted);font-style:italic;">No written comment provided.</p>
            <?php endif; ?>

            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #eaedf7;font-size:.78rem;color:var(--text-muted);">
                <i class="fa-regular fa-clock"></i>
                Submitted on <?= htmlspecialchars(formatDateReadable($feedback['created_at'])) ?>
            </div>
        </div>

        <!-- Side info -->
        <div>

            <!-- Customer info -->
            <div class="feedback-side-card" style="margin-bottom:16px;">
                <div class="detail-card-title" style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #eaedf7;">
                    <i class="fa-solid fa-user"></i> &nbsp;Customer
                </div>
                <div class="feedback-meta-row">
                    <span class="feedback-meta-label">Name</span>
                    <span class="feedback-meta-value"><?= htmlspecialchars($feedback['customer_name']) ?></span>
                </div>
                <div class="feedback-meta-row">
                    <span class="feedback-meta-label">Email</span>
                    <span class="feedback-meta-value" style="font-size:.8rem;"><?= htmlspecialchars($feedback['customer_email']) ?></span>
                </div>
            </div>

            <!-- Booking info -->
            <div class="feedback-side-card">
                <div class="detail-card-title" style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #eaedf7;">
                    <i class="fa-solid fa-calendar-check"></i> &nbsp;Booking
                </div>
                <div class="feedback-meta-row">
                    <span class="feedback-meta-label">Booking ID</span>
                    <span class="feedback-meta-value">
                        <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $feedback['booking_id'] ?>"
                           style="color:var(--primary);">#<?= $feedback['booking_id'] ?></a>
                    </span>
                </div>
                <div class="feedback-meta-row">
                    <span class="feedback-meta-label">Package</span>
                    <span class="feedback-meta-value"><?= htmlspecialchars($feedback['package_name']) ?></span>
                </div>
                <div class="feedback-meta-row">
                    <span class="feedback-meta-label">Event Date</span>
                    <span class="feedback-meta-value"><?= htmlspecialchars(formatDateReadable($feedback['event_date'])) ?></span>
                </div>
                <div class="feedback-meta-row">
                    <span class="feedback-meta-label">Event Type</span>
                    <span class="feedback-meta-value"><?= htmlspecialchars($feedback['event_type'] ?: '—') ?></span>
                </div>
                <div class="feedback-meta-row">
                    <span class="feedback-meta-label">Total Amount</span>
                    <span class="feedback-meta-value"><?= htmlspecialchars(formatCurrency((float)$feedback['total_amount'])) ?></span>
                </div>
            </div>
        </div>

    </div><!-- /.feedback-detail-layout -->

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
