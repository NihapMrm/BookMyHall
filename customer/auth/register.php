<?php
/**
 * customer/auth/register.php — Customer registration.
 * Module 1 – Sahani / Nishtha
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → go to customer area
if (isset($_SESSION['customer_id'])) {
    redirect(BASE_URL . '/customer/bookings/booking_history.php');
}

$errors = [];
$old    = [];   // repopulate form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = sanitizeInput($_POST['full_name'] ?? '');
    $old['email']     = sanitizeInput($_POST['email']     ?? '');
    $old['phone']     = sanitizeInput($_POST['phone']     ?? '');
    $old['address']   = sanitizeInput($_POST['address']   ?? '');
    $password         =               $_POST['password']  ?? '';
    $confirm          =               $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($old['full_name']))          $errors[] = 'Full name is required.';
    if (!validateEmail($old['email']))     $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8)             $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)            $errors[] = 'Passwords do not match.';

    // Email uniqueness check
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$old['email']]);
        if ($check->fetchColumn()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    // Insert customer
    if (empty($errors)) {
        $hashed = hashPassword($password);
        $insert = $pdo->prepare(
            "INSERT INTO users (full_name, email, password, phone, address, role, status)
             VALUES (?, ?, ?, ?, ?, 'customer', 'active')"
        );
        $insert->execute([
            $old['full_name'],
            $old['email'],
            $hashed,
            $old['phone'],
            $old['address'],
        ]);

        setFlash('success', 'Account created successfully! You can now log in.');
        redirect(BASE_URL . '/customer/auth/customer_login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | BookMyHall</title>
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

<div class="auth-card wide">
    <div class="auth-header">
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-subtitle">Start booking your perfect event hall today</p>
    </div>

    <!-- Errors -->
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($err) ?>
        </div>
    <?php endforeach; ?>

    <form class="auth-form" method="POST" action="" novalidate>

        <div class="form-row">
            <div class="form-group">
                <label for="full_name">Full Name <span style="color:#e74c3c">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                           placeholder="Lee Maridean" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email Address <span style="color:#e74c3c">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                           placeholder="you@email.com" required autocomplete="email">
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-phone input-icon"></i>
                    <input type="tel" id="phone" name="phone"
                           value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                           placeholder="+94 77 000 0000">
                </div>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-location-dot input-icon"></i>
                    <input type="text" id="address" name="address"
                           value="<?= htmlspecialchars($old['address'] ?? '') ?>"
                           placeholder="City, Country">
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Password <span style="color:#e74c3c">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="password" name="password"
                           placeholder="At least 8 characters" required>
                    <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <p class="strength-text" id="strengthLabel"></p>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span style="color:#e74c3c">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repeat your password" required>
                    <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Toggle confirm password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-user-plus"></i>
            Create Account
        </button>

    </form>

    <p class="terms-note">
        By registering you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
    </p>

    <p class="auth-footer">
        Already have an account? <a href="<?= BASE_URL ?>/customer/auth/customer_login.php">Sign in</a>
    </p>
</div>

<script src="<?= BASE_URL ?>/assets/js/customer/auth.js"></script>

</body>
</html>
