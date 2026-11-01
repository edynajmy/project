<?php
// admin_dashboard.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';

// Strict Access Guard: Admin Only
checkAccess(['Admin']);

$admin_id = $_SESSION['user_id'];

// --------------------------------------------------------------------
// 1. ACTION HANDLERS
// --------------------------------------------------------------------

// Handle Role Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $target_user_id = (int)$_POST['user_id'];
    $new_role       = $_POST['role'];

    if ($target_user_id > 0 && in_array($new_role, ['Admin', 'Recorder', 'Reporter'])) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        if ($stmt->execute([$new_role, $target_user_id])) {
            $_SESSION['success'] = "User role successfully updated to '$new_role'!";
        } else {
            $_SESSION['error'] = "Failed to update user role.";
        }
    }
    header("Location: admin_dashboard.php?tab=users");
    exit();
}

// Handle Item Disposal / Status Update for Expired Items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispose_item') {
    $item_id = (int)$_POST['item_id'];
    
    if ($item_id > 0) {
        $stmt = $pdo->prepare("UPDATE found_items SET status = 'Disposed' WHERE item_id = ?");
        if ($stmt->execute([$item_id])) {
            $_SESSION['success'] = "Item marked as 'Disposed' and removed from active inventory.";
        } else {
            $_SESSION['error'] = "Failed to update item status.";
        }
    }
    header("Location: admin_dashboard.php?tab=alerts");
    exit();
}

// Handle Add New Storage Location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_location') {
    $location_name = trim($_POST['location_name']);
    $description   = trim($_POST['description']);

    if (!empty($location_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO storage_locations (location_name, description) VALUES (?, ?)");
            $stmt->execute([$location_name, $description]);
            $_SESSION['success'] = "Storage location '$location_name' added successfully!";
        } catch (\PDOException $e) {
            $_SESSION['error'] = "Location already exists or invalid data.";
        }
    }
    header("Location: admin_dashboard.php?tab=locations");
    exit();
}

// --------------------------------------------------------------------
// 2. DATA QUERIES
// --------------------------------------------------------------------

// Active tab selector
$active_tab = $_GET['tab'] ?? 'alerts';

// Metrics
$metric_users     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$metric_stored    = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status = 'Stored'")->fetchColumn();
$metric_pending   = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status = 'Pending Check-in'")->fetchColumn();
$metric_expired   = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status = 'Stored' AND expiry_date <= CURDATE()")->fetchColumn();

// Expired / Expiring Items Query (Items reaching/exceeding retention period)
$expired_sql = "SELECT f.*, c.category_name, c.retention_months, s.location_name 
               FROM found_items f
               JOIN categories c ON f.category_id = c.category_id
               LEFT JOIN storage_locations s ON f.storage_location_id = s.location_id
               WHERE f.status = 'Stored' AND f.expiry_date <= CURDATE()
               ORDER BY f.expiry_date ASC";
$expired_items = $pdo->query($expired_sql)->fetchAll();

// Items that are still stored and approaching their retention expiry date
$expiring_sql = "SELECT
                    f.*,
                    c.category_name,
                    c.retention_months,
                    s.location_name,
                    DATEDIFF(f.expiry_date, CURDATE()) AS days_remaining
                 FROM found_items f
                 JOIN categories c
                    ON f.category_id = c.category_id
                 LEFT JOIN storage_locations s
                    ON f.storage_location_id = s.location_id
                 WHERE f.status = 'Stored'
                   AND f.expiry_date > CURDATE()
                 ORDER BY f.expiry_date ASC";

$expiring_items = $pdo->query($expiring_sql)->fetchAll();

// Users List
$users = $pdo->query("SELECT user_id, full_name, email, phone_number, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// Categories & Retention Rules
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_id ASC")->fetchAll();

// Storage Locations List with current stored item count
$locations = $pdo->query("
    SELECT
        sl.location_id,
        sl.location_name,
        sl.description,
        COUNT(
            CASE
                WHEN fi.status = 'Stored'
                THEN fi.item_id
            END
        ) AS item_count
    FROM storage_locations sl
    LEFT JOIN found_items fi
        ON fi.storage_location_id = sl.location_id
    GROUP BY
        sl.location_id,
        sl.location_name,
        sl.description
    ORDER BY sl.location_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all stored found items grouped by storage location
$stored_items_by_location = [];

$stmtStoredItems = $pdo->query("
    SELECT
        f.item_id,
        f.item_code,
        f.title,
        f.category_id,
        c.category_name,
        f.location_found,
        f.storage_location_id,
        f.image_path,
        f.reporter_id,
        f.date_found,
        f.expiry_date,
        f.status,
        f.remarks
    FROM found_items f
    LEFT JOIN categories c
        ON f.category_id = c.category_id
    WHERE f.status = 'Stored'
      AND f.storage_location_id IS NOT NULL
    ORDER BY f.date_found DESC
");

$stored_items = $stmtStoredItems->fetchAll(PDO::FETCH_ASSOC);

foreach ($stored_items as $item) {
    $locationId = (int)$item['storage_location_id'];

    if (!isset($stored_items_by_location[$locationId])) {
        $stored_items_by_location[$locationId] = [];
    }

    $stored_items_by_location[$locationId][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Resort L&F</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .nav-pills .nav-link.active { background-color: #0d6efd; font-weight: 600; }
        .stat-card { border: none; border-radius: 12px; }
        .table-vcenter td, .table-vcenter th { vertical-align: middle; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fa-solid fa-user-shield text-primary me-2"></i>Admin Control Center
            </a>
            <div class="d-flex align-items-center ms-auto">
                <span class="text-light me-3 small">
                    <i class="fa-solid fa-user-gear text-warning me-1"></i> 
                    <?= htmlspecialchars($_SESSION['user_name']); ?> (Admin)
                </span>
                <a href="storage_overview.php"
                   class="btn btn-outline-light btn-sm me-2 d-inline-flex align-items-center justify-content-center"
                   style="width:42px;height:38px;"
                   title="Storage Overview"
                   aria-label="Storage Overview">
                    <i class="fa-solid fa-warehouse"></i>
                </a>
                <a href="activity_timeline.php"
                   class="btn btn-outline-light btn-sm me-2 d-inline-flex align-items-center justify-content-center"
                   style="width:42px;height:38px;"
                   title="Activity Timeline"
                   aria-label="Activity Timeline">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </a>
                <a href="account_security.php"
                   class="btn btn-outline-warning btn-sm me-2 d-inline-flex align-items-center justify-content-center"
                   style="width:42px;height:38px;"
                   title="Account Security"
                   aria-label="Account Security">
                    <i class="fa-solid fa-shield-halved"></i>
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">

        <!-- NOTIFICATION ALERTS -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- METRIC CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Retention Expired</h6>
                            <h2 class="fw-bold mb-0"><?= $metric_expired; ?></h2>
                        </div>
                        <i class="fa-solid fa-bell-exclamation fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-dark p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Pending Check-in</h6>
                            <h2 class="fw-bold mb-0"><?= $metric_pending; ?></h2>
                        </div>
                        <i class="fa-solid fa-hourglass-half fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Active In Storage</h6>
                            <h2 class="fw-bold mb-0"><?= $metric_stored; ?></h2>
                        </div>
                        <i class="fa-solid fa-boxes-stacked fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white p-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">Total System Users</h6>
                            <h2 class="fw-bold mb-0"><?= $metric_users; ?></h2>
                        </div>
                        <i class="fa-solid fa-users fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN TAB NAVIGATION -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <ul class="nav nav-pills card-header-pills">
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab === 'alerts' ? 'active' : ''; ?>" href="admin_dashboard.php?tab=alerts">
                            <i class="fa-solid fa-bell me-2"></i>Retention Expiry Alerts 
                            <?php if ($metric_expired > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $metric_expired; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab === 'users' ? 'active' : ''; ?>" href="admin_dashboard.php?tab=users">
                            <i class="fa-solid fa-users-gear me-2"></i>User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab === 'categories' ? 'active' : ''; ?>" href="admin_dashboard.php?tab=categories">
                            <i class="fa-solid fa-layer-group me-2"></i>Categories & Retention Rules
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab === 'locations' ? 'active' : ''; ?>" href="admin_dashboard.php?tab=locations">
                            <i class="fa-solid fa-warehouse me-2"></i>Storage Locations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_reports.php">
                            <i class="fa-solid fa-chart-column me-2"></i>Reports
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">

               <!-- TAB 1: RETENTION EXPIRY ALERTS -->
<?php if ($active_tab === 'alerts'): ?>

    <!-- =========================================================
         SECTION 1: EXPIRED RETENTION ITEMS
         ========================================================= -->

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h5 class="fw-bold text-danger mb-0">
            <i class="fa-solid fa-clock-rotate-left me-2"></i>
            Expired Retention Items
        </h5>

        <span class="badge bg-danger">
            <?= count($expired_items); ?> Expired
        </span>

    </div>

    <div class="alert alert-danger d-flex align-items-center mb-4">

        <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>

        <div>
            <strong>Action Required</strong>
            <div class="small">
                These items have already exceeded their retention period
                and require disposal or further administrative action.
            </div>
        </div>

    </div>


    <?php if (empty($expired_items)): ?>

        <div class="text-center py-4 border rounded mb-5">

            <i class="fa-solid fa-circle-check fs-1 text-success mb-3 d-block"></i>

            <h6 class="text-muted mb-0">
                No items have exceeded their retention period.
            </h6>

        </div>

    <?php else: ?>

        <div class="table-responsive mb-5">

            <table class="table table-hover table-vcenter border">

                <thead class="table-light">

                    <tr>

                        <th>Code & Title</th>

                        <th>Category</th>

                        <th>Storage Location</th>

                        <th>Date Found</th>

                        <th>Expiry Date</th>

                        <th class="text-end">
                            Management Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($expired_items as $item): ?>

                        <tr>

                            <!-- CODE & TITLE -->
                            <td>

                                <strong class="d-block">
                                    <?= htmlspecialchars(
                                        $item['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </strong>

                                <span class="badge bg-secondary font-monospace">

                                    <?= htmlspecialchars(
                                        $item['item_code'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </span>

                            </td>


                            <!-- CATEGORY -->
                            <td>

                                <span class="badge bg-info text-dark">

                                    <?= htmlspecialchars(
                                        $item['category_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </span>

                                <small class="d-block text-muted">

                                    <?= (int)$item['retention_months']; ?>
                                    Months Limit

                                </small>

                            </td>


                            <!-- STORAGE LOCATION -->
                            <td>

                                <i class="fa-solid fa-location-dot text-primary me-1"></i>

                                <?= htmlspecialchars(
                                    $item['location_name'] ?? 'Unassigned',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <!-- DATE FOUND -->
                            <td>

                                <?= !empty($item['date_found'])
                                    ? date('M d, Y', strtotime($item['date_found']))
                                    : 'N/A'; ?>

                            </td>


                            <!-- EXPIRY DATE -->
                            <td>

                                <span class="badge bg-danger">

                                    <i class="fa-solid fa-circle-exclamation me-1"></i>

                                    Expired

                                </span>

                                <small class="d-block text-danger mt-1">

                                    <?= date(
                                        'M d, Y',
                                        strtotime($item['expiry_date'])
                                    ); ?>

                                </small>

                            </td>


                            <!-- ACTION -->
                            <td class="text-end">

                                <form
                                    action="admin_dashboard.php"
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm(
                                        'Are you sure you want to mark this item as Disposed?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="dispose_item"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= (int)$item['item_id']; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                    >

                                        <i class="fa-solid fa-trash-can me-1"></i>

                                        Mark Disposed

                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>


            <!-- =========================================================
                SECTION 2: RETENTION EXPIRY COUNTDOWN
                ========================================================= -->

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="fw-bold text-primary mb-0">

                    <i class="fa-solid fa-hourglass-half me-2"></i>

                    Retention Expiry Countdown

                </h5>

                <span class="badge bg-primary">

                    <?= count($expiring_items); ?> Active

                </span>

            </div>


            <div class="alert alert-primary d-flex align-items-center mb-4">

                <i class="fa-solid fa-calendar-days fs-4 me-3"></i>

                <div>

                    <strong>Upcoming Retention Expiry</strong>

                    <div class="small">

                        These stored items have not expired yet.
                        The countdown shows the remaining time before
                        their retention period ends.

                    </div>

                </div>

            </div>


            <?php if (empty($expiring_items)): ?>

                <div class="text-center py-4 border rounded">

                    <i class="fa-solid fa-circle-check fs-1 text-success mb-3 d-block"></i>

                    <h6 class="text-muted mb-0">

                        No stored items are currently approaching expiry.

                    </h6>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover table-vcenter border">

                        <thead class="table-light">

                            <tr>

                                <th>Code & Title</th>

                                <th>Category</th>

                                <th>Storage Location</th>

                                <th>Date Found</th>

                                <th>Expiry Date</th>

                                <th class="text-center">
                                    Countdown
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($expiring_items as $item): ?>

                                <?php
                                $daysRemaining = (int)$item['days_remaining'];

                                if ($daysRemaining <= 7) {
                                    $countdownClass = 'bg-danger';
                                    $countdownIcon = 'fa-triangle-exclamation';
                                } elseif ($daysRemaining <= 30) {
                                    $countdownClass = 'bg-warning text-dark';
                                    $countdownIcon = 'fa-clock';
                                } else {
                                    $countdownClass = 'bg-success';
                                    $countdownIcon = 'fa-calendar-check';
                                }
                                ?>

                                <tr>

                                    <!-- CODE & TITLE -->
                                    <td>

                                        <strong class="d-block">

                                            <?= htmlspecialchars(
                                                $item['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </strong>

                                        <span class="badge bg-secondary font-monospace">

                                            <?= htmlspecialchars(
                                                $item['item_code'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- CATEGORY -->
                                    <td>

                                        <span class="badge bg-info text-dark">

                                            <?= htmlspecialchars(
                                                $item['category_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>

                                        <small class="d-block text-muted">

                                            <?= (int)$item['retention_months']; ?>
                                            Months Limit

                                        </small>

                                    </td>


                                    <!-- STORAGE LOCATION -->
                                    <td>

                                        <i class="fa-solid fa-location-dot text-primary me-1"></i>

                                        <?= htmlspecialchars(
                                            $item['location_name'] ?? 'Unassigned',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </td>


                                    <!-- DATE FOUND -->
                                    <td>

                                        <?= !empty($item['date_found'])
                                            ? date('M d, Y', strtotime($item['date_found']))
                                            : 'N/A'; ?>

                                    </td>


                                    <!-- EXPIRY DATE -->
                                    <td>

                                        <?= date(
                                            'M d, Y',
                                            strtotime($item['expiry_date'])
                                        ); ?>

                                    </td>


                                    <!-- COUNTDOWN -->
                                    <td class="text-center">

                                        <span
                                            class="badge <?= $countdownClass; ?> fs-6 px-3 py-2"
                                        >

                                            <i
                                                class="fa-solid <?= $countdownIcon; ?> me-1"
                                            ></i>

                                            <?= $daysRemaining; ?>

                                            <?= $daysRemaining === 1
                                                ? 'Day Remaining'
                                                : 'Days Remaining'; ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

                <!-- TAB 2: USER MANAGEMENT -->
                <?php elseif ($active_tab === 'users'): ?>
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-users me-2"></i>Registered System Users & Role Control</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter border">
                            <thead class="table-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>Email Address</th>
                                    <th>Phone Number</th>
                                    <th>Current Role</th>
                                    <th>Registered Date</th>
                                    <th class="text-end">Change Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($u['full_name']); ?></strong></td>
                                        <td><?= htmlspecialchars($u['email']); ?></td>
                                        <td><?= htmlspecialchars($u['phone_number']); ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'Admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php elseif ($u['role'] === 'Recorder'): ?>
                                                <span class="badge bg-primary">Recorder (Staff)</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Reporter (Guest)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($u['created_at'])); ?></td>
                                        <td class="text-end">
                                            <form action="admin_dashboard.php" method="POST" class="d-inline-flex align-items-center">
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id']; ?>">
                                                <select name="role" class="form-select form-select-sm me-2" style="width: 130px;">
                                                    <option value="Reporter" <?= $u['role'] === 'Reporter' ? 'selected' : ''; ?>>Reporter</option>
                                                    <option value="Recorder" <?= $u['role'] === 'Recorder' ? 'selected' : ''; ?>>Recorder</option>
                                                    <option value="Admin" <?= $u['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- TAB 3: CATEGORIES & RETENTION -->
                <?php elseif ($active_tab === 'categories'): ?>
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-layer-group me-2"></i>Item Classifications & Retention Rules</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter border">
                            <thead class="table-light">
                                <tr>
                                    <th>Category ID</th>
                                    <th>Category Name</th>
                                    <th>Retention Period (Months)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>#<?= $cat['category_id']; ?></td>
                                        <td><strong><?= htmlspecialchars($cat['category_name']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-info text-dark fs-6 px-3 py-2">
                                                <?= $cat['retention_months']; ?> Months
                                            </span>
                                        </td>
                                        <td><span class="badge bg-success">Active Rule</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- TAB 4: STORAGE LOCATIONS -->
                <?php elseif ($active_tab === 'locations'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-warehouse me-2"></i>Physical Storage Locations</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                            <i class="fa-solid fa-plus me-1"></i>Add Storage Zone
                        </button>
                    </div>

                    <div class="table-responsive">

                        <table class="table table-hover table-vcenter border">

                            <thead class="table-light">

                                <tr>
                                    <th>ID</th>

                                    <th>Storage Location Name</th>

                                    <th>Description</th>

                                    <th class="text-center">Items Stored</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php if (!empty($locations)): ?>

                                    <?php foreach ($locations as $loc): ?>

                                        <tr>

                                            <!-- ID -->
                                            <td>
                                                #<?= (int)$loc['location_id']; ?>
                                            </td>


                                            <!-- LOCATION NAME -->
                                            <td>
                                                <strong>
                                                    <?= htmlspecialchars($loc['location_name']); ?>
                                                </strong>
                                            </td>


                                            <!-- DESCRIPTION -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $loc['description'] ?? 'N/A'
                                                ); ?>
                                            </td>


                                            <!-- ITEM COUNT / VIEW STORED ITEMS -->
                                            <td class="text-center">

                                                <?php
                                                $itemCount = (int)$loc['item_count'];
                                                $locationId = (int)$loc['location_id'];
                                                ?>

                                                <?php if ($itemCount > 0): ?>

                                                    <button
                                                        type="button"
                                                        class="btn btn-primary btn-sm px-3 fw-bold"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#storedItemsModal<?= $locationId; ?>"
                                                    >
                                                        <i class="fa-solid fa-box me-1"></i>

                                                        <?= $itemCount; ?>

                                                        <?= $itemCount === 1 ? 'Item' : 'Items'; ?>

                                                    </button>

                                                <?php else: ?>

                                                    <span class="badge bg-secondary px-3 py-2">

                                                        <i class="fa-solid fa-box-open me-1"></i>

                                                        Empty

                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>

                                        <td colspan="4" class="text-center text-muted py-4">

                                            <i class="fa-solid fa-warehouse fa-2x mb-2"></i>

                                            <br>

                                            No storage locations found.

                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

<!-- STORED ITEMS MODALS -->
<?php foreach ($locations as $loc): ?>

    <?php
    $locationId = (int)$loc['location_id'];
    $locationName = $loc['location_name'];
    $locationItems = $stored_items_by_location[$locationId] ?? [];
    ?>

    <?php if (!empty($locationItems)): ?>

        <div
            class="modal fade"
            id="storedItemsModal<?= $locationId; ?>"
            tabindex="-1"
            aria-hidden="true"
        >

            <div class="modal-dialog modal-xl modal-dialog-centered">

                <div class="modal-content border-0 shadow">

                    <!-- HEADER -->
                    <div class="modal-header bg-primary text-white">

                        <div>
                            <h5 class="modal-title fw-bold mb-1">

                                <i class="fa-solid fa-boxes-stacked me-2"></i>

                                Stored Items - <?= htmlspecialchars($locationName, ENT_QUOTES, 'UTF-8'); ?>

                            </h5>

                            <small>
                                <?= count($locationItems); ?>
                                <?= count($locationItems) === 1 ? 'item' : 'items'; ?>
                                currently stored here
                            </small>
                        </div>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"
                        ></button>

                    </div>

                    <!-- BODY -->
                    <div class="modal-body">

                        <div class="table-responsive">

                            <table class="table table-hover table-bordered table-vcenter mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th>#</th>

                                        <th>Item Code</th>

                                        <th>Item</th>

                                        <th>Category</th>

                                        <th>Location Found</th>

                                        <th>Date Found</th>

                                        <th>Expiry Date</th>

                                        <th>Status</th>

                                        <th>Remarks</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($locationItems as $index => $item): ?>

                                        <tr>

                                            <!-- NUMBER -->
                                            <td>
                                                <?= $index + 1; ?>
                                            </td>

                                            <!-- ITEM CODE -->
                                            <td>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $item['item_code'] ?? 'N/A',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </strong>
                                            </td>

                                            <!-- TITLE -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $item['title'] ?? 'N/A',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </td>

                                            <!-- CATEGORY -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $item['category_name'] ?? 'N/A',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </td>

                                            <!-- LOCATION FOUND -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $item['location_found'] ?? 'N/A',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </td>

                                            <!-- DATE FOUND -->
                                            <td>
                                                <?php
                                                if (!empty($item['date_found'])) {
                                                    echo date(
                                                        'd M Y',
                                                        strtotime($item['date_found'])
                                                    );
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>

                                            <!-- EXPIRY DATE -->
                                            <td>
                                                <?php
                                                if (!empty($item['expiry_date'])) {
                                                    echo date(
                                                        'd M Y',
                                                        strtotime($item['expiry_date'])
                                                    );
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>

                                            <!-- STATUS -->
                                            <td>

                                                <span class="badge bg-success">

                                                    <i class="fa-solid fa-box me-1"></i>

                                                    <?= htmlspecialchars(
                                                        $item['status'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>

                                                </span>

                                            </td>

                                            <!-- REMARKS -->
                                            <td>
                                                <?= htmlspecialchars(
                                                    $item['remarks'] ?? 'N/A',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                    <!-- FOOTER -->
                    <div class="modal-footer bg-light">

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            data-bs-dismiss="modal"
                        >
                            Close
                        </button>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>

<?php endforeach; ?>

    <!-- ADD STORAGE LOCATION MODAL -->
    <div class="modal fade" id="addLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus me-2"></i>Add New Storage Location</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="admin_dashboard.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_location">
                        <div class="mb-3">
                            <label for="location_name" class="form-label fw-bold small">Location / Shelf Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="location_name" name="location_name" placeholder="e.g., Locker Room B - Shelf 3" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold small">Description (Optional)</label>
                            <input type="text" class="form-control" id="description" name="description" placeholder="e.g., Secure safe for valuables">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Save Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>