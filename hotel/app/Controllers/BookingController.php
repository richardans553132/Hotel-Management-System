<?php
namespace App\Controllers;

use App\Helpers\Security;
use App\Helpers\InputValidator;
use App\Helpers\RateLimiter;
use App\Helpers\EmailHelper;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Payment;

/**
 * BookingController – handles searching rooms, creating bookings, viewing history, and cancelling.
 */
class BookingController
{
    /** Show the search form */
    public function searchForm()
    {
        $hotels = (new Hotel())->all();
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/booking/search.php';
    }

    /** Process the search request and display matching rooms */
    public function handleSearch()
    {
        // No CSRF needed for GET search, but we could protect POST if used.
        $hotelId   = intval($_GET['hotel_id'] ?? 0);
        $city      = InputValidator::text($_GET['city'] ?? '', 100);
        $priceMin  = floatval($_GET['price_min'] ?? 0);
        $priceMax  = floatval($_GET['price_max'] ?? 0);
        $type      = InputValidator::text($_GET['type'] ?? '', 50);
        $checkIn   = $_GET['check_in'] ?? '';
        $checkOut  = $_GET['check_out'] ?? '';

        $roomModel = new Room();
        $sql = "SELECT r.* FROM rooms r JOIN hotels h ON r.hotel_id = h.id WHERE 1=1";
        $params = [];
        if ($hotelId) {
            $sql .= " AND h.id = :hotelId"; $params['hotelId'] = $hotelId;
        }
        if ($city) {
            $sql .= " AND h.city LIKE :city"; $params['city'] = "%$city%";
        }
        if ($type) {
            $sql .= " AND r.type = :type"; $params['type'] = $type;
        }
        if ($priceMin > 0) {
            $sql .= " AND r.price_per_night >= :priceMin"; $params['priceMin'] = $priceMin;
        }
        if ($priceMax > 0) {
            $sql .= " AND r.price_per_night <= :priceMax"; $params['priceMax'] = $priceMax;
        }
        // Availability check – ensure no overlapping confirmed bookings
        if ($checkIn && $checkOut) {
            $sql .= " AND r.id NOT IN (SELECT b.room_id FROM bookings b WHERE b.status IN ('pending','confirmed','checked_in') AND ( (b.check_in_date <= :checkOut AND b.check_out_date >= :checkIn) ))";
            $params['checkIn'] = $checkIn;
            $params['checkOut'] = $checkOut;
        }
        $pdo = \App\Helpers\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rooms = $stmt->fetchAll();

        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/booking/search.php';
    }

    /** Show booking creation form for a selected room */
    public function createForm()
    {
        $roomId = intval($_GET['room_id'] ?? 0);
        $room = (new Room())->find($roomId);
        if (!$room) { die('Room not found'); }
        $hotel = (new Hotel())->find($room['hotel_id']);
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/booking/create.php';
    }

    /** Handle POST to create a booking (status pending) and redirect to payment */
    public function handleCreate()
    {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        // Rate‑limit booking attempts per IP
        if (!RateLimiter::allow('booking', 10, 300)) {
            die('Too many booking attempts, please wait.');
        }
        $roomId = intval($_POST['room_id'] ?? 0);
        $room = (new Room())->find($roomId);
        if (!$room) { die('Invalid room'); }

        $userId = $_SESSION['user_id'] ?? null; // assume logged in
        if (!$userId) { die('Please log in to book'); }

        $checkIn   = $_POST['check_in'] ?? '';
        $checkOut  = $_POST['check_out'] ?? '';
        $guests    = intval($_POST['guests'] ?? 1);
        $special   = $_POST['special_requests'] ?? '';

        // Basic date validation omitted for brevity – you would verify format and that checkOut > checkIn
        $days = (new \DateTime($checkOut))->diff(new \DateTime($checkIn))->days;
        $totalPrice = $days * $room['price_per_night'];

        $booking = new Booking();
        $bookingId = $booking->create([
            'booking_reference' => 'BK-' . strtoupper(bin2hex(random_bytes(4))),
            'user_id'           => $userId,
            'hotel_id'          => $room['hotel_id'],
            'room_id'           => $roomId,
            'check_in_date'     => $checkIn,
            'check_out_date'    => $checkOut,
            'total_guests'      => $guests,
            'total_price'       => $totalPrice,
            'status'            => 'pending',
            'customer_name'     => (new User())->find($userId)['full_name'] ?? 'Customer',
            'customer_email'    => (new User())->find($userId)['email'] ?? '',
            'customer_phone'   => (new User())->find($userId)['phone'] ?? ''
        ]);

        // Store essential data in session for payment step
        $_SESSION['pending_booking'] = $bookingId;
        header('Location: ?page=payment_initiate&booking_id=' . $bookingId);
        exit;
    }

    /** Show customer's booking history */
    public function history()
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) { die('Login required'); }
        $stmt = \App\Helpers\Database::getConnection()->prepare('SELECT * FROM bookings WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute(['uid' => $userId]);
        $bookings = $stmt->fetchAll();
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/booking/history.php';
    }

    /** Cancel a pending/confirmed booking (sets status to cancelled) */
    public function cancel()
    {
        if (!Security::validateCsrfToken($_GET['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        $bookingId = intval($_GET['id'] ?? 0);
        $booking = new Booking();
        $existing = $booking->find($bookingId);
        if (!$existing) { die('Booking not found'); }
        // Only allow cancel if not already cancelled
        if (in_array($existing['status'], ['cancelled'])) {
            die('Booking already cancelled');
        }
        $booking->update($bookingId, ['status' => 'cancelled']);
        // Optionally trigger refund logic here.
        header('Location: ?page=booking_history');
        exit;
    }
}
?>
