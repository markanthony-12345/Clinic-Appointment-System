<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$medicine_id = (int)($_GET['id'] ?? 0);
if (!$medicine_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM medicines WHERE medicine_id = ?");
    $stmt->execute([$medicine_id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Medicine record deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>