<?php
// recorder_dashboard.php

session_start();

require_once 'db.php';
require_once 'auth_check.php';
require_once 'notification_helper.php';

// Authorize Recorders and Admins only
checkAccess(['Recorder', 'Admin']);

$recorder_id = $_SESSION['user_id'];

$notification_unread_count = 0;
try {
    $notificationStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ?
          AND is_read = 'No'
    ");
    $notificationStmt->execute([$recorder_id]);
    $notification_unread_count = (int)$notificationStmt->fetchColumn();
} catch (PDOException $e) {
    $notification_unread_count = 0;
}

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| MATCH LOST REPORT WITH FOUND ITEM
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'match_found_item'
) {

    $report_id = isset($_POST['report_id'])
        ? (int)$_POST['report_id']
        : 0;

    $found_item_id = isset($_POST['found_item_id'])
        ? (int)$_POST['found_item_id']
        : 0;

    $match_notes = trim($_POST['match_notes'] ?? '');

    if ($report_id <= 0 || $found_item_id <= 0) {
        $_SESSION['error'] = 'Invalid lost report or found item.';
        header('Location: recorder_dashboard.php');
        exit();
    }

    try {

        $pdo->beginTransaction();

        /*
        |    | 1. Get lost report
        |    */
        $lostStmt = $pdo->prepare("
            SELECT
                report_id,
                category_id,
                status,
                item_title
            FROM lost_items
            WHERE report_id = ?
            LIMIT 1
        ");

        $lostStmt->execute([$report_id]);
        $lost = $lostStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lost) {
            throw new Exception('Lost report not found.');
        }

        if ($lost['status'] !== 'Searching') {
            throw new Exception(
                'This lost report is no longer in Searching status.'
            );
        }

        /*
        |    | 2. Get found item
        |    */
        $foundStmt = $pdo->prepare("
            SELECT
                item_id,
                item_code,
                title,
                category_id,
                status
            FROM found_items
            WHERE item_id = ?
            LIMIT 1
        ");

        $foundStmt->execute([$found_item_id]);
        $found = $foundStmt->fetch(PDO::FETCH_ASSOC);

        if (!$found) {
            throw new Exception('Found item not found.');
        }

        if ($found['status'] !== 'Stored') {
            throw new Exception(
                'Only stored found items can be matched.'
            );
        }

        /*
        |    | 3. Category is for reference only
        |    | Recorders can match any Stored found item after reviewing the details.
        | The Reporter-selected category is not used as a hard matching rule.
        */

        /*
        |    | 4. Check whether this lost report already has a match
        |    */
        $checkLostMatch = $pdo->prepare("
            SELECT match_id
            FROM item_matches
            WHERE lost_report_id = ?
            AND status = 'Matched'
            LIMIT 1
        ");

        $checkLostMatch->execute([$report_id]);

        if ($checkLostMatch->fetch()) {
            throw new Exception(
                'This lost report already has an active match.'
            );
        }

        /*
        |    | 5. Check whether found item is already matched
        |    */
        $checkFoundMatch = $pdo->prepare("
            SELECT match_id
            FROM item_matches
            WHERE found_item_id = ?
            AND status = 'Matched'
            LIMIT 1
        ");

        $checkFoundMatch->execute([$found_item_id]);

        if ($checkFoundMatch->fetch()) {
            throw new Exception(
                'This found item is already matched to another lost report.'
            );
        }

        /*
        |    | 6. Create match record
        |    */
        $matchStmt = $pdo->prepare("
            INSERT INTO item_matches
            (
                lost_report_id,
                found_item_id,
                matched_by_recorder_id,
                match_notes,
                status
            )
            VALUES (?, ?, ?, ?, 'Matched')
        ");

        $matchStmt->execute([
            $report_id,
            $found_item_id,
            $recorder_id,
            $match_notes !== '' ? $match_notes : 'Matched by Recorder'
        ]);

        /*
        |    | 7. Update lost report
        |    */
        $updateLost = $pdo->prepare("
            UPDATE lost_items
            SET status = 'Matched'
            WHERE report_id = ?
            AND status = 'Searching'
        ");

        $updateLost->execute([$report_id]);

        if ($updateLost->rowCount() === 0) {
            throw new Exception(
                'Unable to update the lost report.'
            );
        }

        /*
        |    | 8. Keep found item as Stored
        |    |
        | IMPORTANT:
        | Do NOT change found_items.status to Claimed here.
        | The claimant still needs to submit a claim.
        |
        */

        $pdo->commit();

        // Notify the Reporter that their lost report has been matched.
        try {
            $reporterStmt = $pdo->prepare("
                SELECT reporter_id
                FROM lost_items
                WHERE report_id = ?
                LIMIT 1
            ");
            $reporterStmt->execute([$report_id]);
            $matchedReporterId = (int)$reporterStmt->fetchColumn();

            if ($matchedReporterId > 0) {
                createNotification(
                    $pdo,
                    $matchedReporterId,
                    $found_item_id,
                    'Your lost report #' . $report_id . ' has been matched with found item ' . $found['item_code'] . '.'
                );
            }
        } catch (Throwable $notificationError) {
            error_log('Match notification error: ' . $notificationError->getMessage());
        }

        $_SESSION['success'] =
            'Lost report #' . $report_id .
            ' successfully matched with found item ' .
            $found['item_code'] . '.';

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['error'] = $e->getMessage();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Match Found Item Error: ' . $e->getMessage()
        );

        $_SESSION['error'] =
            'Database error while matching the items.';
    }

    header('Location: recorder_dashboard.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Helper: Build Correct Image URL
|--------------------------------------------------------------------------
| Handles:
|   FOUND-xxxx.jpg
|   uploads/FOUND-xxxx.jpg
|   /uploads/FOUND-xxxx.jpg
|   http://...
|   https://...
|--------------------------------------------------------------------------
*/
function getImagePath($path)
{
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    // External image
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }

    // Absolute server path
    if (str_starts_with($path, '/')) {
        return $path;
    }

    // Already contains uploads/
    if (str_starts_with($path, 'uploads/')) {
        return $path;
    }

    // Normal filename
    return 'uploads/' . ltrim($path, '/\\');
}


/*
|--------------------------------------------------------------------------
| 1. PROCESS STORAGE CHECK-IN
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'process_checkin'
) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $storage_location_id = (int)($_POST['storage_location_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($item_id <= 0 || $storage_location_id <= 0) {
        $_SESSION['error'] = 'Please select a valid found item and storage location.';
        header('Location: recorder_dashboard.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Lock the item so two staff members cannot check it in simultaneously.
        $itemStmt = $pdo->prepare("
            SELECT item_id, status
            FROM found_items
            WHERE item_id = ?
            FOR UPDATE
        ");
        $itemStmt->execute([$item_id]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new RuntimeException('Found item not found.');
        }
        if ($item['status'] !== 'Pending Check-in') {
            throw new RuntimeException('This found item has already been processed.');
        }

        // Lock the selected location and verify capacity/status.
        $locationStmt = $pdo->prepare("
            SELECT location_id, location_name, capacity, status
            FROM storage_locations
            WHERE location_id = ?
            FOR UPDATE
        ");
        $locationStmt->execute([$storage_location_id]);
        $location = $locationStmt->fetch(PDO::FETCH_ASSOC);

        if (!$location) {
            throw new RuntimeException('Selected storage location was not found.');
        }
        if ($location['status'] === 'Maintenance') {
            throw new RuntimeException('This storage location is under maintenance.');
        }
        $occupiedStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM found_items
            WHERE storage_location_id = ?
              AND status = 'Stored'
        " );
        $occupiedStmt->execute([$storage_location_id]);
        $occupied = (int)$occupiedStmt->fetchColumn();

        if ($occupied >= (int)$location['capacity']) {
            throw new RuntimeException('This storage location is full. Please choose another location.');
        }

        $updateSql = "
            UPDATE found_items
            SET
                storage_location_id = ?,
                recorder_handler_id = ?,
                status = 'Stored',
                remarks = CASE
                    WHEN ? <> '' THEN CONCAT(IFNULL(remarks, ''), '\\n[Check-in Note]: ', ?)
                    ELSE remarks
                END
            WHERE item_id = ?
              AND status = 'Pending Check-in'
        ";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([
            $storage_location_id,
            $recorder_id,
            $remarks,
            $remarks,
            $item_id
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Item could not be checked in.');
        }

        $newOccupied = $occupied + 1;
        if ($newOccupied >= (int)$location['capacity']) {
            $updateLocation = $pdo->prepare("UPDATE storage_locations SET status = 'Full' WHERE location_id = ?");
            $updateLocation->execute([$storage_location_id]);
        } elseif ($location['status'] === 'Full') {
            $updateLocation = $pdo->prepare("UPDATE storage_locations SET status = 'Available' WHERE location_id = ?");
            $updateLocation->execute([$storage_location_id]);
        }

        $pdo->commit();

        try {
            $reporterStmt = $pdo->prepare("
                SELECT reporter_id
                FROM found_items
                WHERE item_id = ?
                LIMIT 1
            ");
            $reporterStmt->execute([$item_id]);
            $foundReporterId = (int)$reporterStmt->fetchColumn();

            if ($foundReporterId > 0) {
                createNotification(
                    $pdo,
                    $foundReporterId,
                    $item_id,
                    'Your found item has been checked in and stored at ' . $location['location_name'] . '.'
                );
            }
        } catch (Throwable $notificationError) {
            error_log('Check-in notification error: ' . $notificationError->getMessage());
        }

        $_SESSION['success'] = 'Item successfully checked in and assigned to ' . $location['location_name'] . '.';
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Storage check-in error: ' . $e->getMessage());
        $_SESSION['error'] = 'A database error occurred while checking in the item.';
    }

    header('Location: recorder_dashboard.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| 2. STORAGE LOCATIONS
|--------------------------------------------------------------------------
*/
$storage_locations = [];

try {

    $storage_locations = $pdo
        ->query("
            SELECT *
            FROM storage_locations
            ORDER BY location_name ASC
        ")
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $storage_locations = [];
}

/*
|--------------------------------------------------------------------------
| 3. LOST REPORTS - SEARCHING
|--------------------------------------------------------------------------
*/
$searching_lost_reports = [];

try {

    $lost_sql = "
        SELECT
            l.*,
            c.category_name,
            u.full_name AS reporter_name,
            u.phone_number AS reporter_phone
        FROM lost_items l
        JOIN categories c
            ON l.category_id = c.category_id
        LEFT JOIN users u
            ON l.reporter_id = u.user_id
        WHERE l.status = 'Searching'
        ORDER BY l.date_lost DESC
    ";

    $searching_lost_reports = $pdo
        ->query($lost_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $searching_lost_reports = [];
}

/*
|--------------------------------------------------------------------------
| 4. PENDING CHECK-IN ITEMS
|--------------------------------------------------------------------------
*/
$pending_items = [];

try {

    $pending_sql = "
        SELECT
            f.*,
            c.category_name,
            c.retention_months,
            u.full_name AS reporter_name,
            u.phone_number AS reporter_phone
        FROM found_items f

        JOIN categories c
            ON f.category_id = c.category_id

        JOIN users u
            ON f.reporter_id = u.user_id

        WHERE f.status = 'Pending Check-in'

        ORDER BY f.created_at ASC
    ";

    $pending_items = $pdo
        ->query($pending_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $pending_items = [];
}


/*
|--------------------------------------------------------------------------
| 4. STORED ITEMS
|--------------------------------------------------------------------------
*/
$stored_items = [];

try {

    $stored_sql = "
        SELECT
            f.*,
            c.category_name,
            c.retention_months,

            u.full_name AS reporter_name,
            u.phone_number AS reporter_phone,

            s.location_name,
            s.description AS storage_description

        FROM found_items f

        JOIN categories c
            ON f.category_id = c.category_id

        JOIN users u
            ON f.reporter_id = u.user_id

        LEFT JOIN storage_locations s
            ON f.storage_location_id = s.location_id

        WHERE f.status = 'Stored'
AND f.item_id = (
    SELECT MAX(f2.item_id)
    FROM found_items f2
    WHERE f2.status = 'Stored'
      AND f2.title = f.title
)
ORDER BY f.date_found DESC
    ";

    $stored_items = $pdo
        ->query($stored_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $stored_items = [];
}

/*
|--------------------------------------------------------------------------
| AVAILABLE FOUND ITEMS FOR MATCHING
|--------------------------------------------------------------------------
*/
$matchable_found_items = [];

try {

    $match_sql = "
        SELECT
            f.item_id,
            f.item_code,
            f.title,
            f.remarks AS description,
            f.category_id,
            f.location_found,
            f.date_found,
            f.image_path,
            c.category_name,
            s.location_name
        FROM found_items f
        JOIN categories c
            ON f.category_id = c.category_id
        LEFT JOIN storage_locations s
            ON f.storage_location_id = s.location_id
        WHERE f.status = 'Stored'
        ORDER BY f.date_found DESC
    ";

    $matchable_found_items = $pdo
        ->query($match_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $matchable_found_items = [];
}

/*
|--------------------------------------------------------------------------
| MATCHED LOST REPORTS
|--------------------------------------------------------------------------
*/
$matched_lost_reports = [];

try {

    $matched_sql = "
        SELECT
            im.match_id,
            im.match_date,
            im.match_notes,
            l.report_id,
            l.item_title AS lost_item_title,
            l.description AS lost_description,
            l.location_lost,
            l.date_lost,
            c.category_name,
            u.full_name AS reporter_name,
            f.item_id,
            f.item_code,
            f.title AS found_item_title,
            f.image_path,
            f.status AS found_status,
            s.location_name
        FROM item_matches im
        JOIN lost_items l
            ON im.lost_report_id = l.report_id
        JOIN found_items f
            ON im.found_item_id = f.item_id
        JOIN categories c
            ON l.category_id = c.category_id
        LEFT JOIN users u
            ON l.reporter_id = u.user_id
        LEFT JOIN storage_locations s
            ON f.storage_location_id = s.location_id
        WHERE im.status = 'Matched'
        ORDER BY im.match_date DESC
    ";

    $matched_lost_reports = $pdo
        ->query($matched_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $matched_lost_reports = [];
}

/*
|--------------------------------------------------------------------------
| 5. CLAIMS PENDING VERIFICATION
|--------------------------------------------------------------------------
*/
$pending_claims = [];

try {

    $claim_sql = "
        SELECT
            cl.*,

            f.item_id,
            f.item_code,
            f.title,
            f.image_path,
            f.date_found,
            f.expiry_date,

            c.category_name,

            u.full_name AS claimant_name,
            u.phone_number AS claimant_phone,
            u.email AS claimant_email,

            s.location_name

        FROM claims cl

        JOIN found_items f
            ON cl.found_item_id = f.item_id

        JOIN categories c
            ON f.category_id = c.category_id

        JOIN users u
            ON cl.claimant_id = u.user_id

        LEFT JOIN storage_locations s
            ON f.storage_location_id = s.location_id

        WHERE cl.status = 'Pending Verification'

        ORDER BY cl.date_claimed ASC
    ";

    $pending_claims = $pdo
        ->query($claim_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $pending_claims = [];
}


/*
|--------------------------------------------------------------------------
| 6. APPROVED CLAIMS - AWAITING PICKUP
|--------------------------------------------------------------------------
*/
$approved_pickup_claims = [];

try {

    $approved_pickup_sql = "
        SELECT
            cl.*,

            f.item_id,
            f.item_code,
            f.title,
            f.image_path,
            f.date_found,
            f.expiry_date,
            f.status AS found_status,

            c.category_name,

            u.full_name AS claimant_name,
            u.phone_number AS claimant_phone,
            u.email AS claimant_email,

            s.location_name

        FROM claims cl

        JOIN found_items f
            ON cl.found_item_id = f.item_id

        JOIN categories c
            ON f.category_id = c.category_id

        JOIN users u
            ON cl.claimant_id = u.user_id

        LEFT JOIN storage_locations s
            ON f.storage_location_id = s.location_id

        WHERE cl.status = 'Approved'
          AND cl.checkout_date IS NULL
          AND f.status = 'Claim Pending'

        ORDER BY cl.date_processed ASC
    ";

    $approved_pickup_claims = $pdo
        ->query($approved_pickup_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $approved_pickup_claims = [];
}


/*
|--------------------------------------------------------------------------
| 7. CLAIMED ITEMS
|--------------------------------------------------------------------------
*/
$claimed_items = [];

try {

    $claimed_sql = "
        SELECT
            f.*,

            c.category_name,

            cl.claim_id,
            cl.status AS claim_status,
            cl.date_claimed,
            cl.date_processed,

            u.full_name AS claimant_name,
            u.phone_number AS claimant_phone

        FROM found_items f

        JOIN categories c
            ON f.category_id = c.category_id

        LEFT JOIN claims cl
            ON f.item_id = cl.found_item_id
            AND cl.status = 'Approved'

        LEFT JOIN users u
            ON cl.claimant_id = u.user_id

        WHERE f.status = 'Claimed'

        ORDER BY cl.date_processed DESC
    ";

    $claimed_items = $pdo
        ->query($claimed_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $claimed_items = [];
}


/*
|--------------------------------------------------------------------------
| 8. ITEMS EXPIRING WITHIN 30 DAYS
|--------------------------------------------------------------------------
*/
$expiring_items = [];

try {
    $expiring_sql = "
        SELECT
            f.*,
            c.category_name,
            s.location_name,
            u.full_name AS reporter_name
        FROM found_items f
        JOIN categories c
            ON f.category_id = c.category_id
        LEFT JOIN storage_locations s
            ON f.storage_location_id = s.location_id
        LEFT JOIN users u
            ON f.reporter_id = u.user_id
        WHERE f.status = 'Stored'
          AND f.expiry_date IS NOT NULL
          AND f.expiry_date > CURDATE()
          AND f.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY f.expiry_date ASC
    ";
    $expiring_items = $pdo->query($expiring_sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expiring_items = [];
}

/*
|--------------------------------------------------------------------------
| 9. EXPIRED ITEMS
|--------------------------------------------------------------------------
*/
$expired_items = [];

try {

    $expired_sql = "
        SELECT
            f.*,

            c.category_name,
            c.retention_months,

            s.location_name,

            u.full_name AS reporter_name

        FROM found_items f

        JOIN categories c
            ON f.category_id = c.category_id

        LEFT JOIN storage_locations s
            ON f.storage_location_id = s.location_id

        LEFT JOIN users u
            ON f.reporter_id = u.user_id

        WHERE f.status = 'Stored'

        AND f.expiry_date IS NOT NULL

        AND f.expiry_date <= CURDATE()

        ORDER BY f.expiry_date ASC
    ";

    $expired_items = $pdo
        ->query($expired_sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $expired_items = [];
}


/*
|--------------------------------------------------------------------------
| 9. DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/
$count_searching = count($searching_lost_reports);

$count_pending = count($pending_items);

$count_stored = 0;
$count_claimed = 0;
$count_expired = 0;
$count_pending_claims = count($pending_claims);
$count_approved_pickups = count($approved_pickup_claims);

try {

    $count_stored = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM found_items
            WHERE status = 'Stored'
        ")
        ->fetchColumn();

    $count_claimed = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM found_items
            WHERE status = 'Claimed'
        ")
        ->fetchColumn();

    $count_expired = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM found_items
            WHERE status = 'Stored'
            AND expiry_date IS NOT NULL
            AND expiry_date <= CURDATE()
        ")
        ->fetchColumn();

} catch (PDOException $e) {

    $count_stored = 0;
    $count_claimed = 0;
    $count_expired = 0;
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Recorder Dashboard - Resort L&F</title>


    <!-- Bootstrap 5 -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- FontAwesome -->

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        rel="stylesheet"
    >


    <style>

        body {
            background-color: #f4f6f9;
        }

        .sidebar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .stat-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .item-img-preview {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 8px;
        }

        .item-img-large {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border-radius: 10px;
        }

        .section-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }

        .section-header {
            background: white;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .claim-proof {
            max-width: 100px;
            max-height: 70px;
            object-fit: contain;
            border-radius: 6px;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.45rem 0.7rem;
        }
.table-responsive {
            min-height: 80px;
        }

        .utility-link {
            text-decoration: none;
        }

        .utility-card {
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .utility-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 .35rem 1rem rgba(0,0,0,.08) !important;
        }

        .notification-badge {
            font-size: .68rem;
            min-width: 20px;
        }

    </style>

</head>


<body>


<!-- ================================================================
     NAVBAR
================================================================ -->

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">

    <div class="container-fluid px-4">

        <a
            class="navbar-brand sidebar-brand"
            href="#"
        >

            <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>

            Recorder Portal

        </a>


        <div class="d-flex align-items-center ms-auto">

            <span class="text-light me-3 small">

                <i class="fa-solid fa-user-gear me-1 text-primary"></i>

                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Recorder'); ?>

                (<?= htmlspecialchars($_SESSION['user_role'] ?? 'Recorder'); ?>)

            </span>


            <a
                href="notifications.php"
                class="btn btn-outline-light btn-sm me-2 position-relative"
                title="Notifications"
            >
                <i class="fa-solid fa-bell"></i>
                <?php if ($notification_unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                        <?= $notification_unread_count > 99 ? '99+' : $notification_unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>

            <a
                href="storage_overview.php"
                class="btn btn-outline-light btn-sm me-2"
                title="Storage Overview"
            >
                <i class="fa-solid fa-warehouse"></i>
            </a>

            <a
                href="account_security.php"
                class="btn btn-outline-light btn-sm me-2"
                title="Account Security"
            >
                <i class="fa-solid fa-shield-halved"></i>
            </a>

            <a
                href="logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="fa-solid fa-right-from-bracket me-1"></i>

                Logout

            </a>

        </div>

    </div>

</nav>


<!-- ================================================================
     MAIN CONTAINER
================================================================ -->

<div class="container-fluid px-4 py-4">


    <!-- ============================================================
         ALERTS
    ============================================================= -->

    <?php if (isset($_SESSION['success'])): ?>

        <div
            class="alert alert-success alert-dismissible fade show mb-4"
            role="alert"
        >

            <i class="fa-solid fa-circle-check me-2"></i>

            <?= htmlspecialchars($_SESSION['success']); ?>

            <?php unset($_SESSION['success']); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if (isset($_SESSION['error'])): ?>

        <div
            class="alert alert-danger alert-dismissible fade show mb-4"
            role="alert"
        >

            <i class="fa-solid fa-triangle-exclamation me-2"></i>

            <?= htmlspecialchars($_SESSION['error']); ?>

            <?php unset($_SESSION['error']); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- ============================================================
         STATISTICS
    ============================================================= -->

    <div class="row g-3 mb-4">


        <!-- Pending -->

        <div class="col-md-3">

            <div class="card stat-card bg-warning text-dark p-3 shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">
                            Pending Check-in
                        </h6>

                        <h2 class="fw-bold mb-0">
                            <?= $count_pending; ?>
                        </h2>

                    </div>

                    <i class="fa-solid fa-hourglass-half fs-1 opacity-50"></i>

                </div>

            </div>

        </div>


        <!-- Stored -->

        <div class="col-md-3">

            <div class="card stat-card bg-primary text-white p-3 shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">
                            Items Stored
                        </h6>

                        <h2 class="fw-bold mb-0">
                            <?= $count_stored; ?>
                        </h2>

                    </div>

                    <i class="fa-solid fa-warehouse fs-1 opacity-50"></i>

                </div>

            </div>

        </div>


        <!-- Claims -->

        <div class="col-md-3">

            <div class="card stat-card bg-success text-white p-3 shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">
                            Claims Pending
                        </h6>

                        <h2 class="fw-bold mb-0">
                            <?= $count_pending_claims; ?>
                        </h2>

                    </div>

                    <i class="fa-solid fa-user-check fs-1 opacity-50"></i>

                </div>

            </div>

        </div>


        <!-- Expired -->

        <div class="col-md-3">

            <div class="card stat-card bg-danger text-white p-3 shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="text-uppercase fw-semibold mb-1 small opacity-75">
                            Retention Expired
                        </h6>

                        <h2 class="fw-bold mb-0">
                            <?= $count_expired; ?>
                        </h2>

                    </div>

                    <i class="fa-solid fa-bell fs-1 opacity-50"></i>

                </div>

            </div>

        </div>

    </div>


    

<!-- ============================================================
     DASHBOARD TRACKING NAVIGATION
     New tile/card layout. Replaces the old horizontal buttons.
============================================================= -->

<style>
    .tracking-nav {
        background: #ffffff;
        border: 1px solid #e7ebf1;
        border-radius: 18px;
        padding: 16px;
        box-shadow: 0 .35rem 1rem rgba(31, 45, 61, .06);
    }

    .tracking-nav-grid {
        display: flex;
        align-items: stretch;
        gap: 12px;
    }

    .tracking-group {
        flex: 1 1 auto;
        min-width: 0;
    }

    .tracking-group.reports-group {
        flex: 0 0 210px;
    }

    .tracking-group.expiry-group {
        flex: 1 1 300px;
    }

    .tracking-group-label {
        margin: 0 0 8px 4px;
        color: #7b8798;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .tracking-group-items {
        display: flex;
        gap: 8px;
        height: calc(100% - 25px);
    }

    .tracking-link {
        position: relative;
        flex: 1 1 0;
        min-height: 118px;
        padding: 14px 9px 12px;
        border: 1px solid #e7ebf1;
        border-radius: 13px;
        background: #fff;
        color: #344054;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        transition: all .18s ease;
    }

    .tracking-link:hover {
        color: #0d6efd;
        border-color: #b9d2ff;
        transform: translateY(-2px);
        box-shadow: 0 .3rem .8rem rgba(13, 110, 253, .08);
    }

    .tracking-link.active {
        color: #0d6efd;
        border-color: #9ec5fe;
        box-shadow: inset 0 0 0 1px rgba(13, 110, 253, .06);
    }

    .tracking-icon {
        width: 48px;
        height: 48px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 9px;
        font-size: 1.25rem;
    }

    .tracking-link.lost {
        background: linear-gradient(180deg, #f8fbff, #ffffff);
        border-color: #d8e8ff;
    }
    .tracking-link.lost .tracking-icon {
        background: #e7f0ff;
        color: #0d6efd;
    }

    .tracking-link.pending {
        background: linear-gradient(180deg, #fffaf1, #ffffff);
        border-color: #ffe2b8;
    }
    .tracking-link.pending .tracking-icon {
        background: #fff0d9;
        color: #f08c00;
    }

    .tracking-link.stored {
        background: linear-gradient(180deg, #faf8ff, #ffffff);
        border-color: #e5dcff;
    }
    .tracking-link.stored .tracking-icon {
        background: #eee8ff;
        color: #6f42c1;
    }

    .tracking-link.claim {
        background: linear-gradient(180deg, #f4fff9, #ffffff);
        border-color: #ccefe0;
    }
    .tracking-link.claim .tracking-icon {
        background: #def8eb;
        color: #198754;
    }

    .tracking-link.pickup {
        background: linear-gradient(180deg, #f5faff, #ffffff);
        border-color: #d7eaff;
    }
    .tracking-link.pickup .tracking-icon {
        background: #e5f2ff;
        color: #0d6efd;
    }

    .tracking-link.claimed {
        background: linear-gradient(180deg, #f4fff9, #ffffff);
        border-color: #ccefe0;
    }
    .tracking-link.claimed .tracking-icon {
        background: #def8eb;
        color: #198754;
    }

    .tracking-link.expiring {
        background: linear-gradient(180deg, #fff8f1, #ffffff);
        border-color: #ffdcca;
    }
    .tracking-link.expiring .tracking-icon {
        background: #ffe8df;
        color: #f08c00;
    }

    .tracking-link.expired {
        background: linear-gradient(180deg, #fff5f6, #ffffff);
        border-color: #ffd8dd;
    }
    .tracking-link.expired .tracking-icon {
        background: #ffe1e5;
        color: #dc3545;
    }

    .tracking-title {
        display: block;
        font-size: .79rem;
        line-height: 1.2;
        font-weight: 700;
    }

    .tracking-badge {
        position: absolute;
        top: 7px;
        right: 7px;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .66rem;
        font-weight: 800;
        line-height: 1;
    }

    .tracking-badge-danger {
        background: #dc3545;
        color: #fff;
    }

    .tracking-badge-warning {
        background: #ffc107;
        color: #212529;
    }

    .tracking-divider {
        width: 1px;
        background: #e9edf3;
        margin: 20px 0 2px;
        flex: 0 0 1px;
    }

    @media (max-width: 1300px) {
        .tracking-nav-grid {
            flex-wrap: wrap;
        }

        .tracking-group.reports-group {
            flex: 1 1 190px;
        }

        .tracking-group.expiry-group {
            flex: 1 1 100%;
        }

        .tracking-divider {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .tracking-nav {
            padding: 12px;
        }

        .tracking-group-items {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            height: auto;
        }

        .tracking-group.reports-group,
        .tracking-group.expiry-group {
            flex: 1 1 100%;
        }

        .tracking-link {
            min-height: 92px;
        }
    }

    @media (max-width: 480px) {
        .tracking-group-items {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="tracking-nav mb-4">
    <div class="tracking-nav-grid">

        <!-- REPORTS -->
        <div class="tracking-group reports-group">
            <div class="tracking-group-label">Reports</div>
            <div class="tracking-group-items">
                <a href="#lost-reports-section" class="tracking-link lost">
                    <span class="tracking-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <span class="tracking-title">Lost Reports</span>
                </a>
            </div>
        </div>

        <div class="tracking-divider"></div>

        <!-- FOUND ITEMS -->
        <div class="tracking-group">
            <div class="tracking-group-label">Found Items</div>
            <div class="tracking-group-items">
                <a href="#pending-section" class="tracking-link pending">
                    <?php if ($count_pending > 0): ?>
                        <span class="tracking-badge tracking-badge-danger">
                            <?= $count_pending > 99 ? '99+' : $count_pending; ?>
                        </span>
                    <?php endif; ?>
                    <span class="tracking-icon">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </span>
                    <span class="tracking-title">Pending Check-in</span>
                </a>

                <a href="#stored-section" class="tracking-link stored">
                    <span class="tracking-icon">
                        <i class="fa-solid fa-warehouse"></i>
                    </span>
                    <span class="tracking-title">Stored Items</span>
                </a>
            </div>
        </div>

        <div class="tracking-divider"></div>

        <!-- CLAIMS -->
        <div class="tracking-group">
            <div class="tracking-group-label">Claims</div>
            <div class="tracking-group-items">
                <a href="verify_claim.php" class="tracking-link claim">
                    <?php if ($count_pending_claims > 0): ?>
                        <span class="tracking-badge tracking-badge-danger">
                            <?= $count_pending_claims > 99 ? '99+' : $count_pending_claims; ?>
                        </span>
                    <?php endif; ?>
                    <span class="tracking-icon">
                        <i class="fa-solid fa-user-check"></i>
                    </span>
                    <span class="tracking-title">Claim Verification<br>&amp; Check-Out</span>
                </a>

                <a href="#approved-pickup-section" class="tracking-link pickup">
                    <?php if ($count_approved_pickups > 0): ?>
                        <span class="tracking-badge tracking-badge-danger">
                            <?= $count_approved_pickups > 99 ? '99+' : $count_approved_pickups; ?>
                        </span>
                    <?php endif; ?>
                    <span class="tracking-icon">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </span>
                    <span class="tracking-title">Awaiting Pickup</span>
                </a>

                <a href="#claimed-section" class="tracking-link claimed">
                    <span class="tracking-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </span>
                    <span class="tracking-title">Claimed Items</span>
                </a>
            </div>
        </div>

        <div class="tracking-divider"></div>

        <!-- EXPIRY -->
        <div class="tracking-group expiry-group">
            <div class="tracking-group-label">Expiry</div>
            <div class="tracking-group-items">
                <a href="#expiring-section" class="tracking-link expiring">
                    <?php if (count($expiring_items) > 0): ?>
                        <span class="tracking-badge tracking-badge-danger">
                            <?= count($expiring_items) > 99 ? '99+' : count($expiring_items); ?>
                        </span>
                    <?php endif; ?>
                    <span class="tracking-icon">
                        <i class="fa-solid fa-hourglass-end"></i>
                    </span>
                    <span class="tracking-title">Expiring Soon</span>
                </a>

                <a href="#expired-section" class="tracking-link expired">
                    <?php if ($count_expired > 0): ?>
                        <span class="tracking-badge tracking-badge-danger">
                            <?= $count_expired > 99 ? '99+' : $count_expired; ?>
                        </span>
                    <?php endif; ?>
                    <span class="tracking-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </span>
                    <span class="tracking-title">Expired Items</span>
                </a>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================
     1. LOST ITEM REPORTS - SEARCHING
============================================================= -->

<div
    class="card section-card shadow-sm mb-4"
    id="lost-reports-section"
>

    <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

        <h5 class="fw-bold mb-0 text-secondary">

            <i class="fa-solid fa-magnifying-glass text-warning me-2"></i>

            Lost Item Reports - Searching

        </h5>


        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">

            <?= $count_searching; ?>

            Searching

        </span>

    </div>


    <div class="card-body p-0">

        <?php if (empty($searching_lost_reports)): ?>

            <div class="text-center py-5">

                <i class="fa-solid fa-circle-check fs-1 text-success mb-3 d-block"></i>

                <h6 class="text-muted fw-normal">

                    No lost item reports are currently searching.

                </h6>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="ps-3">
                                Report ID
                            </th>

                            <th>
                                Lost Item
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Location Lost
                            </th>

                            <th>
                                Date Lost
                            </th>

                            <th>
                                Reporter
                            </th>

                            <th class="text-end pe-3">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($searching_lost_reports as $lost): ?>

                            <tr>

                                <!-- REPORT ID -->
                                <td class="ps-3">

                                    <span class="badge bg-secondary font-monospace">

                                        #<?= (int)$lost['report_id']; ?>

                                    </span>

                                </td>


                                <!-- LOST ITEM -->
                                <td>

                                    <strong class="d-block text-dark">

                                        <?= htmlspecialchars(
                                            $lost['item_title']
                                        ); ?>

                                    </strong>


                                    <?php if (!empty($lost['description'])): ?>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                mb_strimwidth(
                                                    $lost['description'],
                                                    0,
                                                    80,
                                                    '...'
                                                )
                                            ); ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <!-- CATEGORY -->
                                <td>

                                    <span class="badge bg-info text-dark">

                                        <?= htmlspecialchars(
                                            $lost['category_name']
                                        ); ?>

                                    </span>

                                </td>


                                <!-- LOCATION LOST -->
                                <td>

                                    <i class="fa-solid fa-location-dot text-danger me-1"></i>

                                    <?= htmlspecialchars(
                                        $lost['location_lost']
                                    ); ?>

                                </td>


                                <!-- DATE LOST -->
                                <td>

                                    <?php if (!empty($lost['date_lost'])): ?>

                                        <?= date(
                                            'M d, Y',
                                            strtotime($lost['date_lost'])
                                        ); ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>


                                <!-- REPORTER -->
                                <td>

                                    <strong class="d-block">

                                        <?= htmlspecialchars(
                                            $lost['reporter_name'] ?? '-'
                                        ); ?>

                                    </strong>


                                    <?php if (!empty($lost['reporter_phone'])): ?>

                                        <small class="text-muted">

                                            <i class="fa-solid fa-phone me-1"></i>

                                            <?= htmlspecialchars(
                                                $lost['reporter_phone']
                                            ); ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTION -->
                                <td class="text-end pe-3">

                                    <button
    type="button"
    class="btn btn-success btn-sm px-3 fw-bold"
    onclick="
        (function(btn) {
            var modal = document.getElementById('matchFoundItemModal');
            if (!modal) {
                alert('Match modal is missing from this page.');
                return;
            }

            var reportId = btn.getAttribute('data-reportid') || '';
            var reportTitle = btn.getAttribute('data-reporttitle') || 'Lost Item';
            var categoryId = btn.getAttribute('data-categoryid') || '';

            var row = btn.closest('tr');
            var categoryBadge = row ? row.querySelector('.badge.bg-info') : null;
            var categoryName = categoryBadge ? categoryBadge.textContent.trim() : 'Category';

            var reportIdInput = document.getElementById('match_report_id');
            var reportTitleEl = document.getElementById('match_report_title');
            var reportCategoryEl = document.getElementById('match_report_category');
            var select = document.getElementById('found_item_id');
            var confirm = document.getElementById('confirmMatchButton');
            if (reportIdInput) reportIdInput.value = reportId;
            if (reportTitleEl) reportTitleEl.textContent = 'Report #' + reportId + ' - ' + reportTitle;
            if (reportCategoryEl) reportCategoryEl.textContent = categoryName;

            if (select) {
                select.value = '';

                Array.from(select.options).forEach(function(option) {
                    option.hidden = false;
                });
            }

            if (confirm) confirm.disabled = true;
            modal.classList.add('show');
            modal.style.display = 'block';
            modal.removeAttribute('aria-hidden');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('role', 'dialog');

            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';

            var oldBackdrop = document.getElementById('manualMatchModalBackdrop');
            if (oldBackdrop) oldBackdrop.remove();

            var backdrop = document.createElement('div');
            backdrop.id = 'manualMatchModalBackdrop';
            backdrop.className = 'modal-backdrop fade show';
            backdrop.onclick = function() {
                var closeBtn = document.getElementById('closeMatchModalButton');
                if (closeBtn) closeBtn.click();
            };
            document.body.appendChild(backdrop);
        })(this);
        return false;
    "
    data-reportid="<?= (int)$lost['report_id']; ?>"
    data-reporttitle="<?= htmlspecialchars(
        $lost['item_title'],
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
    data-categoryid="<?= (int)$lost['category_id']; ?>"
>
    <i class="fa-solid fa-link me-1"></i>
    Match Found Item
</button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>
<!-- ============================================================
         MATCHED LOST REPORTS
    ============================================================= -->

    <div
        class="card section-card shadow-sm mb-4"
        id="matched-reports-section"
    >

        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

            <h5 class="fw-bold mb-0 text-secondary">
                <i class="fa-solid fa-link text-success me-2"></i>
                Matched Lost Reports
            </h5>

            <span class="badge bg-success px-3 py-2 rounded-pill">
                <?= count($matched_lost_reports); ?> Matched
            </span>

        </div>

        <div class="card-body p-0">

            <?php if (empty($matched_lost_reports)): ?>

                <div class="text-center py-5">
                    <i class="fa-solid fa-link-slash fs-1 text-muted mb-3 d-block"></i>
                    <h6 class="text-muted fw-normal">
                        No lost reports have been matched yet.
                    </h6>
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Report</th>
                                <th>Lost Item</th>
                                <th>Matched Found Item</th>
                                <th>Storage</th>
                                <th>Reporter</th>
                                <th>Matched On</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($matched_lost_reports as $match): ?>

                                <tr>

                                    <td class="ps-3">
                                        <span class="badge bg-secondary font-monospace">
                                            #<?= (int)$match['report_id']; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong class="d-block">
                                            <?= htmlspecialchars(
                                                $match['lost_item_title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </strong>
                                        <small class="text-muted">
                                            <?= htmlspecialchars(
                                                $match['category_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <strong class="d-block">
                                            <?= htmlspecialchars(
                                                $match['found_item_title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </strong>
                                        <span class="badge bg-secondary font-monospace">
                                            <?= htmlspecialchars(
                                                $match['item_code'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (!empty($match['location_name'])): ?>
                                            <span class="badge bg-primary">
                                                <i class="fa-solid fa-location-dot me-1"></i>
                                                <?= htmlspecialchars(
                                                    $match['location_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                Not Assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $match['reporter_name'] ?? '-',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= !empty($match['match_date'])
                                            ? date(
                                                'M d, Y H:i',
                                                strtotime($match['match_date'])
                                            )
                                            : '-'; ?>
                                    </td>

                                    <td>
                                        <?php if ($match['found_status'] === 'Stored'): ?>
                                            <span class="badge bg-success status-badge">
                                                <i class="fa-solid fa-hand-pointer me-1"></i>
                                                Waiting for Claim
                                            </span>
                                        <?php elseif ($match['found_status'] === 'Claim Pending'): ?>
                                            <span class="badge bg-warning text-dark status-badge">
                                                <i class="fa-solid fa-clock me-1"></i>
                                                Claim Pending
                                            </span>
                                        <?php elseif ($match['found_status'] === 'Claimed'): ?>
                                            <span class="badge bg-primary status-badge">
                                                <i class="fa-solid fa-check me-1"></i>
                                                Claimed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary status-badge">
                                                <?= htmlspecialchars(
                                                    $match['found_status'] ?? '-',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


<!-- ============================================================
         1. PENDING CHECK-IN
    ============================================================= -->

    <div
        class="card section-card shadow-sm mb-4"
        id="pending-section"
    >

        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

            <h5 class="fw-bold mb-0 text-secondary">

                <i class="fa-solid fa-dolly text-warning me-2"></i>

                Items Awaiting Storage Check-in

            </h5>


            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">

                <?= $count_pending; ?>

                Items Requiring Action

            </span>

        </div>


        <div class="card-body p-0">

            <?php if (empty($pending_items)): ?>

                <div class="text-center py-5">

                    <i class="fa-solid fa-box-open fs-1 text-muted mb-3 d-block"></i>

                    <h6 class="text-muted fw-normal">

                        No items currently waiting for check-in.

                    </h6>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="ps-3">Photo</th>

                                <th>Item Code & Title</th>

                                <th>Category</th>

                                <th>Found Location</th>

                                <th>Date Found / Expiry</th>

                                <th>Reported By</th>

                                <th class="text-end pe-3">Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($pending_items as $item): ?>

                                <tr>


                                    <!-- PHOTO -->

                                    <td class="ps-3">

                                        <?php

                                        $imagePath = getImagePath(
                                            $item['image_path'] ?? ''
                                        );

                                        ?>


                                        <?php if ($imagePath !== ''): ?>

                                            <a
                                                href="#"
                                                data-bs-toggle="modal"
                                                data-bs-target="#itemImageModal"
                                                onclick="showItemImage('<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>'); return false;"
                                                title="Click to view full-size photo"
                                            >

                                                <img
                                                    src="<?= htmlspecialchars($imagePath); ?>"
                                                    alt="Item"
                                                    class="item-img-preview border"
                                                    style="cursor:pointer;"
                                                    onerror="this.parentElement.style.display='none';"
                                                >

                                            </a>

                                        <?php else: ?>

                                            <div class="text-muted small">

                                                <i class="fa-solid fa-image me-1"></i>

                                                No Photo

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ITEM -->

                                    <td>

                                        <strong class="d-block text-dark">

                                            <?= htmlspecialchars($item['title']); ?>

                                        </strong>


                                        <span class="badge bg-secondary font-monospace">

                                            <?= htmlspecialchars($item['item_code']); ?>

                                        </span>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="badge bg-info text-dark">

                                            <?= htmlspecialchars($item['category_name']); ?>

                                        </span>


                                        <small class="d-block text-muted">

                                            <?= htmlspecialchars($item['retention_months']); ?>

                                            Months Retention

                                        </small>

                                    </td>


                                    <!-- FOUND LOCATION -->

                                    <td>

                                        <small>

                                            <i class="fa-solid fa-location-dot text-danger me-1"></i>

                                            <?= htmlspecialchars($item['location_found']); ?>

                                        </small>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <div class="small fw-semibold">

                                            <?= date(
                                                'M d, Y',
                                                strtotime($item['date_found'])
                                            ); ?>

                                        </div>


                                        <small class="text-danger">

                                            Expires:

                                            <?= date(
                                                'M d, Y',
                                                strtotime($item['expiry_date'])
                                            ); ?>

                                        </small>

                                    </td>


                                    <!-- REPORTER -->

                                    <td>

                                        <div class="small fw-bold">

                                            <?= htmlspecialchars($item['reporter_name']); ?>

                                        </div>


                                        <small class="text-muted">

                                            <i class="fa-solid fa-phone me-1"></i>

                                            <?= htmlspecialchars($item['reporter_phone']); ?>

                                        </small>

                                    </td>


                                    <!-- ACTION -->

                                    <td class="text-end pe-3">

                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm px-3 shadow-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#checkInModal"
                                            data-itemid="<?= (int)$item['item_id']; ?>"
                                            data-itemcode="<?= htmlspecialchars($item['item_code'], ENT_QUOTES); ?>"
                                            data-itemtitle="<?= htmlspecialchars($item['title'], ENT_QUOTES); ?>"
                                        >

                                            <i class="fa-solid fa-boxes-packing me-1"></i>

                                            Assign Storage

                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ============================================================
         2. STORED ITEMS
    ============================================================= -->

    <div
        class="card section-card shadow-sm mb-4"
        id="stored-section"
    >

        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

            <h5 class="fw-bold mb-0 text-secondary">

                <i class="fa-solid fa-warehouse text-primary me-2"></i>

                Items Currently in Storage

            </h5>


            <span class="badge bg-primary px-3 py-2 rounded-pill">

                <?= $count_stored; ?> Stored

            </span>

        </div>


        <div class="card-body p-0">

            <?php if (empty($stored_items)): ?>

                <div class="text-center py-5">

                    <i class="fa-solid fa-warehouse fs-1 text-muted mb-3 d-block"></i>

                    <h6 class="text-muted fw-normal">

                        No items are currently stored.

                    </h6>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="ps-3">Photo</th>

                                <th>Item</th>

                                <th>Category</th>

                                <th>Storage Location</th>

                                <th>Date Found</th>

                                <th>Expiry</th>

                                <th>Reporter</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($stored_items as $item): ?>

                                <?php

                                $imagePath = getImagePath(
                                    $item['image_path'] ?? ''
                                );

                                $isExpired =
                                    !empty($item['expiry_date']) &&
                                    strtotime($item['expiry_date']) <= strtotime(date('Y-m-d'));

                                ?>


                                <tr>


                                    <!-- PHOTO -->

                                    <td class="ps-3">

                                        <?php if ($imagePath !== ''): ?>

                                            <a
                                                href="#"
                                                data-bs-toggle="modal"
                                                data-bs-target="#itemImageModal"
                                                onclick="showItemImage('<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>'); return false;"
                                                title="Click to view full-size photo"
                                            >
                                                <img
                                                    src="<?= htmlspecialchars($imagePath); ?>"
                                                    class="item-img-preview border"
                                                    alt="Item"
                                                    style="cursor:pointer;"
                                                    onerror="this.parentElement.style.display='none';"
                                                >

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                No Photo
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ITEM -->

                                    <td>

                                        <strong class="d-block">

                                            <?= htmlspecialchars($item['title']); ?>

                                        </strong>

                                        <span class="badge bg-secondary font-monospace">

                                            <?= htmlspecialchars($item['item_code']); ?>

                                        </span>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="badge bg-info text-dark">

                                            <?= htmlspecialchars($item['category_name']); ?>

                                        </span>

                                    </td>


                                    <!-- STORAGE -->

                                    <td>

                                        <?php if (!empty($item['location_name'])): ?>

                                            <div class="fw-semibold">

                                                <i class="fa-solid fa-location-dot text-primary me-1"></i>

                                                <?= htmlspecialchars($item['location_name']); ?>

                                            </div>


                                            <?php if (!empty($item['storage_description'])): ?>

                                                <small class="text-muted">

                                                    <?= htmlspecialchars($item['storage_description']); ?>

                                                </small>

                                            <?php endif; ?>

                                        <?php else: ?>

                                            <span class="text-danger small">

                                                Storage Not Assigned

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- DATE FOUND -->

                                    <td>

                                        <?= !empty($item['date_found'])
                                            ? date('M d, Y', strtotime($item['date_found']))
                                            : '-'; ?>

                                    </td>


                                    <!-- EXPIRY -->

                                    <td>

                                        <?php if (!empty($item['expiry_date'])): ?>

                                            <span
                                                class="<?= $isExpired ? 'text-danger fw-bold' : 'text-muted'; ?>"
                                            >

                                                <?= date(
                                                    'M d, Y',
                                                    strtotime($item['expiry_date'])
                                                ); ?>

                                            </span>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <!-- REPORTER -->

                                    <td>

                                        <div class="small fw-semibold">

                                            <?= htmlspecialchars($item['reporter_name'] ?? '-'); ?>

                                        </div>

                                        <small class="text-muted">

                                            <?= htmlspecialchars($item['reporter_phone'] ?? ''); ?>

                                        </small>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php if ($isExpired): ?>

                                            <span class="badge bg-danger status-badge">

                                                <i class="fa-solid fa-clock me-1"></i>

                                                Retention Expired

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-primary status-badge">

                                                <i class="fa-solid fa-box me-1"></i>

                                                Stored

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ============================================================
         3. CLAIM VERIFICATION
    ============================================================= -->

    <div
        class="card section-card shadow-sm mb-4"
        id="claims-section"
    >

        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

            <h5 class="fw-bold mb-0 text-secondary">

                <i class="fa-solid fa-user-check text-success me-2"></i>

                Claims Pending Verification

            </h5>


            <div>

                <span class="badge bg-success px-3 py-2 rounded-pill me-2">

                    <?= $count_pending_claims; ?> Pending

                </span>


                <?php if ($count_pending_claims > 0): ?>

                    <a
                        href="verify_claim.php"
                        class="btn btn-success btn-sm"
                    >

                        <i class="fa-solid fa-list-check me-1"></i>

                        Review Claims

                    </a>

                <?php endif; ?>

            </div>

        </div>


        <div class="card-body p-0">

            <?php if (empty($pending_claims)): ?>

                <div class="text-center py-5">

                    <i class="fa-solid fa-circle-check fs-1 text-success mb-3 d-block"></i>

                    <h6 class="text-muted fw-normal">

                        No claims are waiting for verification.

                    </h6>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="ps-3">Item</th>

                                <th>Claimant</th>

                                <th>Contact</th>

                                <th>Storage</th>

                                <th>Proof Notes</th>

                                <th>Date Claimed</th>

                                <th class="text-end pe-3">Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($pending_claims as $claim): ?>

                                <?php

                                $imagePath = getImagePath(
                                    $claim['image_path'] ?? ''
                                );

                                ?>


                                <tr>


                                    <!-- ITEM -->

                                    <td class="ps-3">

                                        <div class="d-flex align-items-center gap-2">

                                            <?php if ($imagePath !== ''): ?>

                                                <a
                                                    href="#"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#itemImageModal"
                                                    onclick="showItemImage('<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>'); return false;"
                                                    title="Click to view full-size photo"
                                                >

                                                    <img
                                                        src="<?= htmlspecialchars($imagePath); ?>"
                                                        class="item-img-preview border"
                                                        alt="Item"
                                                        style="cursor:pointer;"
                                                        onerror="this.parentElement.style.display='none';"
                                                    >

                                                </a>

                                            <?php endif; ?>


                                            <div>

                                                <strong class="d-block">

                                                    <?= htmlspecialchars($claim['title']); ?>

                                                </strong>


                                                <span class="badge bg-secondary font-monospace">

                                                    <?= htmlspecialchars($claim['item_code']); ?>

                                                </span>


                                                <small class="d-block text-muted">

                                                    <?= htmlspecialchars($claim['category_name']); ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- CLAIMANT -->

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars($claim['claimant_name']); ?>

                                        </strong>

                                    </td>


                                    <!-- CONTACT -->

                                    <td>

                                        <div class="small">

                                            <i class="fa-solid fa-phone me-1"></i>

                                            <?= htmlspecialchars($claim['claimant_phone']); ?>

                                        </div>


                                        <?php if (!empty($claim['claimant_email'])): ?>

                                            <div class="small text-muted">

                                                <i class="fa-solid fa-envelope me-1"></i>

                                                <?= htmlspecialchars($claim['claimant_email']); ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STORAGE -->

                                    <td>

                                        <span class="badge bg-primary">

                                            <i class="fa-solid fa-location-dot me-1"></i>

                                            <?= htmlspecialchars(
                                                $claim['location_name'] ?? 'Not Assigned'
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- PROOF -->

                                    <td>

                                        <?php if (!empty($claim['proof_notes'])): ?>

                                            <div
                                                class="small"
                                                style="max-width:220px;"
                                            >

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $claim['proof_notes']
                                                    )
                                                ); ?>

                                            </div>

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                No proof notes
                                            </span>

                                        <?php endif; ?>


                                        <?php if (!empty($claim['proof_image_path'])): ?>

                                            <?php
                                            $proofPath = getImagePath(
                                                $claim['proof_image_path']
                                            );
                                            ?>


                                            <a
                                                href="#"
                                                data-bs-toggle="modal"
                                                data-bs-target="#itemImageModal"
                                                onclick="showItemImage('<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>'); return false;"
                                                title="Click to view full-size photo"
                                            >

                                                <i class="fa-solid fa-file-image me-1"></i>

                                                View Proof

                                            </a>

                                        <?php endif; ?>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <small>

                                            <?= !empty($claim['date_claimed'])
                                                ? date(
                                                    'M d, Y H:i',
                                                    strtotime($claim['date_claimed'])
                                                )
                                                : '-'; ?>

                                        </small>

                                    </td>


                                    <!-- ACTION -->

                                    <td class="text-end pe-3">

                                        <a
                                            href="verify_claim.php"
                                            class="btn btn-success btn-sm"
                                        >

                                            <i class="fa-solid fa-magnifying-glass me-1"></i>

                                            Review

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ============================================================
         4. APPROVED CLAIMS - AWAITING PICKUP
    ============================================================= -->

    <div
        class="card section-card shadow-sm mb-4"
        id="approved-pickup-section"
    >

        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

            <h5 class="fw-bold mb-0 text-secondary">
                <i class="fa-solid fa-hand-holding-heart text-warning me-2"></i>
                Approved Claims - Awaiting Pickup
            </h5>

            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                <?= $count_approved_pickups; ?> Awaiting Pickup
            </span>

        </div>


        <div class="card-body p-0">

            <?php if (empty($approved_pickup_claims)): ?>

                <div class="text-center py-5">

                    <i class="fa-solid fa-circle-check fs-1 text-success mb-3 d-block"></i>

                    <h6 class="text-muted fw-normal">
                        No approved claims are waiting for pickup.
                    </h6>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="ps-3">Photo</th>
                                <th>Item</th>
                                <th>Claimant</th>
                                <th>Storage</th>
                                <th>Approved On</th>
                                <th class="text-end pe-3">Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($approved_pickup_claims as $claim): ?>

                                <?php
                                $imagePath = getImagePath(
                                    $claim['image_path'] ?? ''
                                );
                                ?>

                                <tr>

                                    <!-- PHOTO -->
                                    <td class="ps-3">

                                        <?php if ($imagePath !== ''): ?>

                                            <a
                                                href="#"
                                                data-bs-toggle="modal"
                                                data-bs-target="#itemImageModal"
                                                onclick="showItemImage('<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>'); return false;"
                                                title="Click to view full-size photo"
                                            >
                                                <img
                                                    src="<?= htmlspecialchars($imagePath); ?>"
                                                    class="item-img-preview border"
                                                    alt="Item"
                                                    style="cursor:pointer;"
                                                    onerror="this.parentElement.style.display='none';"
                                                >
                                            </a>

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                No Photo
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ITEM -->
                                    <td>

                                        <strong class="d-block">
                                            <?= htmlspecialchars($claim['title']); ?>
                                        </strong>

                                        <span class="badge bg-secondary font-monospace">
                                            <?= htmlspecialchars($claim['item_code']); ?>
                                        </span>

                                        <small class="d-block text-muted">
                                            <?= htmlspecialchars($claim['category_name']); ?>
                                        </small>

                                    </td>


                                    <!-- CLAIMANT -->
                                    <td>

                                        <strong class="d-block">
                                            <?= htmlspecialchars($claim['claimant_name']); ?>
                                        </strong>

                                        <?php if (!empty($claim['claimant_phone'])): ?>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-phone me-1"></i>
                                                <?= htmlspecialchars($claim['claimant_phone']); ?>
                                            </small>
                                        <?php endif; ?>

                                    </td>


                                    <!-- STORAGE -->
                                    <td>

                                        <span class="badge bg-primary">
                                            <i class="fa-solid fa-location-dot me-1"></i>
                                            <?= htmlspecialchars(
                                                $claim['location_name'] ?? 'Not Assigned'
                                            ); ?>
                                        </span>

                                    </td>


                                    <!-- APPROVED -->
                                    <td>

                                        <small>
                                            <?= !empty($claim['date_processed'])
                                                ? date(
                                                    'M d, Y H:i',
                                                    strtotime($claim['date_processed'])
                                                )
                                                : '-'; ?>
                                        </small>

                                        <span class="badge bg-success d-block mt-1" style="width:max-content;">
                                            <i class="fa-solid fa-check me-1"></i>
                                            Approved - Awaiting Pickup
                                        </span>

                                    </td>


                                    <!-- ACTION -->
                                    <td class="text-end pe-3">

                                        <a
                                            href="verify_claim.php"
                                            class="btn btn-warning btn-sm fw-bold"
                                        >
                                            <i class="fa-solid fa-hand-holding-heart me-1"></i>
                                            Mark as Picked Up
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ============================================================
         5. CLAIMED ITEMS
    ============================================================= -->

    <div
        class="card section-card shadow-sm mb-4"
        id="claimed-section"
    >

        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

            <h5 class="fw-bold mb-0 text-secondary">

                <i class="fa-solid fa-circle-check text-success me-2"></i>

                Claimed Items

            </h5>


            <span class="badge bg-success px-3 py-2 rounded-pill">

                <?= $count_claimed; ?> Claimed

            </span>

        </div>


        <div class="card-body p-0">

            <?php if (empty($claimed_items)): ?>

                <div class="text-center py-5">

                    <i class="fa-solid fa-box-open fs-1 text-muted mb-3 d-block"></i>

                    <h6 class="text-muted fw-normal">

                        No claimed items yet.

                    </h6>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="ps-3">Photo</th>

                                <th>Item</th>

                                <th>Category</th>

                                <th>Claimant</th>

                                <th>Contact</th>

                                <th>Date Claimed</th>

                                <th>Processed</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($claimed_items as $item): ?>

                                <?php

                                $imagePath = getImagePath(
                                    $item['image_path'] ?? ''
                                );

                                ?>


                                <tr>


                                    <!-- PHOTO -->

                                    <td class="ps-3">

                                        <?php if ($imagePath !== ''): ?>

                                            <a
                                                href="#"
                                                data-bs-toggle="modal"
                                                data-bs-target="#itemImageModal"
                                                onclick="showItemImage('<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>'); return false;"
                                                title="Click to view full-size photo"
                                            >
                                                <img
                                                    src="<?= htmlspecialchars($imagePath); ?>"
                                                    class="item-img-preview border"
                                                    alt="Item"
                                                    style="cursor:pointer;"
                                                    onerror="this.parentElement.style.display='none';"
                                                >

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                No Photo
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ITEM -->

                                    <td>

                                        <strong class="d-block">

                                            <?= htmlspecialchars($item['title']); ?>

                                        </strong>


                                        <span class="badge bg-secondary font-monospace">

                                            <?= htmlspecialchars($item['item_code']); ?>

                                        </span>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="badge bg-info text-dark">

                                            <?= htmlspecialchars($item['category_name']); ?>

                                        </span>

                                    </td>


                                    <!-- CLAIMANT -->

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $item['claimant_name'] ?? '-'
                                            ); ?>

                                        </strong>

                                    </td>


                                    <!-- CONTACT -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $item['claimant_phone'] ?? '-'
                                        ); ?>

                                    </td>


                                    <!-- CLAIMED -->

                                    <td>

                                        <small>

                                            <?= !empty($item['date_claimed'])
                                                ? date(
                                                    'M d, Y H:i',
                                                    strtotime($item['date_claimed'])
                                                )
                                                : '-'; ?>

                                        </small>

                                    </td>


                                    <!-- PROCESSED -->

                                    <td>

                                        <small>

                                            <?= !empty($item['date_processed'])
                                                ? date(
                                                    'M d, Y H:i',
                                                    strtotime($item['date_processed'])
                                                )
                                                : '-'; ?>

                                        </small>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span class="badge bg-success status-badge">

                                            <i class="fa-solid fa-check me-1"></i>

                                            Claimed

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ============================================================
         5. EXPIRED ITEMS
    ============================================================= -->
    <!-- ============================================================
         EXPIRY ALERTS - ITEMS EXPIRING WITHIN 30 DAYS
    ============================================================= -->

    <div class="card section-card shadow-sm mb-4" id="expiring-section">
        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-secondary">
                <i class="fa-solid fa-hourglass-end text-warning me-2"></i>
                Items Expiring Within 30 Days
            </h5>
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                <?= count($expiring_items); ?> Upcoming
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (empty($expiring_items)): ?>
                <div class="text-center py-4">
                    <i class="fa-solid fa-circle-check fs-2 text-success mb-2 d-block"></i>
                    <span class="text-muted">No stored items are expiring within the next 30 days.</span>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Item</th>
                                <th>Category</th>
                                <th>Storage</th>
                                <th>Expiry Date</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiring_items as $item): ?>
                                <?php
                                    $daysRemaining = max(
                                        0,
                                        (int)floor(
                                            (strtotime($item['expiry_date']) - strtotime(date('Y-m-d'))) / 86400
                                        )
                                    );
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <strong class="d-block"><?= htmlspecialchars($item['title']); ?></strong>
                                        <span class="badge bg-secondary font-monospace">
                                            <?= htmlspecialchars($item['item_code']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= htmlspecialchars($item['category_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($item['location_name'] ?? 'Not Assigned'); ?>
                                    </td>
                                    <td class="fw-semibold text-warning">
                                        <?= date('M d, Y', strtotime($item['expiry_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            <?= $daysRemaining; ?> day<?= $daysRemaining === 1 ? '' : 's'; ?> left
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card section-card shadow-sm mb-4" id="expired-section">

        <div class="card-header section-header py-3 d-flex justify-content-between align-items-center">

            <h5 class="fw-bold mb-0 text-secondary">

                <i class="fa-solid fa-clock-rotate-left text-danger me-2"></i>

                Retention Expired Items

            </h5>


            <span class="badge bg-danger px-3 py-2 rounded-pill">

                <?= $count_expired; ?> Expired

            </span>

        </div>


        <div class="card-body p-0">

            <?php if (empty($expired_items)): ?>

                <div class="text-center py-5">

                    <i class="fa-solid fa-calendar-check fs-1 text-success mb-3 d-block"></i>

                    <h6 class="text-muted fw-normal">

                        No items have exceeded their retention period.

                    </h6>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="ps-3">Photo</th>

                                <th>Item</th>

                                <th>Category</th>

                                <th>Storage Location</th>

                                <th>Date Found</th>

                                <th>Expired On</th>

                                <th>Reported By</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($expired_items as $item): ?>

                                <?php

                                $imagePath = getImagePath(
                                    $item['image_path'] ?? ''
                                );

                                ?>


                                <tr>


                                    <!-- PHOTO -->

                                    <td class="ps-3">

                                        <?php if ($imagePath !== ''): ?>

                                            <a
                                                href="#"
                                                data-bs-toggle="modal"
                                                data-bs-target="#itemImageModal"
                                                onclick="showItemImage('<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>'); return false;"
                                                title="Click to view full-size photo"
                                            >
                                                <img
                                                    src="<?= htmlspecialchars($imagePath); ?>"
                                                    class="item-img-preview border"
                                                    alt="Item"
                                                    style="cursor:pointer;"
                                                    onerror="this.parentElement.style.display='none';"
                                                >

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                No Photo
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ITEM -->

                                    <td>

                                        <strong class="d-block">

                                            <?= htmlspecialchars($item['title']); ?>

                                        </strong>


                                        <span class="badge bg-secondary font-monospace">

                                            <?= htmlspecialchars($item['item_code']); ?>

                                        </span>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="badge bg-info text-dark">

                                            <?= htmlspecialchars($item['category_name']); ?>

                                        </span>


                                        <small class="d-block text-muted">

                                            <?= htmlspecialchars($item['retention_months']); ?>

                                            months

                                        </small>

                                    </td>


                                    <!-- STORAGE -->

                                    <td>

                                        <?php if (!empty($item['location_name'])): ?>

                                            <span class="badge bg-primary">

                                                <i class="fa-solid fa-location-dot me-1"></i>

                                                <?= htmlspecialchars(
                                                    $item['location_name']
                                                ); ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                Not Assigned
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- DATE FOUND -->

                                    <td>

                                        <?= !empty($item['date_found'])
                                            ? date(
                                                'M d, Y',
                                                strtotime($item['date_found'])
                                            )
                                            : '-'; ?>

                                    </td>


                                    <!-- EXPIRY -->

                                    <td>

                                        <span class="text-danger fw-bold">

                                            <?= !empty($item['expiry_date'])
                                                ? date(
                                                    'M d, Y',
                                                    strtotime($item['expiry_date'])
                                                )
                                                : '-'; ?>

                                        </span>

                                    </td>


                                    <!-- REPORTER -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $item['reporter_name'] ?? '-'
                                        ); ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span class="badge bg-danger status-badge">

                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>

                                            Retention Expired

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


</div>



<!-- ================================================================
     MATCH FOUND ITEM MODAL
================================================================ -->

<div
    class="modal fade"
    id="matchFoundItemModal"
    tabindex="-1"
    aria-hidden="true"
    style="display:none;"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">

                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-link me-2"></i>
                    Match Lost Report with Found Item
                </h5>

                <button
                    type="button"
                    id="closeMatchModalButton"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    onclick="
                        var m=document.getElementById('matchFoundItemModal');
                        if(m){m.classList.remove('show');m.style.display='none';m.setAttribute('aria-hidden','true');}
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow='';
                        var b=document.getElementById('manualMatchModalBackdrop');
                        if(b)b.remove();
                    "
                ></button>

            </div>

            <form action="recorder_dashboard.php" method="POST">

                <div class="modal-body p-4">

                    <input
                        type="hidden"
                        name="action"
                        value="match_found_item"
                    >

                    <input
                        type="hidden"
                        name="report_id"
                        id="match_report_id"
                    >

                    <div class="alert alert-light border mb-4">

                        <small class="text-muted d-block">
                            Lost Report
                        </small>

                        <strong
                            id="match_report_title"
                            class="text-dark d-block"
                        >
                            ---
                        </strong>

                        <span
                            id="match_report_category"
                            class="badge bg-info text-dark mt-1"
                        >
                            ---
                        </span>

                    </div>

                    <div class="mb-3">

                        <label
                            for="found_item_id"
                            class="form-label fw-bold"
                        >
                            Select Found Item
                            <span class="text-danger">*</span>
                        </label>

                        <select
                            class="form-select"
                            id="found_item_id"
                            name="found_item_id"
                            required
                        >

                            <option value="" selected disabled>
                                -- Select any stored found item --
                            </option>

                            <?php foreach ($matchable_found_items as $foundItem): ?>

                                <option
                                    value="<?= (int)$foundItem['item_id']; ?>"
                                    data-category-id="<?= (int)$foundItem['category_id']; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $foundItem['item_code'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                    -
                                    <?= htmlspecialchars(
                                        $foundItem['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                    |
                                    <?= htmlspecialchars(
                                        $foundItem['category_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                    |
                                    <?= htmlspecialchars(
                                        $foundItem['location_name'] ?? 'Storage not assigned',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <?php if (empty($matchable_found_items)): ?>

                            <div class="alert alert-info mt-2 mb-0">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                There are currently no stored found items available for matching.
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="mb-3">

                        <label
                            for="match_notes"
                            class="form-label fw-bold"
                        >
                            Match Notes
                            <span class="text-muted fw-normal">(Optional)</span>
                        </label>

                        <textarea
                            class="form-control"
                            id="match_notes"
                            name="match_notes"
                            rows="3"
                            maxlength="1000"
                            placeholder="Explain why this found item appears to match the lost report..."
                        ></textarea>

                    </div>

                    <div class="alert alert-warning mb-0">

                        <i class="fa-solid fa-circle-exclamation me-1"></i>

                        <strong>Important:</strong>
                        Matching does not mark the found item as claimed.
                        It remains <strong>Stored</strong> until the claimant
                        submits a claim.

                    </div>

                </div>

                <div class="modal-footer bg-light">

                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        data-bs-dismiss="modal"
                        onclick="
                            var m=document.getElementById('matchFoundItemModal');
                            if(m){m.classList.remove('show');m.style.display='none';m.setAttribute('aria-hidden','true');}
                            document.body.classList.remove('modal-open');
                            document.body.style.overflow='';
                            var b=document.getElementById('manualMatchModalBackdrop');
                            if(b)b.remove();
                        "
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        id="confirmMatchButton"
                        class="btn btn-success btn-sm px-4 fw-bold"
                        disabled
                    >
                        <i class="fa-solid fa-link me-1"></i>
                        Confirm Match
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ================================================================
     CHECK-IN MODAL
================================================================ -->

<div
    class="modal fade"
    id="checkInModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow">


            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title fw-bold">

                    <i class="fa-solid fa-warehouse me-2"></i>

                    Storage Locker Check-in

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form
                action="recorder_dashboard.php"
                method="POST"
            >

                <div class="modal-body p-4">


                    <input
                        type="hidden"
                        name="action"
                        value="process_checkin"
                    >


                    <input
                        type="hidden"
                        name="item_id"
                        id="modal_item_id"
                    >


                    <!-- TARGET ITEM -->

                    <div class="alert alert-light border mb-3">

                        <small class="text-muted d-block">
                            Target Item:
                        </small>


                        <strong
                            id="modal_item_title"
                            class="text-dark d-block"
                        >
                            ---
                        </strong>


                        <span
                            id="modal_item_code"
                            class="badge bg-secondary font-monospace"
                        >
                            ---
                        </span>

                    </div>


                    <!-- STORAGE LOCATION -->

                    <div class="mb-3">

                        <label
                            for="storage_location_id"
                            class="form-label fw-bold small"
                        >

                            Assign Physical Storage Locker / Shelf

                            <span class="text-danger">*</span>

                        </label>


                        <select
                            class="form-select"
                            id="storage_location_id"
                            name="storage_location_id"
                            required
                        >

                            <option
                                value=""
                                selected
                                disabled
                            >
                                -- Select Storage Location --
                            </option>


                            <?php foreach ($storage_locations as $loc): ?>

                                <option
                                    value="<?= (int)$loc['location_id']; ?>"
                                >

                                    <?= htmlspecialchars(
                                        $loc['location_name']
                                    ); ?>


                                    <?php if (!empty($loc['description'])): ?>

                                        (
                                        <?= htmlspecialchars(
                                            $loc['description']
                                        ); ?>
                                        )

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- REMARKS -->

                    <div class="mb-3">

                        <label
                            for="remarks"
                            class="form-label fw-bold small"
                        >

                            Recorder Verification Remarks
                            (Optional)

                        </label>


                        <textarea
                            class="form-control"
                            id="remarks"
                            name="remarks"
                            rows="3"
                            placeholder="Condition notes, security bag tag numbers, etc."
                        ></textarea>

                    </div>

                </div>


                <div class="modal-footer bg-light">

                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary btn-sm px-4 fw-bold"
                    >

                        <i class="fa-solid fa-check me-1"></i>

                        Confirm Check-in

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ================================================================
     BOOTSTRAP JS
================================================================ -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
></script>


<!-- ================================================================
     MODAL JAVASCRIPT
================================================================ -->

<script>


function openMatchModal(button) {

    const modal = document.getElementById('matchFoundItemModal');

    if (!modal) {
        alert('Match window could not be loaded. Please refresh the page.');
        return;
    }

    const reportId =
        button.getAttribute('data-reportid') || '';

    const reportTitle =
        button.getAttribute('data-reporttitle') || 'Lost Item';

    const categoryId =
        button.getAttribute('data-categoryid') || '';

    const row =
        button.closest('tr');

    const categoryBadge =
        row
            ? row.querySelector('.badge.bg-info')
            : null;

    const categoryName =
        categoryBadge
            ? categoryBadge.textContent.trim()
            : 'Category';

    const reportIdInput =
        document.getElementById('match_report_id');

    const reportTitleElement =
        document.getElementById('match_report_title');

    const reportCategoryElement =
        document.getElementById('match_report_category');

    const foundItemSelect =
        document.getElementById('found_item_id');

    const confirmButton =
        document.getElementById('confirmMatchButton');

    // Category is displayed for reference only; all Stored items remain selectable.

    if (reportIdInput) {
        reportIdInput.value = reportId;
    }

    if (reportTitleElement) {
        reportTitleElement.textContent =
            'Report #' + reportId + ' - ' + reportTitle;
    }

    if (reportCategoryElement) {
        reportCategoryElement.textContent = categoryName;
    }

    if (foundItemSelect) {

        foundItemSelect.value = '';

        // Do not filter by category. Recorders may select any Stored found item.
        Array.from(foundItemSelect.options).forEach(function (option) {
            option.hidden = false;
        });
    }

    if (confirmButton) {
        confirmButton.disabled = true;
    }


    // Use Bootstrap if available.
    if (
        window.bootstrap &&
        typeof window.bootstrap.Modal === 'function'
    ) {
        const bootstrapModal =
            window.bootstrap.Modal.getOrCreateInstance(modal);

        bootstrapModal.show();
        return;
    }

    // Fallback: open modal without Bootstrap JavaScript.
    modal.classList.add('show');
    modal.style.display = 'block';
    modal.removeAttribute('aria-hidden');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('role', 'dialog');

    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';

    if (!document.getElementById('manualMatchModalBackdrop')) {

        const backdrop =
            document.createElement('div');

        backdrop.id =
            'manualMatchModalBackdrop';

        backdrop.className =
            'modal-backdrop fade show';

        backdrop.onclick =
            closeMatchModal;

        document.body.appendChild(backdrop);
    }
}


function closeMatchModal() {

    const modal =
        document.getElementById('matchFoundItemModal');

    if (!modal) {
        return;
    }

    if (
        window.bootstrap &&
        typeof window.bootstrap.Modal === 'function'
    ) {
        const instance =
            window.bootstrap.Modal.getInstance(modal);

        if (instance) {
            instance.hide();
            return;
        }
    }

    modal.classList.remove('show');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    modal.removeAttribute('aria-modal');
    modal.removeAttribute('role');

    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';

    const backdrop =
        document.getElementById('manualMatchModalBackdrop');

    if (backdrop) {
        backdrop.remove();
    }
}


document.addEventListener('DOMContentLoaded', function () {

    const matchModal =
        document.getElementById('matchFoundItemModal');

    const foundItemSelect =
        document.getElementById('found_item_id');

    const confirmMatchButton =
        document.getElementById('confirmMatchButton');


    function validateMatchSelection() {

    if (!foundItemSelect || !confirmMatchButton) {
        return;
    }

    const selectedOption =
        foundItemSelect.options[
            foundItemSelect.selectedIndex
        ];

    const hasSelection =
        selectedOption &&
        selectedOption.value !== '';

    // Any Stored found item can be selected; category is reference only.
    confirmMatchButton.disabled = !hasSelection;
}

    if (matchModal) {

        matchModal.addEventListener(
            'show.bs.modal',
            function (event) {

                const button = event.relatedTarget;

                if (!button) {
                    return;
                }

                const reportId =
                    button.getAttribute('data-reportid');

                const reportTitle =
                    button.getAttribute('data-reporttitle');

                const categoryName =
                    button
                        .closest('tr')
                        ?.querySelector('.badge.bg-info')
                        ?.textContent
                        ?.trim();

                document.getElementById(
                    'match_report_id'
                ).value = reportId || '';

                document.getElementById(
                    'match_report_title'
                ).textContent =
                    'Report #' +
                    (reportId || '-') +
                    ' - ' +
                    (reportTitle || 'Lost Item');

                document.getElementById(
                    'match_report_category'
                ).textContent =
                    categoryName || 'Category';

                if (foundItemSelect) {

                    foundItemSelect.value = '';

                    // Keep every Stored found item visible regardless of category.
                    Array.from(
                        foundItemSelect.options
                    ).forEach(function (option) {
                        option.hidden = false;
                    });
                }

                if (confirmMatchButton) {
                    confirmMatchButton.disabled = true;
                }

            }
        );
    }

    if (foundItemSelect) {

        foundItemSelect.addEventListener(
            'change',
            validateMatchSelection
        );

    }



    const checkInModal =
        document.getElementById('checkInModal');


    if (checkInModal) {

        checkInModal.addEventListener(
            'show.bs.modal',
            function (event) {

                const button =
                    event.relatedTarget;


                if (!button) {
                    return;
                }


                const itemId =
                    button.getAttribute('data-itemid');


                const itemCode =
                    button.getAttribute('data-itemcode');


                const itemTitle =
                    button.getAttribute('data-itemtitle');


                document.getElementById(
                    'modal_item_id'
                ).value = itemId || '';


                document.getElementById(
                    'modal_item_code'
                ).textContent = itemCode || '---';


                document.getElementById(
                    'modal_item_title'
                ).textContent = itemTitle || '---';

            }
        );

    }


    /*
    |||*/

    document.querySelectorAll(
        '.nav-pills a[href^="#"]'
    ).forEach(function (link) {

        link.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                const target =
                    document.querySelector(
                        this.getAttribute('href')
                    );


                if (target) {

                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                }


                document.querySelectorAll(
                    '.nav-pills .nav-link'
                ).forEach(function (nav) {

                    nav.classList.remove('active');

                });


                this.classList.add('active');

            }
        );

    });

});

</script>

<!-- ITEM IMAGE PREVIEW MODAL -->
<div
    class="modal fade"
    id="itemImageModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content border-0 rounded-4 shadow">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-image text-primary me-2"></i>
                    Item Image
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>

            <div class="modal-body text-center p-4">

                <img
                    id="previewItemImage"
                    src=""
                    alt="Item Preview"
                    style="
                        max-width: 100%;
                        max-height: 75vh;
                        object-fit: contain;
                        border-radius: 10px;
                    "
                >

            </div>

        </div>

    </div>
</div>


<script>
function showItemImage(imagePath) {

    document.getElementById('previewItemImage').src = imagePath;


    
}
</script>

</body>

</html>