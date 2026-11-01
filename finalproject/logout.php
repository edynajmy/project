<?php
// logout.php
session_start();

// 1. Unset all session variables
$_SESSION = array();

// 2. Clear the session cookie from the browser if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Destroy the server-side session data
session_destroy();

// 4. Start a fresh temporary session to pass a logout success notification
session_start();
session_regenerate_id(true);
$_SESSION['success'] = "You have been successfully logged out.";

// 5. Direct browser not to cache sensitive pages post-logout
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// 6. Redirect to the login page
header("Location: login.php");
exit();