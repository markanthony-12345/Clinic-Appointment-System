<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['patients' => [], 'appointments' => []]);
    exit;
}

$searchTerm = '%' . $query . '%';

// Search patients
$patients = $pdo->prepare("
    SELECT patient_id, fullname, age, gender, contact_number 
    FROM patients 
    WHERE fullname LIKE ? 
    ORDER BY fullname LIMIT 10
");
$patients->execute([$searchTerm]);
$patientResults = $patients->fetchAll();

// Search appointments
$appointments = $pdo->prepare("
    SELECT a.appointment_id, p.fullname, a.appointment_date, a.status, d.doctor_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE p.fullname LIKE ? OR d.doctor_name LIKE ?
    ORDER BY a.appointment_date DESC LIMIT 10
");
$appointments->execute([$searchTerm, $searchTerm]);
$apptResults = $appointments->fetchAll();

echo json_encode([
    'patients' => $patientResults,
    'appointments' => $apptResults
]);
?>