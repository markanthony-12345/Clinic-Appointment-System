<?php
require_once 'config.php';
requireLogin();

// Restrict customers to their own record only
if ($_SESSION["user_logged"]["role"] !== "Admin") {
    $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE fullname = ? LIMIT 1");
    $stmt->execute([$_SESSION["user_logged"]["fullname"]]);
    $ownRecord = $stmt->fetch();
    $allowedId = $ownRecord ? (int)$ownRecord["patient_id"] : 0;
    $requestedId = isset($_GET["patient_id"]) ? (int)$_GET["patient_id"] : 0;
    if ($requestedId && $requestedId !== $allowedId) {
        header("Location: dashboard.php?error=access_denied");
        exit;
    }
    if (!$requestedId && $allowedId) {
        header("Location: patient_overview.php?patient_id=" . $allowedId);
        exit;
    }
}

$pid = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$pdata = null;
$payment_data = null;
$consult_done = $lab_done = $med_done = $pay_done = false;
$cleared = false;
$appointments = [];
$medicines = [];
$lab_records = [];

if ($pid > 0) {
    // Patient details
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

        // Clearance checks
        $consult = $pdo->prepare("SELECT 1 FROM appointments WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $consult->execute([$pid]);
        $consult_done = $consult->rowCount() > 0;

        $lab = $pdo->prepare("SELECT 1 FROM laboratory WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $lab->execute([$pid]);
        $lab_done = $lab->rowCount() > 0;

        $med = $pdo->prepare("SELECT 1 FROM medicines WHERE patient_id = ? AND status = 'Taken' LIMIT 1");
        $med->execute([$pid]);
        $med_done = $med->rowCount() > 0;

        $cleared = $consult_done && $lab_done && $med_done && $pay_done;

        // Appointments
        $stmt = $pdo->prepare("
            SELECT a.*, d.doctor_name 
            FROM appointments a 
            JOIN doctors d ON a.doctor_id = d.doctor_id 
            WHERE a.patient_id = ? 
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([$pid]);
        $appointments = $stmt->fetchAll();

        // Medicines
        $stmt = $pdo->prepare("SELECT * FROM medicines WHERE patient_id = ? ORDER BY prescription_date DESC");
        $stmt->execute([$pid]);
        $medicines = $stmt->fetchAll();

        // Laboratory records
        $stmt = $pdo->prepare("SELECT * FROM laboratory WHERE patient_id = ? ORDER BY created_at DESC");
        $stmt->execute([$pid]);
        $lab_records = $stmt->fetchAll();
    }
}

$is_admin = ($_SESSION['user_logged']['role'] === 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Medical Record</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .details-section { margin-top: 2rem; }
        .details-section h3 { margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; }
        .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 768px) { .grid-2col { grid-template-columns: 1fr; } }
        .info-card { background: #f8fafc; padding: 1rem; border-radius: 12px; margin-bottom: 1rem; }
        .info-card p { margin: 0.3rem 0; }
        .label { font-weight: 600; color: #2c5f8a; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1><i class="fas fa-file-invoice-dollar"></i> My Medical Record</h1>
        <a href="dashboard.php" class="btn primary">← Back to Dashboard</a>
    </header>
    <main>
        <?php if ($pid > 0 && !$pdata): ?>
            <div class="alert error">Patient not found</div>
        <?php elseif ($pdata): ?>
            <!-- Basic Info -->
            <div class="card">
                <h2><?= htmlspecialchars($pdata['fullname']) ?> (ID: <?= $pid ?>)</h2>
                <p class="patient-meta">Age: <?= $pdata['age'] ?> | Gender: <?= $pdata['gender'] ?> | Contact: <?= htmlspecialchars($pdata['contact_number']) ?></p>

                <div class="grid-2col">
                    <!-- Payment Column -->
                    <div class="payment-column">
                        <h3><i class="fas fa-money-bill-wave"></i> Payment Status</h3>
                        <div class="payment-summary">
                            <p><strong>Total Bill:</strong> ₱<?= number_format($payment_data['total_amount'], 2) ?></p>
                            <p><strong>Amount Paid:</strong> ₱<?= number_format($payment_data['amount_paid'], 2) ?></p>
                            <p><strong>Balance Due:</strong> 
                                <span class="<?= $pay_done ? 'text-success' : 'text-danger' ?>">
                                    ₱<?= number_format($payment_data['balance'], 2) ?>
                                </span>
                            </p>
                            <?php if ($is_admin && $payment_data['balance'] > 0): ?>
                                <button class="btn primary" onclick="openPaymentModal(<?= $pid ?>, <?= $payment_data['total_amount'] ?>, <?= $payment_data['amount_paid'] ?>)">
                                    <i class="fas fa-plus-circle"></i> Add Payment
                                </button>
                            <?php elseif ($pay_done): ?>
                                <span class="status paid">Fully Paid ✓</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Clearance Column -->
                    <div class="clearance-column">
                        <h3><i class="fas fa-check-circle"></i> Clearance Status</h3>
                        <div class="checklist-section">
                            <?php if ($is_admin): ?>
                                <!-- Admin toggles -->
                                <div class="checklist-item">
                                    <label>🩺 Consultation Completed</label>
                                    <input type="checkbox" <?= $consult_done ? 'checked' : '' ?> onchange="toggleItem('consult', <?= $pid ?>, this.checked)">
                                </div>
                                <div class="checklist-item">
                                    <label>🔬 Laboratory Completed</label>
                                    <input type="checkbox" <?= $lab_done ? 'checked' : '' ?> onchange="toggleItem('lab', <?= $pid ?>, this.checked)">
                                </div>
                                <div class="checklist-item">
                                    <label>💊 Medicine Taken</label>
                                    <input type="checkbox" <?= $med_done ? 'checked' : '' ?> onchange="toggleItem('medicine', <?= $pid ?>, this.checked)">
                                </div>
                                <div class="checklist-item">
                                    <label>💰 Payment Completed</label>
                                    <input type="checkbox" <?= $pay_done ? 'checked' : '' ?> onchange="toggleItem('payment', <?= $pid ?>, this.checked)">
                                </div>
                            <?php else: ?>
                                <!-- Patient view only -->
                                <div class="checklist-item">
                                    <label>🩺 Consultation Completed</label>
                                    <span class="status <?= $consult_done ? 'completed' : 'pending' ?>"><?= $consult_done ? 'Yes' : 'No' ?></span>
                                </div>
                                <div class="checklist-item">
                                    <label>🔬 Laboratory Completed</label>
                                    <span class="status <?= $lab_done ? 'completed' : 'pending' ?>"><?= $lab_done ? 'Yes' : 'No' ?></span>
                                </div>
                                <div class="checklist-item">
                                    <label>💊 Medicine Taken</label>
                                    <span class="status <?= $med_done ? 'completed' : 'pending' ?>"><?= $med_done ? 'Yes' : 'No' ?></span>
                                </div>
                                <div class="checklist-item">
                                    <label>💰 Payment Completed</label>
                                    <span class="status <?= $pay_done ? 'completed' : 'pending' ?>"><?= $pay_done ? 'Yes' : 'No' ?></span>
                                </div>
                            <?php endif; ?>
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
            </div>

            <!-- Appointments, Medicines, Lab Results -->
            <div class="details-section">
                <div class="grid-2col">
                    <!-- Appointments -->
                    <div class="info-card">
                        <h3><i class="fas fa-calendar-alt"></i> Appointments</h3>
                        <?php if (empty($appointments)): ?>
                            <p>No appointments found.</p>
                        <?php else: ?>
                            <table class="table" style="width:100%;">
                                <thead><tr><th>Date & Time</th><th>Doctor</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($appointments as $appt): ?>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime($appt['appointment_date'])) ?></td>
                                        <td><?= htmlspecialchars($appt['doctor_name']) ?></td>
                                        <td><span class="status <?= strtolower($appt['status']) ?>"><?= $appt['status'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Medicines -->
                    <div class="info-card">
                        <h3><i class="fas fa-pills"></i> Medicines</h3>
                        <?php if (empty($medicines)): ?>
                            <p>No medicines prescribed.</p>
                        <?php else: ?>
                            <table class="table" style="width:100%;">
                                <thead><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Status</th><th>Prescribed</th></tr></thead>
                                <tbody>
                                <?php foreach ($medicines as $med): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($med['medicine_name']) ?></td>
                                        <td><?= htmlspecialchars($med['dosage']) ?></td>
                                        <td><?= htmlspecialchars($med['frequency']) ?></td>
                                        <td><span class="status <?= strtolower($med['status']) == 'taken' ? 'taken' : 'pending' ?>"><?= $med['status'] ?></span></td>
                                        <td><?= date('M j, Y', strtotime($med['prescription_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Laboratory Results -->
                <div class="info-card" style="margin-top:1rem;">
                    <h3><i class="fas fa-microscope"></i> Laboratory Results</h3>
                    <?php if (empty($lab_records)): ?>
                        <p>No laboratory records found.</p>
                    <?php else: ?>
                        <table class="table" style="width:100%;">
                            <thead><tr><th>Test Type</th><th>Status</th><th>Result</th><th>Date</th></tr></thead>
                            <tbody>
                            <?php foreach ($lab_records as $lab): ?>
                                <tr>
                                    <td><?= htmlspecialchars($lab['laboratory_type']) ?></td>
                                    <td><span class="status <?= strtolower(str_replace(' ', '-', $lab['status'])) ?>"><?= $lab['status'] ?></span></td>
                                    <td><?= htmlspecialchars($lab['result'] ?: '—') ?></td>
                                    <td><?= date('M j, Y', strtotime($lab['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin Reset Section (only visible to admin) -->
            <?php if ($is_admin): ?>
            <div class="reset-section" style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid #eee;">
                <p class="reset-warning">⚠️ Admin only: Reset all records (appointments, lab, medicine, payment) to default.</p>
                <button class="btn danger" onclick="resetPatient(<?= $pid ?>, '<?= htmlspecialchars($pdata['fullname']) ?>')">
                    ↩ Reset / Undo All Records
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Payment Modal (for admin only, but safe) -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Record Payment</h3>
        <form action="update_payment.php" method="POST">
            <input type="hidden" name="patient_id" id="modal_patient_id">
            <div class="form-group"><label>Total Bill: ₱<span id="modal_total"></span></label></div>
            <div class="form-group"><label>Already Paid: ₱<span id="modal_paid"></span></label></div>
            <div class="form-group"><label>Balance Due: ₱<span id="modal_balance"></span></label></div>
            <div class="form-group"><label>Payment Amount</label><input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0.01" required></div>
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
    document.getElementById('paymentModal').style.display = 'flex';
}
document.querySelector('#paymentModal .close').onclick = () => document.getElementById('paymentModal').style.display = 'none';
window.onclick = (e) => { if (e.target == document.getElementById('paymentModal')) document.getElementById('paymentModal').style.display = 'none'; };

function toggleItem(type, patientId, checked) {
    if (!confirm("Are you sure you want to change clearance status?")) return;
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