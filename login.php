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
    $error = "Too many failed attempts. Please wait " . ceil($lockout_remaining / 60) . " minute(s).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rate['locked']) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request.";
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Admin Login</title>
    <!-- Bootstrap 5 & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Full page gradient background */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 1rem;
        }

        .login-card {
            max-width: 420px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.2s ease;
        }

        .login-card:hover {
            transform: translateY(-2px);
        }

        .login-icon {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .login-icon i {
            font-size: 3.5rem;
            color: #1e6f9f;
            background: rgba(30, 111, 159, 0.1);
            padding: 1rem;
            border-radius: 50%;
            box-shadow: 0 8px 20px rgba(30, 111, 159, 0.15);
        }

        .login-title {
            text-align: center;
            font-weight: 700;
            font-size: 1.8rem;
            color: #1a2c3e;
            margin-bottom: 0.3rem;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            text-align: center;
            color: #5b7f9c;
            font-size: 0.9rem;
            margin-bottom: 1.8rem;
        }

        .form-floating {
            margin-bottom: 1.2rem;
        }

        .form-floating input {
            border-radius: 0.75rem;
            border: 1px solid #dfe6ef;
            padding: 0.8rem 1rem;
            height: auto;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-floating input:focus {
            border-color: #1e6f9f;
            box-shadow: 0 0 0 3px rgba(30, 111, 159, 0.15);
            background: white;
        }

        .form-floating label {
            padding: 0.8rem 1rem;
            color: #5b7f9c;
            font-weight: 500;
        }

        .btn-login {
            width: 100%;
            padding: 0.8rem;
            border-radius: 0.75rem;
            font-weight: 600;
            background: #1e6f9f;
            border: none;
            color: white;
            font-size: 1rem;
            transition: background 0.2s, transform 0.1s;
            box-shadow: 0 4px 12px rgba(30, 111, 159, 0.3);
        }

        .btn-login:hover {
            background: #155d85;
            transform: scale(1.02);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        .btn-login:disabled {
            background: #8ba3bc;
            box-shadow: none;
            cursor: not-allowed;
        }

        .alert {
            border-radius: 0.75rem;
            font-size: 0.9rem;
            padding: 0.8rem 1rem;
            border: none;
        }

        .alert-danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .alert-success {
            background: #e0f2e9;
            color: #1e6f3f;
        }

        .lockout-timer {
            text-align: center;
            margin-top: 1rem;
            font-weight: 600;
            color: #c0392b;
            font-size: 0.95rem;
        }

        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: #8ba3bc;
            font-size: 0.8rem;
        }

        .footer-text a {
            color: #1e6f9f;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.2rem;
            }
            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="login-card">
    <!-- Icon -->
    <div class="login-icon">
        <i class="fas fa-clinic-medical"></i>
    </div>

    <!-- Title -->
    <div class="login-title">Welcome Back</div>
    <div class="login-subtitle">Sign in to manage your clinic</div>

    <!-- Error / Success Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-danger">Your session has expired. Please log in again.</div>
    <?php endif; ?>
    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Registration successful! Please log in.</div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" <?= $lockout_remaining > 0 ? 'onsubmit="return false;"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-floating">
            <input type="text" class="form-control" id="username" name="username" 
                   placeholder="Username" required autocomplete="username"
                   <?= $lockout_remaining > 0 ? 'disabled' : '' ?>>
            <label for="username"><i class="fas fa-user me-2"></i>Username</label>
        </div>

        <div class="form-floating">
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="Password" required autocomplete="current-password"
                   <?= $lockout_remaining > 0 ? 'disabled' : '' ?>>
            <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
        </div>

        <button type="submit" class="btn-login" <?= $lockout_remaining > 0 ? 'disabled' : '' ?>>
            <i class="fas fa-sign-in-alt me-2"></i>Sign In
        </button>
    </form>

    <!-- Lockout countdown (if active) -->
    <?php if ($lockout_remaining > 0): ?>
        <div class="lockout-timer" id="countdown">
            <i class="fas fa-clock me-2"></i>
            Locked: <?= ceil($lockout_remaining / 60) ?>m remaining
        </div>
        <script>
            let secs = <?= $lockout_remaining ?>;
            const el = document.getElementById('countdown');
            const interval = setInterval(() => {
                secs--;
                if (secs <= 0) {
                    clearInterval(interval);
                    location.reload();
                } else {
                    const m = Math.floor(secs / 60), s = secs % 60;
                    el.innerHTML = `<i class="fas fa-clock me-2"></i>Locked: ${m}m ${s}s remaining`;
                }
            }, 1000);
        </script>
    <?php endif; ?>

    <!-- Optional footer link (e.g., to register if enabled) -->
    <div class="footer-text">
        <a href="register.php"><i class="fas fa-user-plus me-1"></i>Create an account</a>
        &nbsp;·&nbsp; <span>Admin access only</span>
    </div>
</div>

</body>
</html>