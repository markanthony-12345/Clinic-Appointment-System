<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'] ?? null;
    $doctor_id  = $_POST['doctor_id'] ?? null;
    $lab_type   = $_POST['laboratory_type'] ?? '';
    $status     = $_POST['status'] ?? '';
    $result     = $_POST['result'] ?? '';

    if (empty($patient_id) || !is_numeric($patient_id)) {
        header("Location: laboratory.php?error=invalid_patient");
        exit;
    }

    // Validate patient exists
    $patientCheck = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    $patientCheck->execute([$patient_id]);
    if ($patientCheck->rowCount() === 0) {
        header("Location: laboratory.php?error=patient_not_found");
        exit;
    }

    // Validate doctor if provided
    if ($doctor_id) {
        $docCheck = $pdo->prepare("SELECT doctor_id FROM doctors WHERE doctor_id = ?");
        $docCheck->execute([$doctor_id]);
        if ($docCheck->rowCount() === 0) {
            $doctor_id = null;
        }
    }

    try {
        $pdo->beginTransaction();

        // Check if there's an existing lab for this patient with status Not Yet Taken or Ongoing
        $check = $pdo->prepare("SELECT lab_id FROM laboratory WHERE patient_id = ? AND status IN ('Not Yet Taken', 'Ongoing') LIMIT 1");
        $check->execute([$patient_id]);
        $existing = $check->fetch();

        if ($existing) {
            // Update existing lab
            $update = $pdo->prepare("UPDATE laboratory SET laboratory_type = ?, status = ?, result = ?, doctor_id = ? WHERE lab_id = ?");
            $update->execute([$lab_type, $status, $result, $doctor_id, $existing['lab_id']]);
        } else {
            // Insert new lab
            $insert = $pdo->prepare("INSERT INTO laboratory (patient_id, doctor_id, laboratory_type, status, result) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$patient_id, $doctor_id, $lab_type, $status, $result]);

            // Add lab fee to payment if not already
            $paymentCheck = $pdo->prepare("SELECT payment_id FROM payments WHERE patient_id = ?");
            $paymentCheck->execute([$patient_id]);
            if ($paymentCheck->rowCount() > 0) {
                $pdo->prepare("UPDATE payments SET laboratory_fee = laboratory_fee + 300, total_amount = consultation_fee + (laboratory_fee + 300) WHERE patient_id = ?")
                    ->execute([$patient_id]);
            }
        }

        $pdo->commit();
        header("Location: laboratory.php?success=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Lab process error: " . $e->getMessage());
        header("Location: laboratory.php?error=database");
        exit;
    }
}
?>