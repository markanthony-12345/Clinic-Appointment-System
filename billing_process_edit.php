<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: transactions.php");
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: transactions.php?error=invalid_request");
    exit;
}

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$id = (int)$_POST['id'];
$amount_paid = (float)$_POST['amount_paid'];
$payment_method = $_POST['payment_method'];
$reference_number = $_POST['reference_number'] ?? null;
$notes = $_POST['notes'] ?? null;

$transactionService = new TransactionService();
$txn = $transactionService->getTransaction($id);
if (!$txn) {
    header("Location: transactions.php?error=not_found");
    exit;
}

if ($amount_paid > $txn['total_amount']) {
    header("Location: transaction_edit.php?id=$id&error=Amount cannot exceed total.");
    exit;
}

$data = [
    'amount_paid' => $amount_paid,
    'payment_method' => $payment_method,
    'reference_number' => $reference_number,
    'notes' => $notes
];

$result = $transactionService->updateTransaction($id, $data);
if ($result) {
    header("Location: transaction_view.php?id=$id&success=updated");
} else {
    header("Location: transaction_edit.php?id=$id&error=update_failed");
}
exit;
?>