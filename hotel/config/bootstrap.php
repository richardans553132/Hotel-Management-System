<?php
// c:\xampp\htdocs\hotel\config\bootstrap.php
// Load environment variables and define legacy constants for backward compatibility.

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Legacy constants that older scripts (e.g., database/migrate.php) might expect.
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}
if (!defined('DB_DATABASE')) {
    define('DB_DATABASE', getenv('DB_DATABASE') ?: 'hotel_booking_system');
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
}
?>
