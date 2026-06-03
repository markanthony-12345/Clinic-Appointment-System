<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// CSRF validation
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: dashboard.php?error=invalid_request");
    exit;
}

$patient_id = (int)($_POST['patient_id'] ?? 0);
$doctor_id  = (int)($_POST['doctor_id'] ?? 0);
$date       = trim($_POST['appointment_date'] ?? '');
$time       = trim($_POST['appointment_time'] ?? '');
$lab_req    = ($_POST['laboratory_required'] ?? 'No') === 'Yes';

if (!$patient_id || !$doctor_id || !$date || !$time) {
    header("Location: dashboard.php?error=Missing fields");
    exit;
}

$time_24 = date('H:i:s', strtotime($time));
$datetime = $date . ' ' . $time_24;

// Validate patient exists
$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
$stmt->execute([$patient_id]);
if (!$stmt->fetch()) {
    header("Location: dashboard.php?error=Invalid Patient ID");
    exit;
}

// Use doctorAvailable() from config.php – checks both day & max patients
$availability = doctorAvailable($pdo, $doctor_id, $date);
if (!$availability['available']) {
    $reason = $availability['reason'] ?? 'Doctor not available on this date';
    header("Location: dashboard.php?error=" . urlencode($reason));
    exit;
}

// Check exact time slot not taken
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status NOT IN ('Cancelled','Completed')");
$stmt->execute([$doctor_id, $datetime]);
if ($stmt->fetchColumn() > 0) {
    header("Location: dashboard.php?error=Time slot already taken");
    exit;
}

// Insert appointment
try {
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, 'Pending')");
    $stmt->execute([$patient_id, $doctor_id, $datetime]);

    if ($lab_req) {
        $stmt2 = $pdo->prepare("INSERT INTO laboratory (patient_id, laboratory_type, status) VALUES (?, 'From Appointment', 'Not Yet Taken')");
        $stmt2->execute([$patient_id]);
    }

    header("Location: dashboard.php?success=Appointment booked!");
    exit;
} catch (PDOException $e) {
    error_log("Appointment error: " . $e->getMessage());
    header("Location: dashboard.php?error=Database error");
    exit;
}
?>