<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db_connection.php';

$name = 'Afrina';
$stmt = $pdo->prepare('SELECT user_id,email,full_name FROM users WHERE full_name LIKE ? LIMIT 1');
$stmt->execute(["%{$name}%"]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo "No user found for '{$name}'\n";
    exit(0);
}

echo "User: " . json_encode($user) . "\n";

$stmt2 = $pdo->prepare('SELECT booking_id,status,event_date,customer_id FROM bookings WHERE customer_id = ? ORDER BY booking_id DESC');
$stmt2->execute([$user['user_id']]);
$bookings = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Bookings: " . json_encode($bookings) . "\n";
?>