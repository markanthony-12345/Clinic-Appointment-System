<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';

if (!$id || !in_array($status, ['Not Taken', 'Taken'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$stmt = $pdo->prepare("UPDATE medicines SET status = ? WHERE medicine_id = ?");
$result = $stmt->execute([$status, $id]);

echo json_encode(['success' => $result]);
?>
