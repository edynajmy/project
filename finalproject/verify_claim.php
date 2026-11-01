<?php
// verify_claim.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_once 'email_helper.php';
require_once 'notification_helper.php';

function getImagePath($path)
{
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    // External image URL
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }

    // Absolute path
    if (str_starts_with($path, '/')) {
        return $path;
    }

    // Already starts with uploads/
    if (str_starts_with($path, 'uploads/')) {
        return $path;
    }

    // Filename only
    return 'uploads/' . ltrim($path, '/\\');
}

// Ensure user has Recorder or Admin privileges
checkAccess(['Recorder', 'Admin']);

$recorder_id = $_SESSION['user_id'];

function sendClaimStatusEmail(PDO $pdo, int $claimId, string $status, string $message, string $pickupLocation = ''): void
{
    $stmt = $pdo->prepare("
        SELECT
            cl.claim_id,
            cl.claimant_id,
            f.item_code,
            f.title AS item_title,
            f.location_found,
            c.category_name,
            u.full_name AS claimant_name,
            u.email AS claimant_email
        FROM claims cl
        JOIN found_items f ON cl.found_item_id = f.item_id
        JOIN categories c ON f.category_id = c.category_id
        JOIN users u ON cl.claimant_id = u.user_id
        WHERE cl.claim_id = ?
        LIMIT 1
    ");
    $stmt->execute([$claimId]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipient || empty($recipient['claimant_email'])) {
        $_SESSION['email_status'] = 'Claim processed, but the claimant email address could not be found.';
        return;
    }

    $subject = match ($status) {
        'Rejected' => 'Claim Rejected - ' . $recipient['item_code'],
        'Picked Up' => 'Item Collected - Claim #' . $claimId,
        default => 'Claim Update - ' . $recipient['item_code'],
    };

    $body = buildClaimEmail(
        $recipient['claimant_name'],
        $recipient['item_title'],
        $recipient['item_code'],
        $recipient['category_name'],
        $recipient['location_found'],
        $status,
        $message,
        $pickupLocation
    );

    $sent = sendReporterEmail(
        $recipient['claimant_email'],
        $recipient['claimant_name'],
        $subject,
        $body
    );

    $_SESSION['email_status'] = $sent
        ? 'Email notification sent to the claimant.'
        : 'Claim processed successfully, but the email notification could not be sent.';
}

// --------------------------------------------------------------------
// 1. ACTION HANDLER: APPROVE, REJECT OR PICKUP CLAIM
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $claim_id      = (int)($_POST['claim_id'] ?? 0);
    $found_item_id = (int)($_POST['found_item_id'] ?? 0);
    $action        = $_POST['action'] ?? '';
    $staff_notes   = trim($_POST['staff_notes'] ?? '');

    if (
        $claim_id <= 0 ||
        $found_item_id <= 0 ||
        !in_array($action, ['approve', 'reject', 'pickup'], true)
    ) {
        $_SESSION['error'] = 'Invalid claim request.';
        header('Location: verify_claim.php');
        exit();
    }

    try {

        $pdo->beginTransaction();

        // ------------------------------------------------------------
        // Get claim
        // ------------------------------------------------------------
        $claimStmt = $pdo->prepare("
            SELECT
                claim_id,
                found_item_id,
                lost_report_id,
                claimant_id,
                status
            FROM claims
            WHERE claim_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $claimStmt->execute([$claim_id]);
        $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            throw new RuntimeException('Claim not found.');
        }

        if ((int)$claim['found_item_id'] !== $found_item_id) {
            throw new RuntimeException(
                'Claim and found item do not match.'
            );
        }

        // ------------------------------------------------------------
        // Validate claim status
        // ------------------------------------------------------------
        if ($action === 'pickup') {

            if ($claim['status'] !== 'Approved') {
                throw new RuntimeException(
                    'This claim is not approved for pickup.'
                );
            }

        } else {

            if ($claim['status'] !== 'Pending Verification') {
                throw new RuntimeException(
                    'This claim has already been processed.'
                );
            }
        }

        // ------------------------------------------------------------
        // Lock found item
        // ------------------------------------------------------------
        $itemStmt = $pdo->prepare("
            SELECT
                item_id,
                item_code,
                status,
                storage_location_id
            FROM found_items
            WHERE item_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $itemStmt->execute([$found_item_id]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new RuntimeException('Found item not found.');
        }

        // ------------------------------------------------------------
        // PICKUP
        // ------------------------------------------------------------
        if ($action === 'pickup') {

            // The item remains Claim Pending until the physical pickup happens.
            // The pickup action is what changes it to Claimed.
            if ($item['status'] !== 'Claim Pending') {
                throw new RuntimeException(
                    'This item is not ready for pickup.'
                );
            }

            $pickupNote = $staff_notes;

            if ($pickupNote === '') {
                $pickupNote = 'Handed over to reporter.';
            }

            $pickupNote =
                "[PICKED_UP]\n" .
                "Item collected by reporter.\n" .
                "Collected on: " . date('Y-m-d H:i:s') . "\n" .
                "Staff Note: " . $pickupNote;

            // Mark checkout time
            $stmt = $pdo->prepare("
                UPDATE claims
                SET
                    staff_notes = ?,
                    checkout_date = NOW()
                WHERE claim_id = ?
                  AND status = 'Approved'
            ");

            $stmt->execute([
                $pickupNote,
                $claim_id
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The claim could not be marked as picked up.'
                );
            }

            // Mark the found item as Claimed after physical pickup
            $stmtItem = $pdo->prepare("
                UPDATE found_items
                SET status = 'Claimed'
                WHERE item_id = ?
                  AND status = 'Claim Pending'
            ");

            $stmtItem->execute([$found_item_id]);

            if ($stmtItem->rowCount() !== 1) {
                throw new RuntimeException(
                    'The found item could not be marked as claimed.'
                );
            }

            // Resolve the lost report
            if (!empty($claim['lost_report_id'])) {

                $stmtLost = $pdo->prepare("
                    UPDATE lost_items
                    SET status = 'Resolved'
                    WHERE report_id = ?
                      AND reporter_id = ?
                      AND status = 'Matched'
                ");

                $stmtLost->execute([
                    (int)$claim['lost_report_id'],
                    (int)$claim['claimant_id']
                ]);
            }

            $_SESSION['success'] =
                "Claim #{$claim_id} has been marked as picked up.";

        // ------------------------------------------------------------
        // APPROVE
        // ------------------------------------------------------------
        } elseif ($action === 'approve') {

            if ($item['status'] !== 'Claim Pending') {
                throw new RuntimeException(
                    'This found item is not awaiting claim verification.'
                );
            }

            // Get storage location
            $locationStmt = $pdo->prepare("
                SELECT
                    sl.location_name,
                    sl.building_zone,
                    sl.description
                FROM found_items f
                LEFT JOIN storage_locations sl
                    ON f.storage_location_id = sl.location_id
                WHERE f.item_id = ?
                LIMIT 1
            ");

            $locationStmt->execute([$found_item_id]);
            $locationInfo = $locationStmt->fetch(PDO::FETCH_ASSOC);

            // Build pickup instructions
            $approvalNote =
                "Pickup at Resort Lost & Found Counter / Front Desk.";

            if (!empty($locationInfo['location_name'])) {

                $approvalNote .=
                    "\nStorage Location: " .
                    $locationInfo['location_name'];
            }

            if (!empty($locationInfo['building_zone'])) {

                $approvalNote .=
                    "\nArea/Zone: " .
                    $locationInfo['building_zone'];
            }

            if ($staff_notes !== '') {

                $approvalNote .=
                    "\nStaff Note: " .
                    $staff_notes;
            }

            // IMPORTANT:
            // Approval does NOT check out the item.
            // checkout_date remains NULL.
            $stmt = $pdo->prepare("
                UPDATE claims
                SET
                    status = 'Approved',
                    staff_notes = ?,
                    handled_by_recorder_id = ?,
                    date_processed = NOW()
                WHERE claim_id = ?
                  AND status = 'Pending Verification'
            ");

            $stmt->execute([
                $approvalNote,
                $recorder_id,
                $claim_id
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The claim could not be approved.'
                );
            }

            // IMPORTANT:
            // Keep found item as Claim Pending until actual pickup.
            // Do NOT change it to Claimed here.
            //
            // Do NOT change lost report to Resolved here.

            $_SESSION['success'] =
                "Claim #{$claim_id} approved. " .
                "The item is now awaiting pickup.";

        // ------------------------------------------------------------
        // REJECT
        // ------------------------------------------------------------
        } else {

            if ($item['status'] !== 'Claim Pending') {
                throw new RuntimeException(
                    'This found item is not awaiting claim verification.'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE claims
                SET
                    status = 'Rejected',
                    staff_notes = ?,
                    handled_by_recorder_id = ?,
                    date_processed = NOW()
                WHERE claim_id = ?
                  AND status = 'Pending Verification'
            ");

            $stmt->execute([
                $staff_notes,
                $recorder_id,
                $claim_id
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The claim could not be rejected.'
                );
            }

            // Return item to storage
            $stmtItem = $pdo->prepare("
                UPDATE found_items
                SET status = 'Stored'
                WHERE item_id = ?
                  AND status = 'Claim Pending'
            ");

            $stmtItem->execute([$found_item_id]);

            if ($stmtItem->rowCount() !== 1) {
                throw new RuntimeException(
                    'The found item could not be returned to storage.'
                );
            }

            $_SESSION['success'] =
                "Claim #{$claim_id} rejected. " .
                "Item returned to Stored inventory.";
        }

        $pdo->commit();

        // Create an in-system notification for the Reporter after the claim
        // transaction has committed. Email and notification failures must not
        // roll back the completed claim action.
        try {
            $notificationMessage = match ($action) {
                'approve' => 'Your claim #' . $claim_id . ' for item ' . $item['item_code'] . ' has been approved. The item is ready for collection.',
                'reject'  => 'Your claim #' . $claim_id . ' has been rejected. Please check the staff notes for more information.',
                'pickup'  => 'Your claimed item for claim #' . $claim_id . ' has been successfully picked up.',
                default   => 'Your claim #' . $claim_id . ' has been updated.'
            };

            createNotification(
                $pdo,
                (int)$claim['claimant_id'],
                (int)$found_item_id,
                $notificationMessage
            );
        } catch (Throwable $notificationError) {
            error_log('Claim status notification error: ' . $notificationError->getMessage());
        }

        // Send the notification only after the claim transaction has committed.
        // Email failure must not roll back the completed claim action.
        if ($action === 'approve') {
            $pickupLocation = 'Resort Lost & Found Counter / Front Desk';

            if (!empty($locationInfo['location_name'])) {
                $pickupLocation .= "\nStorage Location: " . $locationInfo['location_name'];
            }

            if (!empty($locationInfo['building_zone'])) {
                $pickupLocation .= "\nArea/Zone: " . $locationInfo['building_zone'];
            }

            if ($staff_notes !== '') {
                $pickupLocation .= "\nStaff Note: " . $staff_notes;
            }

            sendClaimStatusEmail(
                $pdo,
                $claim_id,
                'Approved',
                'Your ownership claim has been approved. Your item is ready for collection. Please collect it from the resort Lost & Found Counter / Front Desk.',
                $pickupLocation
            );
        } elseif ($action === 'reject') {
            $rejectionReason = $staff_notes !== '' ? $staff_notes : 'Your ownership claim could not be verified by resort staff.';
            sendClaimStatusEmail(
                $pdo,
                $claim_id,
                'Rejected',
                'Your ownership claim has been rejected. Reason provided by resort staff: ' . $rejectionReason
            );
        } elseif ($action === 'pickup') {
            sendClaimStatusEmail(
                $pdo,
                $claim_id,
                'Picked Up',
                'Your claimed item has been recorded as collected from the resort Lost & Found. Thank you.',
                'Resort Lost & Found Counter / Front Desk'
            );
        }

    } catch (RuntimeException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['error'] = $e->getMessage();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Claim verification error: ' .
            $e->getMessage()
        );

        $_SESSION['error'] =
            'A database error occurred while processing the claim.';
    }

    header('Location: verify_claim.php');
    exit();
}

// --------------------------------------------------------------------
// 2. DATA QUERIES
// --------------------------------------------------------------------
// Fetch all PENDING claims with item, location, and user details
$stmt_pending = $pdo->query("
    SELECT 
        cl.claim_id, cl.proof_notes, cl.proof_image_path, cl.date_claimed, cl.lost_report_id,
        f.item_id, f.item_code, f.title AS item_title, f.remarks AS item_description, 
        f.image_path, f.location_found,
        c.category_name,
        loc.location_name AS storage_location,
        u.user_id, u.full_name AS claimant_name, u.email AS claimant_email, u.phone_number AS claimant_phone
    FROM claims cl
    JOIN found_items f ON cl.found_item_id = f.item_id
    JOIN categories c ON f.category_id = c.category_id
    LEFT JOIN storage_locations loc ON f.storage_location_id = loc.location_id
    JOIN users u ON cl.claimant_id = u.user_id
    WHERE cl.status = 'Pending Verification'
    ORDER BY cl.date_claimed ASC
");
$pending_claims = $stmt_pending->fetchAll();

// Fetch processed claim history (Approved/Rejected)
$stmt_history = $pdo->query("
    SELECT 
    cl.claim_id,
    cl.found_item_id,
    cl.status,
    cl.staff_notes,
    cl.date_processed,
        f.item_code, f.title AS item_title,
        u.full_name AS claimant_name,
        staff.full_name AS staff_name
    FROM claims cl
    JOIN found_items f ON cl.found_item_id = f.item_id
    JOIN users u ON cl.claimant_id = u.user_id
    LEFT JOIN users staff ON cl.handled_by_recorder_id = staff.user_id
    WHERE cl.status IN ('Approved', 'Rejected')
    ORDER BY cl.date_processed DESC
    LIMIT 20
");
$claim_history = $stmt_history->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Claims & Check-Out - Resort L&F Staff</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .item-preview-img { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; }
        .claim-card { border-left: 4px solid #ffc107; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Resort L&F Staff Portal
            </a>
            <div class="d-flex align-items-center ms-auto">
                <span class="badge bg-primary me-3">
                    <i class="fa-solid fa-user-shield me-1"></i><?= htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">

        <!-- NOTIFICATION ALERTS -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['email_status'])): ?>
            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-envelope me-2"></i><?= htmlspecialchars($_SESSION['email_status']); unset($_SESSION['email_status']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Claim Verification & Check-Out</h3>
                <p class="text-muted small mb-0">Review pending ownership claims, verify claimant identity, and release items.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
    <a href="recorder_dashboard.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
    </a>

    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
        <?= count($pending_claims); ?> Pending Request(s)
    </span>
</div>
        </div>

        <!-- SECTION 1: PENDING CLAIMS -->
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-hourglass-half text-warning me-2"></i>Pending Verification Queue</h5>

        <?php if (empty($pending_claims)): ?>
            <div class="card border-0 shadow-sm text-center py-5 mb-5">
                <i class="fa-solid fa-check-circle fs-1 text-success mb-2"></i>
                <h6 class="text-muted">No pending ownership claims to review right now.</h6>
            </div>
        <?php else: ?>
            <div class="row g-4 mb-5">
                <?php foreach ($pending_claims as $claim): ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0 claim-card">
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    
                                    <!-- ITEM INFORMATION -->
                                    <div class="col-md-4 border-end">
                                        <div class="d-flex gap-3 align-items-start">
                                            <?php
                                            $imagePath = trim($claim['image_path'] ?? '');

                                            if ($imagePath !== '') {
                                                // Already has uploads/ — don't add it again
                                                if (
                                                    !preg_match('#^(https?:)?//#i', $imagePath) &&
                                                    !str_starts_with($imagePath, '/') &&
                                                    !str_starts_with($imagePath, 'uploads/')
                                                ) {
                                                    $imagePath = 'uploads/' . ltrim($imagePath, '/\\');
                                                }
                                            }
                                            ?>

                                            <?php if ($imagePath !== ''): ?>

                                                <button type="button"
                                                        class="btn p-0 border-0 bg-transparent"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#claimImageModal<?= (int)$claim['claim_id']; ?>"
                                                        title="Click to view full image">
                                                    <img
                                                        src="<?= htmlspecialchars($imagePath); ?>"
                                                        class="item-preview-img border"
                                                        alt="Item Image"
                                                        style="cursor:pointer;"
                                                        onerror="this.style.display='none';"
                                                    >
                                                </button>

                                                <!-- ITEM IMAGE MODAL -->
                                                <div class="modal fade"
                                                     id="claimImageModal<?= (int)$claim['claim_id']; ?>"
                                                     tabindex="-1"
                                                     aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    <i class="fa-solid fa-image me-2 text-primary"></i>
                                                                    <?= htmlspecialchars($claim['item_title']); ?>
                                                                </h5>
                                                                <button type="button"
                                                                        class="btn-close"
                                                                        data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body text-center p-3">
                                                                <img src="<?= htmlspecialchars($imagePath); ?>"
                                                                     alt="Full Item Image"
                                                                     class="img-fluid rounded border"
                                                                     style="max-height:75vh; object-fit:contain;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            <?php else: ?>

                                                <div class="item-preview-img bg-light border d-flex align-items-center justify-content-center text-muted">
                                                    <i class="fa-solid fa-image fs-3"></i>
                                                </div>

                                            <?php endif; ?>
                                            <div>
                                                <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($claim['item_code']); ?></span>
                                                <h5 class="fw-bold mb-1 mt-1"><?= htmlspecialchars($claim['item_title']); ?></h5>
                                                <span class="badge bg-info text-dark mb-2"><?= htmlspecialchars($claim['category_name']); ?></span>
                                                <p class="small text-muted mb-1">
                                                    <i class="fa-solid fa-warehouse me-1 text-primary"></i>
                                                    <strong>Storage Locker:</strong> <?= htmlspecialchars($claim['storage_location'] ?? 'Unassigned'); ?>
                                                </p>
                                                <p class="small text-muted mb-0">
                                                    <i class="fa-solid fa-location-dot me-1 text-danger"></i>
                                                    <strong>Found At:</strong> <?= htmlspecialchars($claim['location_found']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CLAIMANT DETAILS & PROOF NOTES -->
                                    <div class="col-md-5 border-end">
                                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-user me-1 text-primary"></i>Claimant Details</h6>
                                        <ul class="list-unstyled small mb-3">
                                            <li><strong>Name:</strong> <?= htmlspecialchars($claim['claimant_name']); ?></li>
                                            <li><strong>Email:</strong> <?= htmlspecialchars($claim['claimant_email']); ?></li>
                                            <li><strong>Phone:</strong> <?= htmlspecialchars($claim['claimant_phone'] ?? 'N/A'); ?></li>
                                            <li><strong>Claim Date:</strong> <?= date('M d, Y - h:i A', strtotime($claim['date_claimed'])); ?></li>
                                        </ul>

                                        <div class="bg-light p-3 rounded border">
                                            <h6 class="fw-bold small text-secondary mb-1"><i class="fa-solid fa-key me-1"></i>Provided Proof / Serial / Notes:</h6>
                                            <p class="small mb-0 text-dark"><?= nl2br(htmlspecialchars($claim['proof_notes'])); ?></p>
                                        </div>
                                    </div>

                                    <!-- ACTION BUTTONS / MODAL TRIGGER -->
                                    <div class="col-md-3 d-flex flex-column justify-content-center gap-2">
                                        <button type="button" class="btn btn-success fw-bold py-2" data-bs-toggle="modal" data-bs-target="#approveModal<?= $claim['claim_id']; ?>">
                                            <i class="fa-solid fa-circle-check me-1"></i>Approve Claim
                                        </button>
                                        <button type="button" class="btn btn-outline-danger fw-bold py-2" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $claim['claim_id']; ?>">
                                            <i class="fa-solid fa-circle-xmark me-1"></i>Reject Claim
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- APPROVAL MODAL -->
                    <div class="modal fade" id="approveModal<?= $claim['claim_id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="verify_claim.php" method="POST">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title"><i class="fa-solid fa-circle-check me-2"></i>Confirm Claim Approval</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id']; ?>">
                                        <input type="hidden" name="found_item_id" value="<?= $claim['item_id']; ?>">
                                        <input type="hidden" name="lost_report_id" value="<?= $claim['lost_report_id']; ?>">
                                        <input type="hidden" name="action" value="approve">

                                        <p>Are you sure you want to release <strong><?= htmlspecialchars($claim['item_title']); ?> (<?= htmlspecialchars($claim['item_code']); ?>)</strong> to <strong><?= htmlspecialchars($claim['claimant_name']); ?></strong>?</p>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Staff Checkout Notes / ID Verification Ref (Optional)</label>
                                            <textarea class="form-control" name="staff_notes" rows="3" placeholder="e.g., Verified guest driver's license matching name. Handed over at front desk."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success fw-bold btn-sm">Confirm & Approve Claim</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- REJECTION MODAL -->
                    <div class="modal fade" id="rejectModal<?= $claim['claim_id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="verify_claim.php" method="POST">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title"><i class="fa-solid fa-circle-xmark me-2"></i>Reject Claim</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="claim_id" value="<?= $claim['claim_id']; ?>">
                                        <input type="hidden" name="found_item_id" value="<?= $claim['item_id']; ?>">
                                        <input type="hidden" name="action" value="reject">

                                        <p>Reject claim submitted by <strong><?= htmlspecialchars($claim['claimant_name']); ?></strong> for item <strong><?= htmlspecialchars($claim['item_code']); ?></strong>?</p>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Reason for Rejection (Required)</label>
                                            <textarea class="form-control" name="staff_notes" rows="3" required placeholder="e.g., Provided serial number did not match physical item details."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger fw-bold btn-sm">Reject Claim</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- SECTION 2: PROCESSED CLAIMS HISTORY -->
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-history me-2"></i>Recent Claim Verification Activity</h5>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Claim ID</th>
                                <th>Item Details</th>
                                <th>Claimant</th>
                                <th>Status</th>
                                <th>Processed By</th>
                                <th>Date Processed</th>
                                <th>Staff Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($claim_history)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No recent claim processing history found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($claim_history as $hist): ?>
                                    <tr>
                                        <td>#<?= $hist['claim_id']; ?></td>
                                        <td>
                                            <strong class="d-block"><?= htmlspecialchars($hist['item_title']); ?></strong>
                                            <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($hist['item_code']); ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($hist['claimant_name']); ?></td>
                                        <td>
                                            <?php if ($hist['status'] === 'Approved'): ?>
                                                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= htmlspecialchars($hist['staff_name'] ?? 'System'); ?></small></td>
                                        <td><small><?= date('M d, Y h:i A', strtotime($hist['date_processed'])); ?></small></td>
<td>
    <small class="text-muted">
        <?= nl2br(htmlspecialchars($hist['staff_notes'] ?? '-')); ?>
    </small>
</td>

<td>

    <?php if ($hist['status'] === 'Approved'): ?>

        <?php
        $isPickedUp = strpos(
    (string)($hist['staff_notes'] ?? ''),
    '[PICKED_UP]'
) !== false;
        ?>

        <?php if ($isPickedUp): ?>

            <span class="badge bg-success">
                <i class="fa-solid fa-check-double me-1"></i>
                Picked Up
            </span>

        <?php else: ?>

            <form action="verify_claim.php" method="POST"
                  onsubmit="return confirm('Confirm that this item has physically been handed over to the reporter?');">

                <input type="hidden"
                       name="claim_id"
                       value="<?= (int)$hist['claim_id']; ?>">

                <input type="hidden"
                       name="found_item_id"
                       value="<?= (int)$hist['found_item_id']; ?>">

                <input type="hidden"
                       name="action"
                       value="pickup">

                <input type="hidden"
                       name="staff_notes"
                       value="Handed over to reporter.">

                <button type="submit"
                        class="btn btn-success btn-sm fw-bold">

                    <i class="fa-solid fa-hand-holding-heart me-1"></i>
                    Mark as Picked Up

                </button>

            </form>

        <?php endif; ?>

    <?php else: ?>

        <span class="text-muted">—</span>

    <?php endif; ?>

</td>                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>