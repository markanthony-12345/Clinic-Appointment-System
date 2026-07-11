<?php
require_once 'config.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $question = trim($_POST['question']);
    $answer = trim($_POST['answer']);
    $new_password = $_POST['new_password'] ?? '';

    // Validate new password strength
    if (strlen($new_password) < 8) {
        $msg = "New password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $msg = "Password must contain letters, numbers, and special characters.";
    } else {
        // Check user and security answer
        $stmt = $pdo->prepare("SELECT user_id, security_answer FROM users WHERE username = ? AND security_question = ?");
        $stmt->execute([$username, $question]);
        $user = $stmt->fetch();
        if ($user && password_verify($answer, $user['security_answer'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed, $user['user_id']]);
            $msg = "Password updated successfully. <a href='login.php'>Login</a>";
        } else {
            $msg = "Invalid username, question, or answer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            padding: 1rem;
        }
        .card {
            max-width: 420px;
            width: 100%;
            border-radius: 1.5rem;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .btn-primary { background: #1e6f9f; border-color: #1e6f9f; border-radius: 2rem; width: 100%; }
        .btn-primary:hover { background: #155d85; }
    </style>
</head>
<body>
<div class="card p-4">
    <h2 class="text-center"><i class="fas fa-key me-2"></i>Reset Password</h2>
    <?php if ($msg): ?>
        <div class="alert <?= strpos($msg, 'successfully') !== false ? 'alert-success' : 'alert-danger' ?>"><?= $msg ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Security Question</label>
            <select name="question" class="form-select" required>
                <option value="">Select your question</option>
                <option>What is your mother's maiden name?</option>
                <option>What is your pet's name?</option>
                <option>What is your favorite color?</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Answer</label>
            <input type="text" name="answer" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="8">
            <div class="form-text">At least 8 characters with letters, numbers, and special characters.</div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-undo me-1"></i>Reset Password</button>
    </form>
    <p class="mt-3 text-center"><a href="login.php">Back to Login</a></p>
</div>
</body>
</html>