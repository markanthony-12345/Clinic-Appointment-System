<?php
require_once 'config.php';
require_once 'classes/Auth.php';
require_once 'classes/Database.php';

// If already logged in, go to dashboard
$auth = new Auth();
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$userCount = $stmt->fetchColumn();
$isFirstUser = ($userCount == 0);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isFirstUser) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    if ($username && $password && $fullname) {
        // Always assign Admin role
        if ($auth->register($username, $password, $fullname, 'Admin')) {
            $success = "Admin account created successfully. Please <a href='login.php'>login</a>.";
        } else {
            $error = "Registration failed. Username may already exist.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            margin: 0;
            padding: 1rem;
        }
        .register-card {
            max-width: 420px;
            width: 100%;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .register-title {
            text-align: center;
            font-weight: 700;
            font-size: 1.8rem;
            color: #1a2c3e;
            margin-bottom: 0.3rem;
        }
        .register-subtitle {
            text-align: center;
            color: #5b7f9c;
            font-size: 0.9rem;
            margin-bottom: 1.8rem;
        }
        .btn-register {
            width: 100%;
            padding: 0.8rem;
            border-radius: 0.75rem;
            font-weight: 600;
            background: #1e6f9f;
            border: none;
            color: white;
            transition: background 0.2s;
        }
        .btn-register:hover {
            background: #155d85;
        }
        .alert {
            border-radius: 0.75rem;
        }
        .footer {
            margin-top: 1.5rem;
            text-align: center;
        }
        .footer a {
            color: #1e6f9f;
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="register-card">
    <div class="register-title"><i class="fas fa-user-shield me-2"></i>Admin Registration</div>
    <div class="register-subtitle">Create the first admin account</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($isFirstUser): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-register"><i class="fas fa-user-plus me-2"></i>Create Admin Account</button>
        </form>
        <div class="footer"><a href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Already have an account? Login</a></div>
    <?php else: ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Registration is closed. Only one admin account is allowed.</div>
        <div class="footer"><a href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Go to Login</a></div>
    <?php endif; ?>
</div>
</body>
</html>