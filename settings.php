<?php
require_once 'config.php';
requireAdmin();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = [
        'email_username' => trim($_POST['email_username']),
        'email_password' => trim($_POST['email_password']),
        'email_from_name' => trim($_POST['email_from_name']),
        'email_host' => trim($_POST['email_host']),
        'email_port' => trim($_POST['email_port']),
        'email_encryption' => trim($_POST['email_encryption']),
    ];

    try {
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        $success = "Email settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Load current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set defaults if not found
$settings['email_username'] = $settings['email_username'] ?? 'your-email@gmail.com';
$settings['email_password'] = $settings['email_password'] ?? 'your-app-password';
$settings['email_from_name'] = $settings['email_from_name'] ?? 'Clinic Management System';
$settings['email_host'] = $settings['email_host'] ?? 'smtp.gmail.com';
$settings['email_port'] = $settings['email_port'] ?? '465';
$settings['email_encryption'] = $settings['email_encryption'] ?? 'ssl';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .form-label { font-weight: 500; color: #2c5f8a; }
        .btn-primary { background: #1e6f9f; border-color: #1e6f9f; border-radius: 2rem; }
        .btn-primary:hover { background: #155d85; }
        .form-text { font-size: 0.8rem; color: #6c757d; }
        .password-toggle { cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container py-4">
            <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-cog me-2"></i>System Settings</h1>
                <a href="dashboard.php" class="btn btn-outline-primary">← Back to Dashboard</a>
            </header>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="fas fa-envelope me-2"></i>Email Settings</div>
                        <div class="card-body">
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="update_settings" value="1">

                                <div class="mb-3">
                                    <label class="form-label">Email Address (Gmail)</label>
                                    <input type="email" name="email_username" class="form-control" value="<?= htmlspecialchars($settings['email_username']) ?>" required>
                                    <div class="form-text">Your Gmail address used to send emails.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">App Password</label>
                                    <div class="input-group">
                                        <input type="password" name="email_password" id="email_password" class="form-control" value="<?= htmlspecialchars($settings['email_password']) ?>" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                            <i class="fas fa-eye" id="eye-icon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Gmail App Password (not your regular password). <a href="https://myaccount.google.com/apppasswords" target="_blank">Generate one here</a>.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" name="email_from_name" class="form-control" value="<?= htmlspecialchars($settings['email_from_name']) ?>" required>
                                    <div class="form-text">The name that will appear as the sender.</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" name="email_host" class="form-control" value="<?= htmlspecialchars($settings['email_host']) ?>" required>
                                        <div class="form-text">For Gmail: smtp.gmail.com</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="text" name="email_port" class="form-control" value="<?= htmlspecialchars($settings['email_port']) ?>" required>
                                        <div class="form-text">SSL: 465, TLS: 587</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Encryption</label>
                                        <select name="email_encryption" class="form-select">
                                            <option value="ssl" <?= $settings['email_encryption'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            <option value="tls" <?= $settings['email_encryption'] == 'tls' ? 'selected' : '' ?>>TLS</option>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save Email Settings</button>
                            </form>
                        </div>
                    </div>

                    <!-- Sidebar Link -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header"><i class="fas fa-link me-2"></i>Add to Sidebar</div>
                        <div class="card-body">
                            <p>To add this page to the sidebar, open <code>sidebar.php</code> and add this line:</p>
                            <pre class="bg-dark text-light p-2 rounded" style="font-size:0.8rem;">
&lt;li class="nav-item"&gt;
    &lt;a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php"&gt;
        &lt;i class="fas fa-cog"&gt;&lt;/i&gt; Settings
    &lt;/a&gt;
&lt;/li&gt;
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const input = document.getElementById('email_password');
            const icon = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function sendTestEmail() {
            const email = document.getElementById('test_email').value;
            if (!email) {
                document.getElementById('test_result').innerHTML = '<div class="alert alert-warning">Please enter an email address.</div>';
                return;
            }

            document.getElementById('test_result').innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sending...</div>';

            fetch('test_email.php?ajax=1&to=' + encodeURIComponent(email))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('test_result').innerHTML = '<div class="alert alert-success">✅ ' + data.message + '</div>';
                    } else {
                        document.getElementById('test_result').innerHTML = '<div class="alert alert-danger">❌ ' + data.message + '</div>';
                    }
                })
                .catch(err => {
                    document.getElementById('test_result').innerHTML = '<div class="alert alert-danger">❌ Network error: ' + err.message + '</div>';
                });
        }
    </script>
</body>
</html>