<?php
// login_process.php
session_start();
require_once 'db.php'; // Includes the PDO database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = $_POST['redirect'] ?? 'reporter_dashboard.php';
    $allowed_redirects = ['report_lost.php', 'report_found.php', 'reporter_dashboard.php'];
    if (!in_array($redirect, $allowed_redirects, true)) { $redirect = 'reporter_dashboard.php'; }
    $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    // Basic Validation
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please enter both email and password.";
        header("Location: login.php");
        exit();
    }

    try {
        // Retrieve user by email
        $stmt = $pdo->prepare("SELECT user_id, full_name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify password hash
        if ($user && password_verify($password, $user['password'])) {
            
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Set Session Variables
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email']= $user['email'];
            $_SESSION['user_role'] = $user['role']; // 'Admin', 'Recorder', or 'Reporter'
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

            // Role-Based Routing
            switch ($user['role']) {
                case 'Admin':
                    $_SESSION['success'] = "Welcome back, System Admin!";
                    header("Location: admin_dashboard.php");
                    break;

                case 'Recorder':
                    $_SESSION['success'] = "Welcome back, Staff Recorder!";
                    header("Location: recorder_dashboard.php");
                    break;

                case 'Reporter':
                default:
                    $_SESSION['success'] = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
                    header("Location: " . $redirect);
                    break;
            }
            exit();

        } else {
            // Generic error message for security (prevents user enumeration)
            $_SESSION['error'] = "Invalid email or password. Please try again.";
            header("Location: login.php");
            exit();
        }

    } catch (\PDOException $e) {
        $_SESSION['error'] = "A database error occurred during login.";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>