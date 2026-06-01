<?php
require_once 'config.php';
requireLogin();
if ($_POST) {
    $stmt = $pdo->prepare("INSERT INTO medicines (patient_id, medicine_name, dosage, frequency, duration, status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$_POST['patient_id'], $_POST['medicine_name'], $_POST['dosage'], $_POST['frequency'], $_POST['duration'], $_POST['status']]);
    header("Location: medicine.php?success=1");
}
?>