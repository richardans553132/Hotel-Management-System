<?php
/**
 * Authentication and Session Helper
 * 
 * Manages user sessions, check login status, and implements security redirects.
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in
 * 
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Restricts access to authenticated users. Redirects to login page if guest.
 */
function require_auth() {
    if (!is_logged_in()) {
        header("Location: /hotel/login.php");
        exit;
    }
    
    // Prevent browser back-button caching of sensitive administrative data
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}
?>
