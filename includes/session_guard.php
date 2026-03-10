<?php
/**
 * session_guard.php — Admin authentication guard.
 * Include at the very top of every admin-only page.
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/admin/auth/login.php");
    exit();
}
