<?php
// c:\xampp\htdocs\hotel\app\Views\booking\search.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Rooms</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Find a Room</h1>

    <form action="?page=booking_search_results" method="get">
        <input type="hidden" name="page" value="booking_search_results">
        <label>Hotel:
            <select name="hotel_id">
                <option value="">Any</option>
                <?php foreach ($hotels as $h): ?>
                    <option value="<?= \App\Helpers\Security::e((string)$h['id']) ?>">
                        <?= \App\Helpers\Security::e($h['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>
        <label>City:
            <input type="text" name="city" placeholder="Accra">
        </label><br><br>
        <label>Room Type:
            <input type="text" name="type" placeholder="single, double, suite">
        </label><br><br>
        <label>Price Min:
            <input type="number" step="0.01" name="price_min">
        </label><br><br>
        <label>Price Max:
            <input type="number" step="0.01" name="price_max">
        </label><br><br>
        <label>Check‑in:
            <input type="date" name="check_in">
        </label><br><br>
        <label>Check‑out:
            <input type="date" name="check_out">
        </label><br><br>
        <button type="submit">Search</button>
    </form>

    <?php if (isset($rooms)): ?>
        <h2>Available Rooms</h2>
        <?php if (empty($rooms)): ?>
            <p>No rooms match your criteria.</p>
        <?php else: ?>
            <table border="1" cellpadding="5" cellspacing="0">
                <thead>
                    <tr>
                        <th>Hotel ID</th><th>Room #</th><th>Type</th><th>Price/night</th><th>Status</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $r): ?>
                        <tr>
                            <td><?= \App\Helpers\Security::e((string)$r['hotel_id']) ?></td>
                            <td><?= \App\Helpers\Security::e($r['room_number']) ?></td>
                            <td><?= \App\Helpers\Security::e($r['type']) ?></td>
                            <td><?= \App\Helpers\Security::e((string)$r['price_per_night']) ?></td>
                            <td><?= \App\Helpers\Security::e($r['status']) ?></td>
                            <td><a href="?page=booking_create&room_id=<?= $r['id'] ?>">Book now</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <p><a href="?page=dashboard">Back to Dashboard</a></p>
</body>
</html>
?>
