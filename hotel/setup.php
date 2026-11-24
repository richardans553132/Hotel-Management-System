<?php
/**
 * System Database Auto-Installer
 * 
 * Creates the MySQL database, imports the schema, and seeds the default Admin account.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';

$installation_success = null;
$message = '';
$error_details = '';

// Check if database installation was requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // 1. Connect to MySQL Server (Without dbname since it may not exist yet)
        $setup_port = defined('DB_PORT') ? DB_PORT : '3306';
        $setup_pdo = null;
        $ports_to_try = array_values(array_unique([$setup_port, '3306', '3307']));
        $connected_port = $setup_port;

        foreach ($ports_to_try as $p) {
            try {
                $setup_pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . $p . ";charset=utf8mb4", DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $connected_port = $p;
                break;
            } catch (PDOException $pe) {
                // Try next port
            }
        }

        if (!$setup_pdo) {
            throw new Exception("Could not connect to MySQL server on " . DB_HOST . " using ports (" . implode(', ', $ports_to_try) . "). Please ensure MySQL is running in XAMPP.");
        }
        
        // 2. Read the SQL Schema file
        $schema_file = __DIR__ . '/database/schema.sql';
        if (!file_exists($schema_file)) {
            throw new Exception("Schema file not found at " . $schema_file);
        }
        
        $sql = file_get_contents($schema_file);
        
        // 3. Execute the SQL Schema
        $setup_pdo->exec($sql);
        
        // 4. Reconnect to the database to insert seed data
        $db_pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . $connected_port . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // 5. Seed or reset default admin user
        $username = 'admin';
        $default_pass = 'admin123';
        $hashed_password = password_hash($default_pass, PASSWORD_DEFAULT);
        $role = 'admin';
        
        $check_stmt = $db_pdo->query("SELECT id FROM `users` WHERE `username` = 'admin' LIMIT 1");
        $existing_admin = $check_stmt->fetch();
        if (!$existing_admin) {
            $seed_stmt = $db_pdo->prepare("INSERT INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
            $seed_stmt->execute(['System Administrator', $username, 'admin@josilhotel.com', $hashed_password, $role, 'active']);
            $admin_id = $db_pdo->lastInsertId();
        } else {
            $admin_id = $existing_admin['id'];
            $update_stmt = $db_pdo->prepare("UPDATE `users` SET `password` = ?, `status` = 'active' WHERE `id` = ?");
            $update_stmt->execute([$hashed_password, $admin_id]);
        }

        // Ensure administrator link exists
        $admin_ext = $db_pdo->query("SELECT COUNT(*) FROM `administrators` WHERE `user_id` = " . intval($admin_id));
        if ($admin_ext->fetchColumn() == 0) {
            $db_pdo->prepare("INSERT INTO `administrators` (`user_id`, `department`, `access_level`) VALUES (?, ?, ?)")
                   ->execute([$admin_id, 'Executive Operations', 'super_admin']);
        }

        // Seed default settings if they do not exist
        $settings_check = $db_pdo->query("SELECT COUNT(*) FROM `settings`");
        if ($settings_check->fetchColumn() == 0) {
            $default_settings = [
                'hotel_name' => 'Josil Hotel',
                'contact_email' => 'info@josilhotel.com',
                'contact_phone' => '+233 123 456 789',
                'address' => 'Accra, Ghana',
                'currency' => 'GHS'
            ];
            $settings_insert = $db_pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
            foreach ($default_settings as $k => $v) {
                $settings_insert->execute([$k, $v]);
            }
        }
        
        // 6. Seed some optional sample data for rooms and guests to make the dashboard look populated and alive!
        $room_check = $db_pdo->query("SELECT COUNT(*) FROM `rooms`");
        if ($room_check->fetchColumn() == 0) {
            $rooms_data = [
                ['101', 'Single Room', 80.00, 'available'],
                ['102', 'Double Room', 120.00, 'available'],
                ['201', 'Deluxe Suite', 250.00, 'available'],
                ['202', 'Executive Suite', 350.00, 'maintenance'],
                ['301', 'Presidential Suite', 600.00, 'occupied']
            ];
            $room_insert = $db_pdo->prepare("INSERT INTO `rooms` (`room_number`, `type`, `price_per_night`, `status`) VALUES (?, ?, ?, ?)");
            foreach ($rooms_data as $room) {
                $room_insert->execute($room);
            }
        }

        $guest_check = $db_pdo->query("SELECT COUNT(*) FROM `guests`");
        if ($guest_check->fetchColumn() == 0) {
            $guests_data = [
                ['Kwame', 'Mensah', 'kwame.mensah@example.com', '0241234567', 'Airport Residential, Accra'],
                ['Alice', 'Smith', 'alice.smith@example.com', '+1234567890', '123 Maple St, NY'],
                ['Bob', 'Jones', 'bob.jones@example.com', '+1987654321', '456 Oak Rd, CA'],
                ['Charlie', 'Brown', 'charlie.brown@example.com', '+233244123456', '789 Pine Ln, TX']
            ];
            $guest_insert = $db_pdo->prepare("INSERT INTO `guests` (`first_name`, `last_name`, `email`, `phone`, `address`) VALUES (?, ?, ?, ?, ?)");
            foreach ($guests_data as $guest) {
                $guest_insert->execute($guest);
            }
        }
        
        $installation_success = true;
        $message = "Database 'hotel_booking_system' created and seeded successfully!";
    } catch (PDOException $e) {
        $installation_success = false;
        $message = "Database execution error occurred.";
        $error_details = $e->getMessage();
    } catch (Exception $e) {
        $installation_success = false;
        $message = "System error occurred.";
        $error_details = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Booking System - Setup Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            border: none;
            overflow: hidden;
            max-width: 600px;
            width: 100%;
        }
        .setup-header {
            background: #1e3c72;
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: 5px solid #00c6ff;
        }
        .setup-body {
            padding: 40px;
        }
        .btn-install {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.4);
            color: #ffffff;
        }
        .step-badge {
            background-color: #e9ecef;
            color: #495057;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>

    <div class="setup-card shadow-lg">
        <div class="setup-header">
            <h1 class="h3 fw-bold mb-0">Hotel Booking System</h1>
            <p class="text-white-50 mb-0 mt-1">Database Setup & Installation Wizard</p>
        </div>
        
        <div class="setup-body">
            <?php if ($installation_success === null): ?>
                <div class="text-center mb-4">
                    <h2 class="h5 fw-bold text-dark">Welcome to the Installation Wizard</h2>
                    <p class="text-muted">This wizard will configure the database and tables for the system, and seed the initial admin account.</p>
                </div>
                
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center mb-2">
                            <span class="step-badge">1</span>
                            <span class="fw-semibold text-dark">Create Database: <code>hotel_booking_system</code></span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="step-badge">2</span>
                            <span class="fw-semibold text-dark">Create Tables & Foreign Key constraints</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="step-badge">3</span>
                            <span class="fw-semibold text-dark">Create Default Admin: <strong>admin</strong> (pass: <strong>admin123</strong>)</span>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <button type="submit" name="install" class="btn btn-install w-100 py-3 text-uppercase tracking-wider">
                        Start Installation
                    </button>
                </form>
                
            <?php elseif ($installation_success === true): ?>
                <div class="text-center py-3">
                    <h3 class="fw-bold text-dark">Setup Successful!</h3>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($message); ?></p>
                    
                    <div class="alert alert-info border-0 text-start py-3 mb-4">
                        <div class="fw-bold text-dark mb-1">Administrator Credentials:</div>
                        <div class="small text-secondary">Username: <strong class="text-dark">admin</strong></div>
                        <div class="small text-secondary">Password: <strong class="text-dark">admin123</strong></div>
                        <div class="small text-warning-emphasis mt-2">Please change your password after logging in for security.</div>
                    </div>
                    
                    <a href="login.php" class="btn btn-primary btn-install w-100 py-3 text-uppercase">
                        Proceed to Login
                    </a>
                </div>
                
            <?php else: ?>
                <div class="text-center py-3">
                    <h3 class="fw-bold text-dark">Installation Failed</h3>
                    <p class="text-muted"><?php echo htmlspecialchars($message); ?></p>
                    
                    <?php if (!empty($error_details)): ?>
                        <div class="card bg-light border-0 text-start mb-4">
                            <div class="card-body">
                                <span class="fw-bold text-danger">Error Details:</span>
                                <pre class="mb-0 mt-2 text-danger small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($error_details); ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning border-0 text-start mb-4 small">
                        <strong>Suggestions:</strong>
                        <ul class="mb-0 mt-1">
                            <li>Verify that MySQL is running in your XAMPP Control Panel.</li>
                            <li>Check your database credentials in <code>config/db.php</code>.</li>
                            <li>Ensure your SQL schema file exists in <code>database/schema.sql</code>.</li>
                        </ul>
                    </div>
                    
                    <a href="setup.php" class="btn btn-secondary w-100 py-3">
                        Retry Installation
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
