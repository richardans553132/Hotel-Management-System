<?php
// public/index.php – Front controller

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Security;

// Start secure session
session_start();
Security::secureSession();

// Simple router based on ?page= parameter (for demo purposes)
$page = $_GET['page'] ?? 'home';

switch ($page) {
    // Admin Hotel CRUD
    case 'admin_hotels':
        require __DIR__ . '/../app/Controllers/AdminHotelController.php';
        (new AdminHotelController())->list();
        break;
    case 'admin_hotels_create':
        require __DIR__ . '/../app/Controllers/AdminHotelController.php';
        (new AdminHotelController())->createForm();
        break;
    case 'admin_hotels_edit':
        require __DIR__ . '/../app/Controllers/AdminHotelController.php';
        (new AdminHotelController())->editForm();
        break;
    case 'admin_hotels_delete':
        require __DIR__ . '/../app/Controllers/AdminHotelController.php';
        (new AdminHotelController())->delete();
        break;
    case 'admin_hotels_store':
        require __DIR__ . '/../app/Controllers/AdminHotelController.php';
        (new AdminHotelController())->handleCreate();
        break;
    case 'admin_hotels_update':
        require __DIR__ . '/../app/Controllers/AdminHotelController.php';
        (new AdminHotelController())->handleEdit();
        break;
    // Admin Room CRUD
    case 'admin_rooms':
        require __DIR__ . '/../app/Controllers/AdminRoomController.php';
        (new AdminRoomController())->list();
        break;
    case 'admin_rooms_create':
        require __DIR__ . '/../app/Controllers/AdminRoomController.php';
        (new AdminRoomController())->createForm();
        break;
    case 'admin_rooms_edit':
        require __DIR__ . '/../app/Controllers/AdminRoomController.php';
        (new AdminRoomController())->editForm();
        break;
    case 'admin_rooms_delete':
        require __DIR__ . '/../app/Controllers/AdminRoomController.php';
        (new AdminRoomController())->delete();
        break;
    case 'admin_rooms_store':
        require __DIR__ . '/../app/Controllers/AdminRoomController.php';
        (new AdminRoomController())->handleCreate();
        break;
    case 'admin_rooms_update':
        require __DIR__ . '/../app/Controllers/AdminRoomController.php';
        (new AdminRoomController())->handleEdit();
        break;
    // Booking routes
    case 'booking_search':
        require __DIR__ . '/../app/Controllers/BookingController.php';
        (new BookingController())->searchForm();
        break;
    case 'booking_search_results':
        require __DIR__ . '/../app/Controllers/BookingController.php';
        (new BookingController())->handleSearch();
        break;
    case 'booking_create':
        require __DIR__ . '/../app/Controllers/BookingController.php';
        (new BookingController())->createForm();
        break;
    case 'booking_store':
        require __DIR__ . '/../app/Controllers/BookingController.php';
        (new BookingController())->handleCreate();
        break;
    case 'booking_history':
        require __DIR__ . '/../app/Controllers/BookingController.php';
        (new BookingController())->history();
        break;
    case 'booking_cancel':
        require __DIR__ . '/../app/Controllers/BookingController.php';
        (new BookingController())->cancel();
        break;
    // Payment routes
    case 'payment_initiate':
        require __DIR__ . '/../app/Controllers/PaymentController.php';
        (new PaymentController())->initiate();
        break;
    case 'payment_callback':
        require __DIR__ . '/../app/Controllers/PaymentController.php';
        (new PaymentController())->callback();
        break;
    // Auth routes
    case 'login':
        require __DIR__ . '/../app/Controllers/AuthController.php';
        (new AuthController())->showLogin();
        break;
    case 'register':
        require __DIR__ . '/../app/Controllers/AuthController.php';
        (new AuthController())->showRegister();
        break;
    // Dashboard
    case 'dashboard':
        echo '<h1>Dashboard</h1>';
        break;
    // Home
    default:
        echo '<h1>Welcome to Smart Hotel Booking System</h1>';
        echo '<p><a href="?page=login">Login</a> | <a href="?page=register">Register</a></p>';
        break;
}
?>