<?php
/**
 * Administrator Secure Login Page
 * 
 * Authenticates users using prepared statements (PDO) and bcrypt verification.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: /hotel/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Prepared statement to fetch the user by username to avoid SQL injection
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);
                
                // Store data in session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect to dashboard
                header("Location: /hotel/dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure Admin login page for the Hotel Booking System.">
    <title>Login - Hotel Booking System</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <!-- Custom Style -->
    <link href="/hotel/assets/css/style.css?v=1.1" rel="stylesheet">
</head>
<body class="login-body">

    <div class="login-card shadow-lg">
        <div class="login-banner">
            <h4 class="text-primary fw-bold mb-3 text-uppercase">WELCOME MR. JOSIL</h4>
            <h2 class="login-title"><?php echo htmlspecialchars(get_setting('hotel_name', 'Josil')); ?></h2>
            <p class="text-muted mb-0">Management Portal Control Panel</p>
        </div>
        
        <div class="card-body p-4 p-md-5 pt-0">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 d-flex align-items-center mb-4" role="alert">
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold text-dark">Username</label>
                    <input type="text" name="username" id="username" class="form-control form-control-custom" placeholder="Enter username" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold text-dark">Password</label>
                    <input type="password" name="password" id="password" class="form-control form-control-custom" placeholder="Enter password" required>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 py-3 text-uppercase font-heading letter-spacing-1">
                    Sign In
                </button>
            </form>

        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap client-side validation
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
</body>
</html>
