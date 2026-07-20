<?php
require_once 'config.php';
requireLogin();
require_once 'config_email.php';  // <-- ADDED

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: dashboard.php?error=invalid_request");
    exit;
}

$patient_id = (int)$_POST['patient_id'];
$doctor_id = (int)$_POST['doctor_id'];
$appointment_date = $_POST['appointment_date'];
$appointment_time = $_POST['appointment_time'];
$lab_required = $_POST['laboratory_required'] ?? 'No';
$lab_tests = $_POST['lab_tests'] ?? '';
$lab_fee_total = (float)($_POST['lab_fee_total'] ?? 0);

if (!$patient_id || !$doctor_id || !$appointment_date || !$appointment_time) {
    header("Location: dashboard.php?error=missing_fields");
    exit;
}

try {
    $pdo->beginTransaction();

    $datetime = $appointment_date . ' ' . $appointment_time . ':00';
    $stmt = $pdo->prepare("
        INSERT INTO appointments 
        (patient_id, doctor_id, appointment_date, status, lab_required, lab_tests, lab_fee_total)
        VALUES (?, ?, ?, 'Pending', ?, ?, ?)
    ");
    $stmt->execute([$patient_id, $doctor_id, $datetime, $lab_required, $lab_tests, $lab_fee_total]);
    $appointment_id = $pdo->lastInsertId();

    // ========== SEND EMAIL CONFIRMATION ==========
    $stmt = $pdo->prepare("
        SELECT p.email, p.fullname, d.doctor_name 
        FROM patients p
        JOIN doctors d ON d.doctor_id = ?
        WHERE p.patient_id = ?
    ");
    $stmt->execute([$doctor_id, $patient_id]);
    $patient = $stmt->fetch();
    
    if ($patient && !empty($patient['email'])) {
        $subject = "Appointment Confirmation #APT-" . $appointment_id;
        $date_formatted = date('F j, Y', strtotime($appointment_date));
        $time_formatted = date('g:i A', strtotime($appointment_time));
        $body = getAppointmentConfirmationEmail(
            $patient['fullname'],
            $patient['doctor_name'],
            $date_formatted,
            $time_formatted,
            $appointment_id
        );
        sendEmail($patient['email'], $subject, $body);
    }

    $pdo->commit();
    header("Location: dashboard.php?success=appointment_created");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Appointment creation error: " . $e->getMessage());
    header("Location: dashboard.php?error=appointment_failed");
    exit;
}
?>