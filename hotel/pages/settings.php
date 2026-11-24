<?php
/**
 * Administrator Settings Panel
 * 
 * Allows configuring resort name, contact info, currency, and managing admin username/password.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check auth
require_auth();

$active_page = 'settings';
$page_title = 'System Settings';

$error = '';
$success = '';

// Fetch current logged in user details
try {
    $user_stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $current_user = $user_stmt->fetch();
} catch (PDOException $e) {
    $error = "Failed to load account details: " . $e->getMessage();
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Handle General Resort Settings Update
    if (isset($_POST['action']) && $_POST['action'] === 'general') {
        $hotel_name = trim($_POST['hotel_name']);
        $email = trim($_POST['contact_email']);
        $phone = trim($_POST['contact_phone']);
        $address = trim($_POST['address']);
        $currency = trim($_POST['currency']);
        
        if (empty($hotel_name) || empty($email) || empty($phone) || empty($currency)) {
            $error = "All general fields except address are required.";
        } else {
            $update_ok = true;
            $update_ok &= update_setting('hotel_name', $hotel_name);
            $update_ok &= update_setting('contact_email', $email);
            $update_ok &= update_setting('contact_phone', $phone);
            $update_ok &= update_setting('address', $address);
            $update_ok &= update_setting('currency', $currency);
            
            if ($update_ok) {
                $success = "Resort settings updated successfully.";
            } else {
                $error = "Failed to save some configuration values.";
            }
        }
    }
    
    // 2. Handle Security / Administrator Account Update
    if (isset($_POST['action']) && $_POST['action'] === 'security') {
        $new_username = trim($_POST['username']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_username)) {
            $error = "Username cannot be empty.";
        } else {
            try {
                // Check duplicate username if changing
                if ($new_username !== $current_user['username']) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ? AND `id` != ?");
                    $check->execute([$new_username, $_SESSION['user_id']]);
                    if ($check->fetchColumn() > 0) {
                        throw new Exception("Username '{$new_username}' is already in use by another user.");
                    }
                }
                
                if (!empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        throw new Exception("New passwords do not match.");
                    }
                    if (strlen($new_password) < 6) {
                        throw new Exception("Password must be at least 6 characters long.");
                    }
                    
                    // Hash and Update password + username
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE `users` SET `username` = ?, `password` = ? WHERE `id` = ?");
                    $stmt->execute([$new_username, $hashed, $_SESSION['user_id']]);
                } else {
                    // Update username only
                    $stmt = $pdo->prepare("UPDATE `users` SET `username` = ? WHERE `id` = ?");
                    $stmt->execute([$new_username, $_SESSION['user_id']]);
                }
                
                // Update session
                $_SESSION['username'] = $new_username;
                
                // Reload current user info
                $user_stmt->execute([$_SESSION['user_id']]);
                $current_user = $user_stmt->fetch();
                
                $success = "Administrator credentials updated successfully.";
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Read current settings
$hotel_name = get_setting('hotel_name', 'Josil Hotel');
$hotel_email = get_setting('contact_email', 'info@josilhotel.com');
$hotel_phone = get_setting('contact_phone', '+233 123 456 789');
$hotel_address = get_setting('address', 'Accra, Ghana');
$hotel_currency = get_setting('currency', 'GHS');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
        <div>
            <h1 class="h3 text-dark mb-1">System Administration Settings</h1>
            <p class="text-muted mb-0">Configure business information, defaults, and manage administrator access credentials.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- General Settings Form -->
        <div class="col-12 col-lg-7">
            <div class="card card-premium h-100">
                <div class="card-header-premium">
                    <h5 class="mb-0 text-dark">General Resort Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="settings.php" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="general">
                        
                        <div class="mb-3">
                            <label for="hotel_name" class="form-label fw-semibold text-dark">Hotel / Resort Name</label>
                            <input type="text" name="hotel_name" id="hotel_name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($hotel_name); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label for="contact_email" class="form-label fw-semibold text-dark">Contact Email</label>
                                <input type="email" name="contact_email" id="contact_email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($hotel_email); ?>" required>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label for="contact_phone" class="form-label fw-semibold text-dark">Contact Phone</label>
                                <input type="text" name="contact_phone" id="contact_phone" class="form-control form-control-custom" value="<?php echo htmlspecialchars($hotel_phone); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label fw-semibold text-dark">Resort Address</label>
                            <textarea name="address" id="address" rows="3" class="form-control form-control-custom" required><?php echo htmlspecialchars($hotel_address); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="currency" class="form-label fw-semibold text-dark">Currency Symbol / Code</label>
                            <input type="text" name="currency" id="currency" class="form-control form-control-custom" value="<?php echo htmlspecialchars($hotel_currency); ?>" placeholder="e.g. GHS" required>
                            <div class="form-text small">Standard identifier used globally (e.g. GHS, USD, EUR).</div>
                        </div>

                        <button type="submit" class="btn btn-primary-custom px-4 py-2">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security / Password Form -->
        <div class="col-12 col-lg-5">
            <div class="card card-premium h-100">
                <div class="card-header-premium">
                    <h5 class="mb-0 text-dark">Administrator Account</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="settings.php" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="security">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold text-dark">Administrator Username</label>
                            <input type="text" name="username" id="username" class="form-control form-control-custom" value="<?php echo htmlspecialchars($current_user['username']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-semibold text-dark">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control form-control-custom" placeholder="Leave blank to keep current">
                            <div class="form-text small">Minimum length is 6 characters.</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold text-dark">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-custom" placeholder="Leave blank to keep current">
                        </div>

                        <button type="submit" class="btn btn-primary-custom px-4 py-2">Update Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Form client-side validations
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })()
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
