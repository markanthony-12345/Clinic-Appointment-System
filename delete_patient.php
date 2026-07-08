<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$pid = (int)($_GET['patient_id'] ?? 0);
if (!$pid) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Delete appointments (no cascade)
    $stmt = $pdo->prepare("DELETE FROM appointments WHERE patient_id = ?");
    $stmt->execute([$pid]);

    // 2. Delete laboratory (if not cascaded, do it explicitly)
    $stmt = $pdo->prepare("DELETE FROM laboratory WHERE patient_id = ?");
    $stmt->execute([$pid]);

    // 3. Delete medicines (if not cascaded)
    $stmt = $pdo->prepare("DELETE FROM medicines WHERE patient_id = ?");
    $stmt->execute([$pid]);

    // 4. Delete payments (if not cascaded)
    $stmt = $pdo->prepare("DELETE FROM payments WHERE patient_id = ?");
    $stmt->execute([$pid]);

    // 5. Finally delete the patient
    $stmt = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
    $stmt->execute([$pid]);

    $pdo->commit();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Patient and all related records deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>