<?php
// reporter_dashboard.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';

// Ensure user is authenticated
checkAccess(['Reporter']);

$user_id = $_SESSION['user_id'];

$unread_notifications = 0;
try {
    $stmt_notifications = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 'No'");
    $stmt_notifications->execute([$user_id]);
    $unread_notifications = (int)$stmt_notifications->fetchColumn();
} catch (PDOException $e) {
    $unread_notifications = 0;
}

function getImagePath($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '/')) {
        return $path;
    }
    if (str_starts_with($path, 'uploads/')) {
        return $path;
    }
    return 'uploads/' . ltrim($path, '/\\');
}


// --------------------------------------------------------------------
// CATEGORY OPTIONS
// --------------------------------------------------------------------
$categories = [];
try {
    $categories = $pdo->query("SELECT category_id, category_name, retention_months FROM categories ORDER BY category_name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// --------------------------------------------------------------------
// REPORTER EDIT / DELETE ACTIONS
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reporter_action'])) {
    $action = $_POST['reporter_action'];
    $report_id = (int)($_POST['report_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);

    try {
        if ($action === 'delete_lost') {
            $stmt = $pdo->prepare("DELETE FROM lost_items WHERE report_id = ? AND reporter_id = ? AND status = 'Searching'");
            $stmt->execute([$report_id, $user_id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('This lost report can no longer be deleted because it has already been processed.');
            }
            $_SESSION['success'] = 'Lost item report deleted successfully.';

        } elseif ($action === 'update_lost') {
            $item_title = trim($_POST['item_title'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            $location_lost = trim($_POST['location_lost'] ?? '');
            $date_lost = $_POST['date_lost'] ?? '';
            $description = trim($_POST['description'] ?? '');

            if ($report_id <= 0 || $item_title === '' || $category_id <= 0 || $location_lost === '' || $date_lost === '' || $description === '') {
                throw new RuntimeException('Please complete all required lost item fields.');
            }
            if ($date_lost > date('Y-m-d')) {
                throw new RuntimeException('Date lost cannot be in the future.');
            }

            $stmt = $pdo->prepare("
                UPDATE lost_items
                SET item_title = ?, category_id = ?, location_lost = ?, date_lost = ?, description = ?
                WHERE report_id = ? AND reporter_id = ? AND status = 'Searching'
            ");
            $stmt->execute([$item_title, $category_id, $location_lost, $date_lost, $description, $report_id, $user_id]);
            if ($stmt->rowCount() < 1) {
                // MySQL can report 0 when values are unchanged, so verify ownership/state instead of treating it as failure.
                $check = $pdo->prepare("SELECT report_id FROM lost_items WHERE report_id = ? AND reporter_id = ? AND status = 'Searching'");
                $check->execute([$report_id, $user_id]);
                if (!$check->fetch()) {
                    throw new RuntimeException('This lost report can no longer be edited because it has already been processed.');
                }
            }
            $_SESSION['success'] = 'Lost item report updated successfully.';

        } elseif ($action === 'delete_found') {
            $stmt = $pdo->prepare("SELECT image_path FROM found_items WHERE item_id = ? AND reporter_id = ? AND status = 'Pending Check-in' LIMIT 1");
            $stmt->execute([$item_id, $user_id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$found) {
                throw new RuntimeException('This found item can no longer be deleted because staff has already processed it.');
            }

            $stmt = $pdo->prepare("DELETE FROM found_items WHERE item_id = ? AND reporter_id = ? AND status = 'Pending Check-in'");
            $stmt->execute([$item_id, $user_id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to delete the found item.');
            }

            $image = basename((string)($found['image_path'] ?? ''));
            if ($image !== '' && $image !== 'default_item.jpg') {
                $imageFile = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $image;
                if (is_file($imageFile)) {
                    @unlink($imageFile);
                }
            }
            $_SESSION['success'] = 'Found item submission deleted successfully.';

        } elseif ($action === 'update_found') {
            $title = trim($_POST['title'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            $location_found = trim($_POST['location_found'] ?? '');
            $date_found = $_POST['date_found'] ?? '';
            $remarks = trim($_POST['remarks'] ?? '');

            if ($item_id <= 0 || $title === '' || $category_id <= 0 || $location_found === '' || $date_found === '') {
                throw new RuntimeException('Please complete all required found item fields.');
            }
            if ($date_found > date('Y-m-d')) {
                throw new RuntimeException('Date found cannot be in the future.');
            }

            $existingStmt = $pdo->prepare("SELECT image_path FROM found_items WHERE item_id = ? AND reporter_id = ? AND status = 'Pending Check-in' LIMIT 1");
            $existingStmt->execute([$item_id, $user_id]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                throw new RuntimeException('This found item can no longer be edited because staff has already processed it.');
            }

            $image_path = $existing['image_path'] ?? 'default_item.jpg';
            if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('There was an error uploading the new item photo.');
                }
                $extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($extension, $allowed, true)) {
                    throw new RuntimeException('Invalid image type. Allowed: JPG, PNG, WEBP.');
                }
                if ((int)$_FILES['item_image']['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('The item photo must be 5MB or smaller.');
                }

                $newFileName = 'EDITED-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
                $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $uploadDir . $newFileName)) {
                    throw new RuntimeException('Unable to save the new item photo.');
                }
                $image_path = $newFileName;

                $oldImage = basename((string)$existing['image_path']);
                if ($oldImage !== '' && $oldImage !== 'default_item.jpg') {
                    $oldFile = $uploadDir . $oldImage;
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }
            }

            // Recalculate expiry date from the selected category and date found.
            $catStmt = $pdo->prepare("SELECT retention_months FROM categories WHERE category_id = ? LIMIT 1");
            $catStmt->execute([$category_id]);
            $category = $catStmt->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                throw new RuntimeException('Selected category was not found.');
            }
            $retention = (int)($category['retention_months'] ?? 6);
            $expiry_date = date('Y-m-d', strtotime("+$retention months", strtotime($date_found)));

            $stmt = $pdo->prepare("
                UPDATE found_items
                SET title = ?, category_id = ?, location_found = ?, date_found = ?, expiry_date = ?, remarks = ?, image_path = ?
                WHERE item_id = ? AND reporter_id = ? AND status = 'Pending Check-in'
            ");
            $stmt->execute([$title, $category_id, $location_found, $date_found, $expiry_date, $remarks, $image_path, $item_id, $user_id]);
            if ($stmt->rowCount() < 1) {
                $check = $pdo->prepare("SELECT item_id FROM found_items WHERE item_id = ? AND reporter_id = ? AND status = 'Pending Check-in'");
                $check->execute([$item_id, $user_id]);
                if (!$check->fetch()) {
                    throw new RuntimeException('This found item can no longer be edited because staff has already processed it.');
                }
            }
            $_SESSION['success'] = 'Found item submission updated successfully.';
        }
    } catch (RuntimeException $e) {
        $_SESSION['error'] = $e->getMessage();
    } catch (PDOException $e) {
        error_log('Reporter edit/delete error: ' . $e->getMessage());
        $_SESSION['error'] = 'A database error occurred while updating your report.';
    }

    header('Location: reporter_dashboard.php');
    exit();
}

// --------------------------------------------------------------------
// DATA QUERIES FOR LOGGED-IN REPORTER
// --------------------------------------------------------------------
$active_tab = $_GET['tab'] ?? 'lost_items';

// User's Lost Item Reports
$stmt_lost = $pdo->prepare("
    SELECT
        l.*, c.category_name,
        im.match_id, im.match_notes, im.match_date,
        f.item_id AS matched_found_item_id,
        f.item_code AS matched_item_code,
        f.title AS matched_item_title,
        f.status AS matched_item_status,
        f.image_path AS matched_image_path,
        s.location_name AS matched_storage_location
    FROM lost_items l
    JOIN categories c ON l.category_id = c.category_id
    LEFT JOIN item_matches im
        ON l.report_id = im.lost_report_id
       AND im.status = 'Matched'
    LEFT JOIN found_items f ON im.found_item_id = f.item_id
    LEFT JOIN storage_locations s ON f.storage_location_id = s.location_id
    WHERE l.reporter_id = ?
    ORDER BY l.date_lost DESC
");
$stmt_lost->execute([$user_id]);
$lost_reports = $stmt_lost->fetchAll();

// User's Found Item Submissions
$stmt_found = $pdo->prepare("
    SELECT f.*, c.category_name, s.location_name 
    FROM found_items f 
    JOIN categories c ON f.category_id = c.category_id 
    LEFT JOIN storage_locations s ON f.storage_location_id = s.location_id 
    WHERE f.reporter_id = ? 
    ORDER BY f.date_found DESC
");
$stmt_found->execute([$user_id]);
$found_reports = $stmt_found->fetchAll();

// User's Active Claims
$stmt_claims = $pdo->prepare("
    SELECT
        cl.*,
        f.title AS item_title,
        f.item_code,
        f.image_path,
        f.storage_location_id,
        c.category_name,
        s.location_name,
        s.building_zone,
        s.description AS storage_description
    FROM claims cl
    JOIN found_items f
        ON cl.found_item_id = f.item_id
    JOIN categories c
        ON f.category_id = c.category_id
    LEFT JOIN storage_locations s
        ON f.storage_location_id = s.location_id
    WHERE cl.claimant_id = ?
    ORDER BY cl.date_claimed DESC
");
$stmt_claims->execute([$user_id]);
$claims = $stmt_claims->fetchAll();

// Summary Metrics
$count_lost   = count($lost_reports);
$count_found  = count($found_reports);
$count_claims = count($claims);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporter Dashboard - Resort L&amp;F</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
body{background-color:#f4f6f9}.sidebar-brand{font-weight:700;letter-spacing:.5px}.stat-card{border:none;border-radius:12px;transition:transform .2s}.stat-card:hover{transform:translateY(-3px)}.item-img-preview{width:55px;height:55px;object-fit:cover;border-radius:8px}.section-card{border:none;border-radius:12px;overflow:hidden}.section-header{background:white}.table td,.table th{vertical-align:middle}.status-badge{font-size:.75rem;padding:.45rem .7rem}.nav-pills .nav-link{color:#495057;font-weight:600}.nav-pills .nav-link.active{background-color:#0d6efd}.table-responsive{min-height:80px}.claim-proof{max-width:100px;max-height:70px;object-fit:contain;border-radius:6px}.pickup-box{min-width:300px}.quick-actions .btn{font-weight:700}.section-anchor{scroll-margin-top:85px}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
<div class="container-fluid px-4">
<a class="navbar-brand sidebar-brand" href="index.php"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Reporter Portal</a>
<div class="d-flex align-items-center ms-auto">
<span class="text-light me-3 small"><i class="fa-solid fa-user me-1 text-primary"></i><?= htmlspecialchars($_SESSION['user_name'] ?? 'Reporter'); ?> (<?= htmlspecialchars($_SESSION['user_role'] ?? 'Reporter'); ?>)</span>

<a href="notifications.php"
   class="btn btn-outline-light btn-sm me-2 position-relative d-inline-flex align-items-center justify-content-center"
   style="width:42px;height:38px;"
   title="Notifications"
   aria-label="Notifications">
    <i class="fa-solid fa-bell"></i>
    <?php if($unread_notifications > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $unread_notifications > 99 ? '99+' : $unread_notifications; ?>
        </span>
    <?php endif; ?>
</a>

<a href="account_security.php"
   class="btn btn-outline-light btn-sm me-2 d-inline-flex align-items-center justify-content-center"
   style="width:42px;height:38px;"
   title="Account Security"
   aria-label="Account Security">
    <i class="fa-solid fa-shield-halved"></i>
</a>

<a href="logout.php" class="btn btn-outline-danger btn-sm">
    <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
</a>
</div>
</div></nav>
<div class="container-fluid px-4 py-4">
<?php if(isset($_SESSION['success'])): ?><div class="alert alert-success alert-dismissible fade show mb-4" role="alert"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger alert-dismissible fade show mb-4" role="alert"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
<div><h3 class="fw-bold mb-1"><i class="fa-solid fa-gauge-high text-primary me-2"></i>My Reporter Dashboard</h3><p class="text-muted small mb-0">Track your lost reports, found submissions, and ownership claims.</p></div>
<div class="d-flex gap-2 quick-actions"><a href="report_lost.php" class="btn btn-warning shadow-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>Report Lost Item</a><a href="report_found.php" class="btn btn-primary shadow-sm"><i class="fa-solid fa-hand-holding-heart me-1"></i>Report Found Item</a></div>
</div>
<div class="row g-3 mb-4">
<div class="col-md-3"><div class="card stat-card bg-warning text-dark p-3 shadow-sm"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Lost Reports</h6><h2 class="fw-bold mb-0"><?= $count_lost; ?></h2></div><i class="fa-solid fa-magnifying-glass fs-1 opacity-50"></i></div></div></div>
<div class="col-md-3"><div class="card stat-card bg-primary text-white p-3 shadow-sm"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Found Submissions</h6><h2 class="fw-bold mb-0"><?= $count_found; ?></h2></div><i class="fa-solid fa-hand-holding-heart fs-1 opacity-50"></i></div></div></div>
<div class="col-md-3"><div class="card stat-card bg-success text-white p-3 shadow-sm"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Submitted Claims</h6><h2 class="fw-bold mb-0"><?= $count_claims; ?></h2></div><i class="fa-solid fa-user-check fs-1 opacity-50"></i></div></div></div>
<div class="col-md-3"><div class="card stat-card bg-info text-dark p-3 shadow-sm"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Matched Reports</h6><h2 class="fw-bold mb-0"><?= count(array_filter($lost_reports, fn($x)=>(int)($x['match_id']??0)>0)); ?></h2></div><i class="fa-solid fa-link fs-1 opacity-50"></i></div></div></div>
</div>
<div class="card border-0 shadow-sm rounded-3 mb-4"><div class="card-body"><div class="nav nav-pills gap-2">
<a href="#lost-reports-section" class="nav-link active"><i class="fa-solid fa-magnifying-glass me-1"></i>My Lost Reports</a>
<a href="#found-section" class="nav-link"><i class="fa-solid fa-hand-holding-heart me-1"></i>My Found Submissions</a>
<a href="#claims-section" class="nav-link"><i class="fa-solid fa-user-check me-1"></i>Claim Status<?php if($count_claims>0): ?><span class="badge bg-danger ms-1"><?= $count_claims; ?></span><?php endif; ?></a>
</div></div></div>

<div class="card section-card shadow-sm mb-4 section-anchor" id="lost-reports-section"><div class="card-header section-header py-3 d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-magnifying-glass text-warning me-2"></i>My Lost Item Reports</h5><span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><?= $count_lost; ?> Reports</span></div><div class="card-body p-0">
<?php if(empty($lost_reports)): ?><div class="text-center py-5"><i class="fa-solid fa-file-circle-xmark fs-1 text-muted mb-3 d-block"></i><h6 class="text-muted">You haven't reported any lost items yet.</h6><a href="report_lost.php" class="btn btn-warning btn-sm mt-2 fw-bold">Report a Misplaced Item</a></div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th class="ps-3">Item Title</th><th>Category</th><th>Location Lost</th><th>Date Lost</th><th>Status</th><th class="text-end pe-3">Action</th></tr></thead><tbody>
<?php foreach($lost_reports as $lost): ?><tr><td class="ps-3"><strong class="d-block"><?= htmlspecialchars($lost['item_title']); ?></strong><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($lost['description'],0,70,'...')); ?></small></td><td><span class="badge bg-info text-dark"><?= htmlspecialchars($lost['category_name']); ?></span></td><td><i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($lost['location_lost']); ?></td><td><?= date('M d, Y',strtotime($lost['date_lost'])); ?></td><td><?php if($lost['status']==='Searching'): ?><span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Searching</span><?php elseif($lost['status']==='Matched'): ?><span class="badge bg-info text-dark"><i class="fa-solid fa-link me-1"></i>Matched</span><?php if(!empty($lost['matched_found_item_id'])): ?><div class="small mt-2"><strong><?= htmlspecialchars($lost['matched_item_title']); ?></strong> <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($lost['matched_item_code']); ?></span></div><?php if($lost['matched_item_status']==='Stored'): ?><a href="submit_claim.php?found_id=<?= (int)$lost['matched_found_item_id']; ?>" class="btn btn-success btn-sm mt-2 fw-bold"><i class="fa-solid fa-hand-holding-heart me-1"></i>Claim Matched Item</a><?php elseif($lost['matched_item_status']==='Claim Pending'): ?><div class="text-warning small mt-2"><i class="fa-solid fa-hourglass-half me-1"></i>Claim Pending</div><?php elseif($lost['matched_item_status']==='Claimed'): ?><div class="text-success small mt-2"><i class="fa-solid fa-circle-check me-1"></i>Returned to Owner</div><?php endif; ?><?php endif; ?><?php else: ?><span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Resolved / Claimed</span><?php endif; ?></td><td class="text-end pe-3">
<?php if($lost['status']==='Searching'): ?>
<div class="btn-group btn-group-sm">
<button type="button" class="btn btn-outline-primary" title="Edit report" data-bs-toggle="modal" data-bs-target="#editLostModal" onclick="openEditLost(<?= (int)$lost['report_id']; ?>, <?= htmlspecialchars(json_encode($lost['item_title']), ENT_QUOTES, 'UTF-8'); ?>, <?= (int)$lost['category_id']; ?>, <?= htmlspecialchars(json_encode($lost['location_lost']), ENT_QUOTES, 'UTF-8'); ?>, <?= htmlspecialchars(json_encode($lost['date_lost']), ENT_QUOTES, 'UTF-8'); ?>, <?= htmlspecialchars(json_encode($lost['description']), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fa-solid fa-pen"></i></button>
<button type="button" class="btn btn-outline-danger" title="Delete report" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="openDelete('delete_lost', <?= (int)$lost['report_id']; ?>, '<?= htmlspecialchars(addslashes($lost['item_title']), ENT_QUOTES, 'UTF-8'); ?>')"><i class="fa-solid fa-trash"></i></button>
</div>
<?php endif; ?>
<button type="button" class="btn btn-outline-secondary btn-sm" title="View report" data-bs-toggle="modal" data-bs-target="#viewLostModal"
onclick='openViewLost(<?= json_encode([
    "report_id" => (int)$lost["report_id"],
    "item_title" => $lost["item_title"],
    "category_name" => $lost["category_name"],
    "location_lost" => $lost["location_lost"],
    "date_lost" => $lost["date_lost"],
    "description" => $lost["description"],
    "status" => $lost["status"],
    "matched_item_code" => $lost["matched_item_code"] ?? "",
    "matched_item_title" => $lost["matched_item_title"] ?? "",
    "matched_item_status" => $lost["matched_item_status"] ?? "",
    "matched_storage_location" => $lost["matched_storage_location"] ?? ""
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
<i class="fa-solid fa-eye"></i>
</button>
</td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>

<div class="card section-card shadow-sm mb-4 section-anchor" id="found-section"><div class="card-header section-header py-3 d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-hand-holding-heart text-primary me-2"></i>My Found Item Submissions</h5><span class="badge bg-primary px-3 py-2 rounded-pill"><?= $count_found; ?> Submissions</span></div><div class="card-body p-0">
<?php if(empty($found_reports)): ?><div class="text-center py-5"><i class="fa-solid fa-hand-holding-heart fs-1 text-muted mb-3 d-block"></i><h6 class="text-muted">You haven't logged any found items yet.</h6><a href="report_found.php" class="btn btn-primary btn-sm mt-2 fw-bold">Report a Found Item</a></div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th class="ps-3">Item</th><th>Code &amp; Title</th><th>Category</th><th>Location Found</th><th>Date Found</th><th>Processing Status</th><th class="text-end pe-3">Action</th></tr></thead><tbody>
<?php foreach($found_reports as $found): ?><tr><td class="ps-3"><?php if(!empty($found['image_path'])): ?><a href="#" data-bs-toggle="modal" data-bs-target="#itemImageModal" onclick="showItemImage('<?= htmlspecialchars(getImagePath($found['image_path']),ENT_QUOTES,'UTF-8'); ?>');return false;"><img src="<?= htmlspecialchars(getImagePath($found['image_path']),ENT_QUOTES,'UTF-8'); ?>" class="item-img-preview" alt="Item Image" style="cursor:pointer"></a><?php else: ?><div class="item-img-preview bg-light border d-flex align-items-center justify-content-center text-muted"><i class="fa-solid fa-image"></i></div><?php endif; ?></td><td><strong class="d-block"><?= htmlspecialchars($found['title']); ?></strong><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($found['item_code']); ?></span></td><td><span class="badge bg-info text-dark"><?= htmlspecialchars($found['category_name']); ?></span></td><td><i class="fa-solid fa-location-dot text-primary me-1"></i><?= htmlspecialchars($found['location_found']); ?></td><td><?= date('M d, Y',strtotime($found['date_found'])); ?></td><td><?php if($found['status']==='Pending Check-in'): ?><span class="badge bg-warning text-dark">Pending Staff Check-in</span><?php elseif($found['status']==='Stored'): ?><span class="badge bg-primary">Stored in Locker</span><?php elseif($found['status']==='Claim Pending'): ?><span class="badge bg-warning text-dark">Claim Pending</span><?php elseif($found['status']==='Claimed'): ?><span class="badge bg-success">Returned to Owner</span><?php else: ?><span class="badge bg-secondary"><?= htmlspecialchars($found['status']); ?></span><?php endif; ?></td><td class="text-end pe-3">
<?php if($found['status']==='Pending Check-in'): ?>
<div class="btn-group btn-group-sm">
<button type="button" class="btn btn-outline-primary" title="Edit submission" data-bs-toggle="modal" data-bs-target="#editFoundModal" onclick="openEditFound(<?= (int)$found['item_id']; ?>, <?= htmlspecialchars(json_encode($found['title']), ENT_QUOTES, 'UTF-8'); ?>, <?= (int)$found['category_id']; ?>, <?= htmlspecialchars(json_encode($found['location_found']), ENT_QUOTES, 'UTF-8'); ?>, <?= htmlspecialchars(json_encode($found['date_found']), ENT_QUOTES, 'UTF-8'); ?>, <?= htmlspecialchars(json_encode($found['remarks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fa-solid fa-pen"></i></button>
<button type="button" class="btn btn-outline-danger" title="Delete submission" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="openDelete('delete_found', <?= (int)$found['item_id']; ?>, '<?= htmlspecialchars(addslashes($found['title']), ENT_QUOTES, 'UTF-8'); ?>')"><i class="fa-solid fa-trash"></i></button>
</div>
<?php endif; ?>
<button type="button"
        class="btn btn-outline-secondary btn-sm"
        title="View submission"
        data-bs-toggle="modal"
        data-bs-target="#viewFoundModal"
        onclick='openViewFound(<?= json_encode([
            "item_id" => (int)$found["item_id"],
            "item_code" => $found["item_code"],
            "title" => $found["title"],
            "category_name" => $found["category_name"],
            "location_found" => $found["location_found"],
            "date_found" => $found["date_found"],
            "status" => $found["status"],
            "remarks" => $found["remarks"] ?? "",
            "image_path" => getImagePath($found["image_path"] ?? ""),
            "location_name" => $found["location_name"] ?? ""
        ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
    <i class="fa-solid fa-eye"></i>
</button>
</td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>

<div class="card section-card shadow-sm mb-4 section-anchor" id="claims-section"><div class="card-header section-header py-3 d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-user-check text-success me-2"></i>Claim Status</h5><span class="badge bg-success px-3 py-2 rounded-pill"><?= $count_claims; ?> Claims</span></div><div class="card-body p-0">
<?php if(empty($claims)): ?><div class="text-center py-5"><i class="fa-solid fa-id-card fs-1 text-muted mb-3 d-block"></i><h6 class="text-muted">No ownership claims submitted yet.</h6></div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th class="ps-3">Claim / Item</th><th>Category</th><th>Proof</th><th>Date Claimed</th><th>Staff Review Status</th><th>Pickup Instructions</th></tr></thead><tbody>
<?php foreach($claims as $claim): $isPickedUp = strpos((string)($claim['staff_notes']??''),'[PICKED_UP]') !== false; ?><tr><td class="ps-3"><strong class="d-block"><?= htmlspecialchars($claim['item_title']); ?></strong><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($claim['item_code']); ?></span></td><td><span class="badge bg-info text-dark"><?= htmlspecialchars($claim['category_name']); ?></span></td><td><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($claim['proof_notes'],0,70,'...')); ?></small></td><td><?= date('M d, Y',strtotime($claim['date_claimed'])); ?></td><td><?php if($claim['status']==='Pending Verification'): ?><span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i>Under Review</span><?php elseif($claim['status']==='Approved' && $isPickedUp): ?><span class="badge bg-success"><i class="fa-solid fa-check-double me-1"></i>Item Collected</span><?php elseif($claim['status']==='Approved'): ?><span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Approved - Ready for Pickup</span><?php else: ?><span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Rejected</span><?php endif; ?></td><td class="pickup-box">
<?php if($claim['status']==='Approved' && !$isPickedUp): ?><div class="alert alert-success mb-0 py-2 px-3 small"><div class="fw-bold mb-2"><i class="fa-solid fa-circle-check me-1"></i>Ready for Pickup</div><div class="mb-2"><i class="fa-solid fa-location-dot text-danger me-1"></i><strong>Pickup Counter:</strong><br><span class="ms-4">Resort Lost &amp; Found Counter / Front Desk</span></div><div class="mb-2"><i class="fa-solid fa-box text-primary me-1"></i><strong>Storage Location:</strong><br><?php if(!empty($claim['location_name'])): ?><span class="ms-4 fw-bold"><?= htmlspecialchars($claim['location_name']); ?></span><?php if(!empty($claim['building_zone'])): ?><br><span class="ms-4 text-muted"><?= htmlspecialchars($claim['building_zone']); ?></span><?php endif; ?><?php else: ?><span class="ms-4 text-muted">Please ask the Lost &amp; Found counter staff.</span><?php endif; ?></div><div class="mb-2"><i class="fa-solid fa-id-card me-1"></i>Bring your <strong>ID/Passport</strong>.</div><div><i class="fa-solid fa-ticket me-1"></i>Provide Item Code: <strong><?= htmlspecialchars($claim['item_code']); ?></strong> to the staff.</div></div><?php elseif($claim['status']==='Approved' && $isPickedUp): ?><div class="alert alert-success mb-0 py-3 px-3 small"><div class="fw-bold mb-2"><i class="fa-solid fa-check-double me-1"></i>Item Collected Successfully</div><div class="mb-2"><i class="fa-solid fa-circle-check me-1"></i>This item has already been handed over to you.</div><div><i class="fa-solid fa-ticket me-1"></i>Item Code: <strong><?= htmlspecialchars($claim['item_code']); ?></strong></div></div><?php elseif($claim['status']==='Pending Verification'): ?><span class="text-muted small"><i class="fa-solid fa-hourglass-half me-1"></i>Your claim is currently being reviewed by resort staff.</span><?php else: ?><span class="text-danger small"><i class="fa-solid fa-circle-xmark me-1"></i>Claim rejected. Please contact the Lost &amp; Found counter.</span><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
</div>
<!-- VIEW LOST REPORT MODAL -->
<div class="modal fade" id="viewLostModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow">
<div class="modal-header"><h5 class="modal-title fw-bold"><i class="fa-solid fa-file-lines text-primary me-2"></i>Lost Item Report Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><div class="row g-3">
<div class="col-md-6"><div class="small text-muted">Report ID</div><div class="fw-bold" id="viewLostReportId">-</div></div>
<div class="col-md-6"><div class="small text-muted">Status</div><div id="viewLostStatus">-</div></div>
<div class="col-md-6"><div class="small text-muted">Item Title</div><div class="fw-bold" id="viewLostTitle">-</div></div>
<div class="col-md-6"><div class="small text-muted">Category</div><div id="viewLostCategory">-</div></div>
<div class="col-md-6"><div class="small text-muted">Location Lost</div><div id="viewLostLocation">-</div></div>
<div class="col-md-6"><div class="small text-muted">Date Lost</div><div id="viewLostDate">-</div></div>
<div class="col-12"><div class="small text-muted">Description</div><div class="border rounded-3 p-3 bg-light" id="viewLostDescription">-</div></div>
<div class="col-12" id="viewLostMatchBox"><div class="border rounded-3 p-3"><div class="fw-bold mb-2"><i class="fa-solid fa-link text-primary me-1"></i>Matched Found Item</div><div class="row g-2 small">
<div class="col-md-6"><span class="text-muted">Item:</span> <strong id="viewMatchedTitle">-</strong></div>
<div class="col-md-6"><span class="text-muted">Item Code:</span> <strong id="viewMatchedCode">-</strong></div>
<div class="col-md-6"><span class="text-muted">Processing Status:</span> <span id="viewMatchedStatus">-</span></div>
<div class="col-md-6"><span class="text-muted">Storage Location:</span> <span id="viewMatchedStorage">-</span></div>
</div></div></div>
</div></div>
<div class="modal-footer"><span class="text-muted small me-auto"><i class="fa-solid fa-lock me-1"></i>Processed reports are view-only.</span><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<!-- EDIT LOST ITEM MODAL -->
<div class="modal fade" id="editLostModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow">
<div class="modal-header"><h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Lost Item Report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><input type="hidden" name="reporter_action" value="update_lost"><input type="hidden" name="report_id" id="editLostId">
<div class="modal-body"><div class="alert alert-info small"><i class="fa-solid fa-circle-info me-1"></i>You can edit this report while it is still <strong>Searching</strong>.</div>
<div class="mb-3"><label class="form-label fw-semibold">Item Title / Name *</label><input type="text" class="form-control" name="item_title" id="editLostTitle" required></div>
<div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Category *</label><select class="form-select" name="category_id" id="editLostCategory" required><option value="" disabled>Select category</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['category_id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label fw-semibold">Date Lost *</label><input type="date" class="form-control" name="date_lost" id="editLostDate" max="<?= date('Y-m-d'); ?>" required></div></div>
<div class="my-3"><label class="form-label fw-semibold">Estimated Location Lost *</label><input type="text" class="form-control" name="location_lost" id="editLostLocation" required></div>
<div class="mb-2"><label class="form-label fw-semibold">Detailed Description *</label><textarea class="form-control" name="description" id="editLostDescription" rows="4" required></textarea></div></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Save Changes</button></div></form></div></div></div>

<!-- EDIT FOUND ITEM MODAL -->
<div class="modal fade" id="editFoundModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow">
<div class="modal-header"><h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Found Item Submission</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="reporter_action" value="update_found"><input type="hidden" name="item_id" id="editFoundId">
<div class="modal-body"><div class="alert alert-info small"><i class="fa-solid fa-circle-info me-1"></i>You can edit this submission while it is still <strong>Pending Check-in</strong>.</div>
<div class="mb-3"><label class="form-label fw-semibold">Item Name / Title *</label><input type="text" class="form-control" name="title" id="editFoundTitle" required></div>
<div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Category *</label><select class="form-select" name="category_id" id="editFoundCategory" required><option value="" disabled>Select category</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['category_id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label fw-semibold">Date Found *</label><input type="date" class="form-control" name="date_found" id="editFoundDate" max="<?= date('Y-m-d'); ?>" required></div></div>
<div class="my-3"><label class="form-label fw-semibold">Specific Location Found *</label><input type="text" class="form-control" name="location_found" id="editFoundLocation" required></div>
<div class="mb-3"><label class="form-label fw-semibold">Replace Item Photo (Optional)</label><input type="file" class="form-control" name="item_image" accept="image/png,image/jpeg,image/webp"><div class="form-text">JPG, PNG or WEBP, maximum 5MB.</div></div>
<div class="mb-2"><label class="form-label fw-semibold">Condition & Additional Remarks</label><textarea class="form-control" name="remarks" id="editFoundRemarks" rows="3"></textarea></div></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Save Changes</button></div></form></div></div></div>

<!-- VIEW FOUND ITEM MODAL -->
<div class="modal fade" id="viewFoundModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content border-0 rounded-4 shadow">
<div class="modal-header">
<h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-heart text-primary me-2"></i>Found Item Submission Details</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<div class="row g-4">
<div class="col-md-5 text-center">
<div id="viewFoundImageWrap" class="border rounded-4 p-2 bg-light">
<img id="viewFoundImage" src="" alt="Found Item Image" style="max-width:100%;max-height:300px;object-fit:contain;border-radius:12px;">
</div>
</div>
<div class="col-md-7">
<div class="row g-3">
<div class="col-6"><div class="small text-muted">Item Code</div><div class="fw-bold font-monospace" id="viewFoundCode">-</div></div>
<div class="col-6"><div class="small text-muted">Status</div><div id="viewFoundStatus">-</div></div>
<div class="col-12"><div class="small text-muted">Item Title</div><div class="fw-bold" id="viewFoundTitle">-</div></div>
<div class="col-6"><div class="small text-muted">Category</div><div id="viewFoundCategory">-</div></div>
<div class="col-6"><div class="small text-muted">Date Found</div><div id="viewFoundDate">-</div></div>
<div class="col-12"><div class="small text-muted">Location Found</div><div id="viewFoundLocation">-</div></div>
<div class="col-12"><div class="small text-muted">Storage Location</div><div id="viewFoundStorage">-</div></div>
<div class="col-12"><div class="small text-muted">Remarks</div><div class="border rounded-3 p-3 bg-light" id="viewFoundRemarks">-</div></div>
</div>
</div>
</div>
</div>
<div class="modal-footer">
<span class="text-muted small me-auto"><i class="fa-solid fa-lock me-1"></i>Processed submissions are view-only.</span>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
</div>
</div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow">
<div class="modal-header"><h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><input type="hidden" name="reporter_action" id="deleteAction"><input type="hidden" name="report_id" id="deleteReportId"><input type="hidden" name="item_id" id="deleteItemId">
<div class="modal-body"><p class="mb-1">Are you sure you want to delete:</p><p class="fw-bold fs-5 mb-2" id="deleteItemName"></p><div class="alert alert-warning small mb-0"><i class="fa-solid fa-circle-info me-1"></i>This action cannot be undone.</div></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger fw-bold"><i class="fa-solid fa-trash me-1"></i>Delete</button></div></form></div></div></div>

<div class="modal fade" id="itemImageModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0 rounded-4 shadow"><div class="modal-header"><h5 class="modal-title fw-bold"><i class="fa-solid fa-image text-primary me-2"></i>Item Image</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body text-center p-4"><img id="previewItemImage" src="" alt="Item Preview" style="max-width:100%;max-height:75vh;object-fit:contain;border-radius:10px"></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script><script>
function showItemImage(imagePath){document.getElementById('previewItemImage').src=imagePath;}
function openViewLost(data){
    document.getElementById('viewLostReportId').textContent = '#' + (data.report_id || '-');
    document.getElementById('viewLostTitle').textContent = data.item_title || '-';
    document.getElementById('viewLostCategory').textContent = data.category_name || '-';
    document.getElementById('viewLostLocation').innerHTML = '<i class="fa-solid fa-location-dot text-danger me-1"></i>' + escapeHtml(data.location_lost || '-');
    document.getElementById('viewLostDate').textContent = formatViewDate(data.date_lost);
    document.getElementById('viewLostDescription').textContent = data.description || '-';
    let statusHtml = '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Resolved / Claimed</span>';
    if(data.status === 'Searching') statusHtml = '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Searching</span>';
    else if(data.status === 'Matched') statusHtml = '<span class="badge bg-info text-dark"><i class="fa-solid fa-link me-1"></i>Matched</span>';
    document.getElementById('viewLostStatus').innerHTML = statusHtml;
    const hasMatch = !!(data.matched_item_code || data.matched_item_title);
    document.getElementById('viewLostMatchBox').style.display = hasMatch ? '' : 'none';
    if(hasMatch){
        document.getElementById('viewMatchedTitle').textContent = data.matched_item_title || '-';
        document.getElementById('viewMatchedCode').textContent = data.matched_item_code || '-';
        document.getElementById('viewMatchedStorage').textContent = data.matched_storage_location || 'Not assigned';
        let ms = data.matched_item_status || '-';
        document.getElementById('viewMatchedStatus').innerHTML = ms === 'Stored' ? '<span class="badge bg-primary">Stored</span>' : ms === 'Claim Pending' ? '<span class="badge bg-warning text-dark">Claim Pending</span>' : ms === 'Claimed' ? '<span class="badge bg-success">Claimed</span>' : escapeHtml(ms);
    }
}
function formatViewDate(value){
    if(!value) return '-';
    const parts=String(value).split('-');
    if(parts.length!==3) return value;
    return new Date(Number(parts[0]),Number(parts[1])-1,Number(parts[2])).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'});
}
function escapeHtml(value){return String(value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
function openViewFound(data){
    document.getElementById('viewFoundCode').textContent = data.item_code || '-';
    document.getElementById('viewFoundTitle').textContent = data.title || '-';
    document.getElementById('viewFoundCategory').textContent = data.category_name || '-';
    document.getElementById('viewFoundDate').textContent = formatViewDate(data.date_found);
    document.getElementById('viewFoundLocation').innerHTML = '<i class="fa-solid fa-location-dot text-primary me-1"></i>' + escapeHtml(data.location_found || '-');
    document.getElementById('viewFoundStorage').textContent = data.location_name || 'Not assigned';
    document.getElementById('viewFoundRemarks').textContent = data.remarks || '-';

    let statusHtml = '<span class="badge bg-secondary">' + escapeHtml(data.status || '-') + '</span>';
    if(data.status === 'Pending Check-in'){
        statusHtml = '<span class="badge bg-warning text-dark">Pending Staff Check-in</span>';
    } else if(data.status === 'Stored'){
        statusHtml = '<span class="badge bg-primary">Stored in Locker</span>';
    } else if(data.status === 'Claim Pending'){
        statusHtml = '<span class="badge bg-warning text-dark">Claim Pending</span>';
    } else if(data.status === 'Claimed'){
        statusHtml = '<span class="badge bg-success">Returned to Owner</span>';
    }
    document.getElementById('viewFoundStatus').innerHTML = statusHtml;

    const imageWrap = document.getElementById('viewFoundImageWrap');
    const image = document.getElementById('viewFoundImage');
    if(data.image_path){
        image.src = data.image_path;
        imageWrap.style.display = '';
    } else {
        image.removeAttribute('src');
        imageWrap.style.display = 'none';
    }
}

function openEditLost(id,title,category,location,date,description){document.getElementById('editLostId').value=id;document.getElementById('editLostTitle').value=title;document.getElementById('editLostCategory').value=category;document.getElementById('editLostLocation').value=location;document.getElementById('editLostDate').value=date;document.getElementById('editLostDescription').value=description;}
function openEditFound(id,title,category,location,date,remarks){document.getElementById('editFoundId').value=id;document.getElementById('editFoundTitle').value=title;document.getElementById('editFoundCategory').value=category;document.getElementById('editFoundLocation').value=location;document.getElementById('editFoundDate').value=date;document.getElementById('editFoundRemarks').value=remarks;}
function openDelete(action,id,name){document.getElementById('deleteAction').value=action;document.getElementById('deleteReportId').value=action==='delete_lost'?id:'';document.getElementById('deleteItemId').value=action==='delete_found'?id:'';document.getElementById('deleteItemName').textContent=name;}
document.querySelectorAll('.nav-pills a[href^="#"]').forEach(function(link){link.addEventListener('click',function(e){e.preventDefault();const target=document.querySelector(this.getAttribute('href'));if(target)target.scrollIntoView({behavior:'smooth',block:'start'});document.querySelectorAll('.nav-pills .nav-link').forEach(n=>n.classList.remove('active'));this.classList.add('active');});});</script>
</body></html>