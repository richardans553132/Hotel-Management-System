<?php
/**
 * Application Entry Routing Page
 * 
 * Simple router. If authenticated, redirects to dashboard.php.
 * If not authenticated, redirects to login.php.
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header("Location: /hotel/dashboard.php");
} else {
    header("Location: /hotel/login.php");
}
exit;
?>
