<?php
// submit_claim.php
require_once 'auth_check.php';
require_once 'db.php';
require_once 'email_helper.php';
require_once 'notification_helper.php';

// Enforce role-based access control (Reporters only)
checkAccess(['Reporter']);

$user_id = $_SESSION['user_id'];

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
$found_id = filter_input(INPUT_GET, 'found_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'found_id', FILTER_VALIDATE_INT);
$error = '';
$success = '';

if (!$found_id) {
    header("Location: reporter_dashboard.php");
    exit();
}

// --------------------------------------------------------------------
// 1. FETCH FOUND ITEM DETAILS & CHECK EXISTING CLAIMS
// --------------------------------------------------------------------
try {
    // Fetch item details
    $itemStmt = $pdo->prepare("
        SELECT
            fi.*,
            fi.remarks AS item_description,
            c.category_name,
            im.match_id,
            im.lost_report_id AS matched_lost_report_id,
            l.reporter_id AS matched_reporter_id,
            l.status AS matched_lost_status
        FROM found_items fi
        JOIN categories c ON fi.category_id = c.category_id
        JOIN item_matches im
            ON fi.item_id = im.found_item_id
           AND im.status = 'Matched'
        JOIN lost_items l
            ON im.lost_report_id = l.report_id
        WHERE fi.item_id = ?
          AND fi.status = 'Stored'
        LIMIT 1
    ");
    $itemStmt->execute([$found_id]);
    $item = $itemStmt->fetch();

    if (!$item) {
        $_SESSION['error'] = "This item is not currently matched to a lost report and cannot be claimed.";
        header("Location: reporter_dashboard.php");
        exit();
    }

    if ((int)$item['matched_reporter_id'] !== (int)$user_id) {
        $_SESSION['error'] = "You can only claim an item matched to your own lost report.";
        header("Location: reporter_dashboard.php");
        exit();
    }

    if ($item['matched_lost_status'] !== 'Matched') {
        $_SESSION['error'] = "The linked lost report is no longer available for claiming.";
        header("Location: reporter_dashboard.php");
        exit();
    }

    // Check if the current user has already submitted an active claim for this item
    $existingClaimStmt = $pdo->prepare("
        SELECT claim_id, status FROM claims 
        WHERE found_item_id = ? AND claimant_id = ? AND status IN ('Pending Verification', 'Approved')
    ");
    $existingClaimStmt->execute([$found_id, $user_id]);
    $existingClaim = $existingClaimStmt->fetch();

} catch (\PDOException $e) {
    error_log("Fetch Claim Item Error: " . $e->getMessage());
    $error = "A database error occurred while fetching item details.";
}

// --------------------------------------------------------------------
// 2. PROCESS CLAIM SUBMISSION
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingClaim) {
    $proof_notes = trim($_POST['proof_notes'] ?? '');

    if (empty($proof_notes)) {
        $error = "Please provide details or proof of ownership to submit your claim.";
    } else {
        // Handle optional proof attachment upload (e.g., receipt, photo)
        $proof_image_path = null;
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['proof_image']['tmp_name'];
            $fileName    = $_FILES['proof_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = 'uploads/proofs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = 'proof_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                $destPath    = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $proof_image_path = $destPath;
                } else {
                    $error = "Failed to upload the proof document. Please try again.";
                }
            } else {
                $error = "Invalid file type. Allowed formats: JPG, PNG, WEBP, PDF.";
            }
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                // Re-check the active match and ownership inside the transaction.
                $matchedLostReportId = (int)$item['matched_lost_report_id'];
                $ownerStmt = $pdo->prepare("
                    SELECT l.report_id
                    FROM item_matches im
                    JOIN lost_items l ON im.lost_report_id = l.report_id
                    JOIN found_items fi ON im.found_item_id = fi.item_id
                    WHERE im.found_item_id = ?
                      AND im.status = 'Matched'
                      AND l.report_id = ?
                      AND l.reporter_id = ?
                      AND l.status = 'Matched'
                      AND fi.status = 'Stored'
                    LIMIT 1
                ");
                $ownerStmt->execute([$found_id, $matchedLostReportId, $user_id]);
                if (!$ownerStmt->fetch()) {
                    throw new RuntimeException('This found item is no longer matched to your lost report.');
                }

                // Insert claim record
                $claimStmt = $pdo->prepare("
                    INSERT INTO claims (found_item_id, claimant_id, lost_report_id, proof_notes, proof_image_path, status)
                    VALUES (?, ?, ?, ?, ?, 'Pending Verification')
                ");
                $claimStmt->execute([
                    $found_id,
                    $user_id,
                    $matchedLostReportId,
                    $proof_notes,
                    $proof_image_path
                ]);

                // Update found item status to 'Claim Pending'
                $updateItemStmt = $pdo->prepare("UPDATE found_items SET status = 'Claim Pending' WHERE item_id = ?");
                $updateItemStmt->execute([$found_id]);

                $pdo->commit();

                // Notify all Recorders that a new claim is waiting for verification.
                // Notification failure must not undo the successful claim submission.
                try {
                    createRoleNotifications(
                        $pdo,
                        'Recorder',
                        (int)$found_id,
                        'New claim submitted for found item ' . $item['item_code'] . '. The claim is waiting for verification.'
                    );
                } catch (Throwable $notificationError) {
                    error_log('New claim Recorder notification error: ' . $notificationError->getMessage());
                }

                // Create an in-system notification for the Reporter after the
                // claim transaction has committed. Notification failure must
                // not undo a successful claim submission.
                try {
                    createNotification(
                        $pdo,
                        (int)$user_id,
                        (int)$found_id,
                        'Your claim for item ' . $item['item_code'] . ' has been submitted and is waiting for verification.'
                    );
                } catch (Throwable $notificationError) {
                    error_log('Claim submission notification error: ' . $notificationError->getMessage());
                }

                // Send claim submission confirmation after the database transaction succeeds.
                // Email failure must not undo a successful claim submission.
                $emailStmt = $pdo->prepare("
                    SELECT full_name, email
                    FROM users
                    WHERE user_id = ?
                    LIMIT 1
                ");
                $emailStmt->execute([$user_id]);
                $reporter = $emailStmt->fetch(PDO::FETCH_ASSOC);

                if ($reporter && !empty($reporter['email'])) {
                    $claimEmailBody = buildClaimEmail(
                        $reporter['full_name'] ?? ($_SESSION['user_name'] ?? 'Reporter'),
                        $item['title'],
                        $item['item_code'],
                        $item['category_name'],
                        $item['location_found'],
                        'Pending Verification',
                        'Your ownership claim has been successfully submitted. Resort staff will review the proof and verify your claim.',
                        ''
                    );

                    $claimEmailSent = sendReporterEmail(
                        $reporter['email'],
                        $reporter['full_name'] ?? ($_SESSION['user_name'] ?? 'Reporter'),
                        'Claim Submitted - ' . $item['item_code'],
                        $claimEmailBody
                    );

                    $_SESSION['email_status'] = $claimEmailSent
                        ? 'Claim confirmation email sent to your registered email address.'
                        : 'Claim submitted successfully, but the confirmation email could not be sent.';
                }

                $_SESSION['success'] = "Your claim request for item <strong>" . htmlspecialchars($item['item_code']) . "</strong> has been submitted. Resort staff will review your proof.";
                header("Location: reporter_dashboard.php");
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
                error_log("Claim Submission Error: " . $e->getMessage());
                $error = "A database error occurred while filing your claim.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Claim - Resort Lost & Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="reporter_dashboard.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Resort L&F Guest Portal</a>
        <div class="d-flex align-items-center text-white">
            <span class="me-3 small"><i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold m-0"><i class="fa-solid fa-hand text-success me-2"></i>Submit Ownership Claim</h3>
                <a href="reporter_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard</a>
            </div>

            <!-- ALERT NOTIFICATIONS -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($existingClaim): ?>
                <div class="alert alert-info" role="alert">
                    <i class="fa-solid fa-circle-info me-2"></i>You already have a <strong><?= htmlspecialchars($existingClaim['status']); ?></strong> claim request submitted for this item. Staff is currently processing your request.
                </div>
            <?php endif; ?>

            <!-- ITEM SUMMARY CARD -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-box-open me-2"></i>Item Tag: <?= htmlspecialchars($item['item_code']); ?></h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($item['title']); ?></h4>
                            <p class="text-muted small mb-2"><i class="fa-solid fa-tag me-1"></i>Category: <strong><?= htmlspecialchars($item['category_name']); ?></strong></p>
                            <p class="text-muted mb-3"><?= htmlspecialchars($item['item_description']); ?></p>
                            <div class="small text-secondary">
                                <span class="me-3"><i class="fa-solid fa-location-dot me-1"></i>Found Location: <?= htmlspecialchars($item['location_found']); ?></span>
                                <span><i class="fa-solid fa-calendar-day me-1"></i>Date Found: <?= htmlspecialchars($item['date_found']); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($item['image_path'])): ?>
                            <div class="col-md-4 text-center">
                                <img
                                    src="<?= htmlspecialchars(getImagePath($item['image_path']), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="Found Item Image"
                                    class="img-fluid rounded border shadow-sm style-img"
                                    style="max-height: 140px; object-fit: cover; cursor: pointer;"
                                    data-bs-toggle="modal"
                                    data-bs-target="#itemImageModal"
                                    title="Click to enlarge"
                                    onclick="showItemImage(this.src)"
                                >
                                <div class="small text-muted mt-2">
                                    <i class="fa-solid fa-magnifying-glass-plus me-1"></i>Click image to enlarge
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CLAIM FORM CARD -->
            <?php if (!$existingClaim): ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold m-0 text-dark"><i class="fa-solid fa-shield-halved text-success me-2"></i>Proof of Ownership Verification</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="submit_claim.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="found_id" value="<?= $item['item_id']; ?>">

                            <div class="mb-3">
                                <label for="proof_notes" class="form-label fw-semibold small">Proof Notes / Ownership Details *</label>
                                <textarea class="form-control" id="proof_notes" name="proof_notes" rows="4" placeholder="Provide specific identifying details not visible in public descriptions, e.g., phone lockscreen wallpaper, serial numbers, engraving details, wallet contents, or purchase date..." required></textarea>
                                <div class="form-text">Detailed specifications help resort staff verify ownership quickly.</div>
                            </div>

                            <div class="mb-4">
                                <label for="proof_image" class="form-label fw-semibold small">Upload Proof Document / Photo (Optional)</label>
                                <input type="file" class="form-control" id="proof_image" name="proof_image" accept="image/png, image/jpeg, image/webp, application/pdf">
                                <div class="form-text">Attach a receipt, original warranty, purchase invoice, or previous photo of the item (JPG, PNG, WEBP, PDF - Max 5MB).</div>
                            </div>

                            <div class="d-flex justify-content-end align-items-center">
                                <a href="reporter_dashboard.php" class="btn btn-light me-2">Cancel</a>
                                <button type="submit" class="btn btn-success fw-bold px-4">
                                    <i class="fa-solid fa-paper-plane me-2"></i>Submit Claim Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ITEM IMAGE MODAL -->
<div class="modal fade" id="itemImageModal" tabindex="-1" aria-labelledby="itemImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="itemImageModalLabel">
                    <i class="fa-solid fa-image me-2"></i>Found Item Image
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <img id="enlargedItemImage" src="" alt="Enlarged Found Item Image" class="img-fluid rounded" style="max-height: 75vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script>
function showItemImage(imageSrc) {
    document.getElementById('enlargedItemImage').src = imageSrc;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>