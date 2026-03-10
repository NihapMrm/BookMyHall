<?php
/**
 * customer_session_guard.php — Customer authentication guard.
 * Include at the very top of every customer-only page.
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header("Location: " . BASE_URL . "/customer/auth/customer_login.php");
    exit();
}
