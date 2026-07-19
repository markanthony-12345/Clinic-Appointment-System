<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_patient_info' && isset($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    $transactionService = new TransactionService();
    $data = $transactionService->getPatientBillingInfo($patient_id);
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
    }
    exit;
}

if ($action === 'get_patient_transactions' && isset($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    $transactionService = new TransactionService();
    $transactions = $transactionService->getPatientTransactions($patient_id);
    echo json_encode(['success' => true, 'data' => $transactions]);
    exit;
}

if ($action === 'get_outstanding_balances') {
    $transactionService = new TransactionService();
    $list = $transactionService->getOutstandingBalances();
    echo json_encode(['success' => true, 'data' => $list]);
    exit;
}

if ($action === 'get_transaction' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $transactionService = new TransactionService();
    $txn = $transactionService->getTransaction($id);
    if ($txn) {
        echo json_encode(['success' => true, 'data' => $txn]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
?>