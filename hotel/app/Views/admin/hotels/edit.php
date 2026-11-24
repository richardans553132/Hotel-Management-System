<?php
// c:\xampp\htdocs\hotel\app\Views\admin\hotels\edit.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Hotel</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Edit Hotel</h1>
    <form action="?page=admin_hotels_update" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="id" value="<?= \App\Helpers\Security::e((string)$hotel['id']) ?>">
        <label>Name:<br>
            <input type="text" name="name" value="<?= \App\Helpers\Security::e($hotel['name']) ?>" required maxlength="150">
        </label><br><br>
        <label>Description:<br>
            <textarea name="description" rows="5" maxlength="2000"><?= \App\Helpers\Security::e($hotel['description']) ?></textarea>
        </label><br><br>
        <label>Address:<br>
            <input type="text" name="address" value="<?= \App\Helpers\Security::e($hotel['address']) ?>" required maxlength="255">
        </label><br><br>
        <label>City:<br>
            <input type="text" name="city" value="<?= \App\Helpers\Security::e($hotel['city']) ?>" required maxlength="100">
        </label><br><br>
        <label>Country:<br>
            <input type="text" name="country" value="<?= \App\Helpers\Security::e($hotel['country']) ?>" required maxlength="100">
        </label><br><br>
        <label>Phone:<br>
            <input type="text" name="phone" value="<?= \App\Helpers\Security::e($hotel['phone']) ?>" required maxlength="30">
        </label><br><br>
        <label>Email:<br>
            <input type="email" name="email" value="<?= \App\Helpers\Security::e($hotel['email']) ?>" required maxlength="100">
        </label><br><br>
        <label>Star Rating (e.g., 4.5):<br>
            <input type="number" step="0.1" min="0" max="5" name="star_rating" value="<?= \App\Helpers\Security::e((string)$hotel['star_rating']) ?>" required>
        </label><br><br>
        <label>Cover Image (optional – leave empty to keep current):<br>
            <input type="file" name="cover_image" accept="image/*">
        </label><br><br>
        <button type="submit">Update Hotel</button>
    </form>
    <p><a href="?page=admin_hotels">Back to Hotel List</a></p>
</body>
</html>
?>
