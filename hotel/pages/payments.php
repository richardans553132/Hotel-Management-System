<?php
/**
 * Payments Management CRUD Page
 * 
 * Implements listing, searching, creating, editing, and deleting payments.
 * Connects payments to reservations, displaying total cost and balance dynamically.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check Admin Access
require_auth();

$active_page = 'payments';
$page_title = 'Manage Payments';

$error = '';
$success = '';

// Start session storage for alerts if not already set by auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Display messages from redirect
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

$new_payment_id = 0;
if (isset($_SESSION['new_payment_id'])) {
    $new_payment_id = intval($_SESSION['new_payment_id']);
    unset($_SESSION['new_payment_id']);
}

// ---------------------------------------------------------
// POST Handlers (Add and Edit operations)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add Payment Handler
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $reservation_id = intval($_POST['reservation_id']);
        $amount = floatval($_POST['amount']);
        $method = trim($_POST['payment_method']);
        $status = trim($_POST['status']);
        
        if (empty($reservation_id) || $amount <= 0 || empty($method) || empty($status)) {
            $_SESSION['error_msg'] = "All fields are required and amount must be positive.";
        } else {
            try {
                // Register transaction
                $stmt = $pdo->prepare("INSERT INTO `payments` (`reservation_id`, `amount`, `payment_method`, `status`) VALUES (?, ?, ?, ?)");
                $stmt->execute([$reservation_id, $amount, $method, $status]);
                $new_pay_id = $pdo->lastInsertId();
                $_SESSION['new_payment_id'] = $new_pay_id;
                
                $_SESSION['success_msg'] = "Payment transaction of " . get_setting('currency', 'GHS') . " " . number_format($amount, 2) . " registered successfully. <a href='print_receipt.php?id=" . $new_pay_id . "' target='_blank' class='btn btn-sm btn-success text-white ms-2 fw-semibold shadow-sm'><i class='bi bi-printer me-1'></i> Print Client Receipt Now</a>";
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: payments.php");
        exit;
    }

    // Edit Payment Handler
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $reservation_id = intval($_POST['reservation_id']);
        $amount = floatval($_POST['amount']);
        $method = trim($_POST['payment_method']);
        $status = trim($_POST['status']);
        
        if (empty($reservation_id) || $amount <= 0 || empty($method) || empty($status)) {
            $_SESSION['error_msg'] = "All fields are required.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE `payments` SET `reservation_id` = ?, `amount` = ?, `payment_method` = ?, `status` = ? WHERE `id` = ?");
                $stmt->execute([$reservation_id, $amount, $method, $status, $id]);
                
                $_SESSION['success_msg'] = "Payment details updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: payments.php");
        exit;
    }
}

// ---------------------------------------------------------
// GET Handlers (Delete and Edit modal autoload)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM `payments` WHERE `id` = ?");
        $stmt->execute([$id]);
        $_SESSION['success_msg'] = "Payment transaction deleted.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error deleting payment: " . $e->getMessage();
    }
    header("Location: payments.php");
    exit;
}

// Fetch single payment for edit
$edit_pay = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_stmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_pay = $edit_stmt->fetch();
}

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$payments = [];

try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE(r.check_in_date, b.check_in_date) as check_in_date, 
                   COALESCE(r.check_out_date, b.check_out_date) as check_out_date, 
                   COALESCE(g.first_name, b.customer_name, 'Guest') as first_name, 
                   COALESCE(g.last_name, '') as last_name, 
                   COALESCE(rm.room_number, b_rm.room_number, 'N/A') as room_number
            FROM `payments` p
            LEFT JOIN `reservations` r ON p.reservation_id = r.id
            LEFT JOIN `guests` g ON r.guest_id = g.id
            LEFT JOIN `rooms` rm ON r.room_id = rm.id
            LEFT JOIN `bookings` b ON p.booking_id = b.id
            LEFT JOIN `rooms` b_rm ON b.room_id = b_rm.id
            WHERE g.first_name LIKE ? OR g.last_name LIKE ? OR b.customer_name LIKE ? OR rm.room_number LIKE ? OR b_rm.room_number LIKE ? OR p.payment_method LIKE ? OR p.status LIKE ? OR p.payment_reference LIKE ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("
            SELECT p.*, 
                   COALESCE(r.check_in_date, b.check_in_date) as check_in_date, 
                   COALESCE(r.check_out_date, b.check_out_date) as check_out_date, 
                   COALESCE(g.first_name, b.customer_name, 'Guest') as first_name, 
                   COALESCE(g.last_name, '') as last_name, 
                   COALESCE(rm.room_number, b_rm.room_number, 'N/A') as room_number
            FROM `payments` p
            LEFT JOIN `reservations` r ON p.reservation_id = r.id
            LEFT JOIN `guests` g ON r.guest_id = g.id
            LEFT JOIN `rooms` rm ON r.room_id = rm.id
            LEFT JOIN `bookings` b ON p.booking_id = b.id
            LEFT JOIN `rooms` b_rm ON b.room_id = b_rm.id
            ORDER BY p.payment_date DESC
        ");
    }
    $payments = $stmt->fetchAll();
    
    // Fetch all rooms and their active reservations to display in the dropdown.
    $res_stmt = $pdo->query("
        SELECT rm.id as room_id, rm.room_number, rm.type as room_type,
               r.id as reservation_id, g.first_name, g.last_name, r.total_price,
               COALESCE((SELECT SUM(amount) FROM `payments` WHERE reservation_id = r.id AND status = 'completed'), 0.00) as total_paid
        FROM `rooms` rm
        LEFT JOIN `reservations` r ON rm.id = r.room_id AND r.status NOT IN ('cancelled', 'checked_out')
        LEFT JOIN `guests` g ON r.guest_id = g.id
        ORDER BY rm.room_number ASC
    ");
    $reservations_list = $res_stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database operation failed: " . $e->getMessage();
}

// Pre-selected reservation ID checking (from checkout action link)
$preselected_reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$open_add_modal = (isset($_GET['action']) && $_GET['action'] === 'new') || ($preselected_reservation_id > 0);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
        <div>
            <h1 class="h3 text-dark mb-1">Payments Management</h1>
            <p class="text-muted mb-0">Record and track transactions and invoice balances.</p>
        </div>
        <div class="mt-2 mt-sm-0 d-flex gap-2">
            <button type="button" class="btn btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#quickPrintModal">
                <i class="bi bi-printer me-1"></i> Print Client Receipt
            </button>
            <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="bi bi-plus-circle me-1"></i> Record Payment
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <strong>Success!</strong> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filters -->
    <div class="card card-premium mb-4">
        <div class="card-body">
            <form method="GET" action="payments.php" class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">Search</span>
                        <input type="text" name="search" class="form-control form-control-custom border-start-0 ps-0" 
                               placeholder="Search by guest, room, payment method, or status..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary-custom">Filter</button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-6 col-md-2 d-grid">
                        <a href="payments.php" class="btn btn-light border">Reset</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card card-premium">
        <div class="card-body p-0">
            <?php if (count($payments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Transaction Date</th>
                                <th>Reservation / Guest</th>
                                <th>Payment Method</th>
                                <th>Amount Paid</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): 
                                $statusClass = 'badge-pay-' . $pay['status'];
                                $statusLabel = ucfirst($pay['status']);
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo date('Y-m-d H:i', strtotime($pay['payment_date'])); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border mb-1">Room <?php echo htmlspecialchars($pay['room_number']); ?> Booking</span>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?php echo htmlspecialchars($pay['payment_method']); ?></span>
                                    </td>
                                    <td class="fw-bold text-success"><?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?> <?php echo number_format($pay['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge-custom <?php echo $statusClass; ?>">
                                            <?php echo $statusLabel; ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="print_receipt.php?id=<?php echo $pay['id']; ?>" 
                                           target="_blank"
                                           class="btn btn-sm btn-success text-white me-1 rounded-pill px-3 fw-semibold shadow-sm"
                                           title="Print Official Receipt for Client">
                                            <i class="bi bi-printer me-1"></i> Print Receipt
                                        </a>
                                        <a href="payments.php?edit_id=<?php echo $pay['id']; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="btn btn-sm btn-outline-primary me-1 rounded-pill px-2"
                                           title="Edit Transaction">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="payments.php?action=delete&id=<?php echo $pay['id']; ?>" 
                                           onclick="return confirmDelete('Payment')" 
                                           class="btn btn-sm btn-outline-danger rounded-pill px-2"
                                           title="Delete Transaction">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="mt-3 fw-semibold">No Transactions Recorded</h5>
                    <p class="text-muted">There are no records matching your query.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Add Payment Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="addPaymentModalLabel">Record New Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addPaymentForm" method="POST" action="payments.php" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label for="reservation_id" class="form-label fw-semibold text-dark">Select Room (Reservation)</label>
                        <select name="reservation_id" class="form-select form-control-custom reservation-select" required>
                            <option value="" disabled <?php echo ($preselected_reservation_id == 0) ? 'selected' : ''; ?>>Choose room reservation...</option>
                            <?php foreach ($reservations_list as $res): 
                                if ($res['reservation_id']) {
                                    $balance = max(0, $res['total_price'] - $res['total_paid']);
                                    $selectedStr = ($res['reservation_id'] == $preselected_reservation_id) ? 'selected' : '';
                                    $disabledStr = '';
                                    $labelText = "Room " . htmlspecialchars($res['room_number']) . " - " . htmlspecialchars($res['first_name'] . ' ' . $res['last_name']) . " (Bal: " . htmlspecialchars(get_setting('currency', 'GHS')) . " " . number_format($balance, 2) . ")";
                                    $val = $res['reservation_id'];
                                } else {
                                    $balance = 0;
                                    $selectedStr = '';
                                    $disabledStr = 'disabled';
                                    $labelText = "Room " . htmlspecialchars($res['room_number']) . " - (No Active Reservation)";
                                    $val = "";
                                }
                            ?>
                                <option value="<?php echo $val; ?>" 
                                        data-total="<?php echo $res['total_price'] ?? 0; ?>" 
                                        data-paid="<?php echo $res['total_paid'] ?? 0; ?>" 
                                        data-balance="<?php echo $balance; ?>"
                                        <?php echo $selectedStr; ?>
                                        <?php echo $disabledStr; ?>>
                                    <?php echo $labelText; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label fw-semibold text-dark">
                            Payment Amount (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>) 
                            <span class="balance-badge badge bg-secondary ms-2">Remaining Balance: <?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?> 0.00</span>
                        </label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-custom amount-input" placeholder="0.00" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label fw-semibold text-dark">Payment Method</label>
                        <select name="payment_method" class="form-select form-control-custom" required>
                            <option value="Credit Card" selected>Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label fw-semibold text-dark">Payment Status</label>
                        <select name="status" class="form-select form-control-custom" required>
                            <option value="completed" selected>Completed</option>
                            <option value="pending">Pending</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                    
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom px-4">Record Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Edit Payment Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="editPaymentModalLabel">Edit Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="window.history.replaceState({}, '', 'payments.php');"></button>
            </div>
            <?php if ($edit_pay): ?>
                <form id="editPaymentForm" method="POST" action="payments.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_pay['id']; ?>">
                    <div class="modal-body p-4">
                        
                        <div class="mb-3">
                            <label for="edit_reservation_id" class="form-label fw-semibold text-dark">Select Room (Reservation)</label>
                            <select name="reservation_id" id="edit_reservation_id" class="form-select form-control-custom" required>
                                <?php foreach ($reservations_list as $res): 
                                    if ($res['reservation_id']) {
                                        $selectedStr = ($edit_pay['reservation_id'] == $res['reservation_id']) ? 'selected' : '';
                                        $disabledStr = '';
                                        $labelText = "Room " . htmlspecialchars($res['room_number']) . " - " . htmlspecialchars($res['first_name'] . ' ' . $res['last_name']);
                                        $val = $res['reservation_id'];
                                    } else {
                                        $selectedStr = '';
                                        $disabledStr = 'disabled';
                                        $labelText = "Room " . htmlspecialchars($res['room_number']) . " - (No Active Reservation)";
                                        $val = "";
                                    }
                                ?>
                                    <option value="<?php echo $val; ?>" <?php echo $selectedStr; ?> <?php echo $disabledStr; ?>>
                                        <?php echo $labelText; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_amount" class="form-label fw-semibold text-dark">Payment Amount (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>)</label>
                            <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($edit_pay['amount']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_payment_method" class="form-label fw-semibold text-dark">Payment Method</label>
                            <select name="payment_method" id="edit_payment_method" class="form-select form-control-custom" required>
                                <option value="Credit Card" <?php echo ($edit_pay['payment_method'] === 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="Debit Card" <?php echo ($edit_pay['payment_method'] === 'Debit Card') ? 'selected' : ''; ?>>Debit Card</option>
                                <option value="Cash" <?php echo ($edit_pay['payment_method'] === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="Bank Transfer" <?php echo ($edit_pay['payment_method'] === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label fw-semibold text-dark">Payment Status</label>
                            <select name="status" id="edit_status" class="form-select form-control-custom" required>
                                <option value="completed" <?php echo ($edit_pay['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="pending" <?php echo ($edit_pay['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="refunded" <?php echo ($edit_pay['status'] === 'refunded') ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        
                    </div>
                    <div class="modal-footer p-4 pt-0 border-0">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal" onclick="window.history.replaceState({}, '', 'payments.php');">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom px-4">Update Details</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Quick Print Receipt Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="quickPrintModal" tabindex="-1" aria-labelledby="quickPrintModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="quickPrintModalLabel"><i class="bi bi-printer me-2 text-primary"></i>Print Client Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Choose a reservation or enter a transaction number to generate an official print/PDF receipt for your client.</p>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold text-dark">Option 1: Select by Guest Reservation</label>
                    <select id="quickPrintResSelect" class="form-select form-control-custom">
                        <option value="">-- Choose Guest Reservation --</option>
                        <?php foreach ($reservations_list as $res): 
                            if (!empty($res['reservation_id'])): ?>
                                <option value="<?= $res['reservation_id'] ?>">
                                    Room <?= htmlspecialchars($res['room_number']) ?> - <?= htmlspecialchars($res['first_name'] . ' ' . $res['last_name']) ?> (Total: <?= htmlspecialchars(get_setting('currency', 'GHS')) ?> <?= number_format($res['total_price'], 2) ?>)
                                </option>
                        <?php endif; endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary-custom w-100 mt-2" onclick="printFromResSelect()">
                        <i class="bi bi-printer me-1"></i> Print Receipt for Selected Reservation
                    </button>
                </div>

                <div class="position-relative my-4 text-center">
                    <hr class="text-muted">
                    <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small fw-semibold">OR</span>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-semibold text-dark">Option 2: Direct Payment ID / Receipt #</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted">#</span>
                        <input type="number" id="quickPrintPaymentId" class="form-control form-control-custom" placeholder="e.g. 1" min="1">
                        <button type="button" class="btn btn-outline-primary" onclick="printFromPaymentId()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer p-4 pt-0 border-0">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Payment Success & Prompt Receipt Modal
     --------------------------------------------------------- -->
<?php if ($new_payment_id > 0): ?>
<div class="modal fade" id="newPaymentSuccessModal" tabindex="-1" aria-labelledby="newPaymentSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom text-center p-3">
            <div class="modal-body p-4">
                <div class="mb-3 text-success">
                    <i class="bi bi-check-circle-fill display-4"></i>
                </div>
                <h4 class="fw-bold mb-2">Payment Registered!</h4>
                <p class="text-muted mb-4">The payment transaction has been saved to the database. Would you like to print the official client receipt now?</p>
                <div class="d-grid gap-2">
                    <a href="print_receipt.php?id=<?= $new_payment_id ?>" target="_blank" class="btn btn-success py-3 fw-semibold shadow-sm" onclick="bootstrap.Modal.getInstance(document.getElementById('newPaymentSuccessModal')).hide();">
                        <i class="bi bi-printer me-1"></i> Print Client Receipt (Opens in New Tab)
                    </a>
                    <button type="button" class="btn btn-light border py-2" data-bs-dismiss="modal">
                        Dismiss & View Table
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function printFromResSelect() {
    var sel = document.getElementById('quickPrintResSelect');
    if (!sel.value) {
        alert('Please choose a guest reservation from the dropdown.');
        return;
    }
    window.open('print_receipt.php?reservation_id=' + encodeURIComponent(sel.value), '_blank');
}

function printFromPaymentId() {
    var idInput = document.getElementById('quickPrintPaymentId');
    if (!idInput.value || parseInt(idInput.value) <= 0) {
        alert('Please enter a valid Payment ID number.');
        return;
    }
    window.open('print_receipt.php?id=' + encodeURIComponent(idInput.value), '_blank');
}
</script>

<?php 
// Show modals programmatically depending on requests
if ($edit_pay): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
            myModal.show();
        });
    </script>
<?php endif; ?>

<?php if ($open_add_modal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('addPaymentModal'));
            myModal.show();
            // Trigger balance change logic initially
            const selectEl = document.querySelector('.reservation-select');
            if (selectEl) {
                selectEl.dispatchEvent(new Event('change'));
            }
        });
    </script>
<?php endif; ?>

<?php if ($new_payment_id > 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var successModal = new bootstrap.Modal(document.getElementById('newPaymentSuccessModal'));
            successModal.show();
        });
    </script>
<?php endif; ?>

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

    // Interactive Remaining Balance Calculator on reservation selection change
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.querySelector('.reservation-select');
        const amountInput = document.querySelector('.amount-input');
        const badge = document.querySelector('.balance-badge');
        const currency = "<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>";

        if (select && amountInput) {
            select.addEventListener('change', function() {
                const opt = select.options[select.selectedIndex];
                if (opt && opt.value !== "") {
                    const balance = parseFloat(opt.getAttribute('data-balance')) || 0;
                    amountInput.value = balance.toFixed(2);
                    if (badge) {
                        badge.textContent = 'Remaining Balance: ' + currency + ' ' + balance.toFixed(2);
                        badge.className = (balance > 0) ? 'balance-badge badge bg-warning text-dark ms-2' : 'balance-badge badge bg-success ms-2';
                    }
                } else {
                    amountInput.value = '';
                    if (badge) {
                        badge.textContent = 'Remaining Balance: ' + currency + ' 0.00';
                        badge.className = 'balance-badge badge bg-secondary ms-2';
                    }
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
