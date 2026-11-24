<?php
/**
 * Reports and Analytics Dashboard
 * 
 * Aggregates occupancy rates, revenue metrics, and reservation distribution.
 * Implements optional date-range filtering.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check auth
require_auth();

$active_page = 'reports';
$page_title = 'Management Reports';

// Handle Date Range Filters (Default: last 30 days if not set)
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

$currency = get_setting('currency', 'GHS');

try {
    // ---------------------------------------------------------
    // 1. ROOM OCCUPANCY STATISTICS
    // ---------------------------------------------------------
    $total_rooms = intval($pdo->query("SELECT COUNT(*) FROM `rooms`")->fetchColumn());
    $occupied_rooms = intval($pdo->query("SELECT COUNT(*) FROM `rooms` WHERE `status` = 'occupied'")->fetchColumn());
    $maintenance_rooms = intval($pdo->query("SELECT COUNT(*) FROM `rooms` WHERE `status` = 'maintenance'")->fetchColumn());
    $available_rooms = intval($pdo->query("SELECT COUNT(*) FROM `rooms` WHERE `status` = 'available'")->fetchColumn());
    
    $occupancy_rate = ($total_rooms > 0) ? ($occupied_rooms / $total_rooms) * 100 : 0;

    // ---------------------------------------------------------
    // 2. FINANCIAL REVENUE METRICS (Filtered by date range)
    // ---------------------------------------------------------
    $rev_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0.00) as total_revenue,
               COALESCE(AVG(amount), 0.00) as avg_payment,
               COUNT(*) as total_transactions
        FROM `payments` 
        WHERE `status` = 'completed'
          AND DATE(`payment_date`) BETWEEN :start AND :end
    ");
    $rev_stmt->execute(['start' => $start_date, 'end' => $end_date]);
    $financials = $rev_stmt->fetch();

    // Payments by method
    $method_stmt = $pdo->prepare("
        SELECT `payment_method`, COALESCE(SUM(amount), 0.00) as method_total, COUNT(*) as txn_count
        FROM `payments`
        WHERE `status` = 'completed'
          AND DATE(`payment_date`) BETWEEN :start AND :end
        GROUP BY `payment_method`
        ORDER BY method_total DESC
    ");
    $method_stmt->execute(['start' => $start_date, 'end' => $end_date]);
    $payments_by_method = $method_stmt->fetchAll();

    // ---------------------------------------------------------
    // 3. RESERVATIONS ANALYSIS (Filtered by date range)
    // ---------------------------------------------------------
    $res_stmt = $pdo->prepare("
        SELECT COUNT(*) as bookings_count,
               COALESCE(SUM(total_price), 0.00) as bookings_value
        FROM `reservations`
        WHERE DATE(`created_at`) BETWEEN :start AND :end
    ");
    $res_stmt->execute(['start' => $start_date, 'end' => $end_date]);
    $reservations_summary = $res_stmt->fetch();

    // Reservation statuses breakdown
    $status_stmt = $pdo->prepare("
        SELECT `status`, COUNT(*) as status_count
        FROM `reservations`
        WHERE DATE(`created_at`) BETWEEN :start AND :end
        GROUP BY `status`
        ORDER BY status_count DESC
    ");
    $status_stmt->execute(['start' => $start_date, 'end' => $end_date]);
    $res_by_status = $status_stmt->fetchAll();

    // Top Guests list during this period
    $guest_stmt = $pdo->prepare("
        SELECT g.first_name, g.last_name, g.email, COUNT(r.id) as bookings_count, SUM(r.total_price) as total_spent
        FROM `reservations` r
        JOIN `guests` g ON r.guest_id = g.id
        WHERE DATE(r.created_at) BETWEEN :start AND :end
        GROUP BY g.id
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $guest_stmt->execute(['start' => $start_date, 'end' => $end_date]);
    $top_guests = $guest_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Failed to compile report metrics: " . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
        <div>
            <h1 class="h3 text-dark mb-1">Administrative Reports & Analytics</h1>
            <p class="text-muted mb-0">Monitor business occupancy, booking trends, and financial reports.</p>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card card-premium mb-4">
        <div class="card-body">
            <form method="GET" action="reports.php" class="row g-3 align-items-end">
                <div class="col-12 col-sm-4">
                    <label for="start_date" class="form-label fw-semibold text-dark">Report Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-12 col-sm-4">
                    <label for="end_date" class="form-label fw-semibold text-dark">Report End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-12 col-sm-4 d-grid gap-2 d-sm-flex justify-content-sm-end">
                    <button type="submit" class="btn btn-primary-custom w-100">Apply Filter</button>
                    <a href="reports.php" class="btn btn-light border w-100">Reset Date</a>
                </div>
            </form>
        </div>
    </div>

    <!-- occupancy analytics cards -->
    <h5 class="mb-3 text-dark fw-bold text-uppercase small letter-spacing-1">Current Resort Occupancy</h5>
    <div class="row g-4 mb-5">
        <div class="col-6 col-md-3">
            <div class="stat-card rooms text-center py-4">
                <h6 class="text-muted text-uppercase mb-1 fw-bold small">Occupancy Rate</h6>
                <h2 class="mb-0 fw-bold text-primary"><?php echo number_format($occupancy_rate, 1); ?>%</h2>
                <div class="text-muted small mt-2"><?php echo $occupied_rooms; ?> of <?php echo $total_rooms; ?> rooms active</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card guests text-center py-4">
                <h6 class="text-muted text-uppercase mb-1 fw-bold small">Available Rooms</h6>
                <h2 class="mb-0 fw-bold text-success"><?php echo $available_rooms; ?></h2>
                <div class="text-muted small mt-2">Ready for bookings</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card reservations text-center py-4">
                <h6 class="text-muted text-uppercase mb-1 fw-bold small">Occupied Rooms</h6>
                <h2 class="mb-0 fw-bold text-danger"><?php echo $occupied_rooms; ?></h2>
                <div class="text-muted small mt-2">Current in-house guests</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card payments text-center py-4">
                <h6 class="text-muted text-uppercase mb-1 fw-bold small">Under Maintenance</h6>
                <h2 class="mb-0 fw-bold text-warning"><?php echo $maintenance_rooms; ?></h2>
                <div class="text-muted small mt-2">Offline for updates</div>
            </div>
        </div>
    </div>

    <!-- Main Reports Grid -->
    <div class="row g-4 mb-4">
        <!-- Financial Revenue Breakdown -->
        <div class="col-12 col-lg-6">
            <div class="card card-premium h-100">
                <div class="card-header-premium">
                    <h5 class="mb-0 text-dark">Revenue Statement</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4 text-center border-bottom pb-3">
                        <div class="col-6">
                            <h6 class="text-muted small mb-1">Total Revenue Collected</h6>
                            <h3 class="fw-bold text-success mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($financials['total_revenue'], 2); ?></h3>
                        </div>
                        <div class="col-6">
                            <h6 class="text-muted small mb-1">Average Transaction</h6>
                            <h3 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($financials['avg_payment'], 2); ?></h3>
                        </div>
                    </div>
                    
                    <h6 class="fw-semibold mb-3 text-dark">Payments Received by Method:</h6>
                    <?php if (count($payments_by_method) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Payment Method</th>
                                        <th class="text-center">Transactions</th>
                                        <th class="text-end">Total Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments_by_method as $pm): ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($pm['payment_method']); ?></td>
                                            <td class="text-center"><?php echo $pm['txn_count']; ?></td>
                                            <td class="text-end fw-bold text-success"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($pm['method_total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No completed payments recorded during this period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reservation Booking Breakdown -->
        <div class="col-12 col-lg-6">
            <div class="card card-premium h-100">
                <div class="card-header-premium">
                    <h5 class="mb-0 text-dark">Booking Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4 text-center border-bottom pb-3">
                        <div class="col-6">
                            <h6 class="text-muted small mb-1">Total Bookings Count</h6>
                            <h3 class="fw-bold text-primary mb-0"><?php echo number_format($reservations_summary['bookings_count']); ?></h3>
                        </div>
                        <div class="col-6">
                            <h6 class="text-muted small mb-1">Bookings Volume Value</h6>
                            <h3 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($reservations_summary['bookings_value'], 2); ?></h3>
                        </div>
                    </div>
                    
                    <h6 class="fw-semibold mb-3 text-dark">Reservations Status Count:</h6>
                    <?php if (count($res_by_status) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Reservation Status</th>
                                        <th class="text-end">Total Bookings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($res_by_status as $rs): 
                                        $label = str_replace('_', ' ', ucfirst($rs['status']));
                                    ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?php echo $label; ?></td>
                                            <td class="text-end fw-bold"><?php echo $rs['status_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No reservations created during this period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Guests report -->
    <div class="card card-premium mb-4">
        <div class="card-header-premium">
            <h5 class="mb-0 text-dark">Top Registered Guests by Revenue (Period)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($top_guests) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Guest Name</th>
                                <th>Email</th>
                                <th class="text-center">Bookings Count</th>
                                <th class="text-end">Total Booking Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_guests as $tg): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($tg['first_name'] . ' ' . $tg['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($tg['email']); ?></td>
                                    <td class="text-center"><?php echo $tg['bookings_count']; ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($tg['total_spent'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center py-5 mb-0">No active guest reservations recorded during this period.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
