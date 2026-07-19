<?php
require_once 'config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("Invalid billing ID.");

$transactionService = new TransactionService();
$txn = $transactionService->getTransaction($id);
if (!$txn) die("Billing record not found.");

$clinic_name = "ClinicPro Medical Center";
$clinic_address = "123 Health St., City, Country";
$clinic_contact = "Tel: (02) 123-4567";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= htmlspecialchars($txn['transaction_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #1e4a6e; }
        .header small { color: #6c757d; }
        .row { display: flex; justify-content: space-between; margin: 5px 0; }
        .total { font-weight: bold; font-size: 1.2em; border-top: 1px solid #333; padding-top: 10px; margin-top: 10px; }
        .footer { text-align: center; margin-top: 30px; font-size: 0.9em; color: #6c757d; border-top: 1px solid #ddd; padding-top: 15px; }
        .badge-status { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 2rem; font-size: 0.8rem; }
        .badge-status.paid { background: #D1FAE5; color: #065F46; }
        .badge-status.partial { background: #FEF3C7; color: #92400E; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.refunded { background: #E5E7EB; color: #6B7280; }
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h2><?= $clinic_name ?></h2>
        <small><?= $clinic_address ?></small><br>
        <small><?= $clinic_contact ?></small>
    </div>

    <div>
        <div class="row"><strong>Transaction #:</strong> <span><?= htmlspecialchars($txn['transaction_number']) ?></span></div>
        <div class="row"><strong>Date:</strong> <span><?= date('F j, Y g:i A', strtotime($txn['transaction_date'])) ?></span></div>
        <div class="row"><strong>Patient:</strong> <span><?= htmlspecialchars($txn['patient_name'] ?? 'N/A') ?></span></div>
        <div class="row"><strong>Doctor:</strong> <span><?= htmlspecialchars($txn['doctor_name'] ?? 'N/A') ?></span></div>
        <div class="row"><strong>Cashier:</strong> <span><?= htmlspecialchars($txn['cashier_name'] ?? 'N/A') ?></span></div>
    </div>

    <hr>

    <div>
        <div class="row"><strong>Consultation Fee</strong> <span>₱<?= number_format($txn['consultation_fee'], 2) ?></span></div>
        <div class="row"><strong>Laboratory Fee</strong> <span>₱<?= number_format($txn['lab_fee'], 2) ?></span></div>
        <div class="row"><strong>Medicine Fee</strong> <span>₱<?= number_format($txn['medicine_fee'], 2) ?></span></div>
        <div class="row"><strong>Other Charges</strong> <span>₱<?= number_format($txn['other_charges'] ?? 0, 2) ?></span></div>
        <div class="row"><strong>Discount</strong> <span>- ₱<?= number_format($txn['discount'], 2) ?></span></div>
        <div class="row total"><strong>Grand Total</strong> <span>₱<?= number_format($txn['total_amount'], 2) ?></span></div>
        <div class="row"><strong>Amount Paid</strong> <span>₱<?= number_format($txn['amount_paid'], 2) ?></span></div>
        <div class="row"><strong>Change</strong> <span>₱<?= number_format($txn['change_amount'], 2) ?></span></div>
        <div class="row"><strong>Payment Method</strong> <span><?= htmlspecialchars($txn['payment_method']) ?></span></div>
        <?php if ($txn['reference_number']): ?>
            <div class="row"><strong>Reference #</strong> <span><?= htmlspecialchars($txn['reference_number']) ?></span></div>
        <?php endif; ?>
        <div class="row"><strong>Status</strong> <span class="badge-status <?= strtolower(str_replace(' ', '-', $txn['payment_status'])) ?>"><?= $txn['payment_status'] ?></span></div>
    </div>

    <div class="footer">
        <p>Thank you for your visit!</p>
        <p>This is a computer-generated receipt.</p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
        <a href="billing_view.php?id=<?= $id ?>" class="btn btn-secondary">Back</a>
    </div>
</body>
</html>