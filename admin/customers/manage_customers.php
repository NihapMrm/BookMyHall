<?php
/**
 * manage_customers.php — Admin: Paginated Customer List
 * Module 3 – Nishtha
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Filters & Pagination ─────────────────────────────────────────────────
$search  = sanitizeInput($_GET['q']      ?? '');
$status  = sanitizeInput($_GET['status'] ?? '');
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// ─── Build WHERE clause ───────────────────────────────────────────────────
$where  = "WHERE u.role = 'customer'";
$params = [];

if ($search !== '') {
    $where   .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (in_array($status, ['active', 'blocked'])) {
    $where   .= " AND u.status = ?";
    $params[] = $status;
}

// ─── Stats ────────────────────────────────────────────────────────────────
try {
    $stats = $pdo->query(
        "SELECT
            COUNT(*)                                                    AS total,
            SUM(status = 'active')                                      AS active,
            SUM(status = 'blocked')                                     AS blocked,
            SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS new_this_month
         FROM users WHERE role = 'customer'"
    )->fetch();
} catch (PDOException $e) {
    error_log("manage_customers stats: " . $e->getMessage());
    $stats = ['total' => 0, 'active' => 0, 'blocked' => 0, 'new_this_month' => 0];
}

// ─── Total rows for pagination ────────────────────────────────────────────
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
    $countStmt->execute($params);
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);
} catch (PDOException $e) {
    error_log("manage_customers count: " . $e->getMessage());
    $totalRows = $totalPages = 0;
}

// ─── Fetch customers with booking count ───────────────────────────────────
$customers = [];
try {
    $sql = "SELECT u.*,
                   COUNT(b.booking_id) AS total_bookings
            FROM users u
            LEFT JOIN bookings b ON b.customer_id = u.user_id AND b.is_deleted = 0
            $where
            GROUP BY u.user_id
            ORDER BY u.created_at DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("manage_customers fetch: " . $e->getMessage());
}

$flash = getFlash();
$pageTitle    = 'Customers';
$pageSubtitle = 'View, search and manage registered customers';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/customers.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-auto-dismiss>
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- ── Stats Bar ─────────────────────────────────────────────────────── -->
    <div class="cust-stats-bar">
        <div class="cust-stat-card">
            <div class="cs-icon blue"><i class="fa-solid fa-users"></i></div>
            <div class="cs-info">
                <div class="cs-val"><?= number_format($stats['total']) ?></div>
                <div class="cs-lbl">Total Customers</div>
            </div>
        </div>
        <div class="cust-stat-card">
            <div class="cs-icon green"><i class="fa-solid fa-user-check"></i></div>
            <div class="cs-info">
                <div class="cs-val"><?= number_format($stats['active']) ?></div>
                <div class="cs-lbl">Active</div>
            </div>
        </div>
        <div class="cust-stat-card">
            <div class="cs-icon red"><i class="fa-solid fa-user-slash"></i></div>
            <div class="cs-info">
                <div class="cs-val"><?= number_format($stats['blocked']) ?></div>
                <div class="cs-lbl">Blocked</div>
            </div>
        </div>
        <div class="cust-stat-card">
            <div class="cs-icon orange"><i class="fa-solid fa-user-plus"></i></div>
            <div class="cs-info">
                <div class="cs-val"><?= number_format($stats['new_this_month']) ?></div>
                <div class="cs-lbl">New This Month</div>
            </div>
        </div>
    </div>

    <!-- ── Table Card ────────────────────────────────────────────────────── -->
    <div class="cust-table-card">
        <!-- Card header + filter form -->
        <div class="card-header" style="flex-wrap:wrap;gap:12px;">
            <h2>All Customers</h2>
            <form method="GET" action="" class="filter-bar" style="margin:0;flex:1;">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q"
                           placeholder="Search name, email or phone…"
                           value="<?= htmlspecialchars($search) ?>"/>
                </div>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active"  <?= $status === 'active'  ? 'selected' : '' ?>>Active</option>
                    <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <?php if ($search || $status): ?>
                <a href="manage_customers.php" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-xmark"></i> Clear
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Bookings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">
                            <i class="fa-solid fa-users" style="font-size:32px;margin-bottom:8px;display:block;color:#c9d0fd;"></i>
                            No customers found<?= ($search || $status) ? ' matching your filters' : '' ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $i => $c): ?>
                    <?php
                        $initials = strtoupper(implode('', array_map(fn($w) => $w[0],
                            array_slice(explode(' ', $c['full_name']), 0, 2))));
                    ?>
                    <tr data-href="<?= BASE_URL ?>/admin/customers/customer_details.php?id=<?= $c['user_id'] ?>">
                        <td style="color:var(--text-muted);font-size:13px;"><?= $offset + $i + 1 ?></td>
                        <td>
                            <div class="cust-cell-user">
                                <div class="cust-avatar">
                                    <?php if (!empty($c['profile_picture'])): ?>
                                        <img src="<?= BASE_URL ?>/assets/images/profiles/<?= htmlspecialchars($c['profile_picture']) ?>" alt="">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initials) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="cu-name"><?= htmlspecialchars($c['full_name']) ?></div>
                                    <div class="cu-email"><?= htmlspecialchars($c['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:13px;"><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
                        <td style="font-size:13px;"><?= formatDateReadable($c['created_at']) ?></td>
                        <td style="font-size:13px;font-weight:600;"><?= (int)$c['total_bookings'] ?></td>
                        <td>
                            <span class="badge-status <?= htmlspecialchars($c['status']) ?>">
                                <?= ucfirst(htmlspecialchars($c['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <a href="<?= BASE_URL ?>/admin/customers/customer_details.php?id=<?= $c['user_id'] ?>"
                                   class="btn btn-outline btn-sm" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <form method="POST"
                                      action="<?= BASE_URL ?>/admin/customers/block_customer.php"
                                      style="margin:0;">
                                    <input type="hidden" name="customer_id" value="<?= $c['user_id'] ?>">
                                    <input type="hidden" name="redirect" value="manage_customers.php">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <button type="submit" class="btn btn-warning btn-sm"
                                                title="Block Customer"
                                                data-confirm="Block <?= htmlspecialchars($c['full_name']) ?>? They will not be able to log in.">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-success btn-sm"
                                                title="Unblock Customer"
                                                data-confirm="Unblock <?= htmlspecialchars($c['full_name']) ?>?">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    <?php endif; ?>
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
            <span>
                Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?>
                of <?= $totalRows ?> customers
            </span>
            <div class="pagination-links">
                <?php
                $baseUrl = '?q=' . urlencode($search) . '&status=' . urlencode($status);
                if ($page > 1): ?>
                    <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                        <i class="fa-solid fa-chevron-left" style="font-size:10px;"></i>
                    </a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p === 1 || $p === $totalPages || abs($p - $page) <= 1): ?>
                        <?php if ($p === $page): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= $baseUrl ?>&page=<?= $p ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php elseif ($p === 2 && $page > 4): ?>
                        <span class="ellipsis">…</span>
                    <?php elseif ($p === $totalPages - 1 && $page < $totalPages - 3): ?>
                        <span class="ellipsis">…</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" aria-label="Next">
                        <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/customers.js"></script>
</body>
</html>
