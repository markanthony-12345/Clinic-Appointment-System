<?php
// config_email.php – Email configuration (reads from database)

require_once __DIR__ . '/config.php';

// Load settings from database
function getEmailSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Include PHPMailer
require_once __DIR__ . '/classes/src/PHPMailer.php';
require_once __DIR__ . '/classes/src/SMTP.php';
require_once __DIR__ . '/classes/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Load settings from database
        $username = getEmailSetting('email_username', 'your-email@gmail.com');
        $password = getEmailSetting('email_password', 'your-app-password');
        $fromName = getEmailSetting('email_from_name', 'Clinic Management System');
        $host = getEmailSetting('email_host', 'smtp.gmail.com');
        $port = (int)getEmailSetting('email_port', '465');
        $encryption = getEmailSetting('email_encryption', 'ssl');
        
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = $port;
        
        // Sender & recipient
        $mail->setFrom($username, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($username, $fromName);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// ============================================================
// EMAIL TEMPLATES (unchanged)
// ============================================================

function getAppointmentConfirmationEmail($patient_name, $doctor_name, $date, $time, $appointment_id) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e6f9f; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8fafc; padding: 20px; border-radius: 0 0 8px 8px; }
            .details { margin: 20px 0; }
            .details td { padding: 8px; }
            .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 0.9em; }
            .btn { background: #1e6f9f; color: white; padding: 10px 25px; text-decoration: none; border-radius: 25px; display: inline-block; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✅ Appointment Confirmed</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>$patient_name</strong>,</p>
                <p>Your appointment has been <strong>confirmed</strong>.</p>
                <table class='details'>
                    <tr><td><strong>Doctor:</strong></td><td>$doctor_name</td></tr>
                    <tr><td><strong>Date:</strong></td><td>$date</td></tr>
                    <tr><td><strong>Time:</strong></td><td>$time</td></tr>
                    <tr><td><strong>Reference #:</strong></td><td>APT-$appointment_id</td></tr>
                </table>
                <p style='margin-top:20px;'>
                    <a href='http://localhost/Clinic-Appointment-System/dashboard.php' class='btn'>View My Appointments</a>
                </p>
                <p><small>Please arrive 15 minutes before your scheduled time.</small></p>
            </div>
            <div class='footer'>
                <p>© 2026 Clinic Management System</p>
                <p>This is an automated message. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function getPaymentReceiptEmail($patient_name, $amount, $transaction_number, $balance) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8fafc; padding: 20px; border-radius: 0 0 8px 8px; }
            .receipt { border: 1px solid #ddd; padding: 15px; border-radius: 8px; }
            .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🧾 Payment Receipt</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>$patient_name</strong>,</p>
                <p>Thank you for your payment.</p>
                <div class='receipt'>
                    <p><strong>Transaction #:</strong> $transaction_number</p>
                    <p><strong>Amount Paid:</strong> ₱" . number_format($amount, 2) . "</p>
                    <p><strong>Remaining Balance:</strong> ₱" . number_format($balance, 2) . "</p>
                </div>
                <p><a href='http://localhost/Clinic-Appointment-System/billing.php' class='btn'>View All Transactions</a></p>
            </div>
            <div class='footer'>
                <p>© 2026 Clinic Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function getAppointmentReminderEmail($patient_name, $doctor_name, $date, $time) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ffc107; color: #333; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8fafc; padding: 20px; border-radius: 0 0 8px 8px; }
            .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔔 Appointment Reminder</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>$patient_name</strong>,</p>
                <p>This is a friendly reminder about your upcoming appointment.</p>
                <p><strong>Doctor:</strong> $doctor_name</p>
                <p><strong>Date:</strong> $date</p>
                <p><strong>Time:</strong> $time</p>
                <p>We look forward to seeing you!</p>
            </div>
            <div class='footer'>
                <p>© 2026 Clinic Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>