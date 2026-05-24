<?php
require_once 'config.php';
requireLogin();
if ($_POST) {
    $lab_id = $_POST['lab_id'];
    $patient_id = $_POST['patient_id'];
    $lab_type = $_POST['laboratory_type'];
    $status = $_POST['status'];
    $result = $_POST['result'];
    $stmt = $pdo->prepare("UPDATE laboratory SET patient_id=?, laboratory_type=?, status=?, result=? WHERE lab_id=?");
    $stmt->execute([$patient_id, $lab_type, $status, $result, $lab_id]);
    header("Location: laboratory.php?success=1");
}
?>