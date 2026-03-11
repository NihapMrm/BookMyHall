<?php
/**
 * edit_package.php — Admin: Edit an Existing Package
 * Module 2 – Riffna
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$errors = [];
$package = null;
$mainPackages = [];

$packageId = (int) ($_GET['id'] ?? 0);

if ($packageId <= 0) {
    setFlash('danger', 'Invalid package.');
    redirect(BASE_URL . '/admin/packages/manage_packages.php');
}

try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
    if ($hall) {
        $stmt = $pdo->prepare("SELECT * FROM packages WHERE package_id = ? AND hall_id = ?");
        $stmt->execute([$packageId, $hall['hall_id']]);
        $package = $stmt->fetch();

        // All main packages (for parent selector if this is a sub)
        $stmt2 = $pdo->prepare(
            "SELECT package_id, name FROM packages WHERE hall_id = ? AND type = 'main' AND package_id != ? ORDER BY name"
        );
        $stmt2->execute([$hall['hall_id'], $packageId]);
        $mainPackages = $stmt2->fetchAll();
    }
} catch (PDOException $e) {
    error_log("edit_package load: " . $e->getMessage());
}

if (!$package) {
    setFlash('danger', 'Package not found.');
    redirect(BASE_URL . '/admin/packages/manage_packages.php');
}

$currentServices = [];
if (!empty($package['services'])) {
    $decoded = json_decode($package['services'], true);
    if (is_array($decoded)) $currentServices = $decoded;
}

$serviceKeys = ['catering', 'ac', 'decoration', 'wifi', 'parking'];
$serviceLabels = [
    'catering'   => ['label' => 'Catering',    'icon' => 'fa-utensils'],
    'ac'         => ['label' => 'AC',          'icon' => 'fa-snowflake'],
    'decoration' => ['label' => 'Decoration',  'icon' => 'fa-wand-magic-sparkles'],
    'wifi'       => ['label' => 'Wi-Fi',       'icon' => 'fa-wifi'],
    'parking'    => ['label' => 'Parking',     'icon' => 'fa-square-parking'],
];

// ── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = sanitizeInput($_POST['name'] ?? '');
    $type              = in_array($_POST['type'] ?? '', ['main','sub']) ? $_POST['type'] : $package['type'];
    $parent_package_id = (int) ($_POST['parent_package_id'] ?? 0);
    $price             = (float) ($_POST['price'] ?? 0);
    $seat_capacity     = !empty($_POST['seat_capacity']) ? (int)$_POST['seat_capacity'] : null;
    $parking_capacity  = !empty($_POST['parking_capacity']) ? (int)$_POST['parking_capacity'] : null;
    $description       = sanitizeInput($_POST['description'] ?? '');
    $inclusions        = sanitizeInput($_POST['inclusions'] ?? '');
    $is_active         = isset($_POST['is_active']) ? 1 : 0;

    $selectedServices = [];
    foreach ($serviceKeys as $key) {
        if (isset($_POST['services'][$key])) {
            $selectedServices[] = $key;
        }
    }
    $servicesJson = json_encode($selectedServices);
    $currentServices = $selectedServices;

    // Update package object for re-render on error
    $package = array_merge($package, [
        'name'             => $name, 'type' => $type,
        'parent_package_id' => $parent_package_id,
        'price'            => $price, 'seat_capacity' => $seat_capacity,
        'parking_capacity' => $parking_capacity, 'description' => $description,
        'inclusions'       => $inclusions, 'is_active' => $is_active,
    ]);

    // Validation
    if (empty($name))    $errors[] = 'Package name is required.';
    if ($price < 0)      $errors[] = 'Price cannot be negative.';
    if ($type === 'sub' && $parent_package_id <= 0) {
        $errors[] = 'Please select a parent package for a sub-package.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE packages
                 SET name=?, type=?, parent_package_id=?, price=?, seat_capacity=?,
                     parking_capacity=?, description=?, inclusions=?, services=?, is_active=?
                 WHERE package_id=?"
            );
            $stmt->execute([
                $name, $type,
                $type === 'sub' ? $parent_package_id : null,
                $price, $seat_capacity, $parking_capacity,
                $description, $inclusions, $servicesJson, $is_active,
                $packageId,
            ]);
            setFlash('success', 'Package updated successfully.');
            redirect(BASE_URL . '/admin/packages/manage_packages.php');
        } catch (PDOException $e) {
            error_log("edit_package save: " . $e->getMessage());
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit Package — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/packages.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit Package</h1>
                <p class="page-subtitle">Updating: <strong><?= htmlspecialchars($package['name']) ?></strong></p>
            </div>
            <a href="<?= BASE_URL ?>/admin/packages/manage_packages.php" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <ul style="margin:6px 0 0; padding-left:18px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="pkg-form-card">

                <!-- Type Toggle -->
                <div class="form-group">
                    <label class="form-label">Package Type</label>
                    <div class="pkg-type-toggle">
                        <input type="radio" id="type_main" name="type" value="main"
                               <?= $package['type'] === 'main' ? 'checked' : '' ?>>
                        <label for="type_main">Main Package</label>

                        <input type="radio" id="type_sub" name="type" value="sub"
                               <?= $package['type'] === 'sub' ? 'checked' : '' ?>>
                        <label for="type_sub">Sub-Package</label>
                    </div>
                </div>

                <!-- Parent Package -->
                <div class="form-group pkg-parent-field <?= $package['type'] === 'main' ? 'hidden' : '' ?>">
                    <label class="form-label">Parent Package <span class="required">*</span></label>
                    <select name="parent_package_id" class="form-control">
                        <option value="">— Select Main Package —</option>
                        <?php foreach ($mainPackages as $mp): ?>
                            <option value="<?= $mp['package_id'] ?>"
                                <?= $package['parent_package_id'] == $mp['package_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Name & Price -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Package Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150"
                               value="<?= htmlspecialchars($package['name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (LKR) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" class="form-control" required min="0" step="0.01"
                               value="<?= htmlspecialchars($package['price']) ?>">
                        <span id="pricePreview" style="font-size:13px; color:var(--primary); margin-top:4px; display:block;">
                            <?= formatCurrency($package['price']) ?>
                        </span>
                    </div>
                </div>

                <!-- Capacity -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Seat Capacity</label>
                        <input type="number" name="seat_capacity" class="form-control" min="1"
                               value="<?= htmlspecialchars($package['seat_capacity'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Parking Capacity</label>
                        <input type="number" name="parking_capacity" class="form-control" min="0"
                               value="<?= htmlspecialchars($package['parking_capacity'] ?? '') ?>">
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                    ><?= htmlspecialchars($package['description'] ?? '') ?></textarea>
                </div>

                <!-- Inclusions -->
                <div class="form-group">
                    <label class="form-label">Inclusions</label>
                    <textarea name="inclusions" class="form-control" rows="3"
                    ><?= htmlspecialchars($package['inclusions'] ?? '') ?></textarea>
                </div>

                <!-- Services -->
                <div class="form-group">
                    <label class="form-label">Included Services</label>
                    <div class="service-grid">
                        <?php foreach ($serviceLabels as $key => $info): ?>
                        <label class="service-item">
                            <input type="checkbox" name="services[<?= $key ?>]"
                                   <?= in_array($key, $currentServices) ? 'checked' : '' ?>>
                            <i class="fa-solid <?= $info['icon'] ?>"></i>
                            <span><?= $info['label'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" <?= $package['is_active'] ? 'checked' : '' ?>>
                        <span class="toggle-track"></span>
                        <span>Active (visible to customers)</span>
                    </label>
                </div>

                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                    <a href="<?= BASE_URL ?>/admin/packages/manage_packages.php" class="btn btn-outline">Cancel</a>
                    <a href="<?= BASE_URL ?>/admin/packages/delete_package.php?id=<?= $packageId ?>"
                       class="btn btn-danger" style="margin-left:auto;">
                        <i class="fa-solid fa-trash-can"></i> Delete Package
                    </a>
                </div>
            </div>
        </form>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/packages.js"></script>
</body>
</html>
