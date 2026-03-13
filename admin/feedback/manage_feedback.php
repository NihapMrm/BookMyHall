<?php
/**
 * manage_feedback.php — Admin: All Feedback with filters
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
    error_log('manage_feedback migration: ' . $e->getMessage());
}

// ─── Filters ───────────────────────────────────────────────────────────────
$ratingFilter  = (int)($_GET['rating']     ?? 0);
$visibleFilter = sanitizeInput($_GET['visible'] ?? '');
$search        = sanitizeInput($_GET['q']       ?? '');
$perPage       = 12;
$page          = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page - 1) * $perPage;

// ─── Stats ─────────────────────────────────────────────────────────────────
$stats = ['total'=>0,'avg'=>0,'visible'=>0,'hidden'=>0];
try {
    $row = $pdo->query(
        "SELECT COUNT(*) AS total, ROUND(AVG(rating),1) AS avg,
                SUM(is_visible=1) AS visible, SUM(is_visible=0) AS hidden
         FROM feedback"
    )->fetch();
    if ($row) $stats = $row;
} catch (PDOException $e) { error_log('manage_feedback stats: ' . $e->getMessage()); }

// ─── Build WHERE ───────────────────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = [];

if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $where   .= " AND f.rating = ?";
    $params[] = $ratingFilter;
}
if ($visibleFilter === '1') {
    $where .= " AND f.is_visible = 1";
} elseif ($visibleFilter === '0') {
    $where .= " AND f.is_visible = 0";
}
if ($search !== '') {
    $where   .= " AND (u.full_name LIKE ? OR f.comment LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
}

// ─── Count ─────────────────────────────────────────────────────────────────
$totalRows  = 0;
$totalPages = 1;
$feedbacks  = [];
try {
    $countSql  = "SELECT COUNT(*) FROM feedback f
                  JOIN users u ON u.user_id = f.customer_id $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage) ?: 1;

    $sql = "SELECT f.*, u.full_name AS customer_name,
                   b.event_date, b.event_type,
                   p.name AS package_name
            FROM feedback f
            JOIN users u    ON u.user_id    = f.customer_id
            JOIN bookings b ON b.booking_id = f.booking_id
            JOIN packages p ON p.package_id = b.package_id
            $where
            ORDER BY f.created_at DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $feedbacks = $stmt->fetchAll();
} catch (PDOException $e) { error_log('manage_feedback fetch: ' . $e->getMessage()); }

$flash = getFlash();

function starHtml(int $rating): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $i <= $rating ? 'filled' : '';
        $out .= "<i class='fa-solid fa-star {$cls}'></i>";
    }
    return $out;
}

$pageTitle    = 'Feedback';
$pageSubtitle = 'Review and manage customer feedback';
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

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="feedback-stats">
        <div class="fstat-card">
            <div class="fstat-icon total"><i class="fa-solid fa-comments"></i></div>
            <div class="fstat-info">
                <div class="fstat-value"><?= (int)$stats['total'] ?></div>
                <div class="fstat-label">Total Feedback</div>
            </div>
        </div>
        <div class="fstat-card">
            <div class="fstat-icon avg">
                <i class="fa-solid fa-star"></i>
            </div>
            <div class="fstat-info">
                <div class="fstat-value"><?= number_format((float)($stats['avg'] ?? 0), 1) ?></div>
                <div class="fstat-label">Average Rating</div>
            </div>
        </div>
        <div class="fstat-card">
            <div class="fstat-icon visible"><i class="fa-solid fa-eye"></i></div>
            <div class="fstat-info">
                <div class="fstat-value"><?= (int)$stats['visible'] ?></div>
                <div class="fstat-label">Visible</div>
            </div>
        </div>
        <div class="fstat-card">
            <div class="fstat-icon hidden"><i class="fa-solid fa-eye-slash"></i></div>
            <div class="fstat-info">
                <div class="fstat-value"><?= (int)$stats['hidden'] ?></div>
                <div class="fstat-label">Hidden</div>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="GET">
        <div class="feedback-filter-bar">
            <input type="search" name="q" placeholder="Search customer or comment…"
                   value="<?= htmlspecialchars($search) ?>" style="min-width:220px;"/>
            <select name="rating">
                <option value="">All ratings</option>
                <?php for ($r = 5; $r >= 1; $r--): ?>
                <option value="<?= $r ?>" <?= $ratingFilter === $r ? 'selected' : '' ?>>
                    <?= $r ?> star<?= $r > 1 ? 's' : '' ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="visible">
                <option value="">All visibility</option>
                <option value="1" <?= $visibleFilter === '1' ? 'selected' : '' ?>>Visible</option>
                <option value="0" <?= $visibleFilter === '0' ? 'selected' : '' ?>>Hidden</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <a href="<?= BASE_URL ?>/admin/feedback/manage_feedback.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>

    <!-- Table -->
    <div class="feedback-table-card">
        <div class="card-header" style="padding:18px 24px; border-bottom:1px solid #eaedf7; display:flex; align-items:center; justify-content:space-between;">
            <span class="card-title">
                All Feedback
                <span style="font-size:.8rem;font-weight:500;color:var(--text-muted);margin-left:8px;">(<?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?>)</span>
            </span>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Package / Event</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Visibility</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($feedbacks)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">
                            <i class="fa-solid fa-comments" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.4;"></i>
                            No feedback found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($feedbacks as $fb): ?>
                    <tr>
                        <td><strong>#<?= $fb['feedback_id'] ?></strong></td>
                        <td style="font-weight:600;font-size:.88rem;"><?= htmlspecialchars($fb['customer_name']) ?></td>
                        <td>
                            <div style="font-size:.88rem;font-weight:600;"><?= htmlspecialchars($fb['package_name']) ?></div>
                            <div style="font-size:.75rem;color:var(--text-muted);">
                                <?= htmlspecialchars($fb['event_type'] ?: '—') ?> &middot;
                                <?= htmlspecialchars(formatDateReadable($fb['event_date'])) ?>
                            </div>
                        </td>
                        <td>
                            <div class="stars-display">
                                <?= starHtml((int)$fb['rating']) ?>
                                <span class="rating-number"><?= (int)$fb['rating'] ?>.0</span>
                            </div>
                        </td>
                        <td>
                            <div class="feedback-comment-cell" title="<?= htmlspecialchars($fb['comment'] ?? '') ?>">
                                <?= htmlspecialchars($fb['comment'] ?: '—') ?>
                            </div>
                        </td>
                        <td>
                            <span class="visibility-badge <?= $fb['is_visible'] ? 'visible' : 'hidden' ?>">
                                <i class="fa-solid <?= $fb['is_visible'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                <?= $fb['is_visible'] ? 'Visible' : 'Hidden' ?>
                            </span>
                        </td>
                        <td style="font-size:.78rem;color:var(--text-muted);">
                            <?= htmlspecialchars(formatDateReadable($fb['created_at'])) ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="<?= BASE_URL ?>/admin/feedback/view_feedback.php?id=<?= $fb['feedback_id'] ?>"
                                   class="btn btn-sm btn-outline" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <form method="POST" action="<?= BASE_URL ?>/admin/feedback/toggle_feedback.php" style="display:inline;">
                                    <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>"/>
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars(BASE_URL . '/admin/feedback/manage_feedback.php') ?>"/>
                                    <button type="submit" class="btn btn-sm <?= $fb['is_visible'] ? 'btn-warning' : 'btn-success' ?>"
                                            title="<?= $fb['is_visible'] ? 'Hide' : 'Show' ?>">
                                        <i class="fa-solid <?= $fb['is_visible'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $qs   = http_build_query(array_filter(['q'=>$search,'rating'=>$ratingFilter,'visible'=>$visibleFilter]));
            $base = BASE_URL . '/admin/feedback/manage_feedback.php?' . ($qs ? $qs . '&' : '');
            ?>
            <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>"
               href="<?= $base ?>page=<?= $page - 1 ?>">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="page-link <?= $p === $page ? 'active' : '' ?>"
               href="<?= $base ?>page=<?= $p ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>"
               href="<?= $base ?>page=<?= $page + 1 ?>">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /.feedback-table-card -->

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
