<?php
// send_reminders.php – Send email reminders to patients with tomorrow's appointments

require_once 'config.php';
require_once 'config_email.php';

// Get appointments for tomorrow
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$stmt = $pdo->prepare("
    SELECT a.*, p.fullname, p.email, d.doctor_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE DATE(a.appointment_date) = ? AND a.status = 'Pending'
");
$stmt->execute([$tomorrow]);
$appointments = $stmt->fetchAll();

$count = 0;
foreach ($appointments as $app) {
    if (!empty($app['email'])) {
        $subject = "Appointment Reminder - " . date('F j, Y', strtotime($app['appointment_date']));
        $body = getAppointmentReminderEmail(
            $app['fullname'],
            $app['doctor_name'],
            date('F j, Y', strtotime($app['appointment_date'])),
            date('g:i A', strtotime($app['appointment_date']))
        );
        if (sendEmail($app['email'], $subject, $body)) {
            $count++;
        }
    }
}

echo "✅ $count reminder(s) sent for appointments on " . date('F j, Y', strtotime($tomorrow)) . ".";
?>