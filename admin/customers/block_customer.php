<?php
/**
 * block_customer.php — Admin: Toggle customer active/blocked status.
 * Module 3 – Nishtha
 * POST-only handler. No UI — redirects with flash after processing.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/customers/manage_customers.php');
}

$customerId  = (int)($_POST['customer_id'] ?? 0);
$redirectTo  = sanitizeInput($_POST['redirect'] ?? 'manage_customers.php');

// Whitelist redirect targets (prevent open redirect)
$allowedRedirects = ['manage_customers.php', "customer_details.php?id=$customerId"];
if (!in_array($redirectTo, $allowedRedirects)) {
    $redirectTo = 'manage_customers.php';
}

if (!$customerId) {
    setFlash('error', 'Invalid customer ID.');
    redirect(BASE_URL . '/admin/customers/' . $redirectTo);
}

try {
    // Fetch current status
    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, status FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1"
    );
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        setFlash('error', 'Customer not found.');
        redirect(BASE_URL . '/admin/customers/manage_customers.php');
    }

    // Toggle status
    $newStatus = $customer['status'] === 'active' ? 'blocked' : 'active';
    $action    = $newStatus === 'blocked' ? 'blocked' : 'unblocked';

    $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?")
        ->execute([$newStatus, $customerId]);

    logActivity(
        $pdo,
        "Admin {$_SESSION['full_name']} {$action} customer: {$customer['full_name']} (ID #{$customerId})",
        $_SESSION['admin_id']
    );

    setFlash('success', "Customer " . htmlspecialchars($customer['full_name']) . " has been {$action} successfully.");

} catch (PDOException $e) {
    error_log("block_customer: " . $e->getMessage());
    setFlash('error', 'An error occurred. Please try again.');
}

redirect(BASE_URL . '/admin/customers/' . $redirectTo);
