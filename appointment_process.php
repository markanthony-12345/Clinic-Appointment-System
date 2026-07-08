<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}
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

$patientService = new PatientService();
if (!$patientService->getById($patient_id)) {
    header("Location: dashboard.php?error=Invalid Patient");
    exit;
}

$apptService = new AppointmentService();
$availability = $apptService->checkAvailability($doctor_id, $date);
if (!$availability['available']) {
    $reason = $availability['reason'] ?? 'Doctor not available';
    header("Location: dashboard.php?error=" . urlencode($reason));
    exit;
}

// Check exact time slot
$slots = $apptService->getAvailableTimeSlots($doctor_id, $date);
if (!in_array($time, $slots)) {
    header("Location: dashboard.php?error=Time slot already taken");
    exit;
}

if ($apptService->createAppointment($patient_id, $doctor_id, $datetime, $lab_req)) {
    header("Location: dashboard.php?success=Appointment booked!");
} else {
    header("Location: dashboard.php?error=Database error");
}
exit;
?>