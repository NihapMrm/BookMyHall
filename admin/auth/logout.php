<?php
/**
 * admin/auth/logout.php — Destroy admin session and redirect to login.
 * Module 1 – Sahani
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header("Location: /BookMyHall/admin/auth/login.php");
exit();
