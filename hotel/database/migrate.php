<?php
/**
 * Database Migration & Seeding Runner
 * 
 * Safely creates or updates the normalized schema and inserts seed data.
 * Can be run from terminal via CLI or via browser.
 */

require_once __DIR__ . '/../config/db.php';

$is_cli = (php_sapi_name() === 'cli');

function log_message($msg) {
    global $is_cli;
    if ($is_cli) {
        echo "[MIGRATE] " . $msg . "\n";
    } else {
        echo "<p style='font-family: monospace; margin: 4px 0;'><strong>[MIGRATE]</strong> " . htmlspecialchars($msg) . "</p>";
    }
}

try {
    log_message("Starting database migration on " . DB_HOST . ":" . DB_PORT . " / " . DB_NAME);
    
    // 1. Run Schema
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("schema.sql not found at " . $schema_file);
    }
    
    $schema_sql = file_get_contents($schema_file);
    $pdo->exec($schema_sql);
    log_message("✓ Normalized database schema executed successfully.");

    // 2. Ensure backward compatibility alterations if tables existed previously
    // Check if rooms table has hotel_id
    $cols = $pdo->query("SHOW COLUMNS FROM `rooms` LIKE 'hotel_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE `rooms` ADD COLUMN `hotel_id` INT NULL AFTER `id`,
                    ADD COLUMN `capacity` INT NOT NULL DEFAULT 2 AFTER `price_per_night`,
                    ADD COLUMN `amenities` TEXT NULL AFTER `capacity`,
                    ADD COLUMN `description` TEXT NULL AFTER `amenities`,
                    ADD COLUMN `image` VARCHAR(255) DEFAULT 'default_room.jpg' AFTER `description`");
        log_message("✓ Upgraded legacy `rooms` table with hotel_id, capacity, amenities, description, and image.");
    }

    // Check if users table has full_name
    $user_cols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'full_name'")->fetchAll();
    if (empty($user_cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `full_name` VARCHAR(100) NOT NULL DEFAULT 'Administrator' AFTER `id`,
                    ADD COLUMN `email` VARCHAR(100) NULL AFTER `username`,
                    ADD COLUMN `phone` VARCHAR(30) NULL AFTER `email`,
                    ADD COLUMN `avatar` VARCHAR(255) DEFAULT 'default_avatar.png' AFTER `role`,
                    ADD COLUMN `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active' AFTER `avatar`");
        $pdo->exec("UPDATE `users` SET `email` = 'admin@josilhotel.com' WHERE `email` IS NULL OR `email` = ''");
        log_message("✓ Upgraded legacy `users` table with full_name, email, phone, avatar, and status.");
    }

    // Check if payments table has booking_id and payment_reference
    $pay_cols = $pdo->query("SHOW COLUMNS FROM `payments` LIKE 'booking_id'")->fetchAll();
    if (empty($pay_cols)) {
        $pdo->exec("ALTER TABLE `payments` 
                    ADD COLUMN `booking_id` INT NULL AFTER `id`,
                    ADD COLUMN `user_id` INT NULL AFTER `reservation_id`,
                    ADD COLUMN `payment_reference` VARCHAR(100) NULL AFTER `user_id`,
                    ADD COLUMN `transaction_id` VARCHAR(100) NULL AFTER `payment_reference`,
                    ADD COLUMN `currency` VARCHAR(10) NOT NULL DEFAULT 'GHS' AFTER `amount`,
                    ADD COLUMN `gateway_response` TEXT NULL AFTER `status`");
        // Populate payment_reference for existing rows
        $pdo->exec("UPDATE `payments` SET `payment_reference` = CONCAT('PAY-LEGACY-', id) WHERE `payment_reference` IS NULL");
        $pdo->exec("ALTER TABLE `payments` MODIFY `payment_reference` VARCHAR(100) NOT NULL UNIQUE");
        log_message("✓ Upgraded legacy `payments` table with booking_id, user_id, payment_reference, and gateway fields.");
    }

    // 3. Run Seed Data
    $seed_file = __DIR__ . '/seed.sql';
    if (file_exists($seed_file)) {
        $seed_sql = file_get_contents($seed_file);
        $pdo->exec($seed_sql);
        log_message("✓ Initial seed dataset applied successfully.");
    }

    log_message("=========================================");
    log_message("SUCCESS! Database migration and setup complete.");
    log_message("Admin login: username 'admin', password 'admin123'");
    log_message("Customer login: username 'kwamemensah', password 'password123'");
    log_message("=========================================");

} catch (Exception $e) {
    log_message("ERROR: " . $e->getMessage());
    if (!$is_cli) {
        http_response_code(500);
    }
}
