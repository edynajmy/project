<?php
// log_found.php
require_once 'auth_check.php';
require_once 'db.php';

// Enforce role-based authorization (Only Recorders and Admins can log found items)
checkAccess(['Recorder', 'Admin']);

$error = '';
$success = '';

// --------------------------------------------------------------------
// 1. PROCESS FORM SUBMISSION
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id  = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $location_id  = filter_input(INPUT_POST, 'storage_location_id', FILTER_VALIDATE_INT);
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $location_found = trim($_POST['location_found'] ?? '');
    $date_found   = $_POST['date_found'] ?? '';

    // Validation
    if (!$category_id || empty($title) || empty($description) || empty($location_found) || empty($date_found)) {
        $error = "Please fill in all required fields marked with an asterisk (*).";
    } else {
        // Handle File Upload
        $image_path = 'default_item.jpg';
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['item_image']['tmp_name'];
            $fileName    = $_FILES['item_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = 'found_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                $destPath    = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $image_path = $destPath;
                } else {
                    $error = "There was an error moving the uploaded image.";
                }
            } else {
                $error = "Invalid image file type. Allowed: JPG, PNG, WEBP.";
            }
        }

        // Proceed if no upload error occurred
        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                // Auto-generate Unique Tracking Code (e.g., FND-2026-9182)
                $item_code = 'FND-' . date('Y') . '-' . rand(1000, 9999);

                // Initial item status depends on whether a storage location was assigned immediately.
                $status = $location_id ? 'Stored' : 'Pending Check-in';

                $catStmt = $pdo->prepare("SELECT retention_months FROM categories WHERE category_id = ?");
                $catStmt->execute([$category_id]);
                $retention_months = (int)($catStmt->fetchColumn() ?: 6);
                $expiry_date = date('Y-m-d', strtotime("+$retention_months months", strtotime($date_found)));

                // If staff assigns storage immediately, validate the location before inserting.
                if ($location_id) {
                    $locationStmt = $pdo->prepare("
                        SELECT location_id, location_name, capacity, status
                        FROM storage_locations
                        WHERE location_id = ?
                        FOR UPDATE
                    ");
                    $locationStmt->execute([$location_id]);
                    $location = $locationStmt->fetch();

                    if (!$location) {
                        throw new RuntimeException('Selected storage location was not found.');
                    }
                    if ($location['status'] === 'Maintenance') {
                        throw new RuntimeException('Selected storage location is under maintenance.');
                    }

                    $occupiedStmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM found_items
                        WHERE storage_location_id = ?
                          AND status = 'Stored'
                    ");
                    $occupiedStmt->execute([$location_id]);
                    $occupied = (int)$occupiedStmt->fetchColumn();

                    if ($occupied >= (int)$location['capacity']) {
                        throw new RuntimeException('Selected storage location is full.');
                    }
                }

                // Insert into found_items
                $stmt = $pdo->prepare("
                    INSERT INTO found_items 
                    (item_code, category_id, storage_location_id, reporter_id, title, remarks, location_found, image_path, date_found, expiry_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $item_code,
                    $category_id,
                    $location_id ?: null,
                    $_SESSION['user_id'],
                    $title,
                    $description,
                    $location_found,
                    $image_path,
                    $date_found,
                    $expiry_date,
                    $status
                ]);

                // Update storage status after the new stored item is inserted.
                if ($location_id) {
                    $updateLoc = $pdo->prepare("
                        UPDATE storage_locations
                        SET status = CASE
                            WHEN (SELECT COUNT(*) FROM found_items fi WHERE fi.storage_location_id = sl.location_id AND fi.status = 'Stored') >= sl.capacity
                            THEN 'Full'
                            ELSE 'Available'
                        END
                        WHERE sl.location_id = ?
                    ");
                    $updateLoc->execute([$location_id]);
                }

                $pdo->commit();
                $_SESSION['success'] = "Found item logged successfully! Item Tag Code: <strong>{$item_code}</strong>";
                header("Location: log_found.php");
                exit();

            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Found Item Check-in Error: " . $e->getMessage());
                $error = "A database error occurred while registering the item.";
            }
        }
    }
}

// --------------------------------------------------------------------
// 2. FETCH CATEGORIES & AVAILABLE STORAGE LOCATIONS FOR FORM
// --------------------------------------------------------------------
try {
    $categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetchAll();
    
    // Fetch active storage locations with current occupied counts
    $locationsStmt = $pdo->query("
        SELECT sl.location_id, sl.location_name, sl.building_zone, sl.capacity, sl.status,
               COUNT(fi.item_id) AS stored_count
        FROM storage_locations sl
        LEFT JOIN found_items fi ON sl.location_id = fi.storage_location_id AND fi.status = 'Stored'
        WHERE sl.status != 'Maintenance'
        GROUP BY sl.location_id
        ORDER BY sl.location_name ASC
    ");
    $locations = $locationsStmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Fetch Metadata Error: " . $e->getMessage());
    $categories = [];
    $locations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Found Item - Resort Lost & Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fa-solid fa-boxes-stacked me-2"></i>Resort L&F Portal</a>
        <div class="d-flex align-items-center text-white">
            <span class="me-3 small"><i class="fa-solid fa-user-check me-1"></i><?= htmlspecialchars($_SESSION['user_name']); ?> (<?= htmlspecialchars($_SESSION['user_role']); ?>)</span>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold m-0"><i class="fa-solid fa-box-archive text-primary me-2"></i>Check-In Found Item</h3>
                <a href="recorder_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard</a>
            </div>

            <!-- ALERT NOTIFICATIONS -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- MAIN FORM CARD -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <form action="log_found.php" method="POST" enctype="multipart/form-data">
                        
                        <div class="row g-3">
                            <!-- ITEM TITLE -->
                            <div class="col-md-8">
                                <label for="title" class="form-label fw-semibold small">Item Name / Short Title *</label>
                                <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Black Leather Wallet, Silver iPhone 13" required>
                            </div>

                            <!-- CATEGORY -->
                            <div class="col-md-4">
                                <label for="category_id" class="form-label fw-semibold small">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="" selected disabled>Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- LOCATION FOUND -->
                            <div class="col-md-6">
                                <label for="location_found" class="form-label fw-semibold small">Where was it found? *</label>
                                <input type="text" class="form-control" id="location_found" name="location_found" placeholder="e.g., Poolside Cabana #4, Main Lobby Restroom" required>
                            </div>

                            <!-- DATE FOUND -->
                            <div class="col-md-6">
                                <label for="date_found" class="form-label fw-semibold small">Date Found *</label>
                                <input type="date" class="form-control" id="date_found" name="date_found" value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" required>
                            </div>

                            <!-- STORAGE LOCKER SELECTION -->
                            <div class="col-12">
                                <label for="storage_location_id" class="form-label fw-semibold small">Assign Storage Locker / Location</label>
                                <select class="form-select" id="storage_location_id" name="storage_location_id">
                                    <option value="">-- Unassigned (Keep in Temporary Reception holding) --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <?php 
                                            $isFull = ($loc['stored_count'] >= $loc['capacity']) || $loc['status'] === 'Full'; 
                                        ?>
                                        <option value="<?= $loc['location_id']; ?>" <?= $isFull ? 'disabled' : ''; ?>>
                                            <?= htmlspecialchars($loc['location_name']); ?> (<?= htmlspecialchars($loc['building_zone']); ?>) 
                                            — [Occupancy: <?= $loc['stored_count']; ?>/<?= $loc['capacity']; ?>]
                                            <?= $isFull ? ' - FULL' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select an active storage unit to hold this item securely.</div>
                            </div>

                            <!-- DESCRIPTION / DISTINCT FEATURES -->
                            <div class="col-12">
                                <label for="description" class="form-label fw-semibold small">Detailed Description & Serial/Identifier Marks *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Include brand, color, unique scratches, contents, or serial numbers to aid ownership verification..." required></textarea>
                            </div>

                            <!-- PHOTO UPLOAD -->
                            <div class="col-12">
                                <label for="item_image" class="form-label fw-semibold small">Upload Photo (Optional)</label>
                                <input type="file" class="form-control" id="item_image" name="item_image" accept="image/png, image/jpeg, image/webp">
                                <div class="form-text">Accepted formats: JPG, PNG, WEBP (Max 5MB).</div>
                            </div>

                            <!-- SUBMIT -->
                            <div class="col-12 mt-4 text-end">
                                <button type="reset" class="btn btn-light me-2">Reset Form</button>
                                <button type="submit" class="btn btn-primary fw-bold px-4">
                                    <i class="fa-solid fa-file-circle-plus me-2"></i>Log Found Item
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>