<?php
/**
 * add_package.php — Admin: Add New Package
 * Module 2 – Riffna
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$pageTitle    = 'Add Package';
$pageSubtitle = 'Create a new event package';

$errors = [];
$hall   = null;

try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
} catch (PDOException $e) {
    error_log("add_package load: " . $e->getMessage());
}

if (!$hall) {
    setFlash('error', 'Please set up the hall first.');
    redirect(BASE_URL . '/admin/hall/edit_hall.php');
}

$serviceKeys = ['catering', 'ac', 'decoration', 'wifi', 'parking'];
$serviceLabels = [
    'catering'   => ['label' => 'Catering',    'icon' => 'fa-utensils'],
    'ac'         => ['label' => 'AC',          'icon' => 'fa-snowflake'],
    'decoration' => ['label' => 'Decoration',  'icon' => 'fa-wand-magic-sparkles'],
    'wifi'       => ['label' => 'Wi-Fi',       'icon' => 'fa-wifi'],
    'parking'    => ['label' => 'Parking',     'icon' => 'fa-square-parking'],
];

$formData = [
    'name'             => '',
    'price'            => '',
    'seat_capacity'    => '',
    'parking_capacity' => '',
    'description'      => '',
    'inclusions'       => '',
    'services'         => [],
    'is_active'        => 1,
];

// ── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name']             = sanitizeInput($_POST['name'] ?? '');
    $formData['price']            = (float) ($_POST['price'] ?? 0);
    $formData['seat_capacity']    = !empty($_POST['seat_capacity']) ? (int)$_POST['seat_capacity'] : null;
    $formData['parking_capacity'] = !empty($_POST['parking_capacity']) ? (int)$_POST['parking_capacity'] : null;
    $formData['description']      = sanitizeInput($_POST['description'] ?? '');
    $formData['inclusions']       = sanitizeInput($_POST['inclusions'] ?? '');
    $formData['is_active']        = isset($_POST['is_active']) ? 1 : 0;

    $selectedServices = [];
    foreach ($serviceKeys as $key) {
        if (isset($_POST['services'][$key])) {
            $selectedServices[] = $key;
        }
    }
    $formData['services'] = $selectedServices;
    $servicesJson = json_encode($selectedServices);

    if (empty($formData['name']))  $errors[] = 'Package name is required.';
    if ($formData['price'] < 0)    $errors[] = 'Price cannot be negative.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO packages
                 (hall_id, name, price, seat_capacity, parking_capacity,
                  description, inclusions, services, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $hall['hall_id'],
                $formData['name'],
                $formData['price'],
                $formData['seat_capacity'],
                $formData['parking_capacity'],
                $formData['description'],
                $formData['inclusions'],
                $servicesJson,
                $formData['is_active'],
            ]);
            setFlash('success', 'Package "' . $formData['name'] . '" created successfully.');
            redirect(BASE_URL . '/admin/packages/manage_packages.php');
        } catch (PDOException $e) {
            error_log("add_package insert: " . $e->getMessage());
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
    <title>Add Package — <?= SITE_NAME ?></title>
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
                <h1 class="page-title">Add Package</h1>
                <p class="page-subtitle">Create a new event package for <?= htmlspecialchars($hall['name']) ?>.</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/packages/manage_packages.php" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
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

                <!-- Name & Price -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Package Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150"
                               value="<?= htmlspecialchars($formData['name']) ?>"
                               placeholder="e.g., Gold Premium Package">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (LKR) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" class="form-control" required min="0" step="0.01"
                               value="<?= htmlspecialchars($formData['price']) ?>"
                               placeholder="e.g., 75000">
                        <span id="pricePreview" style="font-size:13px; color:var(--primary); margin-top:4px; display:block;"></span>
                    </div>
                </div>

                <!-- Capacity -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Seat Capacity</label>
                        <input type="number" name="seat_capacity" class="form-control" min="1"
                               value="<?= htmlspecialchars($formData['seat_capacity'] ?? '') ?>"
                               placeholder="e.g., 200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Parking Capacity</label>
                        <input type="number" name="parking_capacity" class="form-control" min="0"
                               value="<?= htmlspecialchars($formData['parking_capacity'] ?? '') ?>"
                               placeholder="e.g., 50">
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Briefly describe this package..."
                    ><?= htmlspecialchars($formData['description']) ?></textarea>
                </div>

                <!-- Inclusions -->
                <div class="form-group">
                    <label class="form-label">Inclusions</label>
                    <textarea name="inclusions" class="form-control" rows="3"
                              placeholder="List what is included (one per line)..."
                    ><?= htmlspecialchars($formData['inclusions']) ?></textarea>
                </div>

                <!-- Services -->
                <div class="form-group">
                    <label class="form-label">Included Services</label>
                    <div class="service-grid">
                        <?php foreach ($serviceLabels as $key => $info): ?>
                        <label class="service-item">
                            <input type="checkbox" name="services[<?= $key ?>]"
                                   <?= in_array($key, $formData['services']) ? 'checked' : '' ?>>
                            <i class="fa-solid <?= $info['icon'] ?>"></i>
                            <span><?= $info['label'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Active Status -->
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" <?= $formData['is_active'] ? 'checked' : '' ?>>
                        <span class="toggle-track"></span>
                        <span>Active (visible to customers)</span>
                    </label>
                </div>

                <!-- Actions -->
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> Create Package
                    </button>
                    <a href="<?= BASE_URL ?>/admin/packages/manage_packages.php" class="btn btn-outline">Cancel</a>
                </div>
            </div>
        </form>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/packages.js"></script>
</body>
</html>