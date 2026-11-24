<?php
require_once __DIR__ . '/../config/db.php';
// Simple debug script – only for development; protect it!

if (!$pdo) {
    die('Database not connected.');
}

$stmt = $pdo->query('SELECT id, username, password FROM users LIMIT 100');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug: Users List</title>
    <style>
        body {font-family: Arial, sans-serif; margin: 20px;}
        table {border-collapse: collapse; width: 100%;}
        th, td {border: 1px solid #ddd; padding: 8px;}
        th {background-color: #f2f2f2;}
    </style>
</head>
<body>
    <h1>Users in Database</h1>
    <?php if (empty($users)): ?>
        <p>No user records found.</p>
    <?php else: ?>
        <table>
            <tr><th>ID</th><th>Username</th><th>Password Hash</th></tr>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['id']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['password']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>
