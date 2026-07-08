<?php
require_once 'config.php';
requireLogin();

$pid = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if (!$pid) {
    header("Location: dashboard.php?error=No patient selected");
    exit;
}

$patientService = new PatientService();
$record = $patientService->getFullRecord($pid);
if (!$record) {
    header("Location: dashboard.php?error=Patient not found");
    exit;
}

$pdata = $record['patient'];
$payment = $record['payment'];
$consult_done = $record['consult_done'];
$lab_done = $record['lab_done'];
$med_done = $record['med_done'];
$pay_done = $record['pay_done'];
$cleared = $record['cleared'];
$appointments = $record['appointments'];
$medicines = $record['medicines'];
$lab_records = $record['lab_records'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container py-4">
    <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <h1 class="h3"><i class="fas fa-user-md"></i> <?= htmlspecialchars($pdata['fullname']) ?> (ID: <?= $pid ?>)</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">← Back</a>
    </header>

    <div class="row g-4">
        <!-- Basic Info -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Patient Details</div>
                <div class="card-body">
                    <p><strong>Age:</strong> <?= $pdata['age'] ?></p>
                    <p><strong>Gender:</strong> <?= $pdata['gender'] ?></p>
                    <p><strong>Address:</strong> <?= htmlspecialchars($pdata['address']) ?></p>
                    <p><strong>Contact:</strong> <?= htmlspecialchars($pdata['contact_number']) ?></p>
                </div>
            </div>
        </div>
        <!-- Payment & Clearance -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Payment & Clearance</div>
                <div class="card-body">
                    <p><strong>Total Bill:</strong> ₱<?= number_format($payment['total_amount'], 2) ?></p>
                    <p><strong>Amount Paid:</strong> ₱<?= number_format($payment['amount_paid'], 2) ?></p>
                    <p><strong>Balance:</strong> ₱<?= number_format($payment['balance'], 2) ?></p>
                    <?php if ($payment['balance'] > 0): ?>
                        <button class="btn btn-primary" onclick="openPaymentModal(<?= $pid ?>, <?= $payment['total_amount'] ?>, <?= $payment['amount_paid'] ?>)">Add Payment</button>
                    <?php else: ?>
                        <span class="badge bg-success">Fully Paid</span>
                    <?php endif; ?>
                    <hr>
                    <h5>Clearance</h5>
                    <div class="d-flex flex-wrap gap-3">
                        <span class="badge <?= $consult_done ? 'bg-success' : 'bg-secondary' ?>">Consultation <?= $consult_done ? '✓' : '✗' ?></span>
                        <span class="badge <?= $lab_done ? 'bg-success' : 'bg-secondary' ?>">Lab <?= $lab_done ? '✓' : '✗' ?></span>
                        <span class="badge <?= $med_done ? 'bg-success' : 'bg-secondary' ?>">Medicine <?= $med_done ? '✓' : '✗' ?></span>
                        <span class="badge <?= $pay_done ? 'bg-success' : 'bg-secondary' ?>">Payment <?= $pay_done ? '✓' : '✗' ?></span>
                    </div>
                    <div class="mt-3">
                        <h5>Final Status: <span class="badge <?= $cleared ? 'bg-success' : 'bg-danger' ?>"><?= $cleared ? 'CLEARED' : 'NOT CLEARED' ?></span></h5>
                        <?php if ($cleared): ?>
                            <a href="print_clearance.php?patient_id=<?= $pid ?>" target="_blank" class="btn btn-success"><i class="fas fa-print"></i> Print Clearance</a>
                        <?php endif; ?>
                    </div>
                    <!-- Admin reset -->
                    <button class="btn btn-danger mt-3" onclick="resetPatient(<?= $pid ?>, '<?= htmlspecialchars($pdata['fullname']) ?>')">Reset All Records</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments, Medicines, Lab -->
    <div class="row g-4 mt-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Appointments</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Date</th><th>Doctor</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($appointments as $a): ?>
                            <tr><td><?= date('M j, Y g:i A', strtotime($a['appointment_date'])) ?></td><td><?= htmlspecialchars($a['doctor_name']) ?></td><td><span class="badge bg-<?= $a['status'] == 'Completed' ? 'success' : ($a['status'] == 'Cancelled' ? 'danger' : 'warning') ?>"><?= $a['status'] ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Medicines</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Medicine</th><th>Dosage</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($medicines as $m): ?>
                            <tr><td><?= htmlspecialchars($m['medicine_name']) ?></td><td><?= htmlspecialchars($m['dosage']) ?></td><td><span class="badge bg-<?= $m['status'] == 'Taken' ? 'success' : 'secondary' ?>"><?= $m['status'] ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Laboratory Results</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Test</th><th>Status</th><th>Result</th></tr></thead>
                        <tbody>
                        <?php foreach ($lab_records as $l): ?>
                            <tr><td><?= htmlspecialchars($l['laboratory_type']) ?></td><td><span class="badge bg-<?= $l['status'] == 'Completed' ? 'success' : 'secondary' ?>"><?= $l['status'] ?></span></td><td><?= htmlspecialchars($l['result'] ?: '—') ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5>Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form action="update_payment.php" method="POST">
                    <input type="hidden" name="patient_id" id="modal_patient_id">
                    <p>Total: ₱<span id="modal_total"></span></p>
                    <p>Already Paid: ₱<span id="modal_paid"></span></p>
                    <p>Balance: ₱<span id="modal_balance"></span></p>
                    <div class="mb-3"><label>Payment Amount</label><input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0.01" class="form-control" required></div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPaymentModal(pid, total, paid) {
    document.getElementById('modal_patient_id').value = pid;
    document.getElementById('modal_total').innerText = total.toFixed(2);
    document.getElementById('modal_paid').innerText = paid.toFixed(2);
    document.getElementById('modal_balance').innerText = (total - paid).toFixed(2);
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}
function resetPatient(pid, name) {
    if (!confirm(`Reset ALL records of "${name}"? Cannot be undone.`)) return;
    fetch(`reset_patient.php?patient_id=${pid}`)
        .then(res => res.json())
        .then(data => { if (data.success) location.reload(); else alert('Reset failed'); });
}
</script>
</body>
</html>