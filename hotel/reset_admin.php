<?php
/**
 * Administrator Credential Reset & Diagnosis Utility
 * 
 * Safely restores the default admin credentials and verifies database connectivity.
 */

define('ALLOW_DB_SETUP', true);
require_once __DIR__ . '/config/db.php';

$success = false;
$error = '';
$details = [];

if ($pdo) {
    try {
        // Ensure users table exists
        $user_table = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$user_table) {
            $error = "The `users` table does not exist in database `" . DB_NAME . "`. Please run setup.php first.";
        } else {
            // Hash for admin123
            $admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
            $cust_hash = password_hash('password123', PASSWORD_DEFAULT);

            // 1. Update or Insert admin user
            $check = $pdo->query("SELECT id FROM `users` WHERE `username` = 'admin' LIMIT 1")->fetch();
            if ($check) {
                $stmt = $pdo->prepare("UPDATE `users` SET `password` = ?, `role` = 'admin', `status` = 'active' WHERE `username` = 'admin'");
                $stmt->execute([$admin_hash]);
                $details[] = "Admin account password updated to: <code>admin123</code>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(['System Administrator', 'admin', 'admin@josilhotel.com', $admin_hash, 'admin', 'active']);
                $details[] = "Created default admin account: <code>admin</code> / <code>admin123</code>";
            }

            // 2. Update customer demo accounts if they exist
            $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `username` IN ('kwamemensah', 'akosuaserwaa', 'davidosei')")->execute([$cust_hash]);
            $details[] = "Updated demo customer accounts to password: <code>password123</code>";

            $success = true;
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
} else {
    $error = "Unable to connect to MySQL server. Please ensure Apache and MySQL are running in XAMPP Control Panel.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery & Reset - Hotel System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="/hotel/assets/css/style.css?v=1.1" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-card shadow-lg" style="max-width: 520px;">
        <div class="login-banner">
            <h4 class="text-primary fw-bold mb-2 text-uppercase">Account Recovery</h4>
            <h2 class="login-title">Administrator Reset</h2>
            <p class="text-muted mb-0">System Diagnostic & Password Restorer</p>
        </div>

        <div class="card-body p-4 p-md-5 pt-0">
            <?php if ($success): ?>
                <div class="alert alert-success border-0 mb-4" role="alert">
                    <h5 class="alert-heading fw-bold mb-2">Credentials Reset Successfully!</h5>
                    <p class="mb-2">Your admin login credentials have been restored to defaults:</p>
                    <ul class="mb-0">
                        <li><strong>Username:</strong> <code>admin</code></li>
                        <li><strong>Password:</strong> <code>admin123</code></li>
                    </ul>
                </div>

                <div class="card bg-light border-0 mb-4">
                    <div class="card-body py-2 px-3 small text-muted">
                        <?php foreach ($details as $d): ?>
                            <div>✓ <?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <a href="/hotel/login.php" class="btn btn-primary-custom w-100 py-3 text-uppercase font-heading letter-spacing-1">
                    Proceed to Login
                </a>
            <?php else: ?>
                <div class="alert alert-danger border-0 mb-4" role="alert">
                    <h5 class="alert-heading fw-bold mb-2">Connection Issue</h5>
                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                </div>

                <div class="card bg-light border-0 mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2 text-dark">Steps to Resolve:</h6>
                        <ol class="small text-muted mb-0 ps-3">
                            <li>Open the <strong>XAMPP Control Panel</strong>.</li>
                            <li>Click <strong>Start</strong> next to <strong>Apache</strong>.</li>
                            <li>Click <strong>Start</strong> next to <strong>MySQL</strong>.</li>
                            <li>Ensure MySQL is running on port <code><?= defined('DB_PORT') ? DB_PORT : '3306/3307' ?></code>.</li>
                            <li>Refresh this page.</li>
                        </ol>
                    </div>
                </div>

                <button onclick="window.location.reload();" class="btn btn-outline-primary w-100 py-2">
                    Retry Connection
                </button>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
