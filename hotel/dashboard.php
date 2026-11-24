<?php
/**
 * Administrator Dashboard
 * 
 * Aggregates and displays system totals and recent reservations.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Protect this page
require_auth();

$active_page = 'dashboard';
$page_title = 'Dashboard';

// Fetch totals & recent activities
try {
    // Total counts
    $total_rooms = $pdo->query("SELECT COUNT(*) FROM `rooms`")->fetchColumn();
    $total_guests = $pdo->query("SELECT COUNT(*) FROM `guests`")->fetchColumn();
    $total_reservations = $pdo->query("SELECT COUNT(*) FROM `reservations`")->fetchColumn();
    $total_payments = $pdo->query("SELECT COALESCE(SUM(`amount`), 0.00) FROM `payments`")->fetchColumn();
    
    // Recent reservations query with relationships
    $recent_stmt = $pdo->query("
        SELECT r.id, r.check_in_date, r.check_out_date, r.total_price, r.status,
               g.first_name, g.last_name,
               rm.room_number, rm.type
        FROM `reservations` r
        JOIN `guests` g ON r.guest_id = g.id
        JOIN `rooms` rm ON r.room_id = rm.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $recent_reservations = $recent_stmt->fetchAll();
    
} catch (PDOException $e) {
    // In case tables do not exist (e.g. database exists but schema wasn't imported)
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
                        <svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' fill='#f1c40f' class='bi bi-table' viewBox='0 0 16 16'>
                          <path d='M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2zm15 2h-4v3h4V4zm0 4h-4v3h4V8zm0 4h-4v3h3a1 1 0 0 0 1-1v-2zm-5 3v-3H6v3h4zm-5 0v-3H1v2a1 1 0 0 0 1 1h3zm-4-4h4V8H1v3zm0-4h4V4H1v3zm5-3v3h4V4H6zm0 4v3h4V8H6z'/>
                        </svg>
                    </div>
                    <h2 class='fw-bold mb-3'>Database Tables Missing</h2>
                    <p class='lead'>The database exists, but required tables are missing or incomplete.</p>
                    <div class='alert alert-warning bg-warning text-dark border-0 text-start' role='alert'>
                        <strong>Production actions required:</strong>
                        <ul class='mb-0 mt-2'>
                            <li>Ensure all required tables (<code>users</code>, <code>rooms</code>, <code>guests</code>, <code>reservations</code>, <code>payments</code>, <code>settings</code>) are initialized.</li>
                            <li>Import the SQL schema manually into your database.</li>
                        </ul>
                    </div>
                    <p class='text-white-50 mt-4 small'>Error Detail: " . htmlspecialchars($e->getMessage()) . "</p>
                    <button onclick='window.location.reload();' class='btn btn-light px-4 py-2 mt-2 fw-semibold'>Retry Connection</button>
                </div>
            </div>
        </div>
    </body>
    </html>");
}

// Include page layout header and navigation
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-4">
    <!-- Welcome Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
        <div>
            <h1 class="h2 text-dark mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p class="text-muted mb-0">Here is the quick status of <?php echo htmlspecialchars(get_setting('hotel_name', 'Josil Hotel')); ?> operations today.</p>
        </div>
        <div class="mt-2 mt-sm-0">
            <span class="badge bg-light text-dark border p-2">
                System Date: <?php echo date('F d, Y'); ?>
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <!-- Rooms Total Card -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card rooms d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-1 fw-bold small">Total Rooms</h6>
                    <h3 class="mb-0 fw-bold"><?php echo number_format($total_rooms); ?></h3>
                    <a href="/hotel/pages/rooms.php" class="text-decoration-none small text-primary mt-2 d-inline-block">Manage rooms &rarr;</a>
                </div>
            </div>
        </div>
        <!-- Guests Total Card -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card guests d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-1 fw-bold small">Guests Registered</h6>
                    <h3 class="mb-0 fw-bold"><?php echo number_format($total_guests); ?></h3>
                    <a href="/hotel/pages/guests.php" class="text-decoration-none small text-success mt-2 d-inline-block">Manage guests &rarr;</a>
                </div>
            </div>
        </div>
        <!-- Reservations Total Card -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card reservations d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-1 fw-bold small">Reservations</h6>
                    <h3 class="mb-0 fw-bold"><?php echo number_format($total_reservations); ?></h3>
                    <a href="/hotel/pages/reservations.php" class="text-decoration-none small text-purple mt-2 d-inline-block" style="color: #9b59b6;">Manage bookings &rarr;</a>
                </div>
            </div>
        </div>
        <!-- Revenue Card -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card payments d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-1 fw-bold small">Revenue Collected</h6>
                    <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?> <?php echo number_format($total_payments, 2); ?></h3>
                    <a href="/hotel/pages/payments.php" class="text-decoration-none small text-warning-emphasis mt-2 d-inline-block">Manage payments &rarr;</a>
                </div>
                <div class="stat-icon fw-bold text-warning" style="font-size: 1.5rem;">
                    <?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid Section -->
    <div class="row g-4">
        <!-- Recent Bookings Table -->
        <div class="col-12 col-xl-8">
            <div class="card card-premium h-100">
                <div class="card-header-premium d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-dark">Recent Reservations</h5>
                    <a href="/hotel/pages/reservations.php" class="btn btn-sm btn-outline-primary rounded-pill">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($recent_reservations) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Guest</th>
                                        <th>Room</th>
                                        <th>Check In/Out</th>
                                        <th>Total Price</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_reservations as $res): 
                                        $statusClass = 'badge-res-' . $res['status'];
                                        $statusLabel = str_replace('_', ' ', ucfirst($res['status']));
                                    ?>
                                        <tr>
                                            <td class="fw-semibold text-dark">
                                                <?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    Room <?php echo htmlspecialchars($res['room_number']); ?>
                                                </span>
                                                <div class="small text-muted"><?php echo htmlspecialchars($res['type']); ?></div>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-dark">In: <?php echo htmlspecialchars($res['check_in_date']); ?></div>
                                                <div class="small text-muted">Out: <?php echo htmlspecialchars($res['check_out_date']); ?></div>
                                            </td>
                                            <td class="fw-semibold text-dark">
                                                <?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?> <?php echo number_format($res['total_price'], 2); ?>
                                            </td>
                                            <td>
                                                <span class="badge-custom <?php echo $statusClass; ?>">
                                                    <?php echo $statusLabel; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <p class="text-muted mt-2 mb-0">No reservations found. Create some room bookings to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Administration Controls -->
        <div class="col-12 col-xl-4">
            <div class="card card-premium h-100">
                <div class="card-header-premium">
                    <h5 class="mb-0 text-dark">Quick Operations</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Common management tasks you can perform immediately:</p>
                    
                    <div class="d-grid gap-3">
                        <a href="/hotel/pages/reservations.php?action=new" class="btn btn-primary-custom py-3 text-start d-flex align-items-center justify-content-between">
                            <span>Book New Reservation</span>
                            <span>&rarr;</span>
                        </a>
                        <a href="/hotel/pages/rooms.php?action=new" class="btn btn-light border py-3 text-start d-flex align-items-center justify-content-between">
                            <span>Add New Hotel Room</span>
                            <span>&rarr;</span>
                        </a>
                        <a href="/hotel/pages/guests.php?action=new" class="btn btn-light border py-3 text-start d-flex align-items-center justify-content-between">
                            <span>Add New Guest profile</span>
                            <span>&rarr;</span>
                        </a>
                        <a href="/hotel/pages/payments.php?action=new" class="btn btn-light border py-3 text-start d-flex align-items-center justify-content-between">
                            <span>Record Room Payment</span>
                            <span>&rarr;</span>
                        </a>
                        <a href="/hotel/pages/payments.php" class="btn btn-light border py-3 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-printer text-primary me-2"></i>Print Client Receipt</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include layout footer
require_once __DIR__ . '/includes/footer.php'; 
?>
