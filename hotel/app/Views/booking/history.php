<?php
// c:\xampp\htdocs\hotel\app\Views\booking\history.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking History</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Your Booking History</h1>
    <?php if (empty($bookings)) : ?>
        <p>No bookings found.</p>
    <?php else: ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Hotel</th>
                    <th>Room #</th>
                    <th>Check‑in</th>
                    <th>Check‑out</th>
                    <th>Guests</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= \App\Helpers\Security::e($b['booking_reference']); ?></td>
                        <td><?= \App\Helpers\Security::e($b['hotel_id']); ?></td>
                        <td><?= \App\Helpers\Security::e($b['room_id']); ?></td>
                        <td><?= \App\Helpers\Security::e($b['check_in_date']); ?></td>
                        <td><?= \App\Helpers\Security::e($b['check_out_date']); ?></td>
                        <td><?= \App\Helpers\Security::e((string)$b['total_guests']); ?></td>
                        <td>$<?= \App\Helpers\Security::e(number_format((float)$b['total_price'], 2)); ?></td>
                        <td><?= \App\Helpers\Security::e($b['status']); ?></td>
                        <td>
                            <?php if (in_array($b['status'], ['pending','confirmed'])): ?>
                                <a href="?page=booking_cancel&id=<?= \App\Helpers\Security::e((string)$b['id']); ?>&csrf_token=<?= \App\Helpers\Security::e($csrf); ?>">Cancel</a>
                            <?php else: ?>
                                –
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p><a href="?page=dashboard">Back to Dashboard</a></p>
</body>
</html>
?>
