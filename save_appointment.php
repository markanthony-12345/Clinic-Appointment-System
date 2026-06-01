<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: appointment.php');
    exit;
}

$patient_id = $_POST['patient_id'] ?? '';
$doctor_id = $_POST['doctor_id'] ?? '';
$appointment_date = $_POST['appointment_date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';
$lab_required = $_POST['lab_required'] ?? 'No';

// Validate
if (empty($patient_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
    die("All fields are required");
}

// Combine date and time
$datetime = date('Y-m-d H:i:s', strtotime("$appointment_date $appointment_time"));

// Check if slot is still available
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                      WHERE doctor_id = ? AND appointment_date = ? 
                      AND status NOT IN ('Cancelled', 'Completed')");
$stmt->execute([$doctor_id, $datetime]);
if ($stmt->fetchColumn() > 0) {
    die("This slot is no longer available. Please select another time.");
}

// Insert appointment
$stmt = $pdo->prepare("INSERT INTO appointments 
                      (patient_id, doctor_id, appointment_date, status, lab_required, created_at) 
                      VALUES (?, ?, ?, 'Scheduled', ?, NOW())");
$stmt->execute([$patient_id, $doctor_id, $datetime, $lab_required]);

header('Location: appointment.php?success=1');
exit;
?>