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
$lab_tests  = trim($_POST['lab_tests'] ?? '');
$lab_fee_total = (float)($_POST['lab_fee_total'] ?? 0);

// Medications are now handled only in the Medicines module
$medications = '';
$med_fee_total = 0;

if (!$patient_id || !$doctor_id || !$date || !$time) {
    header("Location: dashboard.php?error=Missing fields");
    exit;
}

$today = date('Y-m-d');
if ($date < $today) {
    header("Location: dashboard.php?error=Cannot book appointment in the past");
    exit;
}

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

$slots = $apptService->getAvailableTimeSlots($doctor_id, $date);
if (!in_array($time, $slots)) {
    header("Location: dashboard.php?error=Time slot already taken");
    exit;
}

$time_24 = date('H:i:s', strtotime($time));
$datetime = $date . ' ' . $time_24;

if ($apptService->createAppointment($patient_id, $doctor_id, $datetime, $lab_req, $lab_tests, $lab_fee_total, $medications, $med_fee_total)) {
    header("Location: dashboard.php?success=Appointment booked!");
} else {
    header("Location: dashboard.php?error=Database error");
}
exit;
?>