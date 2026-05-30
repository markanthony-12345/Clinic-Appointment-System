<?php
require_once 'classes/Auth.php';
$auth = new Auth();
if ($auth->isLoggedIn()) header("Location: dashboard.php");
$msg = '';

// Check if any user exists in the database
$pdo = (new Database())->getConnection();
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$userCount = $stmt->fetchColumn();
$isFirstUser = ($userCount == 0);

if ($_POST) {
    // If this is the first user, force role = 'Admin', otherwise 'User'
    $role = $isFirstUser ? 'Admin' : 'User';
    if ($auth->register($_POST['username'], $_POST['password'], $_POST['fullname'], $role)) {
        header("Location: login.php?registered=1");
        exit;
    } else {
        $msg = "Username already exists.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<div class="container" style="max-width:400px; margin-top:100px;">
    <div class="card">
        <h2>Register</h2>
        <?php if($msg): ?><div class="alert error"><?= $msg ?></div><?php endif; ?>
        <?php if ($isFirstUser): ?>
            <div class="alert info" style="background:#e3f2fd; color:#0b5e8a;">🎉 First user will be registered as ADMIN.</div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Full Name</label><input type="text" name="fullname" required></div>
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="btn primary">Register</button>
            <a href="login.php" class="btn">Back to Login</a>
        </form>
    </div>
</div>
</body>
</html>