<?php
/**
 * admin/auth/login.php — Admin login page.
 * Module 1 – Sahani
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → redirect to dashboard
if (isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin') {
    redirect(BASE_URL . '/admin/dashboard/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitizeInput($_POST['email']    ?? '');
    $password =               $_POST['password'] ?? '';   // raw — needed for verify

    if (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT user_id, full_name, email, password, role, status
             FROM users
             WHERE email = ? AND role = 'admin'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'blocked') {
            $error = 'This account has been disabled. Contact support.';
        } elseif ($user && verifyPassword($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['admin_id']   = $user['user_id'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];

            // Record last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
                ->execute([$user['user_id']]);

            redirect(BASE_URL . '/admin/dashboard/dashboard.php');
        } else {
            // Generic message — do not reveal whether email exists
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
    <title>Admin Login | BookMyHall</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/auth.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">

        <!-- Header -->
        <div class="auth-header">
            <div class="auth-logo">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <h1 class="auth-title">BookMyHall</h1>
            <p class="auth-subtitle">Admin Portal — sign in to continue</p>
        </div>

        <!-- Error alert -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Login form -->
        <form class="auth-form" method="POST" action="" novalidate>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="admin@bookmyhall.com"
                           required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password"
                           required autocomplete="current-password">
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
            Not an admin? <a href="<?= BASE_URL ?>/customer/auth/customer_login.php">Customer login</a>
        </p>
    </div>
</div>

<script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        });
    });
</script>

</body>
</html>
