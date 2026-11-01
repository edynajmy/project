<?php
// auth_check.php

/**
 * Ensures a valid session is active.
 */
function startSessionIfNeeded() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Checks if the current user is logged in and possesses one of the allowed roles.
 * Redirects unauthorized users to the login page or displays an access denied message.
 *
 * @param array $allowed_roles Array of authorized role strings (e.g., ['Admin', 'Recorder'])
 */
function checkAccess(array $allowed_roles = []) {
    startSessionIfNeeded();

    // 1. Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        $_SESSION['error'] = "Please log in to access this page.";
        header("Location: login.php");
        exit();
    }

    // 2. Validate current session against basic IP & User-Agent fingerprinting
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
        // Session hijacking attempt suspected - clear and force re-login
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error'] = "Security session invalidated. Please log in again.";
        header("Location: login.php");
        exit();
    }

    // 3. Check role authorization
    $user_role = $_SESSION['user_role'];
    if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
        // Redirect based on role to their appropriate homepage or display access denied
        $_SESSION['error'] = "Access denied: You do not have permission to view that page.";

        switch ($user_role) {
            case 'Reporter':
                header("Location: reporter_dashboard.php");
                break;
            case 'Recorder':
                header("Location: verify_claim.php");
                break;
            case 'Admin':
                header("Location: admin_dashboard.php");
                break;
            default:
                header("Location: login.php");
                break;
        }
        exit();
    }
}

/**
 * Returns true if the user is logged in, false otherwise.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    startSessionIfNeeded();
    return isset($_SESSION['user_id']);
}

/**
 * Helper to check if logged-in user has a specific role without redirecting.
 *
 * @param string $role
 * @return bool
 */
function hasRole(string $role): bool {
    startSessionIfNeeded();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}