<?php
namespace App\Controllers;

use App\Helpers\Security;
use App\Helpers\InputValidator;
use App\Helpers\RateLimiter;
use App\Models\User;

/**
 * AuthController – handles login, registration, logout.
 * All forms use CSRF tokens generated via Security helper.
 */
class AuthController
{
    /** Show login form */
    public function showLogin()
    {
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/auth/login.php';
    }

    /** Process login POST */
    public function handleLogin()
    {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        // Rate‑limit login attempts per IP
        if (!RateLimiter::allow('login', 5, 300)) {
            die('Too many login attempts, please wait a few minutes.');
        }
        $email = InputValidator::email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $error = 'Please provide both email and password.';
            $csrf = Security::generateCsrfToken();
            require __DIR__ . '/../Views/auth/login.php';
            return;
        }
        $pdo = \App\Helpers\Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            // Successful login – regenerate session ID and store user data
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            // Reset rate limiter on success
            RateLimiter::reset('login');
            header('Location: ?page=dashboard');
            exit;
        } else {
            $error = 'Invalid credentials.';
            $csrf = Security::generateCsrfToken();
            require __DIR__ . '/../Views/auth/login.php';
        }
    }

    /** Show registration form */
    public function showRegister()
    {
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/auth/register.php';
    }

    /** Process registration POST */
    public function handleRegister()
    {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        $name = InputValidator::text($_POST['name'] ?? '', 100);
        $email = InputValidator::email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $errors = [];
        if (!$name) $errors[] = 'Name is required.';
        if (!$email) $errors[] = 'Valid email required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        // Check for existing email
        $pdo = \App\Helpers\Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered.';
        }
        if (!empty($errors)) {
            $csrf = Security::generateCsrfToken();
            require __DIR__ . '/../Views/auth/register.php';
            return;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (:name, :email, :hash, :role)');
        $stmt->execute(['name' => $name, 'email' => $email, 'hash' => $hash, 'role' => 'customer']);
        // Auto‑login after registration
        $userId = $pdo->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $userId;
        $_SESSION['user_name'] = $name;
        header('Location: ?page=dashboard');
        exit;
    }

    /** Logout – clear session */
    public function logout()
    {
        // Destroy the entire session safely
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        header('Location: ?page=home');
        exit;
    }
}
?>
