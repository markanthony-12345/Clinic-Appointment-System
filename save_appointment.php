<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: appointment.php');
    exit;
}

// Debug: log what we received
error_log("POST data: " . print_r($_POST, true));

$patient_id = $_POST['patient_id'] ?? '';
$doctor_id = $_POST['doctor_id'] ?? '';
$appointment_date = $_POST['appointment_date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';
$lab_required = $_POST['lab_required'] ?? 'No';

// Validate all required fields
if (empty($patient_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
    die("Error: All fields are required. Received: patient=$patient_id, doctor=$doctor_id, date=$appointment_date, time=$appointment_time");
}

// Validate patient exists
$checkPatient = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
$checkPatient->execute([$patient_id]);
if (!$checkPatient->fetch()) {
    die("Error: Patient ID not found");
}

// Combine date and time into datetime
$datetime = date('Y-m-d H:i:s', strtotime("$appointment_date $appointment_time"));

// Check if slot is still available
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                      WHERE doctor_id = ? 
                      AND DATE(appointment_date) = ? 
                      AND TIME(appointment_date) = TIME(?)
                      AND status NOT IN ('Cancelled', 'Completed')");
$stmt->execute([$doctor_id, $appointment_date, $datetime]);
if ($stmt->fetchColumn() > 0) {
    die("Error: This time slot is no longer available. Please select another time.");
}

// Insert appointment
try {
    $stmt = $pdo->prepare("INSERT INTO appointments 
                          (patient_id, doctor_id, appointment_date, status, lab_required, created_at) 
                          VALUES (?, ?, ?, 'Scheduled', ?, NOW())");
    $stmt->execute([$patient_id, $doctor_id, $datetime, $lab_required]);
    
    header('Location: appointment.php?success=1');
    exit;
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>