<?php
require_once 'config.php';
requireLogin();

if ($_POST) {
    $lab_id = $_POST['lab_id'];
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'] ?? null;
    $lab_type = $_POST['laboratory_type'];
    $status = $_POST['status'];
    $result = $_POST['result'];

    // Validate doctor if provided
    if ($doctor_id) {
        $docCheck = $pdo->prepare("SELECT doctor_id FROM doctors WHERE doctor_id = ?");
        $docCheck->execute([$doctor_id]);
        if ($docCheck->rowCount() === 0) {
            $doctor_id = null;
        }
    }

    $stmt = $pdo->prepare("UPDATE laboratory SET patient_id=?, doctor_id=?, laboratory_type=?, status=?, result=? WHERE lab_id=?");
    $stmt->execute([$patient_id, $doctor_id, $lab_type, $status, $result, $lab_id]);
    header("Location: laboratory.php?success=1");
}
?>