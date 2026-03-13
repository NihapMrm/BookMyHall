<?php
/**
 * payment_details.php — Admin: Single payment record + transaction audit trail
 *                       Includes inline update-status form
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$paymentId = (int)($_GET['id'] ?? 0);
if ($paymentId <= 0) {
    redirect(BASE_URL . '/admin/payments/manage_payments.php');
}

// ─── Fetch payment ─────────────────────────────────────────────────────────────
$payment = null;
try {
    $stmt = $pdo->prepare(
        "SELECT p.*,
                b.booking_id AS bk_id, b.event_date, b.start_time, b.end_time,
                b.total_amount AS bk_total, b.advance_amount AS bk_advance,
                b.balance_amount AS bk_balance, b.status AS bk_status,
                b.event_type,
                u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                pkg.name AS package_name,
                h.name   AS hall_name
         FROM payments p
         JOIN bookings b  ON b.booking_id  = p.booking_id
         JOIN users u     ON u.user_id     = b.customer_id
         JOIN packages pkg ON pkg.package_id = b.sub_package_id
         JOIN hall h      ON h.hall_id     = b.hall_id
         WHERE p.payment_id = ?"
    );
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
} catch (PDOException $e) { error_log('payment_details fetch: ' . $e->getMessage()); }

if (!$payment) {
    setFlash('error', 'Payment not found.');
    redirect(BASE_URL . '/admin/payments/manage_payments.php');
}

// ─── Fetch transaction history ─────────────────────────────────────────────────
$transactions = [];
try {
    $txStmt = $pdo->prepare(
        "SELECT t.*, u.full_name AS changed_by_name
         FROM transactions t
         JOIN users u ON u.user_id = t.changed_by
         WHERE t.payment_id = ?
         ORDER BY t.created_at ASC"
    );
    $txStmt->execute([$paymentId]);
    $transactions = $txStmt->fetchAll();
} catch (PDOException $e) { error_log('payment_details transactions: ' . $e->getMessage()); }

// ─── Other payments for same booking ──────────────────────────────────────────
$siblingPayments = [];
try {
    $sibStmt = $pdo->prepare(
        "SELECT * FROM payments WHERE booking_id = ? AND payment_id != ? ORDER BY created_at ASC"
    );
    $sibStmt->execute([$payment['booking_id'], $paymentId]);
    $siblingPayments = $sibStmt->fetchAll();
} catch (PDOException $e) { error_log('payment_details siblings: ' . $e->getMessage()); }

$flash = getFlash();

// Status transition rules
$allowedTransitions = [
    'pending'  => ['paid', 'failed'],
    'paid'     => ['refunded'],
    'failed'   => ['pending'],
    'refunded' => [],
];
$nextStatuses = $allowedTransitions[$payment['status']] ?? [];

$pageTitle    = 'Payment #' . $paymentId;
$pageSubtitle = 'Full payment record and transaction history';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/payments.css"/>
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

    <!-- Back link -->
    <div class="page-header" style="margin-bottom:24px;">
        <div>
            <a href="<?= BASE_URL ?>/admin/payments/manage_payments.php"
               style="font-size:.82rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:6px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Payments
            </a>
            <h1 class="page-title">Payment #<?= $paymentId ?></h1>
        </div>
        <div class="page-header-actions">
            <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $payment['bk_id'] ?>" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-calendar-check"></i> View Booking #<?= $payment['bk_id'] ?>
            </a>
        </div>
    </div>

    <!-- Status Update (inline) -->
    <?php if (!empty($nextStatuses)): ?>
    <div class="status-update-card">
        <h3><i class="fa-solid fa-rotate"></i> Update Payment Status</h3>
        <form method="POST" action="<?= BASE_URL ?>/admin/payments/update_payment.php">
            <input type="hidden" name="payment_id" value="<?= $paymentId ?>">
            <div class="status-update-row">
                <div class="form-group">
                    <label>Current Status</label>
                    <div style="padding:9px 0;font-weight:600;">
                        <span class="badge-payment <?= htmlspecialchars($payment['status']) ?>"><?= ucfirst($payment['status']) ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_status">Change To <span style="color:var(--danger)">*</span></label>
                    <select name="new_status" id="new_status" class="form-control" required>
                        <?php foreach ($nextStatuses as $ns): ?>
                        <option value="<?= htmlspecialchars($ns) ?>"><?= ucfirst($ns) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:2;">
                    <label for="txn_note">Note (optional)</label>
                    <input type="text" name="note" id="txn_note" class="form-control"
                           placeholder="Reason or reference for this status change…"/>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary"
                            data-confirm="Are you sure you want to update this payment status?">
                        <i class="fa-solid fa-check"></i> Update
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Detail Grid -->
    <div class="detail-grid">

        <!-- Payment Info -->
        <div class="detail-section">
            <h3><i class="fa-solid fa-receipt" style="margin-right:6px;color:var(--primary);"></i> Payment Details</h3>
            <div class="detail-row">
                <span class="detail-label">Payment ID</span>
                <span class="detail-value">#<?= $paymentId ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value"><span class="badge-type <?= htmlspecialchars($payment['payment_type']) ?>"><?= ucfirst($payment['payment_type']) ?></span></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount</span>
                <span class="detail-value amount-highlight"><?= formatCurrency((float)$payment['amount']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Method</span>
                <span class="detail-value"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $payment['method']))) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value"><span class="badge-payment <?= htmlspecialchars($payment['status']) ?>"><?= ucfirst($payment['status']) ?></span></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Reference</span>
                <span class="detail-value"><?= $payment['reference'] ? htmlspecialchars($payment['reference']) : '<span style="color:var(--text-muted)">—</span>' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Recorded At</span>
                <span class="detail-value"><?= htmlspecialchars(date('d M Y, h:i A', strtotime($payment['created_at']))) ?></span>
            </div>
            <?php if ($payment['updated_at']): ?>
            <div class="detail-row">
                <span class="detail-label">Last Updated</span>
                <span class="detail-value"><?= htmlspecialchars(date('d M Y, h:i A', strtotime($payment['updated_at']))) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($payment['notes']): ?>
            <div class="detail-row">
                <span class="detail-label">Notes</span>
                <span class="detail-value" style="font-style:italic;"><?= htmlspecialchars($payment['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Booking + Customer Info -->
        <div class="detail-section">
            <h3><i class="fa-solid fa-calendar-check" style="margin-right:6px;color:var(--primary);"></i> Booking Details</h3>
            <div class="detail-row">
                <span class="detail-label">Booking</span>
                <span class="detail-value">
                    <a href="<?= BASE_URL ?>/admin/bookings/booking_details.php?id=<?= $payment['bk_id'] ?>"
                       style="color:var(--primary);font-weight:600;">#<?= $payment['bk_id'] ?></a>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Customer</span>
                <span class="detail-value"><?= htmlspecialchars($payment['customer_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value" style="font-size:.85rem;"><?= htmlspecialchars($payment['customer_email']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($payment['customer_phone'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Hall</span>
                <span class="detail-value"><?= htmlspecialchars($payment['hall_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Package</span>
                <span class="detail-value"><?= htmlspecialchars($payment['package_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Event Date</span>
                <span class="detail-value"><?= htmlspecialchars(formatDateReadable($payment['event_date'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time</span>
                <span class="detail-value"><?= htmlspecialchars(substr($payment['start_time'],0,5)) ?> – <?= htmlspecialchars(substr($payment['end_time'],0,5)) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Booking Status</span>
                <span class="detail-value"><span class="badge-status <?= htmlspecialchars($payment['bk_status']) ?>"><?= ucfirst($payment['bk_status']) ?></span></span>
            </div>
        </div>

    </div>

    <!-- Booking Amount Summary -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h2>Booking Financial Summary</h2></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0;border-top:1px solid #f0f3fb;">
            <?php
            $summaryItems = [
                ['Total Amount',   formatCurrency((float)$payment['bk_total']),   'var(--text-main)'],
                ['Advance Due',    formatCurrency((float)$payment['bk_advance']),  'var(--warning)'],
                ['Balance Due',    formatCurrency((float)$payment['bk_balance']),  'var(--info)'],
            ];
            ?>
            <?php foreach ($summaryItems as $item): ?>
            <div style="padding:20px 24px;border-right:1px solid #f0f3fb;">
                <div style="font-size:.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">
                    <?= $item[0] ?>
                </div>
                <div style="font-size:1.3rem;font-weight:700;color:<?= $item[2] ?>;"><?= $item[1] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Other payments for same booking -->
    <?php if (!empty($siblingPayments)): ?>
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h2>Other Payments for Booking #<?= $payment['bk_id'] ?></h2></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th><th>Type</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($siblingPayments as $sp): ?>
                <tr>
                    <td>#<?= $sp['payment_id'] ?></td>
                    <td><span class="badge-type <?= htmlspecialchars($sp['payment_type']) ?>"><?= ucfirst($sp['payment_type']) ?></span></td>
                    <td><?= htmlspecialchars(ucwords(str_replace('_',' ', $sp['method']))) ?></td>
                    <td class="amount-col"><?= formatCurrency((float)$sp['amount']) ?></td>
                    <td><span class="badge-payment <?= htmlspecialchars($sp['status']) ?>"><?= ucfirst($sp['status']) ?></span></td>
                    <td style="font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars(date('d M Y', strtotime($sp['created_at']))) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/payments/payment_details.php?id=<?= $sp['payment_id'] ?>" class="btn btn-outline btn-sm">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transaction Audit Trail -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-timeline" style="color:var(--primary);margin-right:6px;"></i> Status Change History</h2>
        </div>
        <?php if (empty($transactions)): ?>
        <div style="text-align:center;padding:36px;color:var(--text-muted);">
            <i class="fa-solid fa-timeline" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3;"></i>
            No status changes recorded yet.
        </div>
        <?php else: ?>
        <div style="padding:8px 0 12px;">
            <div class="transaction-timeline">
                <?php foreach ($transactions as $tx): ?>
                <div class="txn-item">
                    <div class="txn-header">
                        <span class="badge-payment <?= htmlspecialchars($tx['old_status']) ?>"><?= ucfirst($tx['old_status']) ?></span>
                        <i class="fa-solid fa-arrow-right txn-arrow"></i>
                        <span class="badge-payment <?= htmlspecialchars($tx['new_status']) ?>"><?= ucfirst($tx['new_status']) ?></span>
                        <span class="txn-date"><?= htmlspecialchars(date('d M Y, h:i A', strtotime($tx['created_at']))) ?></span>
                    </div>
                    <?php if ($tx['note']): ?>
                    <div class="txn-note"><?= htmlspecialchars($tx['note']) ?></div>
                    <?php endif; ?>
                    <div class="txn-by"><i class="fa-solid fa-user" style="margin-right:4px;"></i> Changed by: <?= htmlspecialchars($tx['changed_by_name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/payments.js"></script>
</body>
</html>
