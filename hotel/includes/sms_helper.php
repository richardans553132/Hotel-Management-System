<?php
/**
 * Hubtel SMS Integration Helper for Hotel Management System
 * 
 * Provides automated SMS booking confirmations, Ghanaian phone number formatting (+233),
 * secure environment variable credential access, cURL API communication, and MySQL SMS audit logging.
 */

if (!function_exists('sms_get_env')) {
    /**
     * Retrieve environment variable securely across multiple PHP runtime environments,
     * falling back to the settings table in the database if available.
     *
     * @param string $key Environment variable key
     * @param string $default Default fallback value
     * @return string
     */
    function sms_get_env(string $key, string $default = ''): string {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return trim($val);
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return trim($_ENV[$key]);
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return trim($_SERVER[$key]);
        }
        if (defined($key)) {
            return trim((string)constant($key));
        }
        // Fallback to database settings table if defined
        if (function_exists('get_setting')) {
            $settingVal = get_setting(strtolower($key), '');
            if ($settingVal !== '') {
                return trim($settingVal);
            }
        }
        return $default;
    }
}

if (!function_exists('ensure_sms_logs_table')) {
    /**
     * Ensures the sms_logs audit table exists in MySQL
     *
     * @param PDO $pdo Active PDO connection
     */
    function ensure_sms_logs_table(PDO $pdo): void {
        static $checked = false;
        if ($checked) return;

        try {
            $pdo->query("SELECT 1 FROM `sms_logs` LIMIT 1");
            $checked = true;
        } catch (PDOException $e) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `sms_logs` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `booking_id` INT NULL,
                    `phone_number` VARCHAR(30) NOT NULL,
                    `message` TEXT NOT NULL,
                    `status` ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending',
                    `response` TEXT NULL,
                    `error_message` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_sms_logs_booking_id` (`booking_id`),
                    INDEX `idx_sms_logs_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $checked = true;
        }
    }
}

if (!function_exists('format_ghana_phone')) {
    /**
     * Automatically formats Ghanaian mobile numbers into international format (+233).
     * Also supports valid international numbers in E.164 standard (+XXXXXXXXXXX).
     *
     * Examples:
     *   "0244123456"     -> "+233244123456"
     *   "244123456"      -> "+233244123456"
     *   "233244123456"   -> "+233244123456"
     *   "+233244123456"  -> "+233244123456"
     *   "00233244123456" -> "+233244123456"
     *   "+14155552671"   -> "+14155552671"
     *
     * @param string|null $rawPhone Raw phone number string
     * @return string|null Formatted phone number or null if invalid
     */
    function format_ghana_phone(?string $rawPhone): ?string {
        if ($rawPhone === null) return null;
        $phone = trim($rawPhone);
        if ($phone === '') return null;

        // Remove spaces, hyphens, brackets, dots, and slashes
        $clean = preg_replace('/[\s\-\(\)\.\/]/', '', $phone);

        // Convert leading 00 into +
        if (strpos($clean, '00') === 0) {
            $clean = '+' . substr($clean, 2);
        }

        // Case A: 10 digits starting with 0 (e.g. 0244123456, 0501234567, 020..., etc.)
        if (preg_match('/^0([0-9]{9})$/', $clean, $m)) {
            return '+233' . $m[1];
        }

        // Case B: 9 digits without leading 0 (e.g. 244123456, 501234567)
        if (preg_match('/^([235][0-9]{8})$/', $clean, $m)) {
            return '+233' . $m[1];
        }

        // Case C: 12 digits starting with country code 233 (e.g. 233244123456)
        if (preg_match('/^233([0-9]{9})$/', $clean, $m)) {
            return '+233' . $m[1];
        }

        // Case D: Already in +233 international format (e.g. +233244123456)
        if (preg_match('/^\+233([0-9]{9})$/', $clean, $m)) {
            return '+233' . $m[1];
        }

        // Case E: Standard international format (+ and 8 to 15 digits)
        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $clean)) {
            return $clean;
        }

        return null;
    }
}

if (!function_exists('log_sms_attempt')) {
    /**
     * Record SMS delivery attempt, response, and status into MySQL sms_logs table
     *
     * @param PDO $pdo
     * @param int|null $bookingId
     * @param string $phone
     * @param string $message
     * @param string $status 'sent' | 'failed' | 'pending'
     * @param string|null $response Raw response from Hubtel
     * @param string|null $errorMessage Error summary if failed
     * @return int Insert ID
     */
    function log_sms_attempt(PDO $pdo, ?int $bookingId, string $phone, string $message, string $status, ?string $response = null, ?string $errorMessage = null): int {
        ensure_sms_logs_table($pdo);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO `sms_logs` (`booking_id`, `phone_number`, `message`, `status`, `response`, `error_message`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $bookingId,
                $phone,
                $message,
                $status,
                $response,
                $errorMessage ? substr($errorMessage, 0, 255) : null
            ]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Failed to insert SMS log: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('send_hubtel_sms')) {
    /**
     * Send an SMS message using the Hubtel Programmable SMS API via PHP cURL
     *
     * @param PDO $pdo Active PDO connection
     * @param int|null $bookingId Associated booking ID
     * @param string $toPhone Recipient phone number (international format +233...)
     * @param string $message SMS text content
     * @return array Result array with success, status, error, and response
     */
    function send_hubtel_sms(PDO $pdo, ?int $bookingId, string $toPhone, string $message): array {
        ensure_sms_logs_table($pdo);

        $clientId = sms_get_env('HUBTEL_CLIENT_ID');
        $clientSecret = sms_get_env('HUBTEL_CLIENT_SECRET');
        $senderId = sms_get_env('HUBTEL_SENDER_ID', 'JosselHotel');

        // Sender ID constraint: max 11 alphanumeric characters for Ghana SMS
        if (strlen($senderId) > 11) {
            $senderId = substr($senderId, 0, 11);
        }

        // Validate API credentials exist
        if (empty($clientId) || empty($clientSecret) || $clientId === 'your_hubtel_client_id') {
            $errorMsg = 'Hubtel SMS API credentials (HUBTEL_CLIENT_ID / HUBTEL_CLIENT_SECRET) are not configured in .env.';
            log_sms_attempt($pdo, $bookingId, $toPhone, $message, 'failed', null, $errorMsg);
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $errorMsg,
                'http_code' => 0,
                'response' => null
            ];
        }

        // Hubtel recipient format: accepts digits without the leading +
        $hubtelDestination = ltrim($toPhone, '+');

        $payload = [
            'From' => $senderId,
            'To' => $hubtelDestination,
            'Content' => $message,
            'RegisteredDelivery' => true
        ];

        $apiUrl = 'https://smsc.hubtel.com/v1/messages/send';
        $authToken = base64_encode($clientId . ':' . $clientSecret);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . $authToken
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL network failure
        if (!empty($curlError)) {
            $errorMsg = 'cURL Network Error: ' . $curlError;
            log_sms_attempt($pdo, $bookingId, $toPhone, $message, 'failed', $response ?: null, $errorMsg);
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $errorMsg,
                'http_code' => $httpCode,
                'response' => $response
            ];
        }

        // Parse JSON response
        $decoded = json_decode($response, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        // Check Hubtel-specific status if present
        if ($isSuccess && is_array($decoded)) {
            if (isset($decoded['status']) && (int)$decoded['status'] !== 0) {
                $isSuccess = false;
            }
        }

        if ($isSuccess) {
            $messageId = $decoded['messageId'] ?? $decoded['MessageId'] ?? '';
            log_sms_attempt($pdo, $bookingId, $toPhone, $message, 'sent', $response, null);
            return [
                'success' => true,
                'status' => 'sent',
                'message_id' => $messageId,
                'error' => null,
                'http_code' => $httpCode,
                'response' => $response
            ];
        } else {
            $errorMsg = 'Hubtel SMS error (HTTP ' . $httpCode . ')';
            if (is_array($decoded)) {
                $errorMsg = $decoded['message'] ?? $decoded['Message'] ?? ($decoded['description'] ?? $errorMsg);
            }
            log_sms_attempt($pdo, $bookingId, $toPhone, $message, 'failed', $response, $errorMsg);
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $errorMsg,
                'http_code' => $httpCode,
                'response' => $response
            ];
        }
    }
}

if (!function_exists('send_booking_confirmation_sms')) {
    /**
     * Complete Booking Confirmation SMS Orchestrator
     * 
     * 1. Fetches newly saved booking details with guest and room info
     * 2. Prevents duplicate SMS messages for the same booking
     * 3. Validates and formats the guest's Ghanaian phone number (+233)
     * 4. Assembles confirmation text with guest name, hotel name, room number,
     *    check-in date, check-out date, total cost, and reservation status
     * 5. Sends via Hubtel SMS API and records audit log
     *
     * @param PDO $pdo Active PDO connection
     * @param int $bookingId Booking ID in reservations table
     * @return array
     */
    function send_booking_confirmation_sms(PDO $pdo, int $bookingId): array {
        ensure_sms_logs_table($pdo);

        // 1. Prevent duplicate SMS if an SMS was already sent for this booking
        try {
            $duplicateCheck = $pdo->prepare("SELECT COUNT(*) FROM `sms_logs` WHERE `booking_id` = ? AND `status` = 'sent'");
            $duplicateCheck->execute([$bookingId]);
            if ($duplicateCheck->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'already_sent' => true,
                    'status' => 'skipped',
                    'formatted_phone' => '',
                    'message' => 'SMS confirmation has already been sent for this booking.',
                    'error' => 'Duplicate SMS prevented: Booking #' . $bookingId . ' already received a confirmation SMS.'
                ];
            }
        } catch (PDOException $e) {
            // Non-fatal, continue
        }

        // 2. Fetch reservation, guest, and room details
        try {
            $stmt = $pdo->prepare("
                SELECT r.id, r.guest_id, r.room_id, r.check_in_date, r.check_out_date, r.total_price, r.status,
                       g.first_name, g.last_name, g.phone AS guest_phone,
                       rm.room_number, rm.type AS room_type
                FROM `reservations` r
                JOIN `guests` g ON r.guest_id = g.id
                JOIN `rooms` rm ON r.room_id = rm.id
                WHERE r.id = ?
            ");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

            if (!$booking) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'formatted_phone' => '',
                    'message' => 'Booking not found in database.',
                    'error' => 'Booking record #' . $bookingId . ' does not exist.'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'formatted_phone' => '',
                'message' => 'Database error while fetching booking details.',
                'error' => $e->getMessage()
            ];
        }

        // 3. Format and validate guest phone number
        $rawPhone = $booking['guest_phone'] ?? '';
        $formattedPhone = format_ghana_phone($rawPhone);

        if (!$formattedPhone) {
            $errorMsg = "Guest phone number ('" . ($rawPhone ?: 'Empty') . "') is invalid. Must be a valid Ghanaian number (+233) or international standard.";
            log_sms_attempt($pdo, $bookingId, $rawPhone ?: 'N/A', '', 'failed', null, $errorMsg);
            return [
                'success' => false,
                'status' => 'failed',
                'formatted_phone' => $rawPhone,
                'message' => 'Invalid phone number format.',
                'error' => $errorMsg
            ];
        }

        // 4. Assemble confirmation message content
        // Required elements: guest's name, hotel name (Jossel Hotel & Suites), room number,
        // check-in date, check-out date, total cost, reservation status.
        $hotelName = 'Jossel Hotel & Suites';
        $guestName = trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''));
        $roomNumber = $booking['room_number'] ?? 'N/A';
        $checkIn = $booking['check_in_date'] ?? '';
        $checkOut = $booking['check_out_date'] ?? '';
        
        $currency = function_exists('get_setting') ? get_setting('currency', 'GHS') : 'GHS';
        $totalCost = $currency . ' ' . number_format((float)$booking['total_price'], 2);
        $resStatus = ucfirst(str_replace('_', ' ', $booking['status'] ?? 'pending'));

        $smsMessage = "Dear {$guestName}, thank you for choosing {$hotelName}! Your booking for Room {$roomNumber} ({$checkIn} to {$checkOut}) is confirmed. Total: {$totalCost}. Status: {$resStatus}. We look forward to your stay!";

        // 5. Send via Hubtel cURL API
        $sendResult = send_hubtel_sms($pdo, $bookingId, $formattedPhone, $smsMessage);

        return [
            'success' => $sendResult['success'],
            'status' => $sendResult['status'],
            'booking_id' => $bookingId,
            'formatted_phone' => $formattedPhone,
            'sms_message' => $smsMessage,
            'message' => $sendResult['success'] ? 'SMS sent successfully.' : ($sendResult['error'] ?? 'SMS failed.'),
            'error' => $sendResult['error'] ?? null,
            'http_code' => $sendResult['http_code'] ?? null,
            'raw_response' => $sendResult['response'] ?? null
        ];
    }
}

