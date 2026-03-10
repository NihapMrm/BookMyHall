<?php
/**
 * customer/auth/customer_logout.php — Destroy customer session and redirect.
 * Module 1 – Sahani
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear only customer session keys; do not disturb any admin session
unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name'],
    $_SESSION['customer_email']
);

// If no other session data remains, destroy the session entirely
if (empty($_SESSION)) {
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

header("Location: /BookMyHall/customer/auth/customer_login.php");
exit();
