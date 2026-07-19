<?php
require_once 'config.php';
requireLogin();

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

$data = [
    'patient_id' => (int)$_POST['patient_id'],
    'doctor_id' => !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null,
    'appointment_id' => !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null,
    'user_id' => $_SESSION['user_logged']['user_id'],
    'consultation_fee' => (float)$_POST['consultation_fee'],
    'lab_fee' => (float)$_POST['lab_fee'],
    'medicine_fee' => (float)$_POST['medicine_fee'],
    'other_charges' => (float)$_POST['other_charges'],
    'discount' => (float)$_POST['discount'],
    'amount_paid' => (float)$_POST['amount_paid'],
    'payment_method' => $_POST['payment_method'],
    'reference_number' => $_POST['reference_number'] ?? null,
    'notes' => $_POST['notes'] ?? null,
    'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d H:i:s')
];

if ($data['patient_id'] <= 0) {
    header("Location: billing_create.php?error=invalid_patient");
    exit;
}

$transactionService = new TransactionService();
$id = $transactionService->createTransaction($data);

if ($id) {
    header("Location: billing_view.php?id=$id&success=created");
} else {
    header("Location: billing_create.php?error=creation_failed");
}
exit;
?>