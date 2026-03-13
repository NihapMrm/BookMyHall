<?php
/**
 * add_payment.php — Admin: Record a new payment for a booking
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// ─── Fetch approved/completed bookings that still have outstanding amounts ────
$bookings = [];
try {
    $bkStmt = $pdo->query(
        "SELECT b.booking_id, b.event_date, b.total_amount, b.advance_amount, b.balance_amount,
                b.status AS booking_status,
                u.full_name  AS customer_name,
                p.name       AS package_name,
                COALESCE(SUM(CASE WHEN py.status = 'paid' AND py.payment_type = 'advance' THEN py.amount ELSE 0 END), 0) AS paid_advance,
                COALESCE(SUM(CASE WHEN py.status = 'paid' AND py.payment_type = 'balance' THEN py.amount ELSE 0 END), 0) AS paid_balance,
                COALESCE(SUM(CASE WHEN py.status = 'paid' THEN py.amount ELSE 0 END), 0) AS paid_total
         FROM bookings b
         JOIN users u    ON u.user_id    = b.customer_id
         JOIN packages p ON p.package_id = b.package_id
         LEFT JOIN payments py ON py.booking_id = b.booking_id
         WHERE b.is_deleted = 0
           AND b.status IN ('approved','completed')
         GROUP BY b.booking_id
         ORDER BY b.event_date DESC"
    );
    $bookings = $bkStmt->fetchAll();
} catch (PDOException $e) { error_log('add_payment fetch bookings: ' . $e->getMessage()); }

// ─── Pre-select booking from query param ──────────────────────────────────────
$preSelected = (int)($_GET['booking_id'] ?? 0);

// ─── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId   = (int)($_POST['booking_id']    ?? 0);
    $payType     = sanitizeInput($_POST['payment_type'] ?? '');
    $amount      = (float)($_POST['amount']       ?? 0);
    $method      = sanitizeInput($_POST['method']  ?? '');
    $reference   = sanitizeInput($_POST['reference'] ?? '');
    $notes       = sanitizeInput($_POST['notes']    ?? '');
    $status      = sanitizeInput($_POST['status']   ?? 'pending');

    $validTypes    = ['advance','balance','full'];
    $validMethods  = ['cash','bank_transfer','card','online'];
    $validStatuses = ['pending','paid'];

    $errors = [];
    if ($bookingId <= 0)                              $errors[] = 'Please select a booking.';
    if (!in_array($payType, $validTypes, true))       $errors[] = 'Invalid payment type.';
    if ($amount <= 0)                                  $errors[] = 'Amount must be greater than zero.';
    if (!in_array($method, $validMethods, true))      $errors[] = 'Invalid payment method.';
    if (!in_array($status, $validStatuses, true))     $errors[] = 'Invalid status.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $insStmt = $pdo->prepare(
                "INSERT INTO payments (booking_id, payment_type, amount, method, reference, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $insStmt->execute([$bookingId, $payType, $amount, $method,
                               $reference ?: null, $notes ?: null, $status]);
            $paymentId = (int) $pdo->lastInsertId();

            // If status is paid immediately, log initial transaction
            if ($status === 'paid') {
                $txStmt = $pdo->prepare(
                    "INSERT INTO transactions (payment_id, changed_by, old_status, new_status, note)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $txStmt->execute([$paymentId, $_SESSION['admin_id'], 'pending', 'paid', 'Payment recorded as paid']);
            }

            $pdo->commit();
            setFlash('success', 'Payment recorded successfully.');
            redirect(BASE_URL . '/admin/payments/payment_details.php?id=' . $paymentId);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('add_payment insert: ' . $e->getMessage());
            setFlash('error', 'Failed to record payment. Please try again.');
            redirect(BASE_URL . '/admin/payments/add_payment.php?booking_id=' . $bookingId);
        }
    }
    // If validation errors, fall through to display form with errors
}

$flash = getFlash();

$pageTitle    = 'Record Payment';
$pageSubtitle = 'Add a new payment record for a booking';
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

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?php foreach ($errors as $err): ?>
            <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="page-header" style="margin-bottom:28px;">
        <div>
            <a href="<?= BASE_URL ?>/admin/payments/manage_payments.php"
               style="font-size:.82rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:6px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Payments
            </a>
        </div>
    </div>

    <div style="max-width:800px;">
        <div class="card">
            <div class="card-header">
                <h2 style="font-size:1rem;font-weight:600;">Record New Payment</h2>
            </div>

            <form method="POST" action="">
                <!-- Booking Selector -->
                <div class="form-group">
                    <label for="booking_id">Booking <span style="color:var(--danger)">*</span></label>
                    <select name="booking_id" id="booking_id" class="form-control" required>
                        <option value="">— Select a booking —</option>
                        <?php foreach ($bookings as $bk): ?>
                        <option value="<?= $bk['booking_id'] ?>"
                                data-total="<?= $bk['total_amount'] ?>"
                                data-advance="<?= $bk['advance_amount'] ?>"
                                data-balance="<?= $bk['balance_amount'] ?>"
                                data-paid="<?= $bk['paid_total'] ?>"
                                data-paid-adv="<?= $bk['paid_advance'] ?>"
                                data-paid-bal="<?= $bk['paid_balance'] ?>"
                                <?= (isset($bookingId) && $bookingId === (int)$bk['booking_id']) || $preSelected === (int)$bk['booking_id'] ? 'selected' : '' ?>>
                            #<?= $bk['booking_id'] ?> — <?= htmlspecialchars($bk['customer_name']) ?>
                            (<?= htmlspecialchars($bk['package_name']) ?>) —
                            <?= htmlspecialchars(formatDateReadable($bk['event_date'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Booking Info Box (shown by JS) -->
                <div id="bookingInfo" style="display:none;background:var(--primary-light);border-radius:var(--radius-sm);padding:16px 20px;margin-bottom:20px;border:1px solid rgba(77,93,251,.2);">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                        <div>
                            <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;margin-bottom:4px;">Total</div>
                            <div id="infoTotal" style="font-weight:700;color:var(--text-main);"></div>
                        </div>
                        <div>
                            <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;margin-bottom:4px;">Advance Due</div>
                            <div id="infoAdvance" style="font-weight:700;color:var(--warning);"></div>
                        </div>
                        <div>
                            <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;margin-bottom:4px;">Balance Due</div>
                            <div id="infoBalance" style="font-weight:700;color:var(--info);"></div>
                        </div>
                        <div>
                            <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;margin-bottom:4px;">Already Paid</div>
                            <div id="infoPaid" style="font-weight:700;color:var(--success);"></div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <!-- Payment Type -->
                    <div class="form-group">
                        <label for="payment_type">Payment Type <span style="color:var(--danger)">*</span></label>
                        <select name="payment_type" id="payment_type" class="form-control" required>
                            <option value="advance" <?= (isset($payType) && $payType==='advance') ? 'selected':'' ?>>Advance</option>
                            <option value="balance" <?= (isset($payType) && $payType==='balance') ? 'selected':'' ?>>Balance</option>
                            <option value="full"    <?= (isset($payType) && $payType==='full')    ? 'selected':'' ?>>Full Payment</option>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div class="form-group">
                        <label for="amount">Amount (LKR) <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="amount" id="amount" class="form-control"
                               min="0.01" step="0.01" required
                               value="<?= isset($amount) && $amount > 0 ? htmlspecialchars($amount) : '' ?>"/>
                    </div>

                    <!-- Method -->
                    <div class="form-group">
                        <label for="method">Payment Method <span style="color:var(--danger)">*</span></label>
                        <select name="method" id="method" class="form-control" required>
                            <option value="cash"          <?= (isset($method) && $method==='cash')          ? 'selected':'' ?>>Cash</option>
                            <option value="bank_transfer" <?= (isset($method) && $method==='bank_transfer') ? 'selected':'' ?>>Bank Transfer</option>
                            <option value="card"          <?= (isset($method) && $method==='card')          ? 'selected':'' ?>>Card</option>
                            <option value="online"        <?= (isset($method) && $method==='online')        ? 'selected':'' ?>>Online</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="status">Initial Status <span style="color:var(--danger)">*</span></label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="pending" <?= (isset($status) && $status==='pending') ? 'selected':'' ?>>Pending</option>
                            <option value="paid"    <?= (isset($status) && $status==='paid')    ? 'selected':'' ?>>Paid</option>
                        </select>
                    </div>
                </div>

                <!-- Reference -->
                <div class="form-group">
                    <label for="reference">Transaction Reference</label>
                    <input type="text" name="reference" id="reference" class="form-control"
                           placeholder="e.g. TXN-20260313-001"
                           value="<?= isset($reference) ? htmlspecialchars($reference) : '' ?>"/>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3"
                              placeholder="Any additional notes about this payment…"><?= isset($notes) ? htmlspecialchars($notes) : '' ?></textarea>
                </div>

                <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
                    <a href="<?= BASE_URL ?>/admin/payments/manage_payments.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/payments.js"></script>
</body>
</html>
