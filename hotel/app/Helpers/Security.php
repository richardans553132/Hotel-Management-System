<?php
namespace App\Helpers;

class Security {
    // Start a strict, secure session (call after session_start())
    public static function secureSession() {
        // Use only cookies, not URL parameters
        ini_set('session.use_only_cookies', 1);
        // Prevent JavaScript from accessing session cookie
        ini_set('session.cookie_httponly', 1);
        // If you later enable HTTPS, set this to true
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        // Regenerate ID to avoid fixation
        session_regenerate_id(true);
    }

    // CSRF token generation – store in session and return token string
    public static function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Validate token sent via POST (field name: csrf_token)
    public static function validateCsrfToken(string $token): bool {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    // Escape output for HTML context
    public static function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
