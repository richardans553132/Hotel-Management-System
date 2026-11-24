<?php
/**
 * Rooms Management CRUD Page
 * 
 * Implements listing, searching, adding, editing, and deleting rooms.
 * Uses PDO prepared statements and Bootstrap modals for user input.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check Admin Access
require_auth();

$active_page = 'rooms';
$page_title = 'Manage Rooms';

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

// ---------------------------------------------------------
// POST Handlers (Add and Edit operations)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add Room Handler
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $room_number = trim($_POST['room_number']);
        $type = trim($_POST['type']);
        $price = trim($_POST['price_per_night']);
        $status = trim($_POST['status']);
        
        if (empty($room_number) || empty($type) || empty($price) || empty($status)) {
            $_SESSION['error_msg'] = "All fields are required.";
        } else {
            try {
                // Check duplicate room number
                $check = $pdo->prepare("SELECT COUNT(*) FROM `rooms` WHERE `room_number` = ?");
                $check->execute([$room_number]);
                if ($check->fetchColumn() > 0) {
                    $_SESSION['error_msg'] = "Room number '{$room_number}' already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `rooms` (`room_number`, `type`, `price_per_night`, `status`) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$room_number, $type, $price, $status]);
                    $_SESSION['success_msg'] = "Room {$room_number} added successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: rooms.php");
        exit;
    }

    // Edit Room Handler
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $room_number = trim($_POST['room_number']);
        $type = trim($_POST['type']);
        $price = trim($_POST['price_per_night']);
        $status = trim($_POST['status']);
        
        if (empty($room_number) || empty($type) || empty($price) || empty($status)) {
            $_SESSION['error_msg'] = "All fields are required.";
        } else {
            try {
                // Check if room number exists on other rooms
                $check = $pdo->prepare("SELECT COUNT(*) FROM `rooms` WHERE `room_number` = ? AND `id` != ?");
                $check->execute([$room_number, $id]);
                if ($check->fetchColumn() > 0) {
                    $_SESSION['error_msg'] = "Room number '{$room_number}' is already assigned to another room.";
                } else {
                    $stmt = $pdo->prepare("UPDATE `rooms` SET `room_number` = ?, `type` = ?, `price_per_night` = ?, `status` = ? WHERE `id` = ?");
                    $stmt->execute([$room_number, $type, $price, $status, $id]);
                    $_SESSION['success_msg'] = "Room {$room_number} updated successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: rooms.php");
        exit;
    }
}

// ---------------------------------------------------------
// GET Handlers (Delete and Edit modal auto-load)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        // Fetch room info first for the success log
        $info_stmt = $pdo->prepare("SELECT `room_number` FROM `rooms` WHERE `id` = ?");
        $info_stmt->execute([$id]);
        $room_number = $info_stmt->fetchColumn();
        
        if ($room_number) {
            $stmt = $pdo->prepare("DELETE FROM `rooms` WHERE `id` = ?");
            $stmt->execute([$id]);
            $_SESSION['success_msg'] = "Room {$room_number} was successfully deleted.";
        } else {
            $_SESSION['error_msg'] = "Room not found.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error deleting room: " . $e->getMessage();
    }
    header("Location: rooms.php");
    exit;
}

// Fetch single room if edit requested
$edit_room = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_stmt = $pdo->prepare("SELECT * FROM `rooms` WHERE `id` = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_room = $edit_stmt->fetch();
}

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rooms = [];

try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT * FROM `rooms` WHERE `room_number` LIKE ? OR `type` LIKE ? OR `status` LIKE ? ORDER BY `room_number` ASC");
        $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM `rooms` ORDER BY `room_number` ASC");
    }
    $rooms = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to load rooms: " . $e->getMessage();
}

// Check if setup triggered open modal action from URL
$open_add_modal = isset($_GET['action']) && $_GET['action'] === 'new';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb / Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
        <div>
            <h1 class="h3 text-dark mb-1">Rooms Management</h1>
            <p class="text-muted mb-0">Create, view, update, and manage resort rooms.</p>
        </div>
        <div class="mt-2 mt-sm-0">
            <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                Add New Room
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

    <!-- Search and Filters -->
    <div class="card card-premium mb-4">
        <div class="card-body">
            <form method="GET" action="rooms.php" class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">Search</span>
                        <input type="text" name="search" class="form-control form-control-custom border-start-0 ps-0" 
                               placeholder="Search by Room Number, Type, or Status..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary-custom">Filter</button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-6 col-md-2 d-grid">
                        <a href="rooms.php" class="btn btn-light border">Reset</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Rooms Table -->
    <div class="card card-premium">
        <div class="card-body p-0">
            <?php if (count($rooms) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Room Number</th>
                                <th>Room Type</th>
                                <th>Price per Night</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): 
                                $statusClass = 'badge-room-' . $room['status'];
                                $statusLabel = ucfirst($room['status']);
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark">Room <?php echo htmlspecialchars($room['room_number']); ?></td>
                                    <td><?php echo htmlspecialchars($room['type']); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?> <?php echo number_format($room['price_per_night'], 2); ?></td>
                                    <td>
                                        <span class="badge-custom <?php echo $statusClass; ?>">
                                            <?php echo $statusLabel; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="rooms.php?edit_id=<?php echo $room['id']; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="btn btn-sm btn-outline-primary me-1 rounded-pill px-3">
                                            Edit
                                        </a>
                                        <a href="rooms.php?action=delete&id=<?php echo $room['id']; ?>" 
                                           onclick="return confirmDelete('Room')" 
                                           class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="mt-3 fw-semibold">No Rooms Found</h5>
                    <p class="text-muted">There are no records matching your query.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Add Room Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="addRoomModalLabel">Add New Hotel Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="rooms.php" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="room_number" class="form-label fw-semibold text-dark">Room Number</label>
                        <input type="text" name="room_number" class="form-control form-control-custom" placeholder="e.g. 104" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label fw-semibold text-dark">Room Type</label>
                        <select name="type" class="form-select form-control-custom" required>
                            <option value="" disabled selected>Select room type</option>
                            <option value="Single Room">Single Room</option>
                            <option value="Double Room">Double Room</option>
                            <option value="Deluxe Suite">Deluxe Suite</option>
                            <option value="Executive Suite">Executive Suite</option>
                            <option value="Presidential Suite">Presidential Suite</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price_per_night" class="form-label fw-semibold text-dark">Price per Night (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>)</label>
                        <input type="number" step="0.01" name="price_per_night" class="form-control form-control-custom" placeholder="e.g. 120.00" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label fw-semibold text-dark">Status</label>
                        <select name="status" class="form-select form-control-custom" required>
                            <option value="available" selected>Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom px-4">Save Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Edit Room Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="editRoomModal" tabindex="-1" aria-labelledby="editRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="editRoomModalLabel">Edit Room Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="window.history.replaceState({}, '', 'rooms.php');"></button>
            </div>
            <?php if ($edit_room): ?>
                <form method="POST" action="rooms.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_room['id']; ?>">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="edit_room_number" class="form-label fw-semibold text-dark">Room Number</label>
                            <input type="text" name="room_number" id="edit_room_number" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($edit_room['room_number']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_type" class="form-label fw-semibold text-dark">Room Type</label>
                            <select name="type" id="edit_type" class="form-select form-control-custom" required>
                                <option value="Single Room" <?php echo ($edit_room['type'] === 'Single Room') ? 'selected' : ''; ?>>Single Room</option>
                                <option value="Double Room" <?php echo ($edit_room['type'] === 'Double Room') ? 'selected' : ''; ?>>Double Room</option>
                                <option value="Deluxe Suite" <?php echo ($edit_room['type'] === 'Deluxe Suite') ? 'selected' : ''; ?>>Deluxe Suite</option>
                                <option value="Executive Suite" <?php echo ($edit_room['type'] === 'Executive Suite') ? 'selected' : ''; ?>>Executive Suite</option>
                                <option value="Presidential Suite" <?php echo ($edit_room['type'] === 'Presidential Suite') ? 'selected' : ''; ?>>Presidential Suite</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_price_per_night" class="form-label fw-semibold text-dark">Price per Night (<?php echo htmlspecialchars(get_setting('currency', 'GHS')); ?>)</label>
                            <input type="number" step="0.01" name="price_per_night" id="edit_price_per_night" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($edit_room['price_per_night']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label fw-semibold text-dark">Status</label>
                            <select name="status" id="edit_status" class="form-select form-control-custom" required>
                                <option value="available" <?php echo ($edit_room['status'] === 'available') ? 'selected' : ''; ?>>Available</option>
                                <option value="occupied" <?php echo ($edit_room['status'] === 'occupied') ? 'selected' : ''; ?>>Occupied</option>
                                <option value="maintenance" <?php echo ($edit_room['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer p-4 pt-0 border-0">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal" onclick="window.history.replaceState({}, '', 'rooms.php');">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom px-4">Update Room</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Show modals programmatically depending on requests
if ($edit_room): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('editRoomModal'));
            myModal.show();
        });
    </script>
<?php endif; ?>

<?php if ($open_add_modal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('addRoomModal'));
            myModal.show();
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
