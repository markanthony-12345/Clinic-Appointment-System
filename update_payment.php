<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$patient_id = (int)$_POST['patient_id'];
$payment_amount = floatval($_POST['payment_amount']);

if ($patient_id <= 0 || $payment_amount <= 0) {
    header("Location: patient_overview.php?patient_id=$patient_id&error=invalid_amount");
    exit;
}

// Fetch current payment record
$stmt = $pdo->prepare("SELECT amount_paid, total_amount FROM payments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$pay = $stmt->fetch();

if ($pay) {
    $new_paid = $pay['amount_paid'] + $payment_amount;
    $total = $pay['total_amount'];

    // Prevent overpayment
    if ($new_paid > $total) {
        $new_paid = $total;
    }

    $update = $pdo->prepare("UPDATE payments SET amount_paid = ? WHERE patient_id = ?");
    $update->execute([$new_paid, $patient_id]);
}

// Redirect back to patient overview with success message
header("Location: patient_overview.php?patient_id=$patient_id&success=1");
exit;
?>