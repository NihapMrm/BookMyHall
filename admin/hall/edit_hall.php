<?php
/**
 * edit_hall.php — Admin: Create or Edit Hall Details
 * Module 2 – Riffna
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$errors  = [];
$success = false;
$isSetup = isset($_GET['setup']);

// Load existing hall
$hall = null;
try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
} catch (PDOException $e) {
    error_log("edit_hall load: " . $e->getMessage());
}

$currentFeatures = [];
if ($hall && !empty($hall['features'])) {
    $decoded = json_decode($hall['features'], true);
    if (is_array($decoded)) $currentFeatures = $decoded;
}

$amenityKeys = ['ac', 'stage', 'parking', 'sound_system', 'catering', 'wifi', 'bridal_suite', 'projector'];

$amenityLabels = [
    'ac'           => ['label' => 'Air Conditioning', 'icon' => 'fa-snowflake'],
    'stage'        => ['label' => 'Grand Stage',       'icon' => 'fa-star'],
    'parking'      => ['label' => 'Parking',           'icon' => 'fa-square-parking'],
    'sound_system' => ['label' => 'Sound System',      'icon' => 'fa-music'],
    'catering'     => ['label' => 'Catering',          'icon' => 'fa-utensils'],
    'wifi'         => ['label' => 'Wi-Fi',             'icon' => 'fa-wifi'],
    'bridal_suite' => ['label' => 'Bridal Suite',      'icon' => 'fa-heart'],
    'projector'    => ['label' => 'Projector / AV',    'icon' => 'fa-display'],
];

// ── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $capacity    = (int) ($_POST['capacity'] ?? 0);
    $location    = sanitizeInput($_POST['location'] ?? '');
    $size_sqft   = !empty($_POST['size_sqft']) ? (int) $_POST['size_sqft'] : null;
    $base_price  = (float) ($_POST['base_price'] ?? 0);
    $status      = sanitizeInput($_POST['status'] ?? 'available');

    // Collect checked amenities
    $selectedFeatures = [];
    foreach ($amenityKeys as $key) {
        if (isset($_POST['features'][$key])) {
            $selectedFeatures[] = $key;
        }
    }
    $featuresJson = json_encode($selectedFeatures);

    // Validation
    if (empty($name))       $errors[] = 'Hall name is required.';
    if ($capacity <= 0)     $errors[] = 'Capacity must be greater than zero.';
    if ($base_price < 0)    $errors[] = 'Base price cannot be negative.';
    if (!in_array($status, ['available', 'unavailable', 'maintenance'])) {
        $errors[] = 'Invalid status value.';
    }

    if (empty($errors)) {
        try {
            if ($hall) {
                // UPDATE
                $stmt = $pdo->prepare(
                    "UPDATE hall
                     SET name=?, description=?, capacity=?, location=?, size_sqft=?,
                         base_price=?, features=?, status=?
                     WHERE hall_id=?"
                );
                $stmt->execute([
                    $name, $description, $capacity, $location, $size_sqft,
                    $base_price, $featuresJson, $status, $hall['hall_id']
                ]);
            } else {
                // INSERT (first-time setup)
                $stmt = $pdo->prepare(
                    "INSERT INTO hall (name, description, capacity, location, size_sqft, base_price, features, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $name, $description, $capacity, $location, $size_sqft,
                    $base_price, $featuresJson, $status
                ]);
            }
            setFlash('success', 'Hall details updated successfully.');
            redirect(BASE_URL . '/admin/hall/manage_hall.php');
        } catch (PDOException $e) {
            error_log("edit_hall save: " . $e->getMessage());
            $errors[] = 'A database error occurred. Please try again.';
        }
    }

    // Re-populate fields on error
    $hall = [
        'name'        => $name,
        'description' => $description,
        'capacity'    => $capacity,
        'location'    => $location,
        'size_sqft'   => $size_sqft,
        'base_price'  => $base_price,
        'status'      => $status,
    ];
    $currentFeatures = $selectedFeatures;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $isSetup ? 'Set Up Hall' : 'Edit Hall' ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/admin_global.css"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/hall.css"/>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="content-wrapper">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= $isSetup ? 'Set Up Hall' : 'Edit Hall Details' ?></h1>
                <p class="page-subtitle">Update the hall's information, amenities, and operational status.</p>
            </div>
            <?php if (!$isSetup): ?>
            <a href="<?= BASE_URL ?>/admin/hall/manage_hall.php" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
            <?php endif; ?>
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
            <div class="edit-hall-card">

                <!-- Basic Info -->
                <div class="section-divider">Basic Information</div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Hall Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150"
                               value="<?= htmlspecialchars($hall['name'] ?? '') ?>"
                               placeholder="e.g., Lee Maridean Banquet Hall">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" maxlength="255"
                               value="<?= htmlspecialchars($hall['location'] ?? '') ?>"
                               placeholder="e.g., 45 Main Street, Colombo">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"
                              placeholder="Describe the hall's highlights, atmosphere, and unique features..."
                    ><?= htmlspecialchars($hall['description'] ?? '') ?></textarea>
                </div>

                <!-- Capacity & Pricing -->
                <div class="section-divider">Capacity &amp; Pricing</div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Guest Capacity <span class="required">*</span></label>
                        <input type="number" name="capacity" class="form-control" required min="1"
                               value="<?= htmlspecialchars($hall['capacity'] ?? '') ?>"
                               placeholder="e.g., 500">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hall Size (sq ft)</label>
                        <input type="number" name="size_sqft" class="form-control" min="1"
                               value="<?= htmlspecialchars($hall['size_sqft'] ?? '') ?>"
                               placeholder="e.g., 4000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Base Price (LKR) <span class="required">*</span></label>
                        <input type="number" name="base_price" class="form-control" required min="0" step="0.01"
                               value="<?= htmlspecialchars($hall['base_price'] ?? '0.00') ?>"
                               placeholder="e.g., 15000.00">
                    </div>
                </div>

                <!-- Status -->
                <div class="section-divider">Operational Status</div>

                <div class="form-group">
                    <label class="form-label">Hall Status <span class="required">*</span></label>
                    <select name="status" class="form-control" required>
                        <option value="available"   <?= ($hall['status'] ?? '') === 'available'   ? 'selected' : '' ?>>Available</option>
                        <option value="unavailable" <?= ($hall['status'] ?? '') === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                        <option value="maintenance" <?= ($hall['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                    </select>
                </div>

                <!-- Amenities -->
                <div class="section-divider">Amenities &amp; Features</div>

                <div class="checkbox-grid">
                    <?php foreach ($amenityLabels as $key => $info): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="features[<?= $key ?>]"
                               <?= in_array($key, $currentFeatures) ? 'checked' : '' ?>>
                        <i class="fa-solid <?= $info['icon'] ?>" style="color:var(--primary);"></i>
                        <span><?= $info['label'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Actions -->
                <div style="display:flex; gap:12px; margin-top:32px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= $isSetup ? 'Create Hall' : 'Save Changes' ?>
                    </button>
                    <?php if (!$isSetup): ?>
                    <a href="<?= BASE_URL ?>/admin/hall/manage_hall.php" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
