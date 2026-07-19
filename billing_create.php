<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$patients = $pdo->query("SELECT patient_id, fullname FROM patients WHERE is_archived = 0 ORDER BY fullname")->fetchAll();
$doctors = $pdo->query("SELECT doctor_id, doctor_name FROM doctors ORDER BY doctor_name")->fetchAll();
$appointments = $pdo->query("
    SELECT a.appointment_id, p.fullname AS patient_name, d.doctor_name, a.appointment_date
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.status IN ('Scheduled', 'Pending')
    ORDER BY a.appointment_date DESC
")->fetchAll();

$prefill_patient = (int)($_GET['patient_id'] ?? 0);
$prefill_doctor = (int)($_GET['doctor_id'] ?? 0);
$prefill_appointment = (int)($_GET['appointment_id'] ?? 0);
$prefill_lab_fee = 0;
$prefill_med_fee = 0;
if ($prefill_appointment) {
    $stmt = $pdo->prepare("SELECT lab_fee_total, med_fee_total FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$prefill_appointment]);
    $apptData = $stmt->fetch();
    if ($apptData) {
        $prefill_lab_fee = (float)$apptData['lab_fee_total'];
        $prefill_med_fee = (float)$apptData['med_fee_total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: none; }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; }
        .btn-primary { background: linear-gradient(135deg, #1e6f9f, #155d85); border: none; border-radius: 2rem; }
        .btn-primary:hover { background: linear-gradient(135deg, #155d85, #0f4a6e); }
        .form-label { font-weight: 500; color: #2c5f8a; }
        .total-box { background: #f8f9fa; padding: 1rem; border-radius: 1rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-file-invoice me-2"></i>Create Billing</h1>
                <a href="billing.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-edit me-2"></i>Billing Details</div>
                <div class="card-body">
                    <form action="billing_process.php" method="POST" id="billingForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?= $p['patient_id'] ?>" <?= $prefill_patient == $p['patient_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Doctor</label>
                                <select name="doctor_id" class="form-select">
                                    <option value="">Select Doctor (optional)</option>
                                    <?php foreach ($doctors as $d): ?>
                                        <option value="<?= $d['doctor_id'] ?>" <?= $prefill_doctor == $d['doctor_id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['doctor_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Appointment</label>
                                <select name="appointment_id" class="form-select">
                                    <option value="">Select Appointment (optional)</option>
                                    <?php foreach ($appointments as $a): ?>
                                        <option value="<?= $a['appointment_id'] ?>" <?= $prefill_appointment == $a['appointment_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($a['patient_name']) ?> - <?= htmlspecialchars($a['doctor_name']) ?> (<?= date('M j, Y g:i A', strtotime($a['appointment_date'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transaction Date *</label>
                                <input type="datetime-local" name="transaction_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Consultation Fee</label>
                                <input type="number" name="consultation_fee" class="form-control fee-input" step="0.01" value="500.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Lab Fee</label>
                                <input type="number" name="lab_fee" class="form-control fee-input" step="0.01" value="<?= $prefill_lab_fee ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Medicine Fee</label>
                                <input type="number" name="medicine_fee" class="form-control fee-input" step="0.01" value="<?= $prefill_med_fee ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Other Charges</label>
                                <input type="number" name="other_charges" class="form-control fee-input" step="0.01" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Discount</label>
                                <input type="number" name="discount" class="form-control" step="0.01" value="0.00" id="discount">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Payment Method *</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Maya">Maya</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="For digital payments">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Amount Paid *</label>
                                <input type="number" name="amount_paid" class="form-control" step="0.01" required value="0.00" id="amount_paid">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="total-box mt-4 p-3">
                            <div class="row">
                                <div class="col-md-3"><strong>Subtotal:</strong> <span id="subtotal">500.00</span></div>
                                <div class="col-md-3"><strong>Discount:</strong> <span id="discount_display">0.00</span></div>
                                <div class="col-md-3"><strong>Total:</strong> <span id="total_display">500.00</span></div>
                                <div class="col-md-3"><strong>Change:</strong> <span id="change_display">0.00</span></div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i>Save Billing</button>
                            <a href="billing.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const feeInputs = document.querySelectorAll('.fee-input');
        const discountInput = document.getElementById('discount');
        const amountPaidInput = document.getElementById('amount_paid');
        const subtotalSpan = document.getElementById('subtotal');
        const discountSpan = document.getElementById('discount_display');
        const totalSpan = document.getElementById('total_display');
        const changeSpan = document.getElementById('change_display');

        function calculateTotals() {
            let consultation = parseFloat(document.querySelector('input[name="consultation_fee"]').value) || 0;
            let lab = parseFloat(document.querySelector('input[name="lab_fee"]').value) || 0;
            let medicine = parseFloat(document.querySelector('input[name="medicine_fee"]').value) || 0;
            let other = parseFloat(document.querySelector('input[name="other_charges"]').value) || 0;
            let discount = parseFloat(discountInput.value) || 0;
            let subtotal = consultation + lab + medicine + other;
            let total = subtotal - discount;
            if (total < 0) total = 0;
            let paid = parseFloat(amountPaidInput.value) || 0;
            let change = paid - total;
            if (change < 0) change = 0;

            subtotalSpan.textContent = subtotal.toFixed(2);
            discountSpan.textContent = discount.toFixed(2);
            totalSpan.textContent = total.toFixed(2);
            changeSpan.textContent = change.toFixed(2);
        }

        feeInputs.forEach(input => input.addEventListener('input', calculateTotals));
        discountInput.addEventListener('input', calculateTotals);
        amountPaidInput.addEventListener('input', calculateTotals);
        calculateTotals();
    </script>
</body>
</html>