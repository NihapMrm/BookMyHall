<?php
/**
 * seed.php — Create the default admin account for BookMyHall.
 *
 * Run once from the browser: http://localhost/BookMyHall/database/seed.php
 * Then delete or restrict access to this file.
 *
 * Default credentials:
 *   Email   : admin@bookmyhall.com
 *   Password: Admin@123
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

$adminEmail    = 'admin@bookmyhall.com';
$adminPassword = 'Admin@123';
$adminName     = 'System Admin';

// Check if an admin already exists
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$adminEmail]);

if ($stmt->fetchColumn()) {
    echo "<p style='color:orange;'>⚠ Admin account already exists. Seed skipped.</p>";
} else {
    $hashed = hashPassword($adminPassword);
    $insert = $pdo->prepare(
        "INSERT INTO users (full_name, email, password, role, status)
         VALUES (?, ?, ?, 'admin', 'active')"
    );
    $insert->execute([$adminName, $adminEmail, $hashed]);

    echo "<p style='color:green;'>✅ Default admin account created.</p>";
    echo "<p><strong>Email:</strong> {$adminEmail}</p>";
    echo "<p><strong>Password:</strong> {$adminPassword}</p>";
    echo "<p style='color:red;'><strong>⚠ Delete this file after seeding!</strong></p>";
}
