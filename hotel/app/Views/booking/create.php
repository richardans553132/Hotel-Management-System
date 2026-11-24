<?php
// c:\xampp\htdocs\hotel\app\Views\booking\create.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Room</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Book Room</h1>
    <?php if (!empty($room)) : ?>
        <p>Hotel: <?= \App\Helpers\Security::e($hotel['name'] ?? ''); ?> (ID: <?= \App\Helpers\Security::e((string)$room['hotel_id']); ?>)</p>
        <p>Room #: <?= \App\Helpers\Security::e($room['room_number']); ?></p>
        <p>Type: <?= \App\Helpers\Security::e($room['type']); ?></p>
        <p>Price per night: $<?= \App\Helpers\Security::e(number_format((float)$room['price_per_night'], 2)); ?></p>
        <form action="?page=booking_store" method="post">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::e($csrf); ?>">
            <input type="hidden" name="room_id" value="<?= \App\Helpers\Security::e((string)$room['id']); ?>">
            <label>Check‑in:
                <input type="date" name="check_in" required>
            </label><br><br>
            <label>Check‑out:
                <input type="date" name="check_out" required>
            </label><br><br>
            <label>Guests:
                <input type="number" name="guests" min="1" value="1" required>
            </label><br><br>
            <label>Special requests:
                <textarea name="special_requests" rows="4" cols="50"></textarea>
            </label><br><br>
            <button type="submit">Proceed to Payment</button>
        </form>
    <?php else: ?>
        <p>Room information not available.</p>
    <?php endif; ?>
    <p><a href="?page=dashboard">Back to Dashboard</a></p>
</body>
</html>
?>
