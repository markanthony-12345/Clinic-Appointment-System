<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $payment_amount = floatval($_POST['payment_amount']);

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

    // Redirect back to payments page with success message
    header("Location: payments.php?success=1");
    exit;
}

// If accessed directly without POST, go back
header("Location: payments.php");
exit;
?>