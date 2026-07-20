<?php
// test_email.php – Test email configuration (with AJAX support)

require_once 'config_email.php';

// If AJAX request
if (isset($_GET['ajax']) && isset($_GET['to'])) {
    header('Content-Type: application/json');
    $to = trim($_GET['to']);
    
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }
    
    $subject = "Test Email from Clinic System";
    $body = "
        <h2>✅ Email is Working!</h2>
        <p>This is a test email sent from your clinic management system.</p>
        <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
    ";
    
    if (sendEmail($to, $subject, $body)) {
        echo json_encode(['success' => true, 'message' => "Test email sent to $to"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Check your settings and error log.']);
    }
    exit;
}

// If accessed directly (non-AJAX)
$testEmail = 'your-test-email@gmail.com';
?>
<!DOCTYPE html>
<html>
<head><title>Email Test</title></head>
<body>
    <h1>Email Test</h1>
    <p>To test via browser, add ?ajax=1&to=email@example.com to the URL.</p>
    <p>Example: <a href="?ajax=1&to=your-email@gmail.com">Click here</a></p>
</body>
</html>