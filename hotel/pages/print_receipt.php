<?php
/**
 * Printable Payment Receipt & Client Invoice Page
 * 
 * Renders a clean, high-resolution receipt layout for client print/PDF.
 * Dynamically queries settings, payment, guest, and reservation/booking data.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Require user to be logged in
require_auth();

$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

$payment = null;
$payments_history = [];
$reservation = null;
$client_name = '';
$client_email = '';
$client_phone = '';
$client_address = '';
$room_number = '';
$room_type = '';
$price_per_night = 0.00;
$check_in_date = '';
$check_out_date = '';
$total_cost = 0.00;
$total_paid = 0.00;
$balance_remaining = 0.00;
$receipt_number = '';
$payment_date = '';
$payment_method = '';
$payment_status = 'completed';
$error = '';

try {
    if ($payment_id > 0) {
        // 1. Fetch by Payment ID
        $stmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $error = 'Payment record #' . htmlspecialchars($payment_id) . ' was not found in the database.';
        } else {
            $receipt_number = !empty($payment['payment_reference']) ? $payment['payment_reference'] : 'RC-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);
            $payment_date = $payment['payment_date'] ?? date('Y-m-d H:i:s');
            $payment_method = $payment['payment_method'] ?? 'Cash';
            $payment_status = $payment['status'] ?? 'completed';

            // Try to resolve linked reservation
            if (!empty($payment['reservation_id'])) {
                $res_stmt = $pdo->prepare("
                    SELECT r.*, g.first_name, g.last_name, g.email as guest_email, g.phone as guest_phone, g.address as guest_address,
                           rm.room_number, rm.type as room_type, rm.price_per_night
                    FROM `reservations` r
                    JOIN `guests` g ON r.guest_id = g.id
                    JOIN `rooms` rm ON r.room_id = rm.id
                    WHERE r.id = ?
                ");
                $res_stmt->execute([$payment['reservation_id']]);
                $reservation = $res_stmt->fetch();

                if ($reservation) {
                    $client_name = trim($reservation['first_name'] . ' ' . $reservation['last_name']);
                    $client_email = $reservation['guest_email'];
                    $client_phone = $reservation['guest_phone'];
                    $client_address = $reservation['guest_address'] ?? '';
                    $room_number = $reservation['room_number'];
                    $room_type = $reservation['room_type'];
                    $price_per_night = floatval($reservation['price_per_night']);
                    $check_in_date = $reservation['check_in_date'];
                    $check_out_date = $reservation['check_out_date'];
                    $total_cost = floatval($reservation['total_price']);

                    // Fetch cumulative payments for this reservation up to this payment
                    $paid_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0.00) 
                        FROM `payments` 
                        WHERE reservation_id = ? AND status = 'completed' AND id <= ?
                    ");
                    $paid_stmt->execute([$payment['reservation_id'], $payment_id]);
                    $total_paid = floatval($paid_stmt->fetchColumn());

                    // All payment history
                    $hist_stmt = $pdo->prepare("SELECT * FROM `payments` WHERE reservation_id = ? ORDER BY payment_date ASC");
                    $hist_stmt->execute([$payment['reservation_id']]);
                    $payments_history = $hist_stmt->fetchAll();
                }
            }

            // Fallback: Check if payment is linked via bookings table
            if (!$reservation && !empty($payment['booking_id'])) {
                $bk_stmt = $pdo->prepare("
                    SELECT b.*, rm.room_number, rm.type as room_type, rm.price_per_night
                    FROM `bookings` b
                    JOIN `rooms` rm ON b.room_id = rm.id
                    WHERE b.id = ?
                ");
                $bk_stmt->execute([$payment['booking_id']]);
                $booking = $bk_stmt->fetch();

                if ($booking) {
                    $client_name = $booking['customer_name'];
                    $client_email = $booking['customer_email'];
                    $client_phone = $booking['customer_phone'];
                    $client_address = '';
                    $room_number = $booking['room_number'];
                    $room_type = $booking['room_type'];
                    $price_per_night = floatval($booking['price_per_night']);
                    $check_in_date = $booking['check_in_date'];
                    $check_out_date = $booking['check_out_date'];
                    $total_cost = floatval($booking['total_price']);

                    $paid_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0.00) 
                        FROM `payments` 
                        WHERE booking_id = ? AND status = 'completed' AND id <= ?
                    ");
                    $paid_stmt->execute([$payment['booking_id'], $payment_id]);
                    $total_paid = floatval($paid_stmt->fetchColumn());
                }
            }

            if (!$reservation && empty($booking)) {
                // Standalone payment without direct reservation link
                $client_name = 'Valued Guest';
                $total_cost = floatval($payment['amount']);
                $total_paid = floatval($payment['amount']);
            }
        }
    } elseif ($reservation_id > 0) {
        // 2. Fetch by Reservation ID (Folio / Full receipt)
        $res_stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, g.email as guest_email, g.phone as guest_phone, g.address as guest_address,
                   rm.room_number, rm.type as room_type, rm.price_per_night
            FROM `reservations` r
            JOIN `guests` g ON r.guest_id = g.id
            JOIN `rooms` rm ON r.room_id = rm.id
            WHERE r.id = ?
        ");
        $res_stmt->execute([$reservation_id]);
        $reservation = $res_stmt->fetch();

        if (!$reservation) {
            $error = 'Reservation #' . htmlspecialchars($reservation_id) . ' was not found.';
        } else {
            $client_name = trim($reservation['first_name'] . ' ' . $reservation['last_name']);
            $client_email = $reservation['guest_email'];
            $client_phone = $reservation['guest_phone'];
            $client_address = $reservation['guest_address'] ?? '';
            $room_number = $reservation['room_number'];
            $room_type = $reservation['room_type'];
            $price_per_night = floatval($reservation['price_per_night']);
            $check_in_date = $reservation['check_in_date'];
            $check_out_date = $reservation['check_out_date'];
            $total_cost = floatval($reservation['total_price']);

            // Get all payments for this reservation
            $pay_stmt = $pdo->prepare("SELECT * FROM `payments` WHERE reservation_id = ? ORDER BY id DESC");
            $pay_stmt->execute([$reservation_id]);
            $payments_history = $pay_stmt->fetchAll();

            if (!empty($payments_history)) {
                $payment = $payments_history[0]; // Latest payment
                $receipt_number = !empty($payment['payment_reference']) ? $payment['payment_reference'] : 'RC-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);
                $payment_date = $payment['payment_date'] ?? date('Y-m-d H:i:s');
                $payment_method = $payment['payment_method'] ?? 'Multiple';
                $payment_status = $payment['status'] ?? 'completed';

                $paid_sum = 0;
                foreach ($payments_history as $p_item) {
                    if ($p_item['status'] === 'completed') {
                        $paid_sum += floatval($p_item['amount']);
                    }
                }
                $total_paid = $paid_sum;
            } else {
                // No payment recorded yet
                $receipt_number = 'INV-RES-' . str_pad($reservation_id, 6, '0', STR_PAD_LEFT);
                $payment_date = date('Y-m-d H:i:s');
                $payment_method = 'Pending Payment';
                $payment_status = 'pending';
                $total_paid = 0.00;
                $payment = [
                    'id' => 0,
                    'amount' => 0.00,
                    'payment_method' => 'Unpaid',
                    'status' => 'pending',
                    'payment_date' => date('Y-m-d H:i:s')
                ];
            }
        }
    } else {
        $error = 'No payment ID or reservation ID was provided to generate a receipt.';
    }

    $balance_remaining = max(0.00, $total_cost - $total_paid);

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Calculate duration of stay
$nights = 1;
if (!empty($check_in_date) && !empty($check_out_date)) {
    $cin = new DateTime($check_in_date);
    $cout = new DateTime($check_out_date);
    $diff = $cin->diff($cout);
    $nights = max(1, $diff->days);
}

// Load Hotel Settings
$hotel_name = get_setting('hotel_name', 'Josil Hotel');
$hotel_email = get_setting('contact_email', 'info@josilhotel.com');
$hotel_phone = get_setting('contact_phone', '+233 123 456 789');
$hotel_address = get_setting('address', 'Accra, Ghana');
$currency = get_setting('currency', 'GHS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?= htmlspecialchars($receipt_number) ?> | <?= htmlspecialchars($hotel_name) ?></title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 30px 15px;
            margin: 0;
        }
        .action-bar {
            max-width: 820px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .receipt-card {
            max-width: 820px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 45px 50px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
            position: relative;
        }
        .receipt-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #1e3c72, #2a5298, #2ecc71);
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }
        .hotel-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: #1e3c72;
            font-size: 1.85rem;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        .receipt-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: #0f172a;
            font-size: 1.25rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .receipt-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
        }
        .receipt-table td {
            padding: 14px;
            font-size: 0.95rem;
        }
        .amount-highlight {
            font-size: 1.2rem;
            font-weight: 700;
            color: #10b981;
        }
        .badge-status-completed {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
        }
        .badge-status-pending {
            background-color: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
        }
        .signature-box {
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            margin-top: 35px;
            text-align: center;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                border: none !important;
                padding: 20px 0 !important;
                max-width: 100% !important;
            }
            .receipt-card::before {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Controls Bar (Hidden during printing) -->
    <div class="action-bar no-print">
        <div class="d-flex align-items-center gap-2">
            <a href="/hotel/pages/payments.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Payments
            </a>
            <?php if (!empty($reservation)): ?>
                <a href="/hotel/pages/reservations.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-calendar-check me-1"></i> View Reservations
                </a>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button onclick="window.print();" class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold shadow-sm">
                <i class="bi bi-printer me-1"></i> Print Receipt for Client
            </button>
            <button onclick="window.print();" class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-semibold" title="Choose 'Save as PDF' in the destination dropdown">
                <i class="bi bi-file-earmark-pdf me-1"></i> Save as PDF
            </button>
        </div>
    </div>

    <!-- Printable Receipt Container -->
    <div class="receipt-card">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center my-4 py-4 border-0 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-2 mb-2 d-block text-danger"></i>
                <h4 class="alert-heading fw-bold">Receipt Generation Failed</h4>
                <p class="mb-3"><?= htmlspecialchars($error) ?></p>
                <a href="/hotel/pages/payments.php" class="btn btn-secondary rounded-pill px-4">Return to Payments</a>
            </div>
        <?php else: ?>

            <!-- Header Section -->
            <div class="row align-items-start pb-4 mb-4 border-bottom">
                <div class="col-7">
                    <div class="hotel-title"><?= htmlspecialchars($hotel_name) ?></div>
                    <div class="text-muted small">
                        <div><i class="bi bi-geo-alt me-1 text-primary"></i> <?= htmlspecialchars($hotel_address) ?></div>
                        <div><i class="bi bi-telephone me-1 text-primary"></i> <?= htmlspecialchars($hotel_phone) ?> &bull; <i class="bi bi-envelope me-1 text-primary"></i> <?= htmlspecialchars($hotel_email) ?></div>
                    </div>
                </div>
                <div class="col-5 text-end">
                    <span class="badge bg-primary text-white text-uppercase px-3 py-1 mb-2 fw-semibold">Official Receipt</span>
                    <div class="receipt-title text-primary">#<?= htmlspecialchars($receipt_number) ?></div>
                    <div class="text-muted small mt-1">
                        <div><strong>Date:</strong> <?= date('F d, Y - h:i A', strtotime($payment_date)) ?></div>
                        <div><strong>Issued By:</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'Front Desk') ?></div>
                    </div>
                </div>
            </div>

            <!-- Client & Stay Information Grid -->
            <div class="row g-4 mb-4 pb-3">
                <div class="col-6">
                    <div class="p-3 rounded bg-light border">
                        <div class="text-uppercase text-muted fw-bold small mb-2"><i class="bi bi-person me-1 text-primary"></i> Guest Information</div>
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($client_name) ?></h5>
                        <div class="text-muted small">
                            <?php if (!empty($client_phone)): ?>
                                <div><strong>Phone:</strong> <?= htmlspecialchars($client_phone) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($client_email)): ?>
                                <div><strong>Email:</strong> <?= htmlspecialchars($client_email) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($client_address)): ?>
                                <div><strong>Address:</strong> <?= htmlspecialchars($client_address) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-6">
                    <div class="p-3 rounded bg-light border">
                        <div class="text-uppercase text-muted fw-bold small mb-2"><i class="bi bi-door-open me-1 text-primary"></i> Accommodation Details</div>
                        <?php if (!empty($room_number)): ?>
                            <h5 class="fw-bold text-dark mb-1">Room <?= htmlspecialchars($room_number) ?> <span class="badge bg-secondary fw-normal fs-6"><?= htmlspecialchars($room_type) ?></span></h5>
                            <div class="text-muted small">
                                <div><strong>Check-in:</strong> <?= htmlspecialchars($check_in_date) ?></div>
                                <div><strong>Check-out:</strong> <?= htmlspecialchars($check_out_date) ?> (<?= $nights ?> <?= $nights === 1 ? 'Night' : 'Nights' ?>)</div>
                                <div><strong>Nightly Rate:</strong> <?= htmlspecialchars($currency) ?> <?= number_format($price_per_night, 2) ?></div>
                            </div>
                        <?php else: ?>
                            <h5 class="fw-bold text-dark mb-1">Direct Hotel Service</h5>
                            <div class="text-muted small">General transaction recorded on account.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Financial Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered receipt-table align-middle">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-center">Method</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?= !empty($room_number) ? "Lodging & Room Charges (Room " . htmlspecialchars($room_number) . ")" : "Hotel Payment Transaction" ?>
                                </div>
                                <div class="small text-muted">
                                    Transaction Ref: <?= htmlspecialchars($receipt_number) ?> &bull; <?= date('M d, Y', strtotime($payment_date)) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($payment_method) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($payment_status === 'completed'): ?>
                                    <span class="badge-status-completed"><i class="bi bi-check-circle-fill me-1"></i> Completed</span>
                                <?php else: ?>
                                    <span class="badge-status-pending"><?= ucfirst($payment_status) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                <?= htmlspecialchars($currency) ?> <?= number_format($payment['amount'] ?? 0, 2) ?>
                            </td>
                        </tr>

                        <!-- Summary rows -->
                        <?php if ($total_cost > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end fw-semibold text-muted">Total Booking / Reservation Charges:</td>
                                <td class="text-end fw-semibold text-dark"><?= htmlspecialchars($currency) ?> <?= number_format($total_cost, 2) ?></td>
                            </tr>
                        <?php endif; ?>

                        <tr>
                            <td colspan="3" class="text-end fw-bold text-dark">Total Cumulative Paid:</td>
                            <td class="text-end fw-bold amount-highlight"><?= htmlspecialchars($currency) ?> <?= number_format($total_paid, 2) ?></td>
                        </tr>

                        <tr class="<?= $balance_remaining <= 0.00 ? 'table-success' : 'table-warning' ?>">
                            <td colspan="3" class="text-end fw-bold fs-6">
                                <?= $balance_remaining <= 0.00 ? 'Outstanding Balance (PAID IN FULL):' : 'Remaining Balance Owed:' ?>
                            </td>
                            <td class="text-end fw-bold fs-6 <?= $balance_remaining <= 0.00 ? 'text-success' : 'text-danger' ?>">
                                <?= htmlspecialchars($currency) ?> <?= number_format($balance_remaining, 2) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Signatures Section -->
            <div class="row pt-4 mb-4">
                <div class="col-6">
                    <div class="signature-box">
                        <div class="text-muted small mb-1">Guest Signature</div>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($client_name) ?></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="signature-box">
                        <div class="text-muted small mb-1">Authorized Cashier / Stamp</div>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($hotel_name) ?></div>
                    </div>
                </div>
            </div>

            <!-- Receipt Terms & Footer -->
            <div class="text-center pt-3 border-top text-muted small">
                <p class="mb-1 fw-semibold text-dark">Thank you for choosing <?= htmlspecialchars($hotel_name) ?>!</p>
                <p class="mb-0">This receipt serves as official proof of payment. For inquiries or invoice disputes, please reach out to our front desk at <?= htmlspecialchars($hotel_phone) ?> or <?= htmlspecialchars($hotel_email) ?>.</p>
            </div>

        <?php endif; ?>
    </div>

    <!-- Auto Print Script Trigger -->
    <?php if (empty($error) && !isset($_GET['noprint'])): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    window.print();
                }, 400);
            });
        </script>
    <?php endif; ?>
</body>
</html>
