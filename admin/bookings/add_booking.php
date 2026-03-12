<?php
/**
 * add_booking.php — Admin: Manually create a booking on behalf of a customer
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$errors = [];

// ─── Load customers and hall/packages ─────────────────────────────────────
$customers = [];
try {
    $customers = $pdo->query(
        "SELECT user_id, full_name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY full_name ASC"
    )->fetchAll();
} catch (PDOException $e) { error_log('add_booking customers: ' . $e->getMessage()); }

// Fetch hall (single hall system)
$hall = null;
try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
} catch (PDOException $e) { error_log('add_booking hall: ' . $e->getMessage()); }

$packageGroups = [];
if ($hall) {
    $packageGroups = getPackagesByHall($pdo, (int)$hall['hall_id']);
}

// ─── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId     = (int)($_POST['customer_id']    ?? 0);
    $subPackageId   = (int)($_POST['sub_package_id'] ?? 0);
    $eventDate      = sanitizeInput($_POST['event_date']      ?? '');
    $startTime      = sanitizeInput($_POST['start_time']      ?? '');
    $endTime        = sanitizeInput($_POST['end_time']        ?? '');
    $eventType      = sanitizeInput($_POST['event_type']      ?? '');
    $guestCount     = (int)($_POST['guest_count']    ?? 0);
    $specialReqs    = sanitizeInput($_POST['special_requests'] ?? '');

    // Validate
    if ($customerId <= 0)    $errors[] = 'Please select a customer.';
    if ($subPackageId <= 0) $errors[] = 'Please select a package.';
    if ($eventDate === '')  $errors[] = 'Event date is required.';
    if ($startTime === '' || $endTime === '') $errors[] = 'Start and end times are required.';
    if ($startTime >= $endTime) $errors[] = 'End time must be after start time.';
    if ($guestCount <= 0)   $errors[] = 'Guest count must be at least 1.';

    // Validate date is not in the past
    if ($eventDate !== '' && $eventDate < date('Y-m-d')) {
        $errors[] = 'Event date cannot be in the past.';
    }

    if (empty($errors)) {
        // Check availability
        $available = checkAvailability($pdo, $eventDate, $startTime, $endTime, $subPackageId);
        if (!$available) {
            $errors[] = 'This time slot is already booked for the selected package. Please choose a different time.';
        }
    }

    if (empty($errors)) {
        // Fetch sub-package price
        try {
            $pkgStmt = $pdo->prepare("SELECT price FROM packages WHERE package_id = ? AND type = 'sub' AND is_active = 1");
            $pkgStmt->execute([$subPackageId]);
            $pkgRow = $pkgStmt->fetch();
        } catch (PDOException $e) {
            error_log('add_booking pkg price: ' . $e->getMessage());
            $pkgRow = null;
        }

        if (!$pkgRow) {
            $errors[] = 'Selected package is not valid or inactive.';
        } else {
            $totalAmount   = (float)$pkgRow['price'];
            $advanceAmount = round($totalAmount * 0.30, 2);
            $balanceAmount = round($totalAmount - $advanceAmount, 2);

            try {
                $ins = $pdo->prepare(
                    "INSERT INTO bookings
                     (customer_id, hall_id, sub_package_id, event_date, start_time, end_time,
                      event_type, guest_count, special_requests,
                      total_amount, advance_amount, balance_amount, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                );
                $ins->execute([
                    $customerId,
                    $hall['hall_id'],
                    $subPackageId,
                    $eventDate,
                    $startTime,
                    $endTime,
                    $eventType,
                    $guestCount,
                    $specialReqs,
                    $totalAmount,
                    $advanceAmount,
                    $balanceAmount,
                ]);
                $newId = (int)$pdo->lastInsertId();

                setFlash('success', 'Booking #' . $newId . ' has been created successfully.');
                redirect(BASE_URL . '/admin/bookings/booking_details.php?id=' . $newId);
            } catch (PDOException $e) {
                error_log('add_booking insert: ' . $e->getMessage());
                $errors[] = 'Failed to create booking. Please try again.';
            }
        }
    }
}

$pageTitle    = 'Add Booking';
$pageSubtitle = 'Create a booking on behalf of a customer';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/bookings.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <div class="page-header" style="margin-bottom:24px;">
        <div>
            <a href="<?= BASE_URL ?>/admin/bookings/manage_bookings.php"
               style="font-size:.82rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:6px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Bookings
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <ul style="margin:0;padding-left:18px;">
            <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card" style="max-width:800px;">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-calendar-plus"></i> New Booking</span>
        </div>

        <form method="POST" style="padding:26px 28px;">
            <div class="booking-form-grid">

                <!-- Customer -->
                <div class="form-group full-width">
                    <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                    <select class="form-control" id="customer_id" name="customer_id" required>
                        <option value="">— Select customer —</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['user_id'] ?>"
                            <?= (int)($_POST['customer_id'] ?? 0) === (int)$c['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Package selection -->
                <div class="form-group full-width">
                    <label class="form-label">Package <span class="text-danger">*</span></label>
                    <?php if (empty($packageGroups)): ?>
                        <p style="color:var(--danger);font-size:.85rem;">No active packages available.</p>
                    <?php else: ?>
                    <select class="form-control" name="sub_package_id" id="sub_package_id" required>
                        <option value="">— Select a package —</option>
                        <?php foreach ($packageGroups as $main): ?>
                            <optgroup label="<?= htmlspecialchars($main['name']) ?>">
                            <?php foreach ($main['sub_packages'] as $sub): ?>
                                <option value="<?= $sub['package_id'] ?>"
                                        data-price="<?= (float)$sub['price'] ?>"
                                    <?= (int)($_POST['sub_package_id'] ?? 0) === (int)$sub['package_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['name']) ?> —
                                    <?= htmlspecialchars(formatCurrency((float)$sub['price'])) ?>
                                    (<?= (int)$sub['seat_capacity'] ?> seats)
                                </option>
                            <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <!-- Event Date -->
                <div class="form-group">
                    <label class="form-label" for="event_date">Event Date <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" id="event_date" name="event_date"
                           min="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>" required/>
                </div>

                <!-- Event Type -->
                <div class="form-group">
                    <label class="form-label" for="event_type">Event Type</label>
                    <input class="form-control" type="text" id="event_type" name="event_type"
                           placeholder="e.g. Wedding, Birthday, Conference"
                           value="<?= htmlspecialchars($_POST['event_type'] ?? '') ?>"/>
                </div>

                <!-- Start Time -->
                <div class="form-group">
                    <label class="form-label" for="start_time">Start Time <span class="text-danger">*</span></label>
                    <input class="form-control" type="time" id="start_time" name="start_time"
                           value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>" required/>
                </div>

                <!-- End Time -->
                <div class="form-group">
                    <label class="form-label" for="end_time">End Time <span class="text-danger">*</span></label>
                    <input class="form-control" type="time" id="end_time" name="end_time"
                           value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>" required/>
                </div>

                <!-- Guest Count -->
                <div class="form-group">
                    <label class="form-label" for="guest_count">Guest Count <span class="text-danger">*</span></label>
                    <input class="form-control" type="number" id="guest_count" name="guest_count"
                           min="1" max="5000"
                           value="<?= (int)($_POST['guest_count'] ?? '') ?>" required/>
                </div>

                <!-- Price Summary (read-only) -->
                <div class="form-group">
                    <label class="form-label">Price Summary</label>
                    <div class="price-summary" id="price-summary-box" style="display:none;">
                        <div class="price-summary-row">
                            <span>Total Amount</span>
                            <strong id="price-total">—</strong>
                        </div>
                        <div class="price-summary-row">
                            <span>Advance (30%)</span>
                            <span id="price-advance" style="color:var(--success);">—</span>
                        </div>
                        <div class="price-summary-row total-row">
                            <span>Balance Due</span>
                            <span id="price-balance">—</span>
                        </div>
                    </div>
                    <p id="price-placeholder" style="color:var(--text-muted);font-size:.82rem;">Select a package to see pricing.</p>
                </div>

                <!-- Special Requests -->
                <div class="form-group full-width">
                    <label class="form-label" for="special_requests">Special Requests</label>
                    <textarea class="form-control" id="special_requests" name="special_requests"
                              rows="3" placeholder="Any special setup, catering requirements, etc."><?= htmlspecialchars($_POST['special_requests'] ?? '') ?></textarea>
                </div>

            </div><!-- /.booking-form-grid -->

            <div style="display:flex;gap:12px;margin-top:24px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-calendar-plus"></i> Create Booking
                </button>
                <a href="<?= BASE_URL ?>/admin/bookings/manage_bookings.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

</main>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
    // Admin package selector — price preview
    document.getElementById('sub_package_id')?.addEventListener('change', function () {
        const opt  = this.options[this.selectedIndex];
        const price = parseFloat(opt.dataset.price) || 0;
        const ADVANCE_PCT = 0.30;
        const total   = price;
        const advance = total * ADVANCE_PCT;
        const balance = total - advance;

        function fmt(v) { return 'LKR ' + v.toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2}); }

        const box = document.getElementById('price-summary-box');
        const ph  = document.getElementById('price-placeholder');
        if (price > 0) {
            document.getElementById('price-total').textContent   = fmt(total);
            document.getElementById('price-advance').textContent = fmt(advance);
            document.getElementById('price-balance').textContent = fmt(balance);
            if (box) box.style.display = 'block';
            if (ph)  ph.style.display  = 'none';
        } else {
            if (box) box.style.display = 'none';
            if (ph)  ph.style.display  = 'block';
        }
    });
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
