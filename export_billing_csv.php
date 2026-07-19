<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="billing_export.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Transaction #', 'Patient', 'Doctor', 'Consultation', 'Lab', 'Medicine', 'Other', 'Discount', 'Total', 'Paid', 'Balance', 'Method', 'Status', 'Date', 'Cashier']);

$transactionService = new TransactionService();
$transactions = $transactionService->getTransactions([]);

foreach ($transactions as $txn) {
    $balance = $txn['total_amount'] - $txn['amount_paid'];
    fputcsv($output, [
        $txn['transaction_number'],
        $txn['patient_name'],
        $txn['doctor_name'],
        $txn['consultation_fee'],
        $txn['lab_fee'],
        $txn['medicine_fee'],
        $txn['other_charges'] ?? 0,
        $txn['discount'],
        $txn['total_amount'],
        $txn['amount_paid'],
        $balance,
        $txn['payment_method'],
        $txn['payment_status'],
        $txn['transaction_date'],
        $txn['cashier_name']
    ]);
}
fclose($output);
exit;
?>      