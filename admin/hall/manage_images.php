<?php
/**
 * manage_images.php — Admin: Hall Gallery Image Management
 * Module 2 – Riffna
 * Handles: upload, sort order save, delete
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$errors  = [];
$hall    = null;
$images  = [];

$uploadDir    = __DIR__ . '/../../assets/images/hall/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxFileSize  = 5 * 1024 * 1024; // 5 MB

try {
    $hall = $pdo->query("SELECT * FROM hall LIMIT 1")->fetch();
    if ($hall) {
        $stmt = $pdo->prepare(
            "SELECT * FROM hall_images WHERE hall_id = ? ORDER BY sort_order ASC, image_id ASC"
        );
        $stmt->execute([$hall['hall_id']]);
        $images = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("manage_images load: " . $e->getMessage());
}

if (!$hall) {
    setFlash('danger', 'Please set up the hall first before managing images.');
    redirect(BASE_URL . '/admin/hall/edit_hall.php?setup=1');
}

// ── DELETE Image ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    $deleteId = (int) ($_POST['delete_image_id'] ?? 0);
    if ($deleteId > 0) {
        try {
            $img = $pdo->prepare("SELECT filename FROM hall_images WHERE image_id = ? AND hall_id = ?");
            $img->execute([$deleteId, $hall['hall_id']]);
            $row = $img->fetch();
            if ($row) {
                $filePath = $uploadDir . $row['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $pdo->prepare("DELETE FROM hall_images WHERE image_id = ?")->execute([$deleteId]);
                setFlash('success', 'Image deleted successfully.');
            }
        } catch (PDOException $e) {
            error_log("manage_images delete: " . $e->getMessage());
            setFlash('danger', 'Failed to delete image.');
        }
    }
    redirect(BASE_URL . '/admin/hall/manage_images.php');
}

// ── SAVE Sort Order ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sort') {
    $sortData = $_POST['sort'] ?? [];
    if (is_array($sortData)) {
        try {
            $updateSort = $pdo->prepare(
                "UPDATE hall_images SET sort_order = ? WHERE image_id = ? AND hall_id = ?"
            );
            foreach ($sortData as $imageId => $order) {
                $updateSort->execute([(int)$order, (int)$imageId, $hall['hall_id']]);
            }
            setFlash('success', 'Image order saved.');
        } catch (PDOException $e) {
            error_log("manage_images sort: " . $e->getMessage());
            setFlash('danger', 'Failed to save sort order.');
        }
    }
    redirect(BASE_URL . '/admin/hall/manage_images.php');
} 

// ── UPLOAD Images ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['hallImages'])) {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $files     = $_FILES['hallImages'];
    $uploaded  = 0;
    $fileCount = count($files['name']);

    // Current max sort_order
    try {
        $maxOrder = (int) $pdo->prepare(
            "SELECT COALESCE(MAX(sort_order), -1) FROM hall_images WHERE hall_id = ?"
        )->execute([$hall['hall_id']]) ? $pdo->query(
            "SELECT COALESCE(MAX(sort_order), -1) FROM hall_images WHERE hall_id = {$hall['hall_id']}"
        )->fetchColumn() : 0;
    } catch (PDOException $e) {
        $maxOrder = 0;
    }

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $tmpPath  = $files['tmp_name'][$i];
        $origName = $files['name'][$i];
        $fileType = mime_content_type($tmpPath);
        $fileSize = $files['size'][$i];

        // Validate
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = htmlspecialchars($origName) . ': Invalid file type (JPEG, PNG, WebP, GIF only).';
            continue;
        }
        if ($fileSize > $maxFileSize) {
            $errors[] = htmlspecialchars($origName) . ': File exceeds 5 MB limit.';
            continue;
        }

        // Sanitize filename and make unique
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $filename = 'hall_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($tmpPath, $destPath)) {
            try {
                $maxOrder++;
                $ins = $pdo->prepare(
                    "INSERT INTO hall_images (hall_id, filename, sort_order) VALUES (?, ?, ?)"
                );
                $ins->execute([$hall['hall_id'], $filename, $maxOrder]);
                $uploaded++;
            } catch (PDOException $e) {
                error_log("manage_images insert: " . $e->getMessage());
                unlink($destPath);
                $errors[] = 'Database error saving ' . htmlspecialchars($origName) . '.';
            }
        } else {
            $errors[] = 'Failed to upload ' . htmlspecialchars($origName) . '.';
        }
    }

    if ($uploaded > 0) {
        setFlash('success', "$uploaded image(s) uploaded successfully.");
    }
    if (!empty($errors)) {
        setFlash('danger', implode(' ', $errors));
    }
    redirect(BASE_URL . '/admin/hall/manage_images.php');
}

$flash = getFlash();
// Reload images after redirects
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM hall_images WHERE hall_id = ? ORDER BY sort_order ASC, image_id ASC"
    );
    $stmt->execute([$hall['hall_id']]);
    $images = $stmt->fetchAll();
} catch (PDOException $e) { /* leave empty */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Hall Gallery — <?= SITE_NAME ?></title>
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
                <h1 class="page-title">Hall Gallery</h1>
                <p class="page-subtitle">Upload and manage photos for <?= htmlspecialchars($hall['name']) ?>. Drag to reorder.</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/hall/manage_hall.php" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back to Hall
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Upload Section -->
        <div class="hall-info-card" style="margin-bottom:24px;">
            <h3><i class="fa-solid fa-upload"></i> &nbsp;Upload New Images</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="upload-zone">
                    <input type="file" id="hallImages" name="hallImages[]" multiple accept="image/*">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p>Drag &amp; drop images here, or <strong>click to browse</strong></p>
                    <p style="font-size:12px; margin-top:4px;">JPEG, PNG, WebP, GIF — max 5 MB each</p>
                </div>
                <div id="previewStrip" class="preview-strip"></div>
                <div style="margin-top:16px; display:flex; align-items:center; gap:12px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Selected
                    </button>
                    <span style="font-size:13px; color:var(--text-muted);">
                        <?= count($images) ?> image<?= count($images) !== 1 ? 's' : '' ?> in gallery
                    </span>
                </div>
            </form>
        </div>

        <!-- Existing Gallery -->
        <?php if (!empty($images)): ?>
        <div class="hall-info-card">
            <h3><i class="fa-solid fa-images"></i> &nbsp;Gallery
                <span style="font-size:12px; font-weight:500; color:var(--text-muted); margin-left:8px;">
                    Drag thumbnails to reorder, then click Save Order
                </span>
            </h3>

            <!-- Hidden form for sort order -->
            <form id="sortOrderForm" method="POST" action="" style="display:none;">
                <input type="hidden" name="action" value="save_sort">
            </form>

            <div class="image-grid" id="imageGrid">
                <?php foreach ($images as $img): ?>
                <div class="image-thumb" data-image-id="<?= $img['image_id'] ?>">
                    <img src="<?= BASE_URL ?>/assets/images/hall/<?= htmlspecialchars($img['filename']) ?>"
                         alt="Hall photo" loading="lazy">
                    <span class="img-sort-badge"><?= (int)$img['sort_order'] + 1 ?></span>
                    <div class="img-overlay">
                        <button type="button" class="btn-delete-img">
                            <i class="fa-solid fa-trash-can"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:16px; display:flex; gap:12px;">
                <button type="button" id="saveSortBtn" class="btn btn-primary">
                    <i class="fa-solid fa-arrows-up-down-left-right"></i> Save Order
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="hall-info-card" style="text-align:center; padding:40px;">
            <i class="fa-solid fa-image" style="font-size:48px; color:#c9d0fd;"></i>
            <p style="color:var(--text-muted); margin-top:12px;">No images uploaded yet. Use the form above to add photos.</p>
        </div>
        <?php endif; ?>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin/hall.js"></script>
<script>
// Save sort order button
const saveSortBtn = document.getElementById('saveSortBtn');
if (saveSortBtn) {
    saveSortBtn.addEventListener('click', () => {
        const sortForm = document.getElementById('sortOrderForm');
        if (!sortForm) return;
        // Rebuild hidden inputs from current DOM order
        sortForm.innerHTML = '<input type="hidden" name="action" value="save_sort">';
        document.querySelectorAll('#imageGrid .image-thumb').forEach((thumb, i) => {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'sort[' + thumb.dataset.imageId + ']';
            inp.value = i;
            sortForm.appendChild(inp);
        });
        sortForm.submit();
    });
}
</script>
</body>
</html>
