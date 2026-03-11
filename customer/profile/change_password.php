<?php
/**
 * change_password.php — Customer: Change password (requires current password)
 * Module 3 – Nishtha
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

$customerId = (int)$_SESSION['customer_id'];

// Fetch customer
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect(BASE_URL . '/customer/auth/customer_logout.php');
}

$errors  = [];
$success = false;

// ─── Handle POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd     = $_POST['new_password']     ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    if (empty($currentPwd) || empty($newPwd) || empty($confirmPwd)) {
        $errors[] = 'All fields are required.';
    } elseif (!verifyPassword($currentPwd, $customer['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($newPwd) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($newPwd !== $confirmPwd) {
        $errors[] = 'New passwords do not match.';
    } elseif ($newPwd === $currentPwd) {
        $errors[] = 'New password must be different from your current password.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
                ->execute([hashPassword($newPwd), $customerId]);

            setFlash('success', 'Password changed successfully.');
            redirect(BASE_URL . '/customer/profile/profile.php');
        } catch (PDOException $e) {
            error_log("change_password: " . $e->getMessage());
            $errors[] = 'An error occurred. Please try again.';
        }
    }
}

$initials = strtoupper(implode('', array_map(fn($w) => $w[0],
    array_slice(explode(' ', $customer['full_name']), 0, 2))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Change Password — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/profile.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php
$pageTitle    = 'Change Password';
$pageSubtitle = 'Update your account password';
include __DIR__ . '/../includes/customer_topbar.php';
?>

<div class="c-content-wrapper">
<div class="customer-content">

    <!-- Errors -->
    <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($err) ?>
    </div>
    <?php endforeach; ?>

    <div class="profile-layout">

        <!-- Left: profile card -->
        <aside class="profile-card">
            <div class="profile-avatar-wrap">
                <div class="profile-avatar">
                    <?php if (!empty($customer['profile_picture'])): ?>
                        <img src="<?= BASE_URL ?>/assets/images/profiles/<?= htmlspecialchars($customer['profile_picture']) ?>" alt="Profile picture">
                    <?php else: ?>
                        <?= htmlspecialchars($initials) ?>
                    <?php endif; ?>
                </div>
            </div>
            <p class="profile-name"><?= htmlspecialchars($customer['full_name']) ?></p>
            <span class="profile-role-badge">Customer</span>

            <hr class="profile-divider">

            <div style="display:flex;flex-direction:column;gap:10px;margin-top:4px;">
                <a href="<?= BASE_URL ?>/customer/profile/profile.php" class="btn btn-outline btn-full">
                    <i class="fa-solid fa-arrow-left"></i> Back to Profile
                </a>
                <a href="<?= BASE_URL ?>/customer/profile/edit_profile.php" class="btn btn-outline btn-full">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile
                </a>
            </div>
        </aside>

        <!-- Right: change password form -->
        <div class="profile-forms-panel">
            <div class="profile-section-card">
                <h2 class="profile-section-title">
                    <i class="fa-solid fa-lock"></i> Change Password
                </h2>

                <p style="font-size:13px;color:var(--text-muted);margin:0 0 24px;">
                    Choose a strong password of at least 8 characters to keep your account secure.
                </p>

                <form method="POST" action="" novalidate autocomplete="off">

                    <!-- Current password -->
                    <div class="form-group">
                        <label class="form-label" for="current_password">
                            Current Password <span style="color:var(--danger);">*</span>
                        </label>
                        <div style="position:relative;">
                            <input class="form-control" type="password" id="current_password"
                                   name="current_password" required
                                   placeholder="Enter your current password"
                                   style="padding-right:44px;">
                            <button type="button" class="pwd-toggle" aria-label="Show/hide password"
                                    data-target="current_password"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:15px;">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- New password -->
                    <div class="form-group">
                        <label class="form-label" for="new_password">
                            New Password <span style="color:var(--danger);">*</span>
                        </label>
                        <div style="position:relative;">
                            <input class="form-control" type="password" id="new_password"
                                   name="new_password" required minlength="8"
                                   placeholder="At least 8 characters"
                                   style="padding-right:44px;">
                            <button type="button" class="pwd-toggle" aria-label="Show/hide password"
                                    data-target="new_password"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:15px;">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <!-- Strength bar -->
                        <div class="pwd-strength-bar" id="strength-bar">
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <small class="pwd-strength-label" id="strength-label" style="color:var(--text-muted);"></small>
                    </div>

                    <!-- Confirm password -->
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">
                            Confirm New Password <span style="color:var(--danger);">*</span>
                        </label>
                        <div style="position:relative;">
                            <input class="form-control" type="password" id="confirm_password"
                                   name="confirm_password" required
                                   placeholder="Re-enter new password"
                                   style="padding-right:44px;">
                            <button type="button" class="pwd-toggle" aria-label="Show/hide password"
                                    data-target="confirm_password"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:15px;">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <small id="match-hint" style="font-size:12px;margin-top:4px;display:none;"></small>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:8px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-shield-halved"></i> Update Password
                        </button>
                        <a href="<?= BASE_URL ?>/customer/profile/profile.php" class="btn btn-outline">
                            Cancel
                        </a>
                    </div>

                </form>
            </div>
        </div>

    </div><!-- /.profile-layout -->

</div><!-- /.customer-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> — Lee Maridean Banquet Hall. All rights reserved.</p>
</footer>
</div><!-- /.c-content-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
// ── Show/hide password toggles ─────────────────────────────────────────────
document.querySelectorAll('.pwd-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        if (!input) return;
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.querySelector('i').className = isText ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
    });
});

// ── Password strength meter ────────────────────────────────────────────────
const newPwdInput  = document.getElementById('new_password');
const strengthBar  = document.getElementById('strength-bar');
const strengthLbl  = document.getElementById('strength-label');

function measureStrength(pwd) {
    let score = 0;
    if (pwd.length >= 8)                          score++;
    if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd))  score++;
    if (/\d/.test(pwd))                           score++;
    if (/[^A-Za-z0-9]/.test(pwd))                score++;
    return score;
}

newPwdInput.addEventListener('input', () => {
    const val   = newPwdInput.value;
    const score = val.length ? measureStrength(val) : 0;
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const classes = ['', 'weak', 'fair', 'good', 'strong'];
    const colours = ['', 'var(--danger)', 'var(--warning)', 'var(--primary)', 'var(--success)'];

    strengthBar.className = 'pwd-strength-bar ' + (classes[score] || '');
    strengthLbl.textContent = val.length ? labels[score] : '';
    strengthLbl.style.color = colours[score] || 'var(--text-muted)';
});

// ── Confirm match hint ─────────────────────────────────────────────────────
const confirmInput = document.getElementById('confirm_password');
const matchHint    = document.getElementById('match-hint');

confirmInput.addEventListener('input', () => {
    if (!confirmInput.value) { matchHint.style.display = 'none'; return; }
    const match = confirmInput.value === newPwdInput.value;
    matchHint.textContent   = match ? '✓ Passwords match' : '✗ Passwords do not match';
    matchHint.style.color   = match ? 'var(--success)' : 'var(--danger)';
    matchHint.style.display = 'block';
});
</script>
</body>
</html>
