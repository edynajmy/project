<?php
// process_found_report.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_once 'email_helper.php';
require_once 'notification_helper.php';

checkAccess(['Reporter']);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in or quickly register to submit a report.";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reporter_id    = $_SESSION['user_id'];
    $reporter_name  = trim((string)($_SESSION['user_name'] ?? 'Reporter'));
    $title          = trim($_POST['title'] ?? '');
    $category_id    = (int)($_POST['category_id'] ?? 0);
    $location_found = trim($_POST['location_found'] ?? '');
    $date_found     = $_POST['date_found'] ?? '';
    $remarks        = trim($_POST['remarks'] ?? '');

    // Validation
    if (empty($title) || empty($category_id) || empty($location_found) || empty($date_found)) {
        $_SESSION['error'] = "Please fill in all mandatory fields.";
        header("Location: report_found.php");
        exit();
    }

    // Fetch category retention period to set dynamic Expiry Date
    $catStmt = $pdo->prepare("SELECT category_name, retention_months FROM categories WHERE category_id = ?");
    $catStmt->execute([$category_id]);
    $category = $catStmt->fetch();

    $retention_months = $category['retention_months'] ?? 6; // Default 6 months if not found
    
    // Dynamic Expiry Calculation: date_found + retention_months
    $expiry_date = date('Y-m-d', strtotime("+$retention_months months", strtotime($date_found)));

    // Generate unique item tracking code
    $item_code = "FOUND-" . date('Y') . "-" . strtoupper(bin2hex(random_bytes(3)));

    // Handle Optional File Upload
    $image_path = 'default_item.jpg';
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['item_image']['tmp_name'];
        $fileName    = $_FILES['item_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = $item_code . '.' . $fileExtension;
            $uploadFileDir = './uploads/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $image_path = $newFileName;
            }
        }
    }

    // Insert into found_items (Set to 'Pending Check-in' for Recorders)
    $sql = "INSERT INTO found_items 
            (item_code, title, category_id, location_found, image_path, reporter_id, status, date_found, expiry_date, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending Check-in', ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$item_code, $title, $category_id, $location_found, $image_path, $reporter_id, $date_found, $expiry_date, $remarks])) {
        $found_item_id = (int)$pdo->lastInsertId();

        // Notify all Recorders that a new found item is waiting for check-in.
        // Notification failure must not undo the successful report submission.
        try {
            createRoleNotifications(
                $pdo,
                'Recorder',
                $found_item_id,
                'New found item ' . $item_code . ' has been reported and is waiting for check-in.'
            );
        } catch (Throwable $notificationError) {
            error_log('New found item notification error: ' . $notificationError->getMessage());
        }
        $emailStmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
        $emailStmt->execute([$reporter_id]);
        $reporter = $emailStmt->fetch(PDO::FETCH_ASSOC);
        $reporter_email = trim((string)($reporter['email'] ?? ''));

        if ($reporter_email !== '') {
            $emailHtml = buildReporterEmail(
                $reporter_name,
                $title,
                $item_code,
                $category['category_name'] ?? 'Not specified',
                $location_found,
                $date_found,
                'Pending Check-in',
                'Found',
                'Expiry Date',
                $expiry_date
            );
            $emailSent = sendReporterEmail(
                $reporter_email,
                $reporter_name,
                'Found Item Report Submitted - ' . $item_code,
                $emailHtml
            );
            $_SESSION['email_status'] = $emailSent
                ? 'Confirmation email sent to ' . $reporter_email . '.'
                : 'Report saved, but the confirmation email could not be sent. Check email_config.php, PHPMailer installation, and PHP error log.';
        } else {
            $_SESSION['email_status'] = 'Report saved, but no email address is registered for this Reporter account.';
        }

        $_SESSION['success'] = "Thank you! Item report logged under Tracking ID: " . $item_code . ".\n\nNEXT STEP: Please bring the physical found item to the Resort Lost & Found Counter / Front Desk for staff check-in. Do not leave the item unattended or place it in a storage locker yourself.";
        header("Location: reporter_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Database error while processing report.";
        header("Location: report_found.php");
        exit();
    }
}
?>