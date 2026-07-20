<?php
require_once 'config.php';
requireLogin();
require_once 'config_email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$patient_id = (int)$_POST['patient_id'];
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

if (!$patient_id || empty($subject) || empty($message)) {
    header("Location: patient_overview.php?patient_id=$patient_id&error=All fields are required.");
    exit;
}

// Get patient email and name
$stmt = $pdo->prepare("SELECT email, fullname FROM patients WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient || empty($patient['email'])) {
    header("Location: patient_overview.php?patient_id=$patient_id&error=Patient has no email address on file.");
    exit;
}

// Send email
$body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e6f9f; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8fafc; padding: 20px; border-radius: 0 0 8px 8px; }
            .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📩 Message from Clinic</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($patient['fullname']) . "</strong>,</p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr>
                <p style='font-size:0.9em; color:#6c757d;'>This message was sent via the clinic system. Please do not reply to this email directly.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Clinic Management System</p>
            </div>
        </div>
    </body>
    </html>
";

if (sendEmail($patient['email'], $subject, $body)) {
    header("Location: patient_overview.php?patient_id=$patient_id&success=Email sent successfully.");
} else {
    header("Location: patient_overview.php?patient_id=$patient_id&error=Failed to send email. Check your settings.");
}
exit;
?>