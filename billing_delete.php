<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    echo json_encode(['success' => false, 'message' => 'Admin access only']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$transactionService = new TransactionService();
$result = $transactionService->deleteTransaction($id);
if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
?>