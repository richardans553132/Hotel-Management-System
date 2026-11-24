<?php
namespace App\Helpers;

/**
 * Simple input validation helper.
 * Centralises common sanitisation / validation patterns.
 */
class InputValidator {
    /**
     * Validate an email address.
     */
    public static function email(string $value): ?string {
        $value = trim($value);
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
        return null;
    }

    /**
     * Validate a password (minimum length, optional regex).
     */
    public static function password(string $value, int $min = 6): ?string {
        if (strlen($value) >= $min) {
            return $value;
        }
        return null;
    }

    /**
     * Sanitize plain text (remove HTML tags, trim).
     */
    public static function text(string $value, int $maxLength = 255): ?string {
        $value = trim(strip_tags($value));
        if (strlen($value) <= $maxLength) {
            return $value;
        }
        return null;
    }
}
?>
