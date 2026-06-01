<?php
require_once 'config.php';
requireLogin();

// Fetch all payment records with computed balance and status
$stmt = $pdo->query("
    SELECT 
        py.payment_id,
        py.patient_id,
        py.total_amount,
        py.amount_paid,
        (py.total_amount - py.amount_paid) AS balance,
        CASE 
            WHEN py.amount_paid >= py.total_amount THEN 'Paid'
            WHEN py.amount_paid > 0 THEN 'Partial'
            ELSE 'Unpaid'
        END AS computed_status,
        p.fullname
    FROM payments py
    JOIN patients p ON py.patient_id = p.patient_id
    ORDER BY py.payment_id DESC
");
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <header>
        <h1><i class="fas fa-money-bill-wave"></i> Payments</h1>
        <a href="dashboard.php" class="btn primary">← Back to Dashboard</a>
    </header>
    <main>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">Payment recorded successfully!</div>
        <?php endif; ?>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Total Bill</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $row): ?>
                        <?php
                        $statusClass = strtolower($row['computed_status']);
                        $balance = $row['balance'];
                        ?>
                        <tr>
                            <td><?= $row['patient_id'] ?></td>
                            <td><?= htmlspecialchars($row['fullname']) ?></td>
                            <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                            <td>₱<?= number_format($row['amount_paid'], 2) ?></td>
                            <td>₱<?= number_format($balance, 2) ?></td>
                            <td>
                                <span class="status <?= $statusClass ?>">
                                    <?= $row['computed_status'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($balance > 0): ?>
                                    <button class="btn primary" onclick="openPaymentModal(<?= $row['patient_id'] ?>, <?= $row['total_amount'] ?>, <?= $row['amount_paid'] ?>)">
                                        <i class="fas fa-plus-circle"></i> Add Payment
                                    </button>
                                <?php else: ?>
                                    <span class="status paid">Fully Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Record Payment</h3>
        <form action="update_payment.php" method="POST">
            <input type="hidden" name="patient_id" id="modal_patient_id">
            <div class="form-group">
                <label>Total Bill: ₱<span id="modal_total"></span></label>
            </div>
            <div class="form-group">
                <label>Already Paid: ₱<span id="modal_paid"></span></label>
            </div>
            <div class="form-group">
                <label>Balance Due: ₱<span id="modal_balance"></span></label>
            </div>
            <div class="form-group">
                <label>Payment Amount</label>
                <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0.01" required>
            </div>
            <button type="submit" class="btn primary">Submit Payment</button>
        </form>
    </div>
</div>

<script>
function openPaymentModal(patientId, total, paid) {
    const balance = total - paid;
    document.getElementById('modal_patient_id').value = patientId;
    document.getElementById('modal_total').innerText = total.toFixed(2);
    document.getElementById('modal_paid').innerText = paid.toFixed(2);
    document.getElementById('modal_balance').innerText = balance.toFixed(2);
    document.getElementById('payment_amount').max = balance;
    document.getElementById('paymentModal').style.display = 'block';
}

// Modal close logic
document.querySelector('#paymentModal .close').onclick = function() {
    document.getElementById('paymentModal').style.display = 'none';
}
window.onclick = function(e) {
    if (e.target == document.getElementById('paymentModal')) {
        document.getElementById('paymentModal').style.display = 'none';
    }
}
</script>
</body>
</html>