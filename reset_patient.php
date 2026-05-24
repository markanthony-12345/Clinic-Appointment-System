<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$pid = (int)($_GET['patient_id'] ?? 0);

if (!$pid) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

// Only Admin can reset records
if ($_SESSION['user_logged']['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access only']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Reset appointments back to Pending
    $pdo->prepare("UPDATE appointments SET status = 'Pending' WHERE patient_id = ? AND status != 'Cancelled'")
        ->execute([$pid]);

    // Reset laboratory back to Not Yet Taken
    $pdo->prepare("UPDATE laboratory SET status = 'Not Yet Taken' WHERE patient_id = ?")
        ->execute([$pid]);

    // Reset medicines back to Not Taken
    $pdo->prepare("UPDATE medicines SET status = 'Not Taken' WHERE patient_id = ?")
        ->execute([$pid]);

    // Reset payment back to 0
    $pdo->prepare("UPDATE payments SET amount_paid = 0, payment_status = 'Unpaid' WHERE patient_id = ?")
        ->execute([$pid]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Patient records have been reset successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
