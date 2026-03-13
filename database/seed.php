<?php
/**
 * seed.php — Full demo data for BookMyHall (all modules)
 *
 * Run once: http://localhost/BookMyHall/database/seed.php
 * Re-running is safe — skips sections that already exist.
 *
 * Accounts:
 *   Admin     : admin@bookmyhall.com  / Admin@123
 *   Customer 1: priya@example.com     / Customer@123  (Priya Sharma)
 *   Customer 2: rahul@example.com     / Customer@123  (Rahul Perera)
 *   Customer 3: nishtha@example.com   / Customer@123  (Nishtha Fernando)
 *   Customer 4: ashan@example.com     / Customer@123  (Ashan Wijeratne)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

// ─── Output helpers ──────────────────────────────────────────────────────────
function ok(string $msg): void  { echo "<p style='color:#1e8449;margin:4px 0;'>✅ {$msg}</p>"; }
function skip(string $msg): void { echo "<p style='color:#b7950b;margin:4px 0;'>⏭ {$msg}</p>"; }
function h2(string $msg): void  { echo "<h3 style='margin:20px 0 6px;font-family:sans-serif;'>{$msg}</h3>"; }

echo "<!DOCTYPE html><html><head><title>BookMyHall Seed</title></head><body style='font-family:sans-serif;max-width:700px;margin:30px auto;padding:0 20px;'>";
echo "<h2 style='color:#4d5dfb;'>BookMyHall — Demo Data Seed</h2>";

// ─── 1. Admin ────────────────────────────────────────────────────────────────
h2('1. Admin Account');
$adminEmail = 'admin@bookmyhall.com';
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$adminEmail]);
if ($stmt->fetchColumn()) {
    skip('Admin already exists — skipped.');
    $adminId = (int)$pdo->query("SELECT user_id FROM users WHERE email='$adminEmail'")->fetchColumn();
} else {
    $ins = $pdo->prepare("INSERT INTO users (full_name,email,password,role,status,phone) VALUES (?,?,?,'admin','active','0771234567')");
    $ins->execute(['System Admin', $adminEmail, hashPassword('Admin@123')]);
    $adminId = (int)$pdo->lastInsertId();
    ok("Admin created — {$adminEmail} / Admin@123");
}

// ─── 2. Customers ────────────────────────────────────────────────────────────
h2('2. Customer Accounts');
$customerPass = hashPassword('Customer@123');
$customers = [
    ['Priya Sharma',      'priya@example.com',   '0712345678', '45 Galle Road, Colombo 03'],
    ['Rahul Perera',      'rahul@example.com',   '0723456789', '12 Kandy Road, Colombo 07'],
    ['Nishtha Fernando',  'nishtha@example.com', '0734567890', '78 Duplication Road, Colombo 04'],
    ['Ashan Wijeratne',   'ashan@example.com',   '0745678901', '33 Baseline Road, Colombo 09'],
];
$customerIds = [];
foreach ($customers as $c) {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$c[1]]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        skip("{$c[0]} already exists — skipped.");
        $customerIds[] = (int)$existing;
    } else {
        $ins = $pdo->prepare("INSERT INTO users (full_name,email,password,role,status,phone,address) VALUES (?,?,?,'customer','active',?,?)");
        $ins->execute([$c[0], $c[1], $customerPass, $c[2], $c[3]]);
        $customerIds[] = (int)$pdo->lastInsertId();
        ok("{$c[0]} created — {$c[1]} / Customer@123");
    }
}
[$c1, $c2, $c3, $c4] = $customerIds;

// ─── 3. Hall ─────────────────────────────────────────────────────────────────
h2('3. Hall');
$hallRow = $pdo->query("SELECT hall_id FROM hall LIMIT 1")->fetchColumn();
if ($hallRow) {
    skip('Hall already exists — skipped.');
    $hallId = (int)$hallRow;
} else {
    $features = json_encode(['ac','stage','parking','sound_system','catering','wifi','bridal_suite','projector']);
    $ins = $pdo->prepare(
        "INSERT INTO hall (name,description,capacity,location,size_sqft,base_price,features,status)
         VALUES (?,?,?,?,?,?,?,'available')"
    );
    $ins->execute([
        'Lee Maridean Banquet Hall',
        'A premier banquet hall in the heart of Colombo, offering world-class facilities for weddings, corporate events, and social gatherings. Fully air-conditioned with a grand stage, professional sound system, and exclusive bridal suite.',
        600,
        'No. 22, Galle Road, Colombo 03, Sri Lanka',
        8500,
        50000.00,
        $features,
    ]);
    $hallId = (int)$pdo->lastInsertId();
    ok("Hall created — Lee Maridean Banquet Hall (ID: {$hallId})");
}

// ─── 4. Packages ─────────────────────────────────────────────────────────────
h2('4. Packages');
$pkgCheck = $pdo->query("SELECT COUNT(*) FROM packages WHERE hall_id = {$hallId}")->fetchColumn();
if ((int)$pkgCheck >= 4) {
    skip('Packages already exist — skipped.');

    // Fetch existing package IDs for bookings
    $pkgs = $pdo->query(
        "SELECT package_id FROM packages WHERE hall_id={$hallId} ORDER BY package_id ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
    [$pkgSilverBasic, $pkgSilverPlus, $pkgGoldStd, $pkgGoldPrem] = array_slice($pkgs, 0, 4);
} else {
    $insPkg = $pdo->prepare(
        "INSERT INTO packages (hall_id,name,price,seat_capacity,parking_capacity,description,inclusions,services,is_active)
         VALUES (?,?,?,?,?,?,?,?,1)"
    );

    // Silver Basic
    $insPkg->execute([
        $hallId, 'Silver Basic', 85000.00, 200, 30,
        'Ideal for smaller gatherings up to 200 guests.',
        'Hall rental (8 hrs), AC, basic table décor, standard chairs & tables, wifi, parking for 30 vehicles',
        json_encode(['ac','wifi','parking']),
    ]);
    $pkgSilverBasic = (int)$pdo->lastInsertId();

    // Silver Plus
    $insPkg->execute([
        $hallId, 'Silver Plus', 120000.00, 300, 50,
        'Extended Silver experience for up to 300 guests with catering support.',
        'Hall rental (10 hrs), AC, catering setup area, enhanced décor, wifi, parking for 50 vehicles',
        json_encode(['ac','catering','wifi','parking']),
    ]);
    $pkgSilverPlus = (int)$pdo->lastInsertId();

    // Gold Standard
    $insPkg->execute([
        $hallId, 'Gold Standard', 175000.00, 400, 80,
        'Premium experience for up to 400 guests with full décor and catering.',
        'Hall rental (12 hrs), AC, full décor, catering setup, stage, professional sound system, wifi, parking for 80',
        json_encode(['ac','decoration','catering','stage','wifi','parking']),
    ]);
    $pkgGoldStd = (int)$pdo->lastInsertId();

    // Gold Premium
    $insPkg->execute([
        $hallId, 'Gold Premium', 250000.00, 550, 100,
        'The ultimate banquet experience for up to 550 guests — no detail spared.',
        'Hall rental (14 hrs), AC, luxury décor, full catering, stage, sound system, bridal suite, projector, wifi, parking for 100',
        json_encode(['ac','decoration','catering','stage','wifi','parking']),
    ]);
    $pkgGoldPrem = (int)$pdo->lastInsertId();

    ok("Created: Silver Basic (ID:{$pkgSilverBasic}), Silver Plus (ID:{$pkgSilverPlus}), Gold Standard (ID:{$pkgGoldStd}), Gold Premium (ID:{$pkgGoldPrem})");
}

// ─── 5. Bookings ─────────────────────────────────────────────────────────────
h2('5. Bookings');
$bkCheck = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE hall_id = {$hallId}")->fetchColumn();
if ($bkCheck >= 8) {
    skip('Bookings already exist — skipped.');
    $bIds = $pdo->query(
        "SELECT booking_id FROM bookings WHERE hall_id={$hallId} ORDER BY booking_id ASC LIMIT 8"
    )->fetchAll(PDO::FETCH_COLUMN);
    [$bk1,$bk2,$bk3,$bk4,$bk5,$bk6,$bk7,$bk8] = $bIds;
} else {
    $insBk = $pdo->prepare(
        "INSERT INTO bookings
         (customer_id,hall_id,package_id,event_date,end_date,start_time,end_time,
          event_type,guest_count,special_requests,
          total_amount,advance_amount,balance_amount,
          status,rejection_reason,cancellation_reason,is_deleted,completed_at,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)"
    );

    // Multi-day bookings: total = package_price × num_days, advance=30%, balance=70%
    // Silver Basic: 85,000/day | Silver Plus: 120,000/day | Gold Standard: 175,000/day | Gold Premium: 250,000/day
    $bookings = [
        // [cust, hall, pkg, start_date, end_date, start_time, end_time,
        //  type, guests, special_requests, total, advance, balance,
        //  status, reject_reason, cancel_reason, completed_at, created_at]

        // Bk1 – c1, Silver Basic, 3-day Wedding (Jan 15–17)
        // 85,000 × 3 = 255,000 | advance 76,500 | balance 178,500
        [$c1, $hallId, $pkgSilverBasic,
            '2026-01-15', '2026-01-17', '09:00', '22:00', 'Wedding', 180,
            'Floral arch at entrance; bridal suite ready by 8 AM each day.',
            255000.00, 76500.00, 178500.00,
            'completed', null, null, '2026-01-17 22:30:00', '2025-12-10 09:15:00'],

        // Bk2 – c2, Gold Premium, 3-day Wedding (Feb 8–10)
        // 250,000 × 3 = 750,000 | advance 225,000 | balance 525,000
        [$c2, $hallId, $pkgGoldPrem,
            '2026-02-08', '2026-02-10', '09:00', '22:00', 'Wedding', 480,
            'Vegan menu option required for 50 guests; fireworks on final night.',
            750000.00, 225000.00, 525000.00,
            'approved', null, null, null, '2025-12-28 14:22:00'],

        // Bk3 – c3, Silver Plus, 1-day Birthday Party (Mar 25)
        // 120,000 × 1 = 120,000 | advance 36,000 | balance 84,000
        [$c3, $hallId, $pkgSilverPlus,
            '2026-03-25', null, '11:00', '21:00', 'Birthday Party', 260,
            'Birthday cake table setup near the stage; surprise entry at 7 PM.',
            120000.00, 36000.00, 84000.00,
            'pending', null, null, null, '2026-03-01 10:05:00'],

        // Bk4 – c1, Gold Standard, 2-day Corporate Event (Jan 25–26) — CANCELLED
        // 175,000 × 2 = 350,000 | advance 105,000 | balance 245,000
        [$c1, $hallId, $pkgGoldStd,
            '2026-01-25', '2026-01-26', '08:00', '18:00', 'Corporate Event', 350,
            'Conference setup with projection screen; break-out area required.',
            350000.00, 105000.00, 245000.00,
            'cancelled', null, 'Change of venue decided by management.', null, '2025-12-15 16:40:00'],

        // Bk5 – c4, Silver Basic, 1-day Engagement (Feb 14) — REJECTED
        // 85,000 × 1 = 85,000 | advance 25,500 | balance 59,500
        [$c4, $hallId, $pkgSilverBasic,
            '2026-02-14', null, '14:00', '22:00', 'Engagement', 150,
            'Pink and white colour theme preferred.',
            85000.00, 25500.00, 59500.00,
            'rejected', 'Requested date is already booked for another event.', null, null, '2026-01-20 11:30:00'],

        // Bk6 – c2, Gold Standard, 2-day Wedding Reception (Mar 1–2)
        // 175,000 × 2 = 350,000 | advance 105,000 | balance 245,000
        [$c2, $hallId, $pkgGoldStd,
            '2026-03-01', '2026-03-02', '09:00', '21:00', 'Wedding Reception', 380,
            'Welcome drinks station at the entrance; band on Day 1, DJ on Day 2.',
            350000.00, 105000.00, 245000.00,
            'completed', null, null, '2026-03-02 21:30:00', '2026-01-18 08:55:00'],

        // Bk7 – c3, Silver Plus, 2-day Anniversary (Apr 5–6)
        // 120,000 × 2 = 240,000 | advance 72,000 | balance 168,000
        [$c3, $hallId, $pkgSilverPlus,
            '2026-04-05', '2026-04-06', '10:00', '22:00', 'Anniversary', 240,
            'Candlelight dinner setup on Day 2.',
            240000.00, 72000.00, 168000.00,
            'approved', null, null, null, '2026-02-20 13:10:00'],

        // Bk8 – c4, Gold Premium, 3-day Wedding (Apr 15–17)
        // 250,000 × 3 = 750,000 | advance 225,000 | balance 525,000
        [$c4, $hallId, $pkgGoldPrem,
            '2026-04-15', '2026-04-17', '10:00', '22:00', 'Wedding', 500,
            'Fireworks display at 9 PM on the last night — please confirm vendor access.',
            750000.00, 225000.00, 525000.00,
            'pending', null, null, null, '2026-03-05 09:00:00'],
    ];

    $bookingIds = [];
    foreach ($bookings as $b) {
        $insBk->execute($b);
        $bookingIds[] = (int)$pdo->lastInsertId();
    }
    [$bk1,$bk2,$bk3,$bk4,$bk5,$bk6,$bk7,$bk8] = $bookingIds;
    ok('8 bookings created — mix of single-day and multi-day (2–3 days), all statuses represented.');
}

// ─── 6. Payments ─────────────────────────────────────────────────────────────
h2('6. Payments');
$payCheck = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE booking_id IN ({$bk1},{$bk2},{$bk6},{$bk7})")->fetchColumn();
if ($payCheck >= 4) {
    skip('Payments already exist — skipped.');
} else {
    $insPay = $pdo->prepare(
        "INSERT INTO payments (booking_id,payment_type,amount,method,reference,notes,status,created_at)
         VALUES (?,?,?,?,?,?,?,?)"
    );

    $payments = [
        // Bk1 (completed, 3-day Silver Basic wedding) — advance 76,500 + balance 178,500, both paid
        [$bk1, 'advance',  76500.00, 'bank_transfer', 'TXN-2026-0001', 'Advance payment received via bank transfer.', 'paid', '2025-12-12 10:00:00'],
        [$bk1, 'balance', 178500.00, 'cash',           null,            'Balance collected on final event day.',       'paid', '2026-01-17 08:30:00'],

        // Bk2 (approved, 3-day Gold Premium wedding) — advance 225,000 paid; balance 525,000 pending
        [$bk2, 'advance', 225000.00, 'bank_transfer', 'TXN-2026-0002', 'Advance payment received — 3-day booking.', 'paid',    '2025-12-30 11:15:00'],
        [$bk2, 'balance', 525000.00, 'cash',           null,            'Balance due on first event day.',            'pending', '2025-12-30 11:16:00'],

        // Bk6 (completed, 2-day Gold Standard wedding reception) — advance 105,000 + balance 245,000, both paid
        [$bk6, 'advance', 105000.00, 'card',           'TXN-2026-0006', 'Advance paid by card — 2-day booking.',     'paid', '2026-01-20 09:00:00'],
        [$bk6, 'balance', 245000.00, 'cash',            null,            'Balance collected on first event day.',     'paid', '2026-03-01 09:00:00'],

        // Bk7 (approved, 2-day Silver Plus anniversary) — advance 72,000 paid; balance 168,000 pending
        [$bk7, 'advance', 72000.00, 'bank_transfer', 'TXN-2026-0007', 'Advance payment confirmed — 2-day booking.', 'paid',    '2026-02-22 14:00:00'],
        [$bk7, 'balance', 168000.00, 'cash',           null,            'Balance due on first event day.',           'pending', '2026-02-22 14:01:00'],
    ];

    foreach ($payments as $p) {
        $insPay->execute($p);
    }
    ok('8 payment records created (advance & balance for approved/completed bookings).');
}

// ─── 7. Feedback ─────────────────────────────────────────────────────────────
h2('7. Feedback');
$fbCheck = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE booking_id IN ({$bk1},{$bk6})")->fetchColumn();
if ($fbCheck >= 2) {
    skip('Feedback already exists — skipped.');
} else {
    $insFb = $pdo->prepare(
        "INSERT INTO feedback (booking_id,customer_id,rating,comment,is_visible,created_at)
         VALUES (?,?,?,?,?,?)"
    );
    $insFb->execute([
        $bk1, $c1, 5,
        'Absolutely stunning venue! The hall was beautifully decorated and every detail was perfect. The staff was incredibly helpful throughout the event. Our wedding was a dream come true. Highly recommend Lee Maridean to anyone looking for a premium banquet experience.',
        1, '2026-01-16 10:30:00',
    ]);
    $insFb->execute([
        $bk6, $c2, 4,
        'Wonderful experience overall. The venue was elegant and the Silver Plus package exceeded our expectations. The sound system was excellent for speeches and the entrance décor was beautiful. Only minor feedback: parking coordination at the end of the event could be smoother. Would definitely book again!',
        1, '2026-03-02 09:45:00',
    ]);
    ok("2 feedback records created (5-star and 4-star reviews).");
}

// ─── Done ────────────────────────────────────────────────────────────────────
echo "<hr style='margin:30px 0;'>";
echo "<h3 style='font-family:sans-serif;color:#1e8449;'>Seed Complete!</h3>";
echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;font-size:14px;'>";
echo "<tr style='background:#f5f6fa;'><th>Role</th><th>Name</th><th>Email</th><th>Password</th></tr>";
echo "<tr><td>Admin</td><td>System Admin</td><td>admin@bookmyhall.com</td><td>Admin@123</td></tr>";
echo "<tr><td>Customer</td><td>Priya Sharma</td><td>priya@example.com</td><td>Customer@123</td></tr>";
echo "<tr><td>Customer</td><td>Rahul Perera</td><td>rahul@example.com</td><td>Customer@123</td></tr>";
echo "<tr><td>Customer</td><td>Nishtha Fernando</td><td>nishtha@example.com</td><td>Customer@123</td></tr>";
echo "<tr><td>Customer</td><td>Ashan Wijeratne</td><td>ashan@example.com</td><td>Customer@123</td></tr>";
echo "</table>";
echo "<p style='color:#c0392b;font-weight:bold;margin-top:16px;'>⚠ Delete or restrict access to this file after seeding!</p>";
echo "</body></html>";
