<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$reason = trim($_GET['reason'] ?? '');

if (!$id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE appointments 
    SET status = 'Cancelled', cancellation_reason = ? 
    WHERE appointment_id = ? AND status = 'Pending'
");
$result = $stmt->execute([$reason, $id]);

if ($result && $stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not cancel appointment']);
}
?>