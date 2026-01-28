<?php
// controllers/logout.php - Secure logout functionality
session_start();

// Unset all session variables
// Unset all session variables
require_once "../../config/config.php";

if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'LOGOUT', "User logged out");
} elseif (isset($_SESSION['admin_id'])) {
    log_activity($_SESSION['admin_id'], 'LOGOUT', "Admin logged out");
}

$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page with a logout message
// Since we're in controllers folder, we need to go up one level to reach index.php
header("Location: ../../index.php?message=logged_out");
exit();
?>