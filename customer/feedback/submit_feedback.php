<?php
/**
 * submit_feedback.php — Customer: leave feedback on a completed booking
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
    error_log('submit_feedback migration: ' . $e->getMessage());
}

$customerId = (int)$_SESSION['customer_id'];
$bookingId  = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

if ($bookingId <= 0) {
    setFlash('error', 'Invalid booking reference.');
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

// Validate: booking must be completed, belong to this customer, and have no feedback yet
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
    error_log('submit_feedback booking fetch: ' . $e->getMessage());
    $booking = null;
}

if (!$booking) {
    setFlash('error', 'Booking not found or access denied.');
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

// Allow feedback for completed bookings or approved bookings (so customers can review after confirmation).
if (!in_array($booking['status'], ['completed', 'approved'], true)) {
    setFlash('error', 'Feedback can only be submitted after your booking is confirmed and completed.');
    redirect(BASE_URL . '/customer/bookings/booking_details.php?id=' . $bookingId);
}

// Check if already submitted
try {
    $fbCheck = $pdo->prepare("SELECT feedback_id FROM feedback WHERE booking_id = ? LIMIT 1");
    $fbCheck->execute([$bookingId]);
    if ($fbCheck->fetchColumn()) {
        setFlash('error', 'You have already submitted feedback for this booking.');
        redirect(BASE_URL . '/customer/bookings/booking_details.php?id=' . $bookingId);
    }
} catch (PDOException $e) {
    error_log('submit_feedback check: ' . $e->getMessage());
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = (int)($_POST['rating']   ?? 0);
    $comment = sanitizeInput($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5 stars.';
    } elseif (strlen(trim($comment)) < 10) {
        $error = 'Comment must be at least 10 characters.';
    } else {
        try {
            $ins = $pdo->prepare(
                "INSERT INTO feedback (booking_id, customer_id, rating, comment, is_visible, created_at)
                 VALUES (?, ?, ?, ?, 1, NOW())"
            );
            $ins->execute([$bookingId, $customerId, $rating, $comment]);
            setFlash('success', 'Thank you! Your feedback has been submitted.');
            redirect(BASE_URL . '/customer/feedback/my_feedback.php');
        } catch (PDOException $e) {
            error_log('submit_feedback insert: ' . $e->getMessage());
            $error = 'Failed to submit feedback. Please try again.';
        }
    }
}

$pageTitle    = 'Leave Feedback';
$pageSubtitle = 'Share your experience with us';
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

    <div style="max-width:640px;margin:0 auto;">

        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:18px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Back button -->
        <div style="margin-bottom:20px;">
            <a href="<?= BASE_URL ?>/customer/bookings/booking_details.php?id=<?= $bookingId ?>"
               class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back to Booking Details
            </a>
        </div>

        <div class="feedback-form-card">

            <!-- Booking reference -->
            <div class="feedback-form-booking-ref">
                <i class="fa-solid fa-calendar-check" style="color:var(--success);margin-right:8px;"></i>
                <strong><?= htmlspecialchars($booking['package_name']) ?></strong>
                &nbsp;—&nbsp; <?= htmlspecialchars(formatDateReadable($booking['event_date'])) ?>
            </div>

            <form method="POST" id="feedback-form" novalidate>
                <input type="hidden" name="booking_id" value="<?= $bookingId ?>"/>

                <!-- Star rating -->
                <div class="form-group" style="margin-bottom:28px;">
                    <label class="form-label" style="font-size:1rem;font-weight:600;">
                        Your Rating <span style="color:var(--danger)">*</span>
                    </label>
                    <div class="star-rating-input" id="star-rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio"
                               id="star<?= $i ?>"
                               name="rating"
                               value="<?= $i ?>"
                               <?= isset($_POST['rating']) && (int)$_POST['rating'] === $i ? 'checked' : '' ?>/>
                        <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                            <i class="fa-solid fa-star"></i>
                        </label>
                        <?php endfor; ?>
                    </div>
                    <div class="star-label" id="star-label">
                        <?php if (isset($_POST['rating'])): ?>
                        <?= ['','Poor','Fair','Good','Very Good','Excellent'][(int)$_POST['rating']] ?>
                        <?php else: ?>
                        Click a star to rate
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Comment -->
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" for="comment">
                        Your Review <span style="color:var(--danger)">*</span>
                    </label>
                    <textarea class="form-control" id="comment" name="comment"
                              rows="5" placeholder="Tell us about your experience — the hall, service, ambience…"
                              required><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
                    <span class="form-hint">Minimum 10 characters.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-full" id="submit-feedback-btn">
                    <i class="fa-solid fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
        </div>

    </div><!-- /max-width -->

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/customer/feedback.js"></script>
</body>
</html>
