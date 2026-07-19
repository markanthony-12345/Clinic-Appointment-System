<?php
// This file has been merged into the unified Billing & Transactions module.
// Please use billing.php instead.
header("Location: billing.php");
exit;

// Fetch all patients for the dropdown
$patients = $pdo->query("
    SELECT p.patient_id, p.fullname, 
           COALESCE(py.total_amount, 0) AS total_bill,
           COALESCE(py.amount_paid, 0) AS amount_paid,
           COALESCE(py.total_amount - py.amount_paid, 0) AS balance
    FROM patients p
    LEFT JOIN payments py ON p.patient_id = py.patient_id
    WHERE p.is_archived = 0
    ORDER BY p.fullname ASC
")->fetchAll();

// Fetch summary data
$summary = $pdo->query("
    SELECT 
        SUM(total_amount) AS total_revenue,
        SUM(amount_paid) AS total_paid,
        SUM(total_amount - amount_paid) AS pending_balance
    FROM payments
")->fetch();

// Fetch all payment transactions with patient details and running balance
$stmt = $pdo->query("
    SELECT 
        py.payment_id,
        py.patient_id,
        py.amount_paid AS payment_amount,
        py.payment_method,
        py.payment_date,
        p.fullname,
        py.total_amount AS total_bill,
        (SELECT SUM(amount_paid) FROM payments WHERE patient_id = py.patient_id) AS total_paid,
        (py.total_amount - (SELECT SUM(amount_paid) FROM payments WHERE patient_id = py.patient_id)) AS balance
    FROM payments py
    JOIN patients p ON py.patient_id = p.patient_id
    ORDER BY py.payment_date DESC
");
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .stat-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem 1.25rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            transition: all 0.25s ease;
            border: 1px solid rgba(0,0,0,0.02);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.08);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .stat-icon.blue { background: linear-gradient(135deg, #0EA5E9, #2563EB); }
        .stat-icon.green { background: linear-gradient(135deg, #22C55E, #16A34A); }
        .stat-icon.orange { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1F2937;
            line-height: 1.2;
        }
        .stat-label {
            color: #6B7280;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .status-badge.paid { background: #D1FAE5; color: #065F46; }
        .status-badge.partial { background: #FEF3C7; color: #92400E; }
        .status-badge.unpaid { background: #FEE2E2; color: #991B1B; }
        .card {
            border: none;
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            padding: 1.25rem 1.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0EA5E9, #2563EB);
            border: none;
            border-radius: 2rem;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.25s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
            color: white;
        }
        .btn-outline-primary {
            border: 1px solid #2563EB;
            color: #2563EB;
            border-radius: 2rem;
            padding: 0.4rem 1.2rem;
            transition: 0.2s;
        }
        .btn-outline-primary:hover {
            background: #2563EB;
            color: white;
        }
        .table th {
            font-weight: 600;
            color: #4B5563;
            border-bottom-width: 2px;
        }
        .table td {
            vertical-align: middle;
        }
        .modal-content {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        .modal-header { border-bottom: 1px solid rgba(0,0,0,0.05); }
        .modal-footer { border-top: 1px solid rgba(0,0,0,0.05); }
        .payment-form .form-control, .payment-form .form-select {
            border-radius: 0.75rem;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><i class="fas fa-credit-card me-2 text-primary"></i>Payments</h4>
            <a href="dashboard.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>

        <!-- ===== RECORD NEW PAYMENT FORM (Inline) ===== -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>Record New Payment</span>
            </div>
            <div class="card-body">
                <form action="update_payment.php" method="POST" class="payment-form row g-3">
                    <div class="col-md-4">
                        <label for="patient_id" class="form-label fw-bold">Select Patient</label>
                        <select name="patient_id" id="patient_id" class="form-select" required>
                            <option value="">Choose a patient...</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['patient_id'] ?>" data-balance="<?= $p['balance'] ?>">
                                    <?= htmlspecialchars($p['fullname']) ?> (Balance: ₱<?= number_format($p['balance'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="payment_amount" class="form-label fw-bold">Payment Amount</label>
                        <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="col-md-3">
                        <label for="payment_method" class="form-label fw-bold">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="GCash">GCash</option>
                            <option value="PayMaya">PayMaya</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Insurance">Insurance</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i>Submit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4 col-6">
                <div class="stat-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-number">₱<?= number_format($summary['total_revenue'] ?? 0, 2) ?></div>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="stat-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Paid</div>
                        <div class="stat-number">₱<?= number_format($summary['total_paid'] ?? 0, 2) ?></div>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="stat-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Pending Balance</div>
                        <div class="stat-number">₱<?= number_format($summary['pending_balance'] ?? 0, 2) ?></div>
                    </div>
                    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                </div>
            </div>
        </div>

        <!-- Success Alert -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>Payment recorded successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Payments Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-list me-2"></i>All Payment Transactions</span>
                <span class="badge bg-primary rounded-pill"><?= count($payments) ?> records</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                                <th>Payment Date</th>
                                <th>Total Bill</th>
                                <th>Total Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No payment records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($payments as $row):
                                    $balance = $row['balance'];
                                    if ($balance <= 0) {
                                        $status = 'Paid';
                                        $badge = 'paid';
                                    } elseif ($row['total_paid'] > 0 && $balance > 0) {
                                        $status = 'Partial';
                                        $badge = 'partial';
                                    } else {
                                        $status = 'Unpaid';
                                        $badge = 'unpaid';
                                    }
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                                        <td>₱<?= number_format($row['payment_amount'], 2) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                                        <td><?= date('M j, Y g:i A', strtotime($row['payment_date'])) ?></td>
                                        <td>₱<?= number_format($row['total_bill'], 2) ?></td>
                                        <td>₱<?= number_format($row['total_paid'], 2) ?></td>
                                        <td>₱<?= number_format($balance, 2) ?></td>
                                        <td>
                                            <span class="status-badge <?= $badge ?>"><?= $status ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal (still available for inline use) -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="update_payment.php" method="POST" id="paymentForm">
                        <input type="hidden" name="patient_id" id="modal_patient_id">
                        <div class="mb-3">
                            <label class="fw-bold">Patient:</label>
                            <span id="modal_patient_name" class="fw-bold"></span>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Total Bill:</label>
                            <span class="fs-5">₱<span id="modal_total"></span></span>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Already Paid:</label>
                            <span class="fs-5">₱<span id="modal_paid"></span></span>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Balance Due:</label>
                            <span class="fs-5 text-danger">₱<span id="modal_balance"></span></span>
                        </div>
                        <div class="mb-3">
                            <label for="modal_payment_method" class="form-label fw-bold">Payment Method</label>
                            <select name="payment_method" id="modal_payment_method" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="GCash">GCash</option>
                                <option value="PayMaya">PayMaya</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Insurance">Insurance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="modal_payment_amount" class="form-label fw-bold">Payment Amount</label>
                            <input type="number" name="payment_amount" id="modal_payment_amount" step="0.01" min="0.01" class="form-control form-control-lg" required>
                            <div class="form-text">Enter the amount to pay.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i>Submit Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store patient data for modal
        const patientData = {};
        <?php foreach ($payments as $row): ?>
            patientData[<?= $row['patient_id'] ?>] = {
                name: '<?= addslashes($row['fullname']) ?>',
                total: <?= $row['total_bill'] ?>,
                paid: <?= $row['total_paid'] ?>,
                balance: <?= $row['balance'] ?>
            };
        <?php endforeach; ?>

        function openPaymentModal(patientId, total, paid) {
            const balance = total - paid;
            const data = patientData[patientId] || { name: 'Patient #' + patientId, total: total, paid: paid, balance: balance };
            document.getElementById('modal_patient_id').value = patientId;
            document.getElementById('modal_patient_name').innerText = data.name;
            document.getElementById('modal_total').innerText = data.total.toFixed(2);
            document.getElementById('modal_paid').innerText = data.paid.toFixed(2);
            document.getElementById('modal_balance').innerText = data.balance.toFixed(2);
            document.getElementById('modal_payment_amount').max = data.balance;
            document.getElementById('modal_payment_amount').min = 0.01;
            document.getElementById('modal_payment_method').value = 'Cash';
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        // Prevent overpayment in modal
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('modal_payment_amount').value);
            const balance = parseFloat(document.getElementById('modal_balance').innerText);
            if (amount > balance) {
                alert('Payment amount cannot exceed the balance due.');
                e.preventDefault();
                return false;
            }
            return true;
        });

        // Optional: validate inline form before submit
        document.querySelector('.payment-form')?.addEventListener('submit', function(e) {
            const patientSelect = document.getElementById('patient_id');
            const amount = parseFloat(document.getElementById('payment_amount').value);
            const selectedOption = patientSelect.options[patientSelect.selectedIndex];
            const balance = parseFloat(selectedOption?.dataset?.balance || 0);
            if (amount > balance) {
                alert('Payment amount cannot exceed the balance due.');
                e.preventDefault();
                return false;
            }
            return true;
        });
    </script>
</body>
</html>