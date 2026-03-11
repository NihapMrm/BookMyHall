<?php
/**
 * customer/auth/customer_login.php — Customer login page.
 * Module 1 – Sahani / Nishtha
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → redirect
if (isset($_SESSION['customer_id'])) {
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

$error = '';
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitizeInput($_POST['email']    ?? '');
    $password =               $_POST['password'] ?? '';

    if (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT user_id, full_name, email, password, role, status
             FROM users
             WHERE email = ? AND role = 'customer'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'blocked') {
            $error = 'Your account has been suspended. Please contact support.';
        } elseif ($user && verifyPassword($password, $user['password'])) {
            session_regenerate_id(true);

            // Customer session — separate namespace from admin
            $_SESSION['customer_id']    = $user['user_id'];
            $_SESSION['customer_name']  = $user['full_name'];
            $_SESSION['customer_email'] = $user['email'];

            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
                ->execute([$user['user_id']]);

            redirect(BASE_URL . '/index.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login | BookMyHall</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/auth.css">
</head>
<body>

<!-- Brand -->
<div class="auth-brand">
    <span class="auth-brand-icon"><i class="fa-solid fa-building-columns"></i></span>
    <span class="auth-brand-name">BookMyHall</span>
</div>

<div class="auth-card">
    <div class="auth-header">
        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-subtitle">Sign in to your BookMyHall account</p>
    </div>

    <!-- Flash success (e.g., after registration) -->
    <?php if ($flash && $flash['type'] === 'success'): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-check-circle"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Error -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form class="auth-form" method="POST" action="" novalidate>

        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@email.com" required autocomplete="email">
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock input-icon"></i>
                <input type="password" id="password" name="password"
                       placeholder="Enter your password" required autocomplete="current-password">
                <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-right-to-bracket"></i>
            Sign In
        </button>

    </form>

    <p class="auth-footer">
        Don't have an account? <a href="<?= BASE_URL ?>/customer/auth/register.php">Create one</a>
    </p>
    <p class="auth-footer" style="margin-top:8px; font-size:13px;">
        Are you an admin? <a href="<?= BASE_URL ?>/admin/auth/login.php">Admin login</a>
    </p>
</div>

<script src="<?= BASE_URL ?>/assets/js/customer/auth.js"></script>

</body>
</html>
