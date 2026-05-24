<?php
require_once 'config.php';
require_once 'classes/Auth.php';
 
$auth = new Auth();
 
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}
 
$error = '';
$lockout_remaining = 0;
 
$rate = checkLoginRateLimit();
if ($rate['locked']) {
    $lockout_remaining = $rate['remaining'];
    $error = "Too many failed attempts. Please wait " . ceil($lockout_remaining / 60) . " minute(s) before trying again.";
}
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rate['locked']) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
 
        if ($auth->login($username, $password)) {
            clearLoginAttempts();
            session_regenerate_id(true);
            header("Location: dashboard.php");
            exit;
        } else {
            recordFailedLogin();
            $rate = checkLoginRateLimit();
            if ($rate['locked']) {
                $error = "Too many failed attempts. Locked out for 5 minutes.";
            } else {
                $attempts_left = 5 - ($_SESSION['login_attempts'] ?? 0);
                $error = "Invalid username or password. ($attempts_left attempt(s) remaining)";
            }
        }
    }
}
 
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .lockout-timer { color: #e74c3c; font-weight: bold; font-size: 1.1em; }
    </style>
</head>
<body>
<div class="container" style="max-width:400px; margin-top:100px;">
    <div class="card">
        <h2>Clinic Login</h2>
 
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert error">Your session has expired. Please log in again.</div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert success">Registration successful! Please log in.</div>
        <?php endif; ?>
 
        <form method="POST" <?= $lockout_remaining > 0 ? 'onsubmit="return false;"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autocomplete="username"
                       <?= $lockout_remaining > 0 ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="current-password"
                       <?= $lockout_remaining > 0 ? 'disabled' : '' ?>>
            </div>
            <button type="submit" class="btn primary" <?= $lockout_remaining > 0 ? 'disabled' : '' ?>>Login</button>
            <a href="register.php" class="btn">Register</a>
        </form>
 
        <?php if ($lockout_remaining > 0): ?>
        <p class="lockout-timer" id="countdown">Locked: <?= ceil($lockout_remaining / 60) ?>m remaining</p>
        <script>
        let secs = <?= $lockout_remaining ?>;
        const el = document.getElementById('countdown');
        const interval = setInterval(() => {
            secs--;
            if (secs <= 0) { clearInterval(interval); location.reload(); }
            else {
                const m = Math.floor(secs / 60), s = secs % 60;
                el.textContent = `Locked: ${m}m ${s}s remaining`;
            }
        }, 1000);
        </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>