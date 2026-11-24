<?php
namespace App\Controllers;

use App\Helpers\Security;
use App\Helpers\EmailHelper;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\User;
use App\Helpers\Database;

/**
 * PaymentController – integrates Paystack sandbox and sends booking confirmation email.
 */
class PaymentController
{
    /**
     * Initiate a Paystack transaction.
     * Expects a pending booking ID in the query string.
     */
    public function initiate()
    {
        $bookingId = intval($_GET['booking_id'] ?? 0);
        $booking = (new Booking())->find($bookingId);
        if (!$booking) { die('Booking not found'); }

        // Build Paystack request payload
        $payload = [
            'email'       => $booking['customer_email'],
            'amount'      => (int)($booking['total_price'] * 100), // amount in kobo
            'reference'   => 'PAY_' . strtoupper(bin2hex(random_bytes(5))),
            'callback_url'=> (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/?page=payment_callback',
        ];

        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . getenv('PAYSTACK_SECRET_KEY'),
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            die('cURL Error: ' . $err);
        }
        $data = json_decode($response, true);
        if (!$data['status']) {
            die('Paystack error: ' . $data['message']);
        }
        // Store reference to be able to match later
        $payment = new Payment();
        $payment->create([
            'booking_id'         => $bookingId,
            'user_id'            => $booking['user_id'],
            'payment_reference'  => $payload['reference'],
            'transaction_id'     => null,
            'payment_method'     => 'paystack',
            'amount'             => $booking['total_price'],
            'currency'           => 'GHS',
            'status'             => 'pending',
            'gateway_response'   => null
        ]);
        // Redirect user to Paystack checkout URL
        header('Location: ' . $data['data']['authorization_url']);
        exit;
    }

    /**
     * Callback URL that Paystack redirects to after payment.
     * Verifies the transaction, updates DB, marks booking as confirmed, and sends email.
     */
    public function callback()
    {
        $reference = $_GET['reference'] ?? '';
        if (!$reference) { die('Missing reference'); }

        // Verify transaction with Paystack
        $ch = curl_init('https://api.paystack.co/transaction/verify/' . $reference);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . getenv('PAYSTACK_SECRET_KEY')
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) { die('cURL Error: ' . $err); }
        $data = json_decode($response, true);
        if (!$data['status']) { die('Verification failed: ' . $data['message']); }

        $txn = $data['data'];
        // Find payment record by reference
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE payment_reference = :ref');
        $stmt->execute(['ref' => $reference]);
        $payment = $stmt->fetch();
        if (!$payment) { die('Payment record not found'); }

        // Update payment record
        $stmt = $pdo->prepare('UPDATE payments SET transaction_id = :txid, status = :status, gateway_response = :resp WHERE id = :id');
        $stmt->execute([
            'txid' => $txn['id'],
            'status' => $txn['status'] === 'success' ? 'completed' : 'failed',
            'resp' => json_encode($txn),
            'id' => $payment['id']
        ]);

        // Update booking status if successful
        if ($txn['status'] === 'success') {
            $bookingModel = new Booking();
            $booking = $bookingModel->find($payment['booking_id']);
            $bookingModel->update($booking['id'], ['status' => 'confirmed']);

            // Send confirmation email
            $user = (new User())->find($booking['user_id']);
            $hotelStmt = $pdo->prepare('SELECT name FROM hotels WHERE id = :hid');
            $hotelStmt->execute(['hid' => $booking['hotel_id']]);
            $hotelName = $hotelStmt->fetchColumn();
            $roomStmt = $pdo->prepare('SELECT room_number FROM rooms WHERE id = :rid');
            $roomStmt->execute(['rid' => $booking['room_id']]);
            $roomNumber = $roomStmt->fetchColumn();

            $subject = "Booking Confirmation – {$booking['booking_reference']}";
            $body = "<h2>Booking Confirmed</h2>" .
                    "<p>Dear {$user['full_name']},</p>" .
                    "<p>Your reservation at <strong>{$hotelName}</strong> (Room {$roomNumber}) has been confirmed.</p>" .
                    "<p>Check‑in: {$booking['check_in_date']}<br>Check‑out: {$booking['check_out_date']}</p>" .
                    "<p>Total Paid: GHS {$booking['total_price']}</p>" .
                    "<p>Reference: {$booking['booking_reference']}</p>" .
                    "<p>Thank you for choosing us!</p>";
            EmailHelper::send($user['email'], $subject, $body);

            echo '<h2>Payment successful! Confirmation email sent.</h2>';
            echo '<p><a href="?page=booking_history">View My Bookings</a></p>';
        } else {
            echo '<h2>Payment failed or cancelled.</h2>';
            echo '<p>Please try again or contact support.</p>';
        }
    }
}
?>
