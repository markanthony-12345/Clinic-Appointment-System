<?php
require_once 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$patient_id     = (int)($_POST['patient_id'] ?? 0);
$fullname       = sanitize($_POST['fullname'] ?? '');
$age            = (int)($_POST['age'] ?? 0);
$sex            = sanitize($_POST['sex'] ?? '');
$address        = sanitize($_POST['address'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$email          = trim($_POST['email'] ?? '');
$civil_status   = sanitize($_POST['civil_status'] ?? '');
$citizenship    = sanitize($_POST['citizenship'] ?? '');
$place_of_birth = sanitize($_POST['place_of_birth'] ?? '');

// Validate
$errors = [];
if (!preg_match('/^[A-Za-z\s\-]+$/', $fullname) || str_word_count($fullname) < 2) {
    $errors[] = "Invalid full name.";
}
if ($age < 0 || $age > 150) {
    $errors[] = "Invalid age.";
}
$digits = preg_replace('/\D/', '', $contact_number);
if (strlen($digits) !== 11) {
    $errors[] = "Contact number must be 11 digits.";
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email.";
}
if (empty($sex)) $errors[] = "Sex is required.";

if (!empty($errors)) {
    header("Location: edit_patient.php?id=$patient_id&error=" . urlencode(implode(", ", $errors)));
    exit;
}

$contact_clean = $digits;

$stmt = $pdo->prepare("
    UPDATE patients 
    SET fullname = ?, age = ?, sex = ?, address = ?, contact_number = ?, 
        email = ?, civil_status = ?, citizenship = ?, place_of_birth = ?
    WHERE patient_id = ?
");
$stmt->execute([
    $fullname, $age, $sex, $address, $contact_clean,
    $email, $civil_status, $citizenship, $place_of_birth,
    $patient_id
]);

header("Location: dashboard.php?success=Patient updated successfully");
exit;
?>