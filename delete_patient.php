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
    $stmt = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
    $stmt->execute([$pid]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Patient deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>