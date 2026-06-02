<?php
require_once 'config.php';
requireAdmin(); // Admin only

// Tanggapin lang ang POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$patient_id     = (int)($_POST['patient_id'] ?? 0);
$fullname       = sanitize($_POST['fullname'] ?? '');
$age            = (int)($_POST['age'] ?? 0);
$gender         = sanitize($_POST['gender'] ?? '');
$address        = sanitize($_POST['address'] ?? '');
$contact_number = sanitize($_POST['contact_number'] ?? '');

// Basic validation
if (!$patient_id || !$fullname || !$age || !$gender) {
    header("Location: edit_patient.php?id=$patient_id&error=missing_fields");
    exit;
}

// I-update ang patient record sa database gamit ang prepared statement
$stmt = $pdo->prepare("
    UPDATE patients
    SET fullname = ?, age = ?, gender = ?, address = ?, contact_number = ?
    WHERE patient_id = ?
");
$stmt->execute([$fullname, $age, $gender, $address, $contact_number, $patient_id]);

// Redirect pabalik sa dashboard na may success message
header("Location: dashboard.php?success=1");
exit;
?>