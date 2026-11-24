<?php
/**
 * Shared Sidebar Layout & Navigation Template
 * 
 * Responsive left sidebar navigation. Displays main routes without icons when logged in.
 * Opens the main content wrap to structure the desktop grid.
 */
$active = isset($active_page) ? $active_page : '';
?>
<?php if (is_logged_in()): ?>
<div class="app-container">
    <nav class="navbar navbar-expand-md navbar-dark sidebar-custom align-items-md-start p-3 flex-md-column">
        <div class="container-fluid flex-md-column align-items-md-start px-md-1 w-100">
            <a class="navbar-brand mb-md-4 text-white font-heading fw-bold" href="/hotel/dashboard.php"><?php echo htmlspecialchars(get_setting('hotel_name', 'Josil')); ?></a>
            
            <button class="navbar-toggler mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse w-100 mt-2 mt-md-0" id="sidebarMenu">
                <ul class="navbar-nav flex-column w-100 mb-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active === 'dashboard') ? 'active' : ''; ?>" href="/hotel/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active === 'rooms') ? 'active' : ''; ?>" href="/hotel/pages/rooms.php">Rooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active === 'guests') ? 'active' : ''; ?>" href="/hotel/pages/guests.php">Guests</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active === 'reservations') ? 'active' : ''; ?>" href="/hotel/pages/reservations.php">Reservations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active === 'payments') ? 'active' : ''; ?>" href="/hotel/pages/payments.php">Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active === 'reports') ? 'active' : ''; ?>" href="/hotel/pages/reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active === 'settings') ? 'active' : ''; ?>" href="/hotel/pages/settings.php">Settings</a>
                    </li>
                </ul>
                
                <div class="mt-md-auto pt-3 border-top border-secondary w-100 mt-3">
                    <div class="text-white-50 small mb-1">Logged in as:</div>
                    <div class="fw-bold mb-3 text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <a class="btn btn-outline-light btn-sm w-100 rounded-pill" href="/hotel/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    <main class="main-content">
<?php endif; ?>
