<?php
/**
 * Database Connection Configuration
 * 
 * Establishes a secure connection using PDO. 
 * Automatically handles missing database exceptions and guides the user to setup.php.
 */

// Load environment variables from .env file if it exists
$env_file = dirname(__DIR__) . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Remove optional surrounding quotes
            $value = preg_replace('/^["\'](.*)["\']$/', '$1', $value);
            if (!defined($key)) {
                define($key, $value);
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// Fallback to default constants if not defined via .env
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'hotel_booking_system');


$ports_to_try = [DB_PORT];
if (DB_PORT !== '3306') {
    $ports_to_try[] = '3306';
}
if (DB_PORT !== '3307') {
    $ports_to_try[] = '3307';
}
$ports_to_try = array_values(array_unique($ports_to_try));

$pdo = null;
$db_connection_error = null;

foreach ($ports_to_try as $port) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $db_connection_error = null;
        break; // Connected successfully
    } catch (PDOException $e) {
        $db_connection_error = $e;
    }
}

if ($pdo) {
    // Ensure settings table exists and default settings are seeded
    try {
        $pdo->query("SELECT 1 FROM `settings` LIMIT 1");
    } catch (PDOException $se) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(50) NOT NULL UNIQUE,
                `setting_value` TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        $default_settings = [
            'hotel_name' => 'Josil Hotel',
            'contact_email' => 'info@josilhotel.com',
            'contact_phone' => '+233 123 456 789',
            'address' => 'Accra, Ghana',
            'currency' => 'GHS'
        ];
        
        $seed_stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
        foreach ($default_settings as $k => $v) {
            $seed_stmt->execute([$k, $v]);
        }
    }

    // Ensure sms_logs audit table exists
    try {
        $pdo->query("SELECT 1 FROM `sms_logs` LIMIT 1");
    } catch (PDOException $se) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `sms_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `booking_id` INT NULL,
                `phone_number` VARCHAR(30) NOT NULL,
                `message` TEXT NOT NULL,
                `status` ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending',
                `response` TEXT NULL,
                `error_message` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_sms_logs_booking_id` (`booking_id`),
                INDEX `idx_sms_logs_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
} else {
    // Both connection attempts failed.
    // Try to connect to MySQL server directly (without dbname) to check availability
    $server_connected = false;
    $server_test_error = null;
    foreach ($ports_to_try as $port) {
        try {
            $server_test = new PDO("mysql:host=" . DB_HOST . ";port=" . $port . ";charset=utf8mb4", DB_USER, DB_PASS);
            $server_connected = true;
            break;
        } catch (PDOException $se) {
            $server_test_error = $se;
        }
    }

    if ($server_connected) {
        $current_script = basename($_SERVER['PHP_SELF'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($current_script === 'setup.php' || defined('ALLOW_DB_SETUP')) {
            // Allow setup script to initialize the database
            return;
        }
        // Server is active, but the database is missing or uninitialized.
        die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Database Error</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap' rel='stylesheet'>
            <style>
                body {
                    font-family: 'Outfit', sans-serif;
                    background: radial-gradient(circle at 50% 50%, #1e3c72 0%, #2a5298 100%);
                    color: #ffffff;
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .card {
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 16px;
                    color: #ffffff;
                    padding: 30px;
                    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
                }
            </style>
        </head>
        <body>
            <div class='container col-md-6 text-center'>
                <div class='card shadow-lg'>
                    <div class='card-body'>
                        <div class='mb-4'>
                            <svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' fill='#f1c40f' class='bi bi-database-fill-exclamation' viewBox='0 0 16 16'>
                              <path d='M8 1c-1.573 0-3.022.289-4.096.777C2.81 2.277 2 2.91 2 3.5v9c0 .188.08.355.224.504C3.022 13.711 4.427 14 6 14c.26 0 .515-.008.766-.024.166-.464.444-.88.809-1.218C7.147 12.729 6.588 12.7 6 12.7c-1.355 0-2.613-.244-3.554-.678C2.109 11.854 2 11.666 2 11.5v-2c0-.188.08-.355.224-.504C3.022 9.711 4.427 10 6 10c.26 0 .515-.008.766-.024.166-.464.444-.88.809-1.218C7.147 8.729 6.588 8.7 6 8.7c-1.355 0-2.613-.244-3.554-.678C2.109 7.854 2 7.666 2 7.5v-2c0-.188.08-.355.224-.504C3.022 5.711 4.427 6 6 6c.26 0 .515-.008.766-.024.166-.464.444-.88.809-1.218C7.147 4.729 6.588 4.7 6 4.7c-1.355 0-2.613-.244-3.554-.678C2.109 3.854 2 3.666 2 3.5c0-.166.109-.354.446-.578C3.387 2.494 4.645 2.25 6 2.25c1.355 0 2.613.244 3.554.678.337.154.446.342.446.502v.03c.536.071 1.031.254 1.46.536C11.758 3.731 11.91 3.535 12 3.5c0-.59-.81-1.223-1.904-1.723C9.022 1.289 7.573 1 6 1zm4.5 14a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zm.5-5v1.5a.5.5 0 0 1-1 0V10a.5.5 0 0 1 1 0zm0 3a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0z'/>
                            </svg>
                        </div>
                        <h2 class='fw-bold mb-3'>Database Missing</h2>
                        <p class='lead'>The MySQL server is running, but the application database is missing or uninitialized.</p>
                        <div class='alert alert-warning bg-warning text-dark border-0 text-start' role='alert'>
                            <strong>Production actions required:</strong>
                            <ul class='mb-0 mt-2'>
                                <li>Ensure database '<strong>" . DB_NAME . "</strong>' exists in MySQL.</li>
                                <li>Import the database schema manually into the database.</li>
                                <li>Verify that configuration parameters in <code>config/db.php</code> are correct.</li>
                            </ul>
                        </div>
                        <p class='text-white-50 mt-4 small'>Error Detail: " . htmlspecialchars($db_connection_error->getMessage()) . "</p>
                        <a href='/hotel/setup.php' class='btn btn-warning px-4 py-2 mt-2 fw-bold text-dark me-2'>Run Setup Wizard (1-Click Install)</a>
                        <button onclick='window.location.reload();' class='btn btn-light px-4 py-2 mt-2 fw-semibold'>Retry Connection</button>
                    </div>
                </div>
            </div>
        </body>
        </html>");
    } else {
        // MySQL server is likely offline
        die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Database Error</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap' rel='stylesheet'>
            <style>
                body {
                    font-family: 'Outfit', sans-serif;
                    background: radial-gradient(circle at 50% 50%, #1e3c72 0%, #2a5298 100%);
                    color: #ffffff;
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .card {
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 16px;
                    color: #ffffff;
                    padding: 30px;
                    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
                }
            </style>
        </head>
        <body>
            <div class='container col-md-6 text-center'>
                <div class='card shadow-lg'>
                    <div class='card-body'>
                        <div class='mb-4'>
                            <svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' fill='#ff4757' class='bi bi-exclamation-triangle-fill' viewBox='0 0 16 16'>
                              <path d='M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z'/>
                            </svg>
                        </div>
                        <h2 class='fw-bold mb-3'>MySQL Server Offline</h2>
                        <p class='lead'>The application could not connect to your local MySQL database server.</p>
                        <div class='alert alert-danger bg-danger text-white border-0 text-start' role='alert'>
                            <strong>Troubleshooting checklist:</strong>
                            <ul class='mb-0 mt-2'>
                                <li>Open your <strong>XAMPP Control Panel</strong>.</li>
                                <li>Start the <strong>MySQL</strong> service.</li>
                                <li>Ensure the MySQL port (default 3306 or 3307) isn't blocked by another service.</li>
                            </ul>
                        </div>
                        <p class='text-white-50 mt-4 small'>Error Detail: " . htmlspecialchars($server_test_error->getMessage()) . "</p>
                        <button onclick='window.location.reload();' class='btn btn-light px-4 py-2 mt-2 fw-semibold'>Retry Connection</button>
                    </div>
                </div>
            </div>
        </body>
        </html>");
    }
}

/**
 * Dynamic configuration helper to get settings from database
 */
function get_setting($key, $default = '') {
    global $pdo;
    static $settings = null;
    if ($settings === null && isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT `setting_key`, `setting_value` FROM `settings`");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            $settings = [];
        }
    }
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Helper to update configuration settings in database
 */
function update_setting($key, $value) {
    global $pdo;
    if (!isset($pdo)) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        return false;
    }
}
?>

