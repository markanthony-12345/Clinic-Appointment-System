<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ? AND status != 'Cancelled'");
$result = $stmt->execute([$id]);

if ($result && $stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not update appointment']);
}
?>
