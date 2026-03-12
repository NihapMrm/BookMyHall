<?php
/**
 * book_hall.php — Customer: Booking form
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

// Auto-migrate: add end_date column if not present (multi-day booking support)
try {
    $colCheck = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'end_date'"
    );
    if ((int)$colCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN end_date DATE NULL AFTER event_date");
    }
} catch (PDOException $e) {
    error_log('book_hall migration: ' . $e->getMessage());
}

$errors = [];

// Fetch hall (single hall system)
$hall = null;
try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
} catch (PDOException $e) { error_log('book_hall hall: ' . $e->getMessage()); }

$packageGroups = [];
if ($hall) {
    $packageGroups = getPackagesByHall($pdo, (int)$hall['hall_id']);
}

// Pre-select package if passed via URL
$preselectedPkg = (int)($_GET['pkg'] ?? 0);

// ─── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subPackageId = (int)($_POST['sub_package_id'] ?? 0);
    $startDate    = sanitizeInput($_POST['start_date']       ?? '');
    $endDate      = sanitizeInput($_POST['end_date']         ?? '');
    $startTime    = sanitizeInput($_POST['start_time']       ?? '');
    $endTime      = sanitizeInput($_POST['end_time']         ?? '');
    $eventType    = sanitizeInput($_POST['event_type']       ?? '');
    $guestCount   = (int)($_POST['guest_count']   ?? 0);
    $specialReqs  = sanitizeInput($_POST['special_requests'] ?? '');

    // Default end_date to start_date for single-day bookings
    if ($endDate === '') $endDate = $startDate;

    // Validations
    if ($subPackageId <= 0) $errors[] = 'Please select a package.';
    if ($startDate === '')  $errors[] = 'Please select an event date on the calendar.';
    if ($startTime === '' || $endTime === '') $errors[] = 'Daily start and end times are required.';
    if ($startTime !== '' && $endTime !== '' && $startTime >= $endTime) {
        $errors[] = 'End time must be after start time.';
    }
    if ($guestCount <= 0) $errors[] = 'Guest count must be at least 1.';
    if ($startDate !== '' && $startDate < date('Y-m-d')) {
        $errors[] = 'Event date cannot be in the past.';
    }
    if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
        $errors[] = 'End date cannot be before the start date.';
    }

    if (empty($errors)) {
        $available = checkAvailability($pdo, $startDate, $startTime, $endTime, $subPackageId, $endDate);
        if (!$available) {
            $errors[] = 'Sorry, the selected dates are already booked for this package. Please choose different dates.';
        }
    }

    if (empty($errors)) {
        try {
            $pkgStmt = $pdo->prepare(
                "SELECT p.package_id, p.price, p.seat_capacity, h.hall_id
                 FROM packages p
                 JOIN hall h ON h.hall_id = p.hall_id
                 WHERE p.package_id = ? AND p.type = 'sub' AND p.is_active = 1"
            );
            $pkgStmt->execute([$subPackageId]);
            $pkgRow = $pkgStmt->fetch();
        } catch (PDOException $e) {
            error_log('book_hall pkg: ' . $e->getMessage());
            $pkgRow = null;
        }

        if (!$pkgRow) {
            $errors[] = 'Selected package is not valid or has been deactivated.';
        } elseif ($guestCount > (int)$pkgRow['seat_capacity']) {
            $errors[] = 'Guest count exceeds the selected package capacity of ' . $pkgRow['seat_capacity'] . ' seats.';
        } else {
            $numDays       = max(1, (int)round((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
            $totalAmount   = (float)$pkgRow['price'] * $numDays;
            $advanceAmount = round($totalAmount * 0.30, 2);
            $balanceAmount = round($totalAmount - $advanceAmount, 2);

            try {
                $ins = $pdo->prepare(
                    "INSERT INTO bookings
                     (customer_id, hall_id, sub_package_id, event_date, end_date, start_time, end_time,
                      event_type, guest_count, special_requests,
                      total_amount, advance_amount, balance_amount, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                );
                $ins->execute([
                    $_SESSION['customer_id'],
                    $pkgRow['hall_id'],
                    $subPackageId,
                    $startDate,
                    $endDate,
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

                setFlash('success', 'Your booking request has been submitted! We will review and confirm it shortly.');
                redirect(BASE_URL . '/customer/bookings/booking_details.php?id=' . $newId);
            } catch (PDOException $e) {
                error_log('book_hall insert: ' . $e->getMessage());
                $errors[] = 'Failed to submit booking. Please try again.';
            }
        }
    }
}

$pageTitle    = 'Book the Hall';
$pageSubtitle = 'Submit your reservation request';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/bookings.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php include __DIR__ . '/../includes/customer_topbar.php'; ?>

<div class="c-content-wrapper">
<div class="customer-content">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom:20px;">
        <i class="fa-solid fa-circle-exclamation"></i>
        <ul style="margin:4px 0 0;padding-left:18px;">
            <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!$hall): ?>
    <div class="alert alert-info">The hall information is not available at this time. Please contact us directly.</div>
    <?php elseif (empty($packageGroups)): ?>
    <div class="alert alert-info">No packages are currently available for booking. Please check back later.</div>
    <?php else: ?>

    <form class="booking-form-card" method="POST" id="booking-form" novalidate>

        <!-- Hidden date inputs (populated by JS calendar) -->
        <input type="hidden" id="start_date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>"/>
        <input type="hidden" id="end_date"   name="end_date"   value="<?= htmlspecialchars($_POST['end_date']   ?? '') ?>"/>

        <!-- Section 1: Package Selection -->
        <div class="booking-form-section-title">
            <i class="fa-solid fa-box-open"></i> &nbsp;1. Select Package
        </div>

        <div style="margin-bottom:4px;">
            <?php foreach ($packageGroups as $main): ?>
            <?php if (!empty($main['sub_packages'])): ?>
            <div class="pkg-group-title">
                <i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($main['name']) ?>
            </div>
            <div class="package-selector" style="margin-bottom:14px;">
                <?php foreach ($main['sub_packages'] as $sub): ?>
                <?php $isPreselected = $preselectedPkg === (int)$sub['package_id']; ?>
                <label class="package-option <?= $isPreselected ? 'selected' : '' ?>" for="pkg_<?= $sub['package_id'] ?>">
                    <input type="radio"
                           id="pkg_<?= $sub['package_id'] ?>"
                           name="sub_package_id"
                           value="<?= $sub['package_id'] ?>"
                           data-price="<?= (float)$sub['price'] ?>"
                           <?= $isPreselected ? 'checked' : '' ?>/>
                    <div class="package-option-info">
                        <div class="package-option-name"><?= htmlspecialchars($sub['name']) ?></div>
                        <div class="package-option-meta">
                            <i class="fa-solid fa-users"></i> Up to <?= (int)$sub['seat_capacity'] ?> guests
                            <?php if ($sub['parking_capacity']): ?>
                            &nbsp;·&nbsp; <i class="fa-solid fa-car"></i> <?= (int)$sub['parking_capacity'] ?> parking
                            <?php endif; ?>
                            <?php if (!empty($sub['services_arr'])): ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars(implode(', ', array_map('ucfirst', $sub['services_arr']))) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="package-option-price">
                        <?= htmlspecialchars(formatCurrency((float)$sub['price'])) ?>
                        <span style="font-size:.7rem;font-weight:500;color:var(--text-muted)">/day</span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Section 2: Date Selection Calendar -->
        <div class="booking-form-section-title">
            <i class="fa-solid fa-calendar-days"></i> &nbsp;2. Select Your Event Dates
        </div>

        <div class="form-group full" style="margin-bottom:20px;">
            <p class="form-hint" style="margin-bottom:10px;">
                <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i>
                Select a package first to see booked dates. Click a <strong>start date</strong>, then click an <strong>end date</strong> to book multiple days.
            </p>
            <!-- JS renders the interactive calendar here -->
            <div id="booking-calendar"></div>
        </div>

        <!-- Section 3: Event Details -->
        <div class="booking-form-section-title">
            <i class="fa-solid fa-circle-info"></i> &nbsp;3. Event Details
        </div>

        <div class="booking-form-grid">

            <div class="form-group">
                <label class="form-label" for="event_type">Event Type</label>
                <input class="form-control" type="text" id="event_type" name="event_type"
                       placeholder="e.g. Wedding, Birthday, Conference"
                       value="<?= htmlspecialchars($_POST['event_type'] ?? '') ?>"/>
            </div>

            <div class="form-group">
                <label class="form-label" for="guest_count">Number of Guests <span style="color:var(--danger)">*</span></label>
                <input class="form-control" type="number" id="guest_count" name="guest_count"
                       min="1" placeholder="e.g. 150"
                       value="<?= htmlspecialchars((string)($_POST['guest_count'] ?? '')) ?>" required/>
                <span class="form-hint">Must not exceed your selected package's seat capacity.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="start_time">Daily Start Time <span style="color:var(--danger)">*</span></label>
                <input class="form-control" type="time" id="start_time" name="start_time"
                       value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>" required/>
            </div>

            <div class="form-group">
                <label class="form-label" for="end_time">Daily End Time <span style="color:var(--danger)">*</span></label>
                <input class="form-control" type="time" id="end_time" name="end_time"
                       value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>" required/>
                <div id="avail-indicator" class="avail-indicator"></div>
            </div>

            <!-- Price Summary (full width) -->
            <div class="form-group full">
                <div id="price-summary-box" class="price-summary" style="display:none;">
                    <div class="price-summary-title"><i class="fa-solid fa-receipt"></i> Payment Breakdown</div>
                    <div class="price-row">
                        <span class="price-label">Package Rate</span>
                        <span class="price-value" id="price-per-day">—</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Duration</span>
                        <span class="price-value" id="price-days">—</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Total Amount</span>
                        <span class="price-value" id="price-total">—</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Advance Required (30%)</span>
                        <span class="price-value" id="price-advance">—</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Balance on Event Day</span>
                        <span class="price-value" id="price-balance">—</span>
                    </div>
                </div>
                <p id="price-placeholder" style="color:var(--text-muted);font-size:.82rem;margin:0;">
                    Select a package and dates to see the price breakdown.
                </p>
            </div>

            <!-- Special Requests (full width) -->
            <div class="form-group full">
                <label class="form-label" for="special_requests">Special Requests</label>
                <textarea class="form-control" id="special_requests" name="special_requests"
                          rows="3" placeholder="Any special decorations, catering requirements, setup instructions…"><?= htmlspecialchars($_POST['special_requests'] ?? '') ?></textarea>
            </div>

        </div><!-- /.booking-form-grid -->

        <div style="margin-top:24px;">
            <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px;line-height:1.6;">
                <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i>
                Your booking will be submitted for review. Once approved, you will need to pay the <strong>30% advance</strong>
                to confirm your reservation. The balance is due on the event day.
            </div>
            <button type="submit" class="btn btn-primary btn-full">
                <i class="fa-solid fa-calendar-check"></i> Submit Booking Request
            </button>
        </div>
    </form>

    <?php endif; ?>

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script>
    const BASE_URL = '<?= BASE_URL ?>';
    const INIT_START_DATE = '<?= htmlspecialchars($_POST['start_date'] ?? '') ?>';
    const INIT_END_DATE   = '<?= htmlspecialchars($_POST['end_date']   ?? '') ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/customer/booking.js"></script>
</body>
</html>
