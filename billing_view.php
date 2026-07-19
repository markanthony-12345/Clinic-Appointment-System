<?php
require_once 'config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: billing.php?error=invalid_id");
    exit;
}

$transactionService = new TransactionService();
$txn = $transactionService->getTransaction($id);
if (!$txn) {
    header("Location: billing.php?error=not_found");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing #<?= htmlspecialchars($txn['transaction_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: none; margin-bottom: 1.5rem; }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; }
        .badge-status { padding: 0.35rem 0.85rem; border-radius: 2rem; font-weight: 500; font-size: 0.85rem; }
        .badge-status.paid { background: #D1FAE5; color: #065F46; }
        .badge-status.partial { background: #FEF3C7; color: #92400E; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.refunded { background: #E5E7EB; color: #6B7280; }
        .info-label { font-weight: 600; color: #4a6f8c; }
        .receipt-btn { border-radius: 2rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-file-invoice me-2"></i>Billing Details</h1>
                <div>
                    <a href="billing_receipt.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary receipt-btn"><i class="fas fa-print me-1"></i>Print Receipt</a>
                    <a href="billing.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] == 'created'): ?>
                <div class="alert alert-success">Billing record created successfully!</div>
            <?php endif; ?>
            <?php if (isset($_GET['success']) && $_GET['success'] == 'updated'): ?>
                <div class="alert alert-success">Billing record updated successfully!</div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-info-circle me-2"></i>Billing Information</div>
                        <div class="card-body">
                            <p><span class="info-label">Transaction #:</span> <?= htmlspecialchars($txn['transaction_number']) ?></p>
                            <p><span class="info-label">Date:</span> <?= date('F j, Y g:i A', strtotime($txn['transaction_date'])) ?></p>
                            <p><span class="info-label">Patient:</span> <?= htmlspecialchars($txn['patient_name'] ?? 'N/A') ?></p>
                            <p><span class="info-label">Doctor:</span> <?= htmlspecialchars($txn['doctor_name'] ?? 'N/A') ?></p>
                            <p><span class="info-label">Appointment:</span> <?= $txn['appointment_id'] ? '#' . $txn['appointment_id'] : 'N/A' ?></p>
                            <p><span class="info-label">Cashier:</span> <?= htmlspecialchars($txn['cashier_name'] ?? 'N/A') ?></p>
                            <p><span class="info-label">Payment Method:</span> <?= htmlspecialchars($txn['payment_method']) ?></p>
                            <?php if ($txn['reference_number']): ?>
                                <p><span class="info-label">Reference #:</span> <?= htmlspecialchars($txn['reference_number']) ?></p>
                            <?php endif; ?>
                            <p><span class="info-label">Status:</span> <span class="badge-status <?= strtolower(str_replace(' ', '-', $txn['payment_status'])) ?>"><?= $txn['payment_status'] ?></span></p>
                            <?php if ($txn['is_refunded']): ?>
                                <p><span class="badge bg-danger">Refunded</span></p>
                            <?php endif; ?>
                            <?php if ($txn['notes']): ?>
                                <p><span class="info-label">Notes:</span> <?= nl2br(htmlspecialchars($txn['notes'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-calculator me-2"></i>Financial Breakdown</div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><td>Consultation Fee</td><td class="text-end">₱<?= number_format($txn['consultation_fee'], 2) ?></td></tr>
                                <tr><td>Laboratory Fee</td><td class="text-end">₱<?= number_format($txn['lab_fee'], 2) ?></td></tr>
                                <tr><td>Medicine Fee</td><td class="text-end">₱<?= number_format($txn['medicine_fee'], 2) ?></td></tr>
                                <tr><td>Other Charges</td><td class="text-end">₱<?= number_format($txn['other_charges'] ?? 0, 2) ?></td></tr>
                                <tr><td><strong>Subtotal</strong></td><td class="text-end">₱<?= number_format($txn['consultation_fee'] + $txn['lab_fee'] + $txn['medicine_fee'] + ($txn['other_charges'] ?? 0), 2) ?></td></tr>
                                <tr><td>Discount</td><td class="text-end">- ₱<?= number_format($txn['discount'], 2) ?></td></tr>
                                <tr><td><strong>Grand Total</strong></td><td class="text-end"><strong>₱<?= number_format($txn['total_amount'], 2) ?></strong></td></tr>
                                <tr><td>Amount Paid</td><td class="text-end">₱<?= number_format($txn['amount_paid'], 2) ?></td></tr>
                                <tr><td>Change</td><td class="text-end">₱<?= number_format($txn['change_amount'], 2) ?></td></tr>
                                <tr><td><strong>Balance</strong></td><td class="text-end"><strong>₱<?= number_format($txn['total_amount'] - $txn['amount_paid'], 2) ?></strong></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>