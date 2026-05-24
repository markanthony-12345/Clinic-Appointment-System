<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id  = (int)($_POST['doctor_id'] ?? 0);
    $date       = $_POST['appointment_date'] ?? '';
    $time       = $_POST['appointment_time'] ?? '';
    $lab_req    = ($_POST['laboratory_required'] ?? 'No') === 'Yes';

    if (!$patient_id || !$doctor_id || !$date || !$time) {
        header("Location: dashboard.php?error=missing_fields");
        exit;
    }

    $datetime = $date . ' ' . $time;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, 'Pending')");
        $stmt->execute([$patient_id, $doctor_id, $datetime]);

        if ($lab_req) {
            $stmt2 = $pdo->prepare("INSERT INTO laboratory (patient_id, laboratory_type, status) VALUES (?, 'From Appointment', 'Not Yet Taken')");
            $stmt2->execute([$patient_id]);
        }

        $pdo->commit();
        header("Location: dashboard.php?success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Appointment error: " . $e->getMessage());
        header("Location: dashboard.php?error=booking_failed");
        exit;
    }
}

header("Location: dashboard.php");
exit;
?>
