<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: dashboard.php?error=invalid_request");
    exit;
}

$patient_id = (int)$_POST['patient_id'];
$doctor_id = (int)$_POST['doctor_id'];
$appointment_date = $_POST['appointment_date'];
$appointment_time = $_POST['appointment_time'];
$lab_required = $_POST['laboratory_required'] ?? 'No';
$lab_tests = $_POST['lab_tests'] ?? '';       // comma-separated
$lab_fee_total = (float)($_POST['lab_fee_total'] ?? 0);

if (!$patient_id || !$doctor_id || !$appointment_date || !$appointment_time) {
    header("Location: dashboard.php?error=missing_fields");
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Insert appointment
    $datetime = $appointment_date . ' ' . $appointment_time . ':00';
    $stmt = $pdo->prepare("
        INSERT INTO appointments 
        (patient_id, doctor_id, appointment_date, status, lab_required, lab_tests, lab_fee_total)
        VALUES (?, ?, ?, 'Pending', ?, ?, ?)
    ");
    $stmt->execute([$patient_id, $doctor_id, $datetime, $lab_required, $lab_tests, $lab_fee_total]);
    $appointment_id = $pdo->lastInsertId();

    // 2. If lab is required, insert one lab record per test
    if ($lab_required === 'Yes' && !empty($lab_tests)) {
        $tests = array_map('trim', explode(',', $lab_tests));
        foreach ($tests as $test_name) {
            // You can fetch the fee from a price table if available; for now we use 0
            $fee = 0; 
            $insertLab = $pdo->prepare("
                INSERT INTO laboratory 
                (appointment_id, patient_id, doctor_id, laboratory_type, procedure_name, procedure_fee, appointment_date, appointment_time, status, payment_status)
                VALUES (?, ?, ?, 'From Appointment', ?, ?, ?, ?, 'Pending', 'Unpaid')
            ");
            $success = $insertLab->execute([
                $appointment_id,
                $patient_id,
                $doctor_id,
                $test_name,
                $fee,
                $appointment_date,
                $appointment_time
            ]);
            if (!$success) {
                // Log error for debugging
                error_log("Failed to insert lab record for appointment $appointment_id, test: $test_name");
                file_put_contents('lab_insert_errors.log', date('Y-m-d H:i:s') . " - Failed to insert lab for appt $appointment_id, test: $test_name\n", FILE_APPEND);
            }
        }
    }

    // 3. Notifications
    $msg = "New appointment #$appointment_id scheduled for " . date('M d, Y', strtotime($appointment_date)) . " at $appointment_time.";
    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, url, is_read, created_at)
        VALUES (?, 'appointment', ?, 'appointment_view.php?id=$appointment_id', 0, NOW())
    ");
    $notifStmt->execute([$patient_id, $msg]);

    $staffNotif = "New appointment #$appointment_id for patient ID $patient_id.";
    $staffStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, url, is_read, created_at)
        SELECT user_id, 'appointment', ?, 'appointment_view.php?id=$appointment_id', 0, NOW()
        FROM users WHERE role IN ('Admin', 'Staff')
    ");
    $staffStmt->execute([$staffNotif]);

    $pdo->commit();
    header("Location: dashboard.php?success=appointment_created");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Appointment creation error: " . $e->getMessage());
    file_put_contents('appointment_errors.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    header("Location: dashboard.php?error=appointment_failed");
    exit;
}
?>