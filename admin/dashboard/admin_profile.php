<?php
/**
 * admin/dashboard/admin_profile.php — View & edit admin profile; change password.
 * Module 1 – Sahani
 */
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = $_SESSION['admin_id'];

// ─── Fetch admin record ───────────────────────────────────────────────────────
$stmt  = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    redirect(BASE_URL . '/admin/auth/logout.php');
}

$profileErrors   = [];
$passwordErrors  = [];
$profileSuccess  = false;
$passwordSuccess = false;

// ─── Handle profile update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $phone    = sanitizeInput($_POST['phone']     ?? '');
    $address  = sanitizeInput($_POST['address']   ?? '');

    if (empty($fullName)) {
        $profileErrors[] = 'Full name is required.';
    }

    if (empty($profileErrors)) {
        $upd = $pdo->prepare(
            "UPDATE users SET full_name = ?, phone = ?, address = ? WHERE user_id = ?"
        );
        $upd->execute([$fullName, $phone, $address, $adminId]);
        $_SESSION['full_name'] = $fullName;   // keep session in sync
        $profileSuccess = true;
        // Refresh admin data
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
    }
}

// ─── Handle password change ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd     = $_POST['new_password']     ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    if (empty($currentPwd) || empty($newPwd) || empty($confirmPwd)) {
        $passwordErrors[] = 'All password fields are required.';
    } elseif (!verifyPassword($currentPwd, $admin['password'])) {
        $passwordErrors[] = 'Current password is incorrect.';
    } elseif (strlen($newPwd) < 8) {
        $passwordErrors[] = 'New password must be at least 8 characters.';
    } elseif ($newPwd !== $confirmPwd) {
        $passwordErrors[] = 'New passwords do not match.';
    }

    if (empty($passwordErrors)) {
        $hashed = hashPassword($newPwd);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
            ->execute([$hashed, $adminId]);
        $passwordSuccess = true;
    }
}

$pageTitle    = 'My Profile';
$pageSubtitle = 'Manage your account details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | BookMyHall Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css">
    <style>
        .profile-layout { display: grid; grid-template-columns: 280px 1fr; gap: 24px; }
        .profile-card   { background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); padding: 32px 24px; text-align: center; }
        .avatar-circle  { width: 90px; height: 90px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 36px; font-weight: 700; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .profile-name   { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
        .profile-role   { font-size: 13px; color: var(--text-muted); margin: 0 0 20px; }
        .profile-detail { font-size: 13px; color: var(--text-muted); margin: 6px 0; }
        .profile-detail i { width: 18px; margin-right: 6px; color: var(--primary); }
        .forms-panel { display: flex; flex-direction: column; gap: 24px; }
        .section-card { background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); padding: 28px; }
        .section-title { font-size: 16px; font-weight: 700; margin: 0 0 20px; display: flex; align-items: center; gap: 10px; }
        .section-title i { color: var(--primary); }
        @media (max-width: 900px) { .profile-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">

    <div class="profile-layout">

        <!-- ── Profile card (left) ─────────────────────────────────────────── -->
        <aside class="profile-card">
            <?php
            $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', $admin['full_name']), 0, 2))));
            ?>
            <div class="avatar-circle"><?= htmlspecialchars($initials) ?></div>
            <p class="profile-name"><?= htmlspecialchars($admin['full_name']) ?></p>
            <p class="profile-role">Administrator</p>

            <p class="profile-detail"><i class="fa-solid fa-envelope"></i><?= htmlspecialchars($admin['email']) ?></p>
            <?php if ($admin['phone']): ?>
            <p class="profile-detail"><i class="fa-solid fa-phone"></i><?= htmlspecialchars($admin['phone']) ?></p>
            <?php endif; ?>
            <?php if ($admin['address']): ?>
            <p class="profile-detail"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($admin['address']) ?></p>
            <?php endif; ?>
            <p class="profile-detail"><i class="fa-solid fa-calendar"></i>Joined <?= formatDateReadable($admin['created_at']) ?></p>
            <?php if ($admin['last_login']): ?>
            <p class="profile-detail"><i class="fa-solid fa-clock"></i>Last login <?= formatDateReadable($admin['last_login']) ?></p>
            <?php endif; ?>
        </aside>

        <!-- ── Forms panel (right) ─────────────────────────────────────────── -->
        <div class="forms-panel">

            <!-- Edit profile form -->
            <section class="section-card">
                <h2 class="section-title"><i class="fa-solid fa-user-pen"></i> Edit Profile</h2>

                <?php if ($profileSuccess): ?>
                    <div class="alert alert-success" data-auto-dismiss>
                        <i class="fa-solid fa-check-circle"></i> Profile updated successfully.
                    </div>
                <?php endif; ?>
                <?php foreach ($profileErrors as $e): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color:red">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="form-control"
                                   value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email_display">Email Address</label>
                            <input type="email" id="email_display" class="form-control"
                                   value="<?= htmlspecialchars($admin['email']) ?>" disabled
                                   title="Email cannot be changed here">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($admin['phone'] ?? '') ?>"
                                   placeholder="+94 77 000 0000">
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control"
                                   value="<?= htmlspecialchars($admin['address'] ?? '') ?>"
                                   placeholder="City, Country">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                </form>
            </section>

            <!-- Change password form -->
            <section class="section-card">
                <h2 class="section-title"><i class="fa-solid fa-lock"></i> Change Password</h2>

                <?php if ($passwordSuccess): ?>
                    <div class="alert alert-success" data-auto-dismiss>
                        <i class="fa-solid fa-check-circle"></i> Password changed successfully.
                    </div>
                <?php endif; ?>
                <?php foreach ($passwordErrors as $e): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password"
                               class="form-control" placeholder="Enter current password" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password"
                                   class="form-control" placeholder="At least 8 characters" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="form-control" placeholder="Repeat new password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-key"></i> Update Password
                    </button>
                </form>
            </section>

        </div><!-- /.forms-panel -->
    </div><!-- /.profile-layout -->

</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

</body>
</html>
