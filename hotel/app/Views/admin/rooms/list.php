<?php
// c:\xampp\htdocs\hotel\app\Views\admin\rooms\list.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin – Rooms</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Room Management</h1>
    <p><a href="?page=admin_rooms_create">Add New Room</a></p>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Hotel</th>
                <th>Number</th>
                <th>Type</th>
                <th>Price/night</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rooms as $room): ?>
                <tr>
                    <td><?= \App\Helpers\Security::e((string)$room['id']) ?></td>
                    <td><?= \App\Helpers\Security::e((string)$room['hotel_id']) ?></td>
                    <td><?= \App\Helpers\Security::e($room['room_number']) ?></td>
                    <td><?= \App\Helpers\Security::e($room['type']) ?></td>
                    <td><?= \App\Helpers\Security::e((string)$room['price_per_night']) ?></td>
                    <td><?= \App\Helpers\Security::e($room['status']) ?></td>
                    <td>
                        <a href="?page=admin_rooms_edit&id=<?= $room['id'] ?>&csrf_token=<?= \App\Helpers\Security::e($_SESSION['csrf_token']) ?>">Edit</a> |
                        <a href="?page=admin_rooms_delete&id=<?= $room['id'] ?>&csrf_token=<?= \App\Helpers\Security::e($_SESSION['csrf_token']) ?>" onclick="return confirm('Delete this room?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><a href="?page=dashboard">Back to Dashboard</a></p>
</body>
</html>
?>
