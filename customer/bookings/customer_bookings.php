<?php
/**
 * customer_bookings.php — Customer: calendar view of own bookings
 * Module 4 – Afrina
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

$customerId = (int)$_SESSION['customer_id'];

// Fetch all non-deleted bookings for calendar (no pagination for calendar)
$bookings = [];
try {
    $stmt = $pdo->prepare(
        "SELECT b.booking_id, b.event_date, COALESCE(b.end_date, b.event_date) AS end_date, b.start_time, b.end_time,
                b.status, b.event_type, b.guest_count,
                p.name AS package_name
         FROM bookings b
         JOIN packages p ON p.package_id = b.package_id
         WHERE b.customer_id = ? AND b.is_deleted = 0
         ORDER BY b.event_date ASC"
    );
    $stmt->execute([$customerId]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('customer_bookings calendar: ' . $e->getMessage());
}

// Build JS-ready array — multi-day bookings create one entry per day
$calendarData = [];
foreach ($bookings as $bk) {
    $startDate = $bk['event_date'];
    $endDate   = $bk['end_date'];

    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);

    for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
        $date = date('Y-m-d', $ts);
        $calendarData[] = [
            'id'           => (int)$bk['booking_id'],
            'event_date'   => $date,
            'status'       => $bk['status'],
            'package_name' => $bk['package_name'],
            'event_type'   => $bk['event_type'] ?? '',
            'start_time'   => date('g:i A', strtotime($bk['start_time'])),
            'end_time'     => date('g:i A', strtotime($bk['end_time'])),
            'detail_url'   => BASE_URL . '/customer/bookings/booking_details.php?id=' . $bk['booking_id'],
        ];
    }
}
$jsonBookings = json_encode(array_values($calendarData), JSON_HEX_TAG | JSON_HEX_AMP);

$pageTitle    = 'My Bookings Calendar';
$pageSubtitle = 'Visual view of your reservations';
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

    <!-- Page header actions -->
    <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:20px;gap:10px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/customer/bookings/booking_history.php" class="btn btn-outline">
            <i class="fa-solid fa-list"></i> List View
        </a>
        <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Booking
        </a>
    </div>

    <!-- Calendar — rendered dynamically by calendar.js -->
    <div id="customer-calendar" class="c-calendar-wrapper"></div>

    <?php if (empty($bookings)): ?>
    <div style="text-align:center;padding:30px 0;color:var(--text-muted);">
        <p>You have no bookings yet.</p>
        <a href="<?= BASE_URL ?>/customer/bookings/book_hall.php" class="btn btn-primary" style="margin-top:8px;">
            <i class="fa-solid fa-calendar-plus"></i> Book the Hall Now
        </a>
    </div>
    <?php endif; ?>

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script>
    const CUSTOMER_BOOKINGS = <?= $jsonBookings ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/customer/calendar.js"></script>
</body>
</html>
