<?php
/**
 * Guests Management CRUD Page
 * 
 * Implements listing, searching, adding, editing, and deleting guests.
 * Restricts duplicate email records and provides form validations.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_helper.php';

// Check Admin Access
require_auth();

$active_page = 'guests';
$page_title = 'Manage Guests';

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
    
    // Add Guest Handler
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
            $_SESSION['error_msg'] = "First name, last name, email, and phone are required.";
        } else {
            // Automatically format Ghanaian phone numbers to international standard (+233)
            $formatted_phone = format_ghana_phone($phone);
            if ($formatted_phone) {
                $phone = $formatted_phone;
            }
            try {
                // Check duplicate email
                $check = $pdo->prepare("SELECT COUNT(*) FROM `guests` WHERE `email` = ?");
                $check->execute([$email]);
                if ($check->fetchColumn() > 0) {
                    $_SESSION['error_msg'] = "Guest email '{$email}' already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `guests` (`first_name`, `last_name`, `email`, `phone`, `address`) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $address]);
                    $_SESSION['success_msg'] = "Guest {$first_name} {$last_name} registered successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: guests.php");
        exit;
    }

    // Edit Guest Handler
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
            $_SESSION['error_msg'] = "First name, last name, email, and phone are required.";
        } else {
            // Automatically format Ghanaian phone numbers to international standard (+233)
            $formatted_phone = format_ghana_phone($phone);
            if ($formatted_phone) {
                $phone = $formatted_phone;
            }
            try {
                // Check if email exists on other guests
                $check = $pdo->prepare("SELECT COUNT(*) FROM `guests` WHERE `email` = ? AND `id` != ?");
                $check->execute([$email, $id]);
                if ($check->fetchColumn() > 0) {
                    $_SESSION['error_msg'] = "Email '{$email}' is already registered to another guest.";
                } else {
                    $stmt = $pdo->prepare("UPDATE `guests` SET `first_name` = ?, `last_name` = ?, `email` = ?, `phone` = ?, `address` = ? WHERE `id` = ?");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $address, $id]);
                    $_SESSION['success_msg'] = "Guest {$first_name} {$last_name} details updated successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: guests.php");
        exit;
    }
}

// ---------------------------------------------------------
// GET Handlers (Delete and Edit modal auto-load)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        // Fetch guest name for the alert logs
        $info_stmt = $pdo->prepare("SELECT `first_name`, `last_name` FROM `guests` WHERE `id` = ?");
        $info_stmt->execute([$id]);
        $guest = $info_stmt->fetch();
        
        if ($guest) {
            $stmt = $pdo->prepare("DELETE FROM `guests` WHERE `id` = ?");
            $stmt->execute([$id]);
            $_SESSION['success_msg'] = "Guest " . htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']) . " was successfully deleted.";
        } else {
            $_SESSION['error_msg'] = "Guest not found.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error deleting guest: " . $e->getMessage();
    }
    header("Location: guests.php");
    exit;
}

// Fetch single guest if edit requested
$edit_guest = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_stmt = $pdo->prepare("SELECT * FROM `guests` WHERE `id` = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_guest = $edit_stmt->fetch();
}

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$guests = [];

try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT * FROM `guests` WHERE `first_name` LIKE ? OR `last_name` LIKE ? OR `email` LIKE ? OR `phone` LIKE ? ORDER BY `last_name` ASC");
        $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM `guests` ORDER BY `last_name` ASC");
    }
    $guests = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to load guests: " . $e->getMessage();
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
            <h1 class="h3 text-dark mb-1">Guests Management</h1>
            <p class="text-muted mb-0">Create, view, update, and manage hotel guests profiles.</p>
        </div>
        <div class="mt-2 mt-sm-0">
            <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addGuestModal">
                Register New Guest
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
            <form method="GET" action="guests.php" class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">Search</span>
                        <input type="text" name="search" class="form-control form-control-custom border-start-0 ps-0" 
                               placeholder="Search by name, email, or phone number..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary-custom">Filter</button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-6 col-md-2 d-grid">
                        <a href="guests.php" class="btn btn-light border">Reset</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Guests Table -->
    <div class="card card-premium">
        <div class="card-body p-0">
            <?php if (count($guests) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone Number</th>
                                <th>Home Address</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guests as $g): ?>
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($g['first_name'] . ' ' . $g['last_name']); ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($g['email']); ?>" class="text-decoration-none text-primary">
                                            <?php echo htmlspecialchars($g['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="tel:<?php echo htmlspecialchars($g['phone']); ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($g['phone']); ?>
                                        </a>
                                    </td>
                                    <td class="text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($g['address'] ?? 'N/A'); ?></td>
                                    <td class="text-end">
                                        <a href="guests.php?edit_id=<?php echo $g['id']; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="btn btn-sm btn-outline-primary me-1 rounded-pill px-3">
                                            Edit
                                        </a>
                                        <a href="guests.php?action=delete&id=<?php echo $g['id']; ?>" 
                                           onclick="return confirmDelete('Guest')" 
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
                    <h5 class="mt-3 fw-semibold">No Guests Found</h5>
                    <p class="text-muted">There are no records matching your query.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Add Guest Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="addGuestModal" tabindex="-1" aria-labelledby="addGuestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="addGuestModalLabel">Register Guest</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="guests.php" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="first_name" class="form-label fw-semibold text-dark">First Name</label>
                            <input type="text" name="first_name" class="form-control form-control-custom" placeholder="e.g. John" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="last_name" class="form-label fw-semibold text-dark">Last Name</label>
                            <input type="text" name="last_name" class="form-control form-control-custom" placeholder="e.g. Doe" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-dark">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-custom" placeholder="e.g. john.doe@example.com" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold text-dark">Phone Number</label>
                        <input type="text" name="phone" class="form-control form-control-custom" placeholder="e.g. +123456789" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold text-dark">Home Address</label>
                        <textarea name="address" rows="3" class="form-control form-control-custom" placeholder="Enter home address..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom px-4">Register Guest</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ---------------------------------------------------------
     Edit Guest Modal
     --------------------------------------------------------- -->
<div class="modal fade" id="editGuestModal" tabindex="-1" aria-labelledby="editGuestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="editGuestModalLabel">Edit Guest Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="window.history.replaceState({}, '', 'guests.php');"></button>
            </div>
            <?php if ($edit_guest): ?>
                <form method="POST" action="guests.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_guest['id']; ?>">
                    <div class="modal-body p-4">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="edit_first_name" class="form-label fw-semibold text-dark">First Name</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control form-control-custom" 
                                       value="<?php echo htmlspecialchars($edit_guest['first_name']); ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="edit_last_name" class="form-label fw-semibold text-dark">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control form-control-custom" 
                                       value="<?php echo htmlspecialchars($edit_guest['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label fw-semibold text-dark">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($edit_guest['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label fw-semibold text-dark">Phone Number</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($edit_guest['phone']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_address" class="form-label fw-semibold text-dark">Home Address</label>
                            <textarea name="address" id="edit_address" rows="3" class="form-control form-control-custom"><?php echo htmlspecialchars($edit_guest['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-4 pt-0 border-0">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal" onclick="window.history.replaceState({}, '', 'guests.php');">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom px-4">Update Details</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Show modals programmatically depending on requests
if ($edit_guest): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('editGuestModal'));
            myModal.show();
        });
    </script>
<?php endif; ?>

<?php if ($open_add_modal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('addGuestModal'));
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
