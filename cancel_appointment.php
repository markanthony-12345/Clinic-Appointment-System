<?php
require_once 'config.php';
requireLogin();

if (!in_array($_SESSION['user_logged']['role'], ['Admin', 'Staff'])) {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$appointment_id = (int)($_GET['id'] ?? 0);
if (!$appointment_id) {
    header("Location: appointments.php?error=invalid_id");
    exit;
}

try {
    $pdo->beginTransaction();

    // Cancel appointment
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);

    // Cancel linked lab records
    $labStmt = $pdo->prepare("UPDATE laboratory SET status = 'Cancelled' WHERE appointment_id = ?");
    $labStmt->execute([$appointment_id]);

    // Notify patient
    $getPatient = $pdo->prepare("SELECT patient_id FROM appointments WHERE appointment_id = ?");
    $getPatient->execute([$appointment_id]);
    $patient = $getPatient->fetch();
    if ($patient) {
        $notif = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, url, is_read, created_at)
            VALUES (?, 'appointment', 'Your appointment has been cancelled.', 'dashboard.php', 0, NOW())
        ");
        $notif->execute([$patient['patient_id']]);
    }

    $pdo->commit();
    header("Location: appointments.php?success=cancelled");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Cancel appointment error: " . $e->getMessage());
    header("Location: appointments.php?error=cancel_failed");
    exit;
}
?>