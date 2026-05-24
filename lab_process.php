<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'];
    $lab_type = $_POST['laboratory_type'];
    $status = $_POST['status'];
    $result = $_POST['result'];

    $check = $pdo->prepare("
        SELECT lab_id FROM laboratory 
        WHERE patient_id = ? AND status IN ('Not Yet Taken', 'Ongoing')
        LIMIT 1
    ");
    $check->execute([$patient_id]);
    $existing = $check->fetch();

    if ($existing) {
        $update = $pdo->prepare("
            UPDATE laboratory 
            SET laboratory_type = ?, status = ?, result = ? 
            WHERE lab_id = ?
        ");
        $update->execute([$lab_type, $status, $result, $existing['lab_id']]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO laboratory (patient_id, laboratory_type, status, result) 
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute([$patient_id, $lab_type, $status, $result]);

        $pdo->prepare("
            UPDATE payments 
            SET laboratory_fee = laboratory_fee + 300, 
                total_amount = consultation_fee + (laboratory_fee + 300) 
            WHERE patient_id = ?
        ")->execute([$patient_id]);
    }

    header("Location: laboratory.php?success=1");
    exit;
}
?>