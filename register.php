<?php
require_once 'classes/Auth.php';
$auth = new Auth();
if ($auth->isLoggedIn()) header("Location: dashboard.php");
$msg = '';
if ($_POST) {
    if ($auth->register($_POST['username'], $_POST['password'], $_POST['fullname'], 'User')) {
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