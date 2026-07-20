<?php
// Enable error reporting for debugging (remove later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// Retrieve all fields
$patient_id     = (int)($_POST['patient_id'] ?? 0);
$first_name     = trim($_POST['first_name'] ?? '');
$middle_name    = trim($_POST['middle_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$suffix         = trim($_POST['suffix'] ?? '');
$age            = (int)($_POST['age'] ?? 0);
$gender         = sanitize($_POST['gender'] ?? '');
$address        = sanitize($_POST['address'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$email          = trim($_POST['email'] ?? '');
$civil_status   = sanitize($_POST['civil_status'] ?? '');
$citizenship    = sanitize($_POST['citizenship'] ?? '');
$place_of_birth = sanitize($_POST['place_of_birth'] ?? '');

// --- Validation ---
$errors = [];

if (empty($first_name) || !preg_match('/^[A-Za-z\s\-]+$/', $first_name)) {
    $errors[] = "First Name is required and must contain only letters, spaces, and hyphens.";
}
if (!empty($middle_name) && !preg_match('/^[A-Za-z\s\-]*$/', $middle_name)) {
    $errors[] = "Middle Name must contain only letters, spaces, and hyphens.";
}
if (empty($last_name) || !preg_match('/^[A-Za-z\s\-]+$/', $last_name)) {
    $errors[] = "Last Name is required and must contain only letters, spaces, and hyphens.";
}
if (!empty($suffix) && !preg_match('/^[A-Za-z\.\s]+$/', $suffix)) {
    $errors[] = "Suffix must contain only letters, dots, and spaces.";
}
if ($age < 0 || $age > 150) {
    $errors[] = "Invalid age (0-150).";
}
$digits = preg_replace('/\D/', '', $contact_number);
if (strlen($digits) !== 10 || $digits[0] !== '9') {
    $errors[] = "Contact number must be exactly 10 digits starting with 9.";
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email address.";
}
if (empty($gender)) $errors[] = "Gender is required.";

if (!empty($errors)) {
    header("Location: edit_patient.php?id=$patient_id&error=" . urlencode(implode(", ", $errors)));
    exit;
}

$contact_clean = $digits; // 10 digits

// --- Build full name ---
$fullname = $first_name;
if ($middle_name) $fullname .= ' ' . $middle_name;
$fullname .= ' ' . $last_name;
if ($suffix) $fullname .= ' ' . $suffix;

// --- Update database ---
try {
    $stmt = $pdo->prepare("
        UPDATE patients 
        SET fullname = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
            age = ?, gender = ?, address = ?, contact_number = ?, 
            email = ?, civil_status = ?, citizenship = ?, place_of_birth = ?
        WHERE patient_id = ?
    ");
    $stmt->execute([
        $fullname, $first_name, $middle_name, $last_name, $suffix,
        $age, $gender, $address, $contact_clean,
        $email, $civil_status, $citizenship, $place_of_birth,
        $patient_id
    ]);

    header("Location: dashboard.php?success=Patient updated successfully");
    exit;

} catch (PDOException $e) {
    error_log("Update patient error: " . $e->getMessage());
    header("Location: edit_patient.php?id=$patient_id&error=" . urlencode("Database error: " . $e->getMessage()));
    exit;
}
?>