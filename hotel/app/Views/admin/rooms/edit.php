<?php
// c:\xampp\htdocs\hotel\app\Views\admin\rooms\edit.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Room</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Edit Room</h1>
    <form action="?page=admin_rooms_update" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="id" value="<?= \App\Helpers\Security::e((string)$room['id']) ?>">
        <label>Hotel:<br>
            <select name="hotel_id" required>
                <?php foreach ($hotels as $hotel): ?>
                    <option value="<?= \App\Helpers\Security::e((string)$hotel['id']) ?>"
                        <?= $hotel['id'] == $room['hotel_id'] ? 'selected' : '' ?>>
                        <?= \App\Helpers\Security::e($hotel['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>
        <label>Room Number:<br>
            <input type="text" name="room_number" value="<?= \App\Helpers\Security::e($room['room_number']) ?>" required maxlength="20">
        </label><br><br>
        <label>Type:<br>
            <input type="text" name="type" value="<?= \App\Helpers\Security::e($room['type']) ?>" required maxlength="50">
        </label><br><br>
        <label>Price per Night:<br>
            <input type="number" step="0.01" name="price" value="<?= \App\Helpers\Security::e((string)$room['price_per_night']) ?>" required>
        </label><br><br>
        <label>Capacity:<br>
            <input type="number" name="capacity" min="1" value="<?= \App\Helpers\Security::e((string)$room['capacity']) ?>" required>
        </label><br><br>
        <label>Description:<br>
            <textarea name="description" rows="4" maxlength="2000"><?= \App\Helpers\Security::e($room['description']) ?></textarea>
        </label><br><br>
        <label>Amenities:<br>
            <input type="text" name="amenities" value="<?= \App\Helpers\Security::e($room['amenities']) ?>" maxlength="1000">
        </label><br><br>
        <label>Status:<br>
            <select name="status" required>
                <option value="available" <?= $room['status'] == 'available' ? 'selected' : '' ?>>Available</option>
                <option value="occupied" <?= $room['status'] == 'occupied' ? 'selected' : '' ?>>Occupied</option>
                <option value="maintenance" <?= $room['status'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
            </select>
        </label><br><br>
        <label>Primary Image (optional – leave blank to keep current):<br>
            <input type="file" name="image" accept="image/*">
        </label><br><br>
        <button type="submit">Update Room</button>
    </form>
    <p><a href="?page=admin_rooms">Back to Room List</a></p>
</body>
</html>
?>
