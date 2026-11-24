<?php
/**
 * Logout Page
 * 
 * Clears the session variables, destroys the session and redirects to the login page.
 */

require_once __DIR__ . '/includes/auth.php';

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: /hotel/login.php");
exit;
?>
