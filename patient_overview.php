<?php
require_once 'config.php';
requireLogin();

$pid = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$pdata = null;
$payment_data = null;
$consult_done = $lab_done = $med_done = $pay_done = false;
$cleared = false;

if ($pid > 0) {
    // Fetch patient
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->execute([$pid]);
    $pdata = $stmt->fetch();

    if ($pdata) {
        // Payment info
        $stmt = $pdo->prepare("SELECT total_amount, amount_paid, (total_amount - amount_paid) AS balance FROM payments WHERE patient_id = ?");
        $stmt->execute([$pid]);
        $payment_data = $stmt->fetch();
        if (!$payment_data) {
            $payment_data = ['total_amount' => 500, 'amount_paid' => 0, 'balance' => 500];
        }
        $pay_done = ($payment_data['balance'] <= 0);

        // Consultation
        $consult = $pdo->prepare("SELECT 1 FROM appointments WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $consult->execute([$pid]);
        $consult_done = $consult->rowCount() > 0;

        // Laboratory
        $lab = $pdo->prepare("SELECT 1 FROM laboratory WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $lab->execute([$pid]);
        $lab_done = $lab->rowCount() > 0;

        // Medicine
        $med = $pdo->prepare("SELECT 1 FROM medicines WHERE patient_id = ? AND status = 'Taken' LIMIT 1");
        $med->execute([$pid]);
        $med_done = $med->rowCount() > 0;

        $cleared = $consult_done && $lab_done && $med_done && $pay_done;
    }
}

$is_admin = ($_SESSION['user_logged']['role'] === 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Overview – Payment & Clearance</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .two-columns { grid-template-columns: 1fr; }
        }
        .payment-summary {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
        }
        .amount { font-size: 1.8rem; font-weight: 700; }
        .text-success { color: #1e6f3f; }
        .text-danger { color: #c23d2e; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1><i class="fas fa-file-invoice-dollar"></i> Patient Overview</h1>
        <a href="dashboard.php" class="btn primary">← Back to Dashboard</a>
    </header>
    <main>
        <!-- Search -->
        <div class="card">
            <h3>Search Patient</h3>
            <form method="GET">
                <div class="form-row">
                    <input type="number" name="patient_id" placeholder="Enter Patient ID" value="<?= $pid ?: '' ?>" required>
                    <button type="submit" class="btn primary">Load Patient</button>
                </div>
            </form>
        </div>

        <?php if ($pid > 0 && !$pdata): ?>
            <div class="alert error">Patient not found</div>
        <?php elseif ($pdata): ?>
            <div class="card">
                <h2><?= htmlspecialchars($pdata['fullname']) ?> (ID: <?= $pid ?>)</h2>
                <p>Age: <?= $pdata['age'] ?> | Gender: <?= $pdata['gender'] ?> | Contact: <?= htmlspecialchars($pdata['contact_number']) ?></p>

                <div class="two-columns">
                    <!-- LEFT COLUMN: PAYMENT SECTION -->
                    <div>
                        <h3><i class="fas fa-money-bill-wave"></i> Payment Status</h3>
                        <div class="payment-summary">
                            <p><strong>Total Bill:</strong> <span class="amount">₱<?= number_format($payment_data['total_amount'], 2) ?></span></p>
                            <p><strong>Amount Paid:</strong> ₱<?= number_format($payment_data['amount_paid'], 2) ?></p>
                            <p><strong>Balance Due:</strong> 
                                <span class="<?= $pay_done ? 'text-success' : 'text-danger' ?>">
                                    ₱<?= number_format($payment_data['balance'], 2) ?>
                                </span>
                            </p>
                            <?php if ($payment_data['balance'] > 0): ?>
                                <button class="btn primary" onclick="openPaymentModal(<?= $pid ?>, <?= $payment_data['total_amount'] ?>, <?= $payment_data['amount_paid'] ?>)">
                                    <i class="fas fa-plus-circle"></i> Add Payment
                                </button>
                            <?php else: ?>
                                <span class="status paid">Fully Paid ✓</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: CLEARANCE CHECKLIST -->
                    <div>
                        <h3><i class="fas fa-check-circle"></i> Clearance Checklist</h3>
                        <div class="checklist-section">
                            <div class="checklist-item">
                                <label>🩺 Consultation Completed</label>
                                <input type="checkbox" <?= $consult_done ? 'checked' : '' ?>
                                       onchange="toggleItem('consult', <?= $pid ?>, this.checked)">
                            </div>
                            <div class="checklist-item">
                                <label>🔬 Laboratory Completed</label>
                                <input type="checkbox" <?= $lab_done ? 'checked' : '' ?>
                                       onchange="toggleItem('lab', <?= $pid ?>, this.checked)">
                            </div>
                            <div class="checklist-item">
                                <label>💊 Medicine Taken</label>
                                <input type="checkbox" <?= $med_done ? 'checked' : '' ?>
                                       onchange="toggleItem('medicine', <?= $pid ?>, this.checked)">
                            </div>
                            <div class="checklist-item">
                                <label>💰 Payment Completed</label>
                                <input type="checkbox" <?= $pay_done ? 'checked' : '' ?>
                                       onchange="toggleItem('payment', <?= $pid ?>, this.checked)">
                            </div>
                        </div>
                        <div class="clearance-status">
                            <h4>Final Status:
                                <span class="<?= $cleared ? 'completed' : 'cancelled' ?>">
                                    <?= $cleared ? '✅ CLEARED' : '❌ NOT CLEARED' ?>
                                </span>
                            </h4>
                            <?php if ($cleared): ?>
                                <button class="btn primary" onclick="printClearance(<?= $pid ?>)">
                                    <i class="fas fa-print"></i> Print Clearance
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RESET SECTION (Admin only) -->
                <?php if ($is_admin): ?>
                <div class="reset-section" style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid #eee;">
                    <p class="reset-warning">⚠️ Admin only: Reset all records (appointments, lab, medicine, payment) to default.</p>
                    <button class="btn danger" onclick="resetPatient(<?= $pid ?>, '<?= htmlspecialchars($pdata['fullname']) ?>')">
                        ↩ Reset / Undo All Records
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Payment Modal (same as before) -->
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
document.querySelector('#paymentModal .close').onclick = () => document.getElementById('paymentModal').style.display = 'none';
window.onclick = (e) => { if (e.target == document.getElementById('paymentModal')) document.getElementById('paymentModal').style.display = 'none'; };

function toggleItem(type, patientId, checked) {
    fetch(`update_clearance_item.php?type=${type}&patient_id=${patientId}&value=${checked}`)
        .then(res => res.json())
        .then(data => { if (data.success) location.reload(); else alert("Update failed: " + (data.message || "Unknown error")); });
}
function printClearance(patientId) {
    window.open(`print_clearance.php?patient_id=${patientId}`, '_blank');
}
function resetPatient(patientId, name) {
    if (!confirm(`Reset ALL records of "${name}"? This cannot be undone.`)) return;
    fetch(`reset_patient.php?patient_id=${patientId}`)
        .then(res => res.json())
        .then(data => { if (data.success) { alert('Records reset.'); location.reload(); } else alert('Reset failed: ' + data.message); });
}
</script>
</body>
</html>