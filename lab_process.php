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
    file_put_contents('debug.log', print_r($_POST, true), FILE_APPEND);
    // ... rest of code
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'] ?? null;
    $lab_type = $_POST['laboratory_type'] ?? '';
    $status = $_POST['status'] ?? '';
    $result = $_POST['result'] ?? '';

    // === VALIDATION: Check if patient_id is valid ===
    if (empty($patient_id) || !is_numeric($patient_id)) {
        header("Location: laboratory.php?error=invalid_patient");
        exit;
    }

    // === VALIDATION: Verify patient exists in patients table ===
    $patientCheck = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    $patientCheck->execute([$patient_id]);
    
    if ($patientCheck->rowCount() === 0) {
        header("Location: laboratory.php?error=patient_not_found");
        exit;
    }
    // === END VALIDATION ===

    try {
        $pdo->beginTransaction();

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

            // Also fix: This UPDATE will fail with same error if patient has no payment record
            $paymentCheck = $pdo->prepare("SELECT payment_id FROM payments WHERE patient_id = ?");
            $paymentCheck->execute([$patient_id]);
            
            if ($paymentCheck->rowCount() > 0) {
                $pdo->prepare("
                    UPDATE payments 
                    SET laboratory_fee = laboratory_fee + 300, 
                        total_amount = consultation_fee + (laboratory_fee + 300) 
                    WHERE patient_id = ?
                ")->execute([$patient_id]);
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
>>>>>>> e47bb6c16c358686372866dd16fcde1ea2f9833b
}
?>