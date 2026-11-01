<?php
// auth_check.php
session_start();

function checkAccess($allowedRoles = []) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        $_SESSION['error'] = "Please log in to access this page.";
        header("Location: login.php");
        exit();
    }

    if (!empty($allowedRoles) && !in_array($_SESSION['user_role'], $allowedRoles)) {
        $_SESSION['error'] = "Access denied. You do not have permission to view this section.";
        
        // Redirect unauthorized access to their appropriate home
        switch ($_SESSION['user_role']) {
            case 'Admin':
                header("Location: admin_dashboard.php");
                break;
            case 'Recorder':
                header("Location: recorder_dashboard.php");
                break;
            case 'Reporter':
            default:
                header("Location: reporter_dashboard.php");
                break;
        }
        exit();
    }
}
?>