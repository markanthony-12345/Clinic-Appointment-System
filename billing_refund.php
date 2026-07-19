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
$txn = $transactionService->getTransaction($id);
if (!$txn) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    exit;
}
if ($txn['is_refunded']) {
    echo json_encode(['success' => false, 'message' => 'Already refunded']);
    exit;
}

$result = $transactionService->refundTransaction($id);
if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Refund failed']);
}
?>