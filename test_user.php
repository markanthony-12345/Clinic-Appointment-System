<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT username, password FROM users WHERE username = 'admin'");
$user = $stmt->fetch();
if ($user) {
    echo "✅ Admin user found. Password hash: " . $user['password'];
} else {
    echo "❌ Admin user missing – did you import the SQL file?";
}
?>