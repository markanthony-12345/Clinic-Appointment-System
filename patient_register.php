<?php
require_once 'config.php';
requireLogin();
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $age      = (int)($_POST['age'] ?? 0);
    $gender   = sanitize($_POST['gender'] ?? '');
    $address  = sanitize($_POST['address'] ?? '');
    $contact  = sanitize($_POST['contact_number'] ?? '');
 
    if (!$fullname || !$age || !$gender) {
        die("Missing required fields.");
    }
 
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO patients (fullname, age, gender, address, contact_number) VALUES (?,?,?,?,?)");
    $stmt->execute([$fullname, $age, $gender, $address, $contact]);
    $patient_id = $pdo->lastInsertId();
    $stmt2 = $pdo->prepare("INSERT INTO payments (patient_id, consultation_fee, total_amount) VALUES (?, 500, 500)");
    $stmt2->execute([$patient_id]);
    $pdo->commit();
    header("Location: dashboard.php?success=1");
    exit;
}
?>