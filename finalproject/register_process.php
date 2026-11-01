<?php
// register_process.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = $_POST['redirect'] ?? 'reporter_dashboard.php';
    $allowed_redirects = ['report_lost.php', 'report_found.php', 'reporter_dashboard.php'];
    if (!in_array($redirect, $allowed_redirects, true)) { $redirect = 'reporter_dashboard.php'; }
    $full_name    = trim($_POST['full_name']);
    $email        = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password     = $_POST['password'];

    // Basic Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: register.php");
        exit();
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "This email is already registered.";
        header("Location: register.php");
        exit();
    }

    // Securely Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user with default 'Reporter' role
    $sql = "INSERT INTO users (full_name, email, phone_number, password, role) VALUES (?, ?, ?, ?, 'Reporter')";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$full_name, $email, $phone_number, $hashed_password])) {
        $_SESSION['user_id']   = $pdo->lastInsertId();
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_role'] = 'Reporter';

        $_SESSION['success'] = "Account created successfully! You can now submit your report.";
        header("Location: " . $redirect);
        exit();
    } else {
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: register.php");
        exit();
    }
}
?>