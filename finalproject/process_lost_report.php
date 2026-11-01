<?php
// process_lost_report.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_once 'email_helper.php';

checkAccess(['Reporter']);

// Ensure user is logged in as Reporter
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in or quickly register to submit a report.";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reporter_id   = $_SESSION['user_id'];
    $reporter_name = trim((string)($_SESSION['user_name'] ?? 'Reporter'));
    $item_title    = trim($_POST['item_title'] ?? '');
    $category_id   = (int)($_POST['category_id'] ?? 0);
    $location_lost = trim($_POST['location_lost'] ?? '');
    $date_lost     = $_POST['date_lost'] ?? '';
    $description   = trim($_POST['description'] ?? '');

    // Validation
    if (empty($item_title) || empty($category_id) || empty($location_lost) || empty($date_lost) || empty($description)) {
        $_SESSION['error'] = "Please fill in all required item details.";
        header("Location: report_lost.php");
        exit();
    }

    // Generate unique reference code
    $reference_code = "REF-" . date('Y') . "-" . strtoupper(bin2hex(random_bytes(3)));

    $sql = "INSERT INTO lost_items (reference_code, reporter_id, item_title, category_id, location_lost, date_lost, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Searching')";

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$reference_code, $reporter_id, $item_title, $category_id, $location_lost, $date_lost, $description])) {
        $emailStmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
        $emailStmt->execute([$reporter_id]);
        $reporter = $emailStmt->fetch(PDO::FETCH_ASSOC);
        $reporter_email = trim((string)($reporter['email'] ?? ''));

        $catStmt = $pdo->prepare("SELECT category_name FROM categories WHERE category_id = ? LIMIT 1");
        $catStmt->execute([$category_id]);
        $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
        $category_name = $catRow['category_name'] ?? 'Not specified';

        if ($reporter_email !== '') {
            $emailHtml = buildReporterEmail(
                $reporter_name,
                $item_title,
                $reference_code,
                $category_name,
                $location_lost,
                $date_lost,
                'Searching',
                'Lost'
            );
            $emailSent = sendReporterEmail(
                $reporter_email,
                $reporter_name,
                'Lost Item Report Submitted - ' . $reference_code,
                $emailHtml
            );
            $_SESSION['email_status'] = $emailSent
                ? 'Confirmation email sent to ' . $reporter_email . '.'
                : 'Report saved, but the confirmation email could not be sent. Check email_config.php, PHPMailer installation, and PHP error log.';
        } else {
            $_SESSION['email_status'] = 'Report saved, but no email address is registered for this Reporter account.';
        }

        $_SESSION['success'] = "Lost report submitted! Your Reference Tracking Code is: " . $reference_code;
        header("Location: reporter_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to submit lost report.";
        header("Location: report_lost.php");
        exit();
    }
}
?>