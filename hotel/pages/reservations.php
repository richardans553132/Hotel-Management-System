<?php
/**
 * Reservations Management CRUD Page
 * 
 * Implements listing, searching, booking, editing, and deleting reservations.
 * Employs prepared statements (PDO) and includes overlapping double-booking prevention.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_helper.php';

// Check Admin Access
require_auth();

$active_page = 'reservations';
$page_title = 'Manage Reservations';

$error = '';
$success = '';
$warning = '';

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
if (isset($_SESSION['warning_msg'])) {
    $warning = $_SESSION['warning_msg'];
    unset($_SESSION['warning_msg']);
}

// Initialize anti-duplicate submission token for new bookings
if (empty($_SESSION['reservation_form_token'])) {
    $_SESSION['reservation_form_token'] = bin2hex(random_bytes(16));
}

// ---------------------------------------------------------
// POST Handlers (Add and Edit operations)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add Reservation Handler
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        // Prevent duplicate submissions via token validation
        $submitted_token = $_POST['form_token'] ?? '';
        $session_token = $_SESSION['reservation_form_token'] ?? '';

        if (!empty($session_token) && !empty($submitted_token)) {
            if ($submitted_token !== $session_token) {
                $_SESSION['warning_msg'] = "This booking request was already submitted or expired. Duplicate submission prevented.";
                header("Location: reservations.php");
                exit;
            }
            // Consume token to prevent multiple clicks
            unset($_SESSION['reservation_form_token']);
        }

        $guest_id = intval($_POST['guest_id']);
        $room_id = intval($_POST['room_id']);
        $check_in = trim($_POST['check_in_date']);
        $check_out = trim($_POST['check_out_date']);
        $total_price = floatval($_POST['total_price']);
        $status = trim($_POST['status']);
        
        // Basic Validations
        if (empty($guest_id) || empty($room_id) || empty($check_in) || empty($check_out) || $total_price <= 0 || empty($status)) {
            $_SESSION['error_msg'] = "All fields are required and total price must be calculated.";
        } elseif (strtotime($check_out) <= strtotime($check_in)) {
            $_SESSION['error_msg'] = "Check-out date must be after the check-in date.";
        } else {
            try {
                // Check for overlapping bookings for this room (ignore cancelled/checked_out reservations)
                $overlap_stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM `reservations` 
                    WHERE `room_id` = ? 
                      AND `status` NOT IN ('cancelled', 'checked_out')
                      AND ((`check_in_date` < ? AND `check_out_date` > ?))
                ");
                $overlap_stmt->execute([$room_id, $check_out, $check_in]);
                
                if ($overlap_stmt->fetchColumn() > 0) {
                    $_SESSION['error_msg'] = "Double Booking Alert: The selected room is already booked/occupied during these dates.";
                } else {
                    // 1. Create booking in MySQL database
                    $stmt = $pdo->prepare("INSERT INTO `reservations` (`guest_id`, `room_id`, `check_in_date`, `check_out_date`, `total_price`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$guest_id, $room_id, $check_in, $check_out, $total_price, $status]);
                    $new_booking_id = (int)$pdo->lastInsertId();
                    
                    // 2. Update room status if guest checked in immediately
                    if ($status === 'checked_in') {
                        $update_room = $pdo->prepare("UPDATE `rooms` SET `status` = 'occupied' WHERE `id` = ?");
                        $update_room->execute([$room_id]);
                    }
                    
                    // 3. Immediately send automatic SMS booking confirmation to guest
                    $sms_result = send_booking_confirmation_sms($pdo, $new_booking_id);

                    if ($sms_result['success']) {
                        $_SESSION['success_msg'] = "Reservation created successfully! Confirmation SMS sent to " . htmlspecialchars($sms_result['formatted_phone']) . ".";
                    } else {
                        // The booking MUST remain saved even if SMS delivery fails
                        $_SESSION['success_msg'] = "Reservation created successfully.";
                        $_SESSION['warning_msg'] = "Note: SMS notification could not be sent. " . htmlspecialchars($sms_result['error'] ?? $sms_result['message']);
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: reservations.php");
        exit;
    }

    // Edit Reservation Handler
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $guest_id = intval($_POST['guest_id']);
        $room_id = intval($_POST['room_id']);
        $check_in = trim($_POST['check_in_date']);
        $check_out = trim($_POST['check_out_date']);
        $total_price = floatval($_POST['total_price']);
        $status = trim($_POST['status']);
        
        if (empty($guest_id) || empty($room_id) || empty($check_in) || empty($check_out) || $total_price <= 0 || empty($status)) {
            $_SESSION['error_msg'] = "All fields are required.";
        } elseif (strtotime($check_out) <= strtotime($check_in)) {
            $_SESSION['error_msg'] = "Check-out date must be after the check-in date.";
        } else {
            try {
                // Check overlapping bookings excluding this reservation
                $overlap_stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM `reservations` 
                    WHERE `room_id` = ? 
                      AND `id` != ?
                      AND `status` NOT IN ('cancelled', 'checked_out')
                      AND ((`check_in_date` < ? AND `check_out_date` > ?))
                ");
                $overlap_stmt->execute([$room_id, $id, $check_out, $check_in]);
                
                if ($overlap_stmt->fetchColumn() > 0) {
                    $_SESSION['error_msg'] = "Conflict: The room is occupied by another guest during these dates.";
                } else {
                    // Fetch original room & status to check for status transitions
                    $orig_stmt = $pdo->prepare("SELECT `room_id`, `status` FROM `reservations` WHERE `id` = ?");
                    $orig_stmt->execute([$id]);
                    $original = $orig_stmt->fetch();

                    // Update reservation
                    $stmt = $pdo->prepare("UPDATE `reservations` SET `guest_id` = ?, `room_id` = ?, `check_in_date` = ?, `check_out_date` = ?, `total_price` = ?, `status` = ? WHERE `id` = ?");
                    $stmt->execute([$guest_id, $room_id, $check_in, $check_out, $total_price, $status, $id]);
                    
                    // Manage Room status transitions:
                    // If moving room or changing status
                    if ($original) {
                        // Reset old room to available if it changed or checked out
                        if ($original['room_id'] != $room_id || $status === 'checked_out' || $status === 'cancelled') {
                            $reset_room = $pdo->prepare("UPDATE `rooms` SET `status` = 'available' WHERE `id` = ?");
                            $reset_room->execute([$original['room_id']]);
                        }
                        // Set new room status based on check_in status
                        if ($status === 'checked_in') {
                            $set_room = $pdo->prepare("UPDATE `rooms` SET `status` = 'occupied' WHERE `id` = ?");
                            $set_room->execute([$room_id]);
                        }
                    }
                    
                    $_SESSION['success_msg'] = "Reservation updated successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: reservations.php");
        exit;
    }
}

// ---------------------------------------------------------
// GET Handlers (Delete and Edit modal autoload)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        // Fetch reservation info to release the room status if checked_in
        $info_stmt = $pdo->prepare("SELECT `room_id`, `status` FROM `reservations` WHERE `id` = ?");
        $info_stmt->execute([$id]);
        $res = $info_stmt->fetch();
        
        if ($res) {
            // Delete reservation
            $stmt = $pdo->prepare("DELETE FROM `reservations` WHERE `id` = ?");
            $stmt->execute([$id]);
            
            // Release the room to available if it was occupied/active
            if ($res['status'] === 'checked_in') {
                $release_room = $pdo->prepare("UPDATE `rooms` SET `status` = 'available' WHERE `id` = ?");
                $release_room->execute([$res['room_id']]);
            }
            
            $_SESSION['success_msg'] = "Reservation deleted and room status updated.";
        } else {
            $_SESSION['error_msg'] = "Reservation not found.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error deleting reservation: " . $e->getMessage();
    }
    header("Location: reservations.php");
    exit;
}

// Fetch single reservation for edit
$edit_res = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_stmt = $pdo->prepare("SELECT * FROM `reservations` WHERE `id` = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_res = $edit_stmt->fetch();
}

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$reservations = [];

try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, g.email, rm.room_number, rm.type
            FROM `reservations` r
            JOIN `guests` g ON r.guest_id = g.id
            JOIN `rooms` rm ON r.room_id = rm.id
            WHERE g.first_name LIKE ? OR g.last_name LIKE ? OR rm.room_number LIKE ? OR r.status LIKE ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("
            SELECT r.*, g.first_name, g.last_name, g.email, rm.room_number, rm.type
            FROM `reservations` r
            JOIN `guests` g ON r.guest_id = g.id
            JOIN `rooms` rm ON r.room_id = rm.id
            ORDER BY r.created_at DESC
        ");
    }
    $reservations = $stmt->fetchAll();
    
    // Fetch guests & rooms for form drop downs
    $guests = $pdo->query("SELECT `id`, `first_name`, `last_name` FROM `guests` ORDER BY `last_name` ASC")->fetchAll();
    $rooms = $pdo->query("SELECT `id`, `room_number`, `type`, `price_per_night`, `status` FROM `rooms` ORDER BY `room_number` ASC")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database operation failed: " . $e->getMessage();
}

// Check if setup triggered open modal action from URL
$open_add_modal = isset($_GET['action']) && $_GET['action'] === 'new';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
        <div>
            <h1 class="h3 text-dark mb-1">Reservations Management</h1>
            <p class="text-muted mb-0">Record, check availability, and manage guest bookings.</p>
        </div>
        <div class="mt-2 mt-sm-0">
            <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addReservationModal">
                New Booking
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
            <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($warning)): ?>
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="alert">
            <strong>Notice:</strong> <?php echo htmlspecialchars($warning); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filters -->
    <div class="card card-premium mb-4">
        <div class="card-body">
            <form method="GET" action="reservations.php" class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">Search</span>
                        <input type="text" name="search" class="form-control form-control-custom border-start-0 ps-0" 
                               placeholder="Search by guest name, room number, or status..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary-custom">Filter</button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-6 col-md-2 d-grid">
                        <a href="reservations.php" class="btn btn-light border">Reset</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Reservations Table -->
    <div class="card card-premium">
        <div class="card-body p-0">
            <?php if (count($reservations) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room Number</th>
                                <th>Check In / Out Dates</th>
                                <th>Total Cost</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $res): 
                                $statusClass = 'badge-res-' . $res['status'];
                                $statusLabel = str_replace('_', ' ', ucfirst($res['status']));
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($res['email']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">Room <?php echo htmlspecialchars($res['room_number']); ?></span>
                                        <div class="small text-muted"><?php echo htmlspecialchars($res['type']); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark">In: <?php echo htmlspecialchars($res['check_in_date']); ?></div>
                                        <div class="small text-muted">Out: <?php echo htmlspecialchars($res['check_out_date']); ?></div>
                                    </td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?> <?php echo number_format($res['total_price'], 2); ?></td>
                                    <td>
                                        <span class="badge-custom <?php echo $statusClass; ?>">
                                            <?php echo $statusLabel; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="/hotel/pages/print_receipt.php?reservation_id=<?php echo $res['id']; ?>" 
                                               target="_blank"
                                               class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-1"
                                               title="Print Client Receipt / Guest Folio">
                                                <i class="bi bi-printer me-1"></i> Receipt
                                            </a>
                                            <a href="reservations.php?edit_id=<?php echo $res['id']; ?>&search=<?php echo urlencode($search); ?>" 
                                               class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">
                                                Edit
                                            </a>
                                            <a href="/hotel/pages/payments.php?action=new&reservation_id=<?php echo $res['id']; ?>" 
                                               class="btn btn-sm btn-outline-success rounded-pill px-3 me-1">
                                                Pay
                                            </a>
                                            <a href="reservations.php?action=delete&id=<?php echo $res['id']; ?>" 
                                               onclick="return confirmDelete('Reservation')" 
                                               class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="mt-3 fw-semibold">No Reservations Found</h5>
                    <p class="text-muted">There are no records matching your query.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Add Reservation Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="addReservationModal" tabindex="-1" aria-labelledby="addReservationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="addReservationModalLabel">Book New Reservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addReservationForm" method="POST" action="reservations.php" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['reservation_form_token']); ?>">
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label for="guest_id" class="form-label fw-semibold text-dark">Select Guest</label>
                        <select name="guest_id" class="form-select form-control-custom" required>
                            <option value="" disabled selected>Choose a guest...</option>
                            <?php foreach ($guests as $g): ?>
                                <option value="<?php echo $g['id']; ?>">
                                    <?php echo htmlspecialchars($g['first_name'] . ' ' . $g['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="room_id" class="form-label fw-semibold text-dark">Select Room</label>
                        <select name="room_id" class="form-select form-control-custom room-select" required>
                            <option value="" disabled selected>Choose a room...</option>
                            <?php foreach ($rooms as $r): 
                                $statusNote = ($r['status'] !== 'available') ? " (" . ucfirst($r['status']) . ")" : "";
                            ?>
                                <option value="<?php echo $r['id']; ?>" data-price="<?php echo $r['price_per_night']; ?>">
                                    Room <?php echo htmlspecialchars($r['room_number']); ?> - <?php echo htmlspecialchars($r['type']); ?> (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?><?php echo number_format($r['price_per_night'], 2); ?>/night) <?php echo $statusNote; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="check_in_date" class="form-label fw-semibold text-dark">Check-in Date</label>
                            <input type="date" name="check_in_date" class="form-control form-control-custom check-in-date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="check_out_date" class="form-label fw-semibold text-dark">Check-out Date</label>
                            <input type="date" name="check_out_date" class="form-control form-control-custom check-out-date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="total_price" class="form-label fw-semibold text-dark">
                            Total Price (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>)
                            <span class="nights-count-badge badge bg-secondary ms-2">0 nights</span>
                        </label>
                        <input type="number" step="0.01" name="total_price" class="form-control form-control-custom total-price-input" readonly value="0.00" required>
                        <div class="form-text small">Calculated automatically based on nights stay and room rate.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label fw-semibold text-dark">Reservation Status</label>
                        <select name="status" class="form-select form-control-custom" required>
                            <option value="pending" selected>Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="checked_in">Checked In</option>
                            <option value="checked_out">Checked Out</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnCreateBooking" class="btn btn-primary-custom px-4">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Edit Reservation Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="editReservationModal" tabindex="-1" aria-labelledby="editReservationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="editReservationModalLabel">Edit Reservation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="window.history.replaceState({}, '', 'reservations.php');"></button>
            </div>
            <?php if ($edit_res): ?>
                <form id="editReservationForm" method="POST" action="reservations.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_res['id']; ?>">
                    <div class="modal-body p-4">
                        
                        <div class="mb-3">
                            <label for="edit_guest_id" class="form-label fw-semibold text-dark">Guest</label>
                            <select name="guest_id" id="edit_guest_id" class="form-select form-control-custom" required>
                                <?php foreach ($guests as $g): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php echo ($edit_res['guest_id'] == $g['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['first_name'] . ' ' . $g['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_room_id" class="form-label fw-semibold text-dark">Room</label>
                            <select name="room_id" id="edit_room_id" class="form-select form-control-custom room-select" required>
                                <?php foreach ($rooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>" data-price="<?php echo $r['price_per_night']; ?>" <?php echo ($edit_res['room_id'] == $r['id']) ? 'selected' : ''; ?>>
                                        Room <?php echo htmlspecialchars($r['room_number']); ?> - <?php echo htmlspecialchars($r['type']); ?> (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?><?php echo number_format($r['price_per_night'], 2); ?>/night)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="edit_check_in_date" class="form-label fw-semibold text-dark">Check-in Date</label>
                                <input type="date" name="check_in_date" id="edit_check_in_date" class="form-control form-control-custom check-in-date" 
                                       value="<?php echo htmlspecialchars($edit_res['check_in_date']); ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="edit_check_out_date" class="form-label fw-semibold text-dark">Check-out Date</label>
                                <input type="date" name="check_out_date" id="edit_check_out_date" class="form-control form-control-custom check-out-date" 
                                       value="<?php echo htmlspecialchars($edit_res['check_out_date']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_total_price" class="form-label fw-semibold text-dark">
                                Total Price (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>)
                                <span class="nights-count-badge badge bg-primary ms-2">Calculated</span>
                            </label>
                            <input type="number" step="0.01" name="total_price" id="edit_total_price" class="form-control form-control-custom total-price-input" 
                                   readonly value="<?php echo htmlspecialchars($edit_res['total_price']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label fw-semibold text-dark">Reservation Status</label>
                            <select name="status" id="edit_status" class="form-select form-control-custom" required>
                                <option value="pending" <?php echo ($edit_res['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo ($edit_res['status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="checked_in" <?php echo ($edit_res['status'] === 'checked_in') ? 'selected' : ''; ?>>Checked In</option>
                                <option value="checked_out" <?php echo ($edit_res['status'] === 'checked_out') ? 'selected' : ''; ?>>Checked Out</option>
                                <option value="cancelled" <?php echo ($edit_res['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                    </div>
                    <div class="modal-footer p-4 pt-0 border-0">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal" onclick="window.history.replaceState({}, '', 'reservations.php');">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom px-4">Update Booking</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Show modals programmatically depending on requests
if ($edit_res): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('editReservationModal'));
            myModal.show();
        });
    </script>
<?php endif; ?>

<?php if ($open_add_modal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('addReservationModal'));
            myModal.show();
        });
    </script>
<?php endif; ?>

<script>
    // Form client-side validations and double-submit prevention
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    } else if (form.id === 'addReservationForm') {
                        // Prevent multiple clicks from triggering duplicate bookings or duplicate SMS
                        var btn = form.querySelector('#btnCreateBooking');
                        if (btn) {
                            setTimeout(function () {
                                btn.disabled = true;
                                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Creating Booking & Sending SMS...';
                            }, 10);
                        }
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })()
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
