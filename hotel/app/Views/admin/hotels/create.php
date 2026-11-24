<?php
// c:\xampp\htdocs\hotel\app\Views\admin\hotels\create.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Hotel</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Add New Hotel</h1>
    <form action="?page=admin_hotels_store" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::e($_SESSION['csrf_token']) ?>">
        <label>Name:<br>
            <input type="text" name="name" required maxlength="150">
        </label><br><br>
        <label>Description:<br>
            <textarea name="description" rows="5" maxlength="2000"></textarea>
        </label><br><br>
        <label>Address:<br>
            <input type="text" name="address" required maxlength="255">
        </label><br><br>
        <label>City:<br>
            <input type="text" name="city" required maxlength="100">
        </label><br><br>
        <label>Country:<br>
            <input type="text" name="country" required maxlength="100" value="Ghana">
        </label><br><br>
        <label>Phone:<br>
            <input type="text" name="phone" required maxlength="30">
        </label><br><br>
        <label>Email:<br>
            <input type="email" name="email" required maxlength="100">
        </label><br><br>
        <label>Star Rating (e.g., 4.5):<br>
            <input type="number" step="0.1" min="0" max="5" name="star_rating" required>
        </label><br><br>
        <label>Cover Image (optional):<br>
            <input type="file" name="cover_image" accept="image/*">
        </label><br><br>
        <button type="submit">Create Hotel</button>
    </form>
    <p><a href="?page=admin_hotels">Back to Hotel List</a></p>
</body>
</html>
?>
