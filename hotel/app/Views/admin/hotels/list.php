<?php
// c:\xampp\htdocs\hotel\app\Views\admin\hotels\list.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin – Hotels</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Hotel Management</h1>
    <p><a href="?page=admin_hotels_create">Add New Hotel</a></p>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>City</th>
                <th>Country</th>
                <th>Stars</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hotels as $hotel): ?>
                <tr>
                    <td><?= \App\Helpers\Security::e((string)$hotel['id']) ?></td>
                    <td><?= \App\Helpers\Security::e($hotel['name']) ?></td>
                    <td><?= \App\Helpers\Security::e($hotel['city']) ?></td>
                    <td><?= \App\Helpers\Security::e($hotel['country']) ?></td>
                    <td><?= \App\Helpers\Security::e((string)$hotel['star_rating']) ?></td>
                    <td>
                        <a href="?page=admin_hotels_edit&id=<?= $hotel['id'] ?>&csrf_token=<?= \App\Helpers\Security::e($_SESSION['csrf_token']) ?>">Edit</a> |
                        <a href="?page=admin_hotels_delete&id=<?= $hotel['id'] ?>&csrf_token=<?= \App\Helpers\Security::e($_SESSION['csrf_token']) ?>" onclick="return confirm('Delete this hotel?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><a href="?page=dashboard">Back to Dashboard</a></p>
</body>
</html>
?>
