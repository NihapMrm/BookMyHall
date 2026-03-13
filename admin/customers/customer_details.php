<?php
/**
 * customer_details.php — Admin: Full Customer Profile View
 * Module 3 – Nishtha
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$customerId = (int)($_GET['id'] ?? 0);
if (!$customerId) {
    setFlash('error', 'Invalid customer ID.');
    redirect(BASE_URL . '/admin/customers/manage_customers.php');
}

// ─── Fetch customer ───────────────────────────────────────────────────────
$customer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
} catch (PDOException $e) {
    error_log("customer_details fetch: " . $e->getMessage());
}

if (!$customer) {
    setFlash('error', 'Customer not found.');
    redirect(BASE_URL . '/admin/customers/manage_customers.php');
}

// ─── Booking stats ────────────────────────────────────────────────────────
$bookingStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'completed' => 0, 'cancelled' => 0, 'rejected' => 0, 'total_spent' => 0];
try {
    $s = $pdo->prepare(
        "SELECT
            COUNT(*)                          AS total,
            SUM(status = 'pending')           AS pending,
            SUM(status = 'approved')          AS approved,
            SUM(status = 'completed')         AS completed,
            SUM(status = 'cancelled')         AS cancelled,
            SUM(status = 'rejected')          AS rejected,
            COALESCE(SUM(CASE WHEN status IN ('approved','completed') THEN total_amount END), 0) AS total_spent
         FROM bookings
         WHERE customer_id = ? AND is_deleted = 0"
    );
    $s->execute([$customerId]);
    $bookingStats = $s->fetch();
} catch (PDOException $e) {
    error_log("customer_details stats: " . $e->getMessage());
}

// ─── Recent bookings (last 10) ────────────────────────────────────────────
$recentBookings = [];
try {
    $s = $pdo->prepare(
        "SELECT b.booking_id, b.event_date, b.start_time, b.end_time,
                b.status, b.total_amount, b.advance_amount, b.balance_amount,
                b.event_type, p.name AS package_name
         FROM bookings b
         LEFT JOIN packages p ON p.package_id = b.sub_package_id
         WHERE b.customer_id = ? AND b.is_deleted = 0
         ORDER BY b.created_at DESC
         LIMIT 10"
    );
    $s->execute([$customerId]);
    $recentBookings = $s->fetchAll();
} catch (PDOException $e) {
    error_log("customer_details bookings: " . $e->getMessage());
}

// ─── Payment history (last 10) ────────────────────────────────────────────
$payments = [];
try {
    $s = $pdo->prepare(
        "SELECT pay.payment_id, pay.amount, pay.payment_type, pay.method,
                pay.status, pay.recorded_at, b.booking_id, b.event_date
         FROM payments pay
         JOIN bookings b ON b.booking_id = pay.booking_id
         WHERE b.customer_id = ? AND b.is_deleted = 0
         ORDER BY pay.recorded_at DESC
         LIMIT 10"
    );
    $s->execute([$customerId]);
    $payments = $s->fetchAll();
} catch (PDOException $e) {
    error_log("customer_details payments: " . $e->getMessage());
}

// ─── Feedback submitted ───────────────────────────────────────────────────
$feedbackList = [];
try {
    $s = $pdo->prepare(
        "SELECT f.rating, f.comment, f.submitted_at, b.booking_id, b.event_date
         FROM feedback f
         JOIN bookings b ON b.booking_id = f.booking_id
         WHERE f.customer_id = ?
         ORDER BY f.submitted_at DESC
         LIMIT 5"
    );
    $s->execute([$customerId]);
    $feedbackList = $s->fetchAll();
} catch (PDOException $e) {
    error_log("customer_details feedback: " . $e->getMessage());
}

$flash = getFlash();
$initials = strtoupper(implode('', array_map(fn($w) => $w[0],
    array_slice(explode(' ', $customer['full_name']), 0, 2))));

$pageTitle    = htmlspecialchars($customer['full_name']);
$pageSubtitle = 'Customer profile & booking history';
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

    <!-- Back link -->
    <div style="margin-bottom:16px;">
        <a href="manage_customers.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Customers
        </a>
    </div>

    <!-- ── Main Layout ───────────────────────────────────────────────────── -->
    <div class="cdetail-layout">

        <!-- ── Profile Card (left) ───────────────────────────────────────── -->
        <aside>
            <div class="cdetail-profile-card">
                <!-- Avatar -->
                <div class="cdetail-avatar">
                    <?php if (!empty($customer['profile_picture'])): ?>
                        <img src="<?= BASE_URL ?>/assets/images/profiles/<?= htmlspecialchars($customer['profile_picture']) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars($initials) ?>
                    <?php endif; ?>
                </div>

                <p class="cdetail-name"><?= htmlspecialchars($customer['full_name']) ?></p>
                <p class="cdetail-since">
                    <span class="badge-status <?= htmlspecialchars($customer['status']) ?>">
                        <?= ucfirst(htmlspecialchars($customer['status'])) ?>
                    </span>
                </p>

                <hr class="cdetail-divider">

                <div class="cdetail-info-row">
                    <i class="fa-solid fa-envelope"></i>
                    <span><?= htmlspecialchars($customer['email']) ?></span>
                </div>
                <?php if ($customer['phone']): ?>
                <div class="cdetail-info-row">
                    <i class="fa-solid fa-phone"></i>
                    <span><?= htmlspecialchars($customer['phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($customer['address']): ?>
                <div class="cdetail-info-row">
                    <i class="fa-solid fa-location-dot"></i>
                    <span><?= htmlspecialchars($customer['address']) ?></span>
                </div>
                <?php endif; ?>
                <div class="cdetail-info-row">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Joined <?= formatDateReadable($customer['created_at']) ?></span>
                </div>

                <hr class="cdetail-divider">

                <!-- Block / Unblock -->
                <?php if ($customer['status'] === 'active'): ?>
                <div class="status-banner active">
                    <p><i class="fa-solid fa-circle-check"></i> Account is active</p>
                </div>
                <form method="POST" action="block_customer.php" style="margin-top:12px;">
                    <input type="hidden" name="customer_id" value="<?= $customer['user_id'] ?>">
                    <input type="hidden" name="redirect" value="customer_details.php?id=<?= $customer['user_id'] ?>">
                    <button type="submit" class="btn btn-warning btn-full"
                            data-confirm="Block this customer? They will not be able to log in.">
                        <i class="fa-solid fa-ban"></i> Block Account
                    </button>
                </form>
                <?php else: ?>
                <div class="status-banner blocked">
                    <p><i class="fa-solid fa-ban"></i> Account is blocked</p>
                </div>
                <form method="POST" action="block_customer.php" style="margin-top:12px;">
                    <input type="hidden" name="customer_id" value="<?= $customer['user_id'] ?>">
                    <input type="hidden" name="redirect" value="customer_details.php?id=<?= $customer['user_id'] ?>">
                    <button type="submit" class="btn btn-success btn-full"
                            data-confirm="Unblock this customer?">
                        <i class="fa-solid fa-circle-check"></i> Unblock Account
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </aside>

        <!-- ── Right Column ───────────────────────────────────────────────── -->
        <div class="cdetail-right">

            <!-- Mini Stats -->
            <div class="cdetail-mini-stats">
                <div class="cdetail-mini-stat">
                    <div class="ms-val"><?= (int)$bookingStats['total'] ?></div>
                    <div class="ms-lbl">Total Bookings</div>
                </div>
                <div class="cdetail-mini-stat">
                    <div class="ms-val"><?= (int)$bookingStats['completed'] ?></div>
                    <div class="ms-lbl">Completed</div>
                </div>
                <div class="cdetail-mini-stat">
                    <div class="ms-val"><?= formatCurrency((float)$bookingStats['total_spent']) ?></div>
                    <div class="ms-lbl">Total Spent</div>
                </div>
            </div>

            <!-- Booking History Table -->
            <div class="cdetail-section-card">
                <div class="cds-header">
                    <h3><i class="fa-solid fa-calendar-check"></i> Booking History</h3>
                    <span style="font-size:12px;color:var(--text-muted);">Latest 10</span>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Event Date</th>
                                <th>Package</th>
                                <th>Event Type</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentBookings)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">
                                    No bookings found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentBookings as $b): ?>
                            <tr>
                                <td style="font-size:12px;color:var(--text-muted);">#<?= $b['booking_id'] ?></td>
                                <td style="font-size:13px;"><?= formatDateReadable($b['event_date']) ?></td>
                                <td style="font-size:13px;"><?= htmlspecialchars($b['package_name'] ?? '—') ?></td>
                                <td style="font-size:13px;"><?= htmlspecialchars($b['event_type'] ?? '—') ?></td>
                                <td style="font-size:13px;font-weight:600;"><?= formatCurrency((float)$b['total_amount']) ?></td>
                                <td><span class="badge-status <?= htmlspecialchars($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment History Table -->
            <div class="cdetail-section-card">
                <div class="cds-header">
                    <h3><i class="fa-solid fa-credit-card"></i> Payment Records</h3>
                    <span style="font-size:12px;color:var(--text-muted);">Latest 10</span>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Event Date</th>
                                <th>Type</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Recorded</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">
                                    No payments found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td style="font-size:12px;color:var(--text-muted);">#<?= $pay['booking_id'] ?></td>
                                <td style="font-size:13px;"><?= formatDateReadable($pay['event_date']) ?></td>
                                <td style="font-size:13px;"><?= ucfirst(htmlspecialchars($pay['payment_type'])) ?></td>
                                <td style="font-size:13px;"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($pay['method']))) ?></td>
                                <td style="font-size:13px;font-weight:600;"><?= formatCurrency((float)$pay['amount']) ?></td>
                                <td><span class="badge-status <?= htmlspecialchars($pay['status']) ?>"><?= ucfirst($pay['status']) ?></span></td>
                                <td style="font-size:12px;color:var(--text-muted);"><?= formatDateReadable($pay['recorded_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feedback Given -->
            <?php if (!empty($feedbackList)): ?>
            <div class="cdetail-section-card">
                <div class="cds-header">
                    <h3><i class="fa-solid fa-star"></i> Feedback Given</h3>
                </div>
                <div style="padding:16px 24px;display:flex;flex-direction:column;gap:14px;">
                    <?php foreach ($feedbackList as $fb): ?>
                    <div style="border:1px solid #eaedf7;border-radius:var(--radius-sm);padding:14px 18px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <span style="font-size:13px;color:var(--text-muted);">
                                Booking #<?= $fb['booking_id'] ?> — <?= formatDateReadable($fb['event_date']) ?>
                            </span>
                            <span style="color:#f39c12;font-size:13px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-<?= $i <= $fb['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                <?php endfor; ?>
                            </span>
                        </div>
                        <?php if ($fb['comment']): ?>
                        <p style="margin:0;font-size:13px;color:var(--text-muted);line-height:1.5;">
                            <?= htmlspecialchars($fb['comment']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.cdetail-right -->

    </div><!-- /.cdetail-layout -->

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/customers.js"></script>
</body>
</html>
