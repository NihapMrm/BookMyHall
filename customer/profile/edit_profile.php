<?php
/**
 * edit_profile.php — Customer: Edit name, phone, address, profile picture
 * Module 3 – Nishtha
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/customer_session_guard.php';

$customerId = (int)$_SESSION['customer_id'];

// ─── Fetch customer ───────────────────────────────────────────────────────
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

    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $phone    = sanitizeInput($_POST['phone']     ?? '');
    $address  = sanitizeInput($_POST['address']   ?? '');

    // Validation
    if (empty($fullName)) {
        $errors[] = 'Full name is required.';
    }

    // ── Profile picture upload ────────────────────────────────────────────
    $newPicture = $customer['profile_picture']; // keep existing by default

    if (!empty($_FILES['profile_picture']['name'])) {
        $file    = $_FILES['profile_picture'];
        $tmpPath = $file['tmp_name'];
        $maxSize = 2 * 1024 * 1024; // 2 MB

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType     = mime_content_type($tmpPath);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Image must be 2 MB or smaller.';
        } elseif (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Only JPG, PNG, GIF or WebP images are allowed.';
        } else {
            $ext        = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                default      => 'jpg',
            };
            $filename   = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $uploadDir  = __DIR__ . '/../../assets/images/profiles/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!move_uploaded_file($tmpPath, $uploadDir . $filename)) {
                $errors[] = 'Could not save the uploaded image. Please try again.';
            } else {
                // Delete old picture if it exists
                if (!empty($customer['profile_picture'])) {
                    $oldPath = $uploadDir . $customer['profile_picture'];
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $newPicture = $filename;
            }
        }
    }

    // ── Save to DB ────────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $pdo->prepare(
                "UPDATE users SET full_name = ?, phone = ?, address = ?, profile_picture = ? WHERE user_id = ?"
            )->execute([$fullName, $phone, $address, $newPicture, $customerId]);

            // Keep session name in sync
            $_SESSION['customer_name'] = $fullName;

            setFlash('success', 'Your profile has been updated successfully.');
            redirect(BASE_URL . '/customer/profile/profile.php');
        } catch (PDOException $e) {
            error_log("edit_profile save: " . $e->getMessage());
            $errors[] = 'An error occurred while saving. Please try again.';
        }
    }

    // On error, re-merge POST values into $customer for form repopulation
    $customer['full_name'] = $fullName;
    $customer['phone']     = $phone;
    $customer['address']   = $address;
}

$initials = strtoupper(implode('', array_map(fn($w) => $w[0],
    array_slice(explode(' ', $customer['full_name']), 0, 2))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Profile — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/customer_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer/profile.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/customer_sidebar.php'; ?>
<?php
$pageTitle    = 'Edit Profile';
$pageSubtitle = 'Update your personal details and profile picture';
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

        <!-- Left: current info card -->
        <aside class="profile-card">
            <div class="profile-avatar-wrap">
                <div class="profile-avatar">
                    <?php if (!empty($customer['profile_picture'])): ?>
                        <img id="avatar-preview"
                             src="<?= BASE_URL ?>/assets/images/profiles/<?= htmlspecialchars($customer['profile_picture']) ?>"
                             alt="Profile picture">
                    <?php else: ?>
                        <span id="avatar-initials"><?= htmlspecialchars($initials) ?></span>
                        <img id="avatar-preview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;">
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
                <a href="<?= BASE_URL ?>/customer/profile/change_password.php" class="btn btn-outline btn-full">
                    <i class="fa-solid fa-lock"></i> Change Password
                </a>
            </div>
        </aside>

        <!-- Right: edit form -->
        <div class="profile-forms-panel">
            <div class="profile-section-card">
                <h2 class="profile-section-title">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile
                </h2>

                <form method="POST" action="" enctype="multipart/form-data" novalidate>

                    <!-- Avatar upload -->
                    <div class="avatar-upload-area">
                        <div class="avatar-upload-preview" id="upload-preview">
                            <?php if (!empty($customer['profile_picture'])): ?>
                                <img src="<?= BASE_URL ?>/assets/images/profiles/<?= htmlspecialchars($customer['profile_picture']) ?>"
                                     id="upload-thumb" alt="Current picture">
                            <?php else: ?>
                                <i class="fa-solid fa-user" id="upload-icon"></i>
                                <img id="upload-thumb" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;">
                            <?php endif; ?>
                        </div>
                        <div class="avatar-upload-info">
                            <p>Profile picture (optional) — JPG, PNG, GIF or WebP · max 2 MB</p>
                            <label class="btn btn-outline btn-sm" for="profile_picture" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                                <i class="fa-solid fa-upload"></i> Choose Image
                            </label>
                            <input type="file" id="profile_picture" name="profile_picture"
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   style="display:none;">
                        </div>
                    </div>

                    <!-- Full name -->
                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name <span style="color:var(--danger);">*</span></label>
                        <input class="form-control" type="text" id="full_name" name="full_name"
                               value="<?= htmlspecialchars($customer['full_name']) ?>"
                               required autocomplete="name" placeholder="Your full name">
                    </div>

                    <!-- Email (read-only) -->
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input class="form-control" type="email" id="email"
                               value="<?= htmlspecialchars($customer['email']) ?>"
                               disabled style="opacity:.7;cursor:not-allowed;">
                        <small style="font-size:12px;color:var(--text-muted);margin-top:4px;display:block;">
                            <i class="fa-solid fa-lock" style="font-size:10px;"></i>
                            Email cannot be changed. Contact support if needed.
                        </small>
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input class="form-control" type="tel" id="phone" name="phone"
                               value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                               autocomplete="tel" placeholder="e.g. +94 77 123 4567">
                    </div>

                    <!-- Address -->
                    <div class="form-group">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control" id="address" name="address"
                                  rows="3" placeholder="Your home or mailing address"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:8px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Save Changes
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
// Live avatar preview on file select
document.getElementById('profile_picture').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const thumb = document.getElementById('upload-thumb');
        const icon  = document.getElementById('upload-icon');
        if (thumb) { thumb.src = e.target.result; thumb.style.display = 'block'; }
        if (icon)  { icon.style.display = 'none'; }
    };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>
