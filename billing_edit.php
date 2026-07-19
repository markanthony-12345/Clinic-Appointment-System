<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

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

if ($txn['is_refunded']) {
    header("Location: billing_view.php?id=$id&error=refunded_cannot_edit");
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: none; }
        .btn-primary { background: linear-gradient(135deg, #1e6f9f, #155d85); border: none; border-radius: 2rem; }
        .btn-primary:hover { background: linear-gradient(135deg, #155d85, #0f4a6e); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-edit me-2"></i>Edit Billing #<?= htmlspecialchars($txn['transaction_number']) ?></h1>
                <a href="billing_view.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form action="billing_process_edit.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Amount Paid</label>
                                <input type="number" name="amount_paid" class="form-control" step="0.01" value="<?= $txn['amount_paid'] ?>" required>
                                <small class="text-muted">Cannot exceed total (₱<?= number_format($txn['total_amount'], 2) ?>).</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="Cash" <?= $txn['payment_method'] == 'Cash' ? 'selected' : '' ?>>Cash</option>
                                    <option value="GCash" <?= $txn['payment_method'] == 'GCash' ? 'selected' : '' ?>>GCash</option>
                                    <option value="Maya" <?= $txn['payment_method'] == 'Maya' ? 'selected' : '' ?>>Maya</option>
                                    <option value="Bank Transfer" <?= $txn['payment_method'] == 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control" value="<?= htmlspecialchars($txn['reference_number']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($txn['notes']) ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Billing</button>
                            <a href="billing_view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>