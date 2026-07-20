<?php
require_once 'config.php';
requireLogin();
require_once 'config_email.php';  // <-- ADDED

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: billing.php");
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: billing.php?error=invalid_request");
    exit;
}

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$transaction_id = (int)$_POST['transaction_id'];
$amount_paid = (float)$_POST['amount_paid'];
$payment_method = $_POST['payment_method'] ?? 'Cash';
$reference_number = $_POST['reference_number'] ?? null;
$notes = $_POST['notes'] ?? null;

if (!$transaction_id || $amount_paid <= 0) {
    header("Location: billing.php?error=invalid_amount");
    exit;
}

$transactionService = new TransactionService();
$txn = $transactionService->getTransaction($transaction_id);
if (!$txn) {
    header("Location: billing.php?error=transaction_not_found");
    exit;
}

$total = $txn['total_amount'];
$new_paid = $txn['amount_paid'] + $amount_paid;
if ($new_paid > $total) {
    $new_paid = $total;
}

$data = [
    'amount_paid' => $new_paid,
    'payment_method' => $payment_method,
    'reference_number' => $reference_number,
    'notes' => $notes
];

$result = $transactionService->updateTransaction($transaction_id, $data);

if ($result) {
    // ========== SEND PAYMENT RECEIPT EMAIL ==========
    $stmt = $pdo->prepare("
        SELECT p.fullname, p.email, t.transaction_number, t.total_amount, t.amount_paid 
        FROM transactions t
        JOIN patients p ON t.patient_id = p.patient_id
        WHERE t.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $txnData = $stmt->fetch();
    
    if ($txnData && !empty($txnData['email'])) {
        $balance = $txnData['total_amount'] - $txnData['amount_paid'];
        $subject = "Payment Receipt - " . $txnData['transaction_number'];
        $body = getPaymentReceiptEmail(
            $txnData['fullname'],
            $txnData['amount_paid'],
            $txnData['transaction_number'],
            $balance
        );
        sendEmail($txnData['email'], $subject, $body);
    }
    
    header("Location: billing.php?success=Payment recorded successfully");
} else {
    header("Location: billing.php?error=payment_failed");
}
exit;
?>