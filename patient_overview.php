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

// Fetch appointment history (all appointments for this patient)
$appointmentHistory = $patientService->getAppointmentHistory($pid);

// Fetch billing/transaction history
$transactionService = new TransactionService();
$billingHistory = $transactionService->getPatientTransactions($pid);

// Current tab (default: info)
$tab = $_GET['tab'] ?? 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .nav-tabs .nav-link { color: #1e4a6e; font-weight: 500; }
        .nav-tabs .nav-link.active { color: #1e6f9f; font-weight: 600; border-bottom: 2px solid #1e6f9f; }
        .badge-status { padding: 0.35rem 0.85rem; border-radius: 2rem; font-weight: 500; font-size: 0.75rem; }
        .badge-status.paid { background: #D1FAE5; color: #065F46; }
        .badge-status.partial { background: #FEF3C7; color: #92400E; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.refunded { background: #E5E7EB; color: #6B7280; }
        .badge-status.completed { background: #DBEAFE; color: #1E40AF; }
        .badge-status.pending { background: #FEF3C7; color: #92400E; }
        .badge-status.cancelled { background: #FEE2E2; color: #991B1B; }
        .badge-status.scheduled { background: #E0F2FE; color: #0369A1; }
        .filter-bar { background: #f8f9fa; border-radius: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container py-4">
            <!-- Header with History Link -->
            <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-user-md me-2"></i> <?= htmlspecialchars($pdata['fullname']) ?></h1>
                <div>
                    <a href="patient_history.php?patient_id=<?= $pid ?>" class="btn btn-outline-primary me-2"><i class="fas fa-history me-1"></i>Full History</a>
                    <a href="dashboard.php" class="btn btn-outline-primary">← Back</a>
                </div>
            </header>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'info' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=info" role="tab">Patient Info</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'appointments' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=appointments" role="tab">Appointments</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'billing' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=billing" role="tab">Billing & Transactions</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'laboratory' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=laboratory" role="tab">Laboratory</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'medicines' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=medicines" role="tab">Medicines</a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">

                <!-- Patient Info Tab -->
                <div class="tab-pane fade <?= $tab == 'info' ? 'show active' : '' ?>" id="info">
                    <div class="row g-4">
                        <!-- Basic Info -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Patient Details</div>
                                <div class="card-body">
                                    <p><strong>Age:</strong> <?= $pdata['age'] ?></p>
                                    <p><strong>Gender:</strong> <?= htmlspecialchars($pdata['gender'] ?? '') ?></p>
                                    <p><strong>Address:</strong> <?= htmlspecialchars($pdata['address']) ?></p>
                                    <p><strong>Contact:</strong> +63<?= htmlspecialchars($pdata['contact_number']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($pdata['email'] ?? 'N/A') ?></p>
                                    <p><strong>Civil Status:</strong> <?= htmlspecialchars($pdata['civil_status'] ?? 'N/A') ?></p>
                                    <p><strong>Citizenship:</strong> <?= htmlspecialchars($pdata['citizenship'] ?? 'N/A') ?></p>
                                    <p><strong>Place of Birth:</strong> <?= htmlspecialchars($pdata['place_of_birth'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                        </div>
                        <!-- Payment & Clearance -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header"><i class="fas fa-money-bill-wave me-2"></i>Payment & Clearance</div>
                                <div class="card-body">
                                    <?php
                                    $balance = $payment['balance'];
                                    $total = $payment['total_amount'];
                                    $hasCharges = ($total > 0);
                                    ?>
                                    <p><strong>Total Bill:</strong> ₱<?= number_format($total, 2) ?></p>
                                    <p><strong>Amount Paid:</strong> ₱<?= number_format($payment['amount_paid'], 2) ?></p>
                                    <p><strong>Balance:</strong> ₱<?= number_format($balance, 2) ?></p>

                                    <?php if (!$hasCharges): ?>
                                        <span class="badge bg-secondary">No Charges</span>
                                    <?php elseif ($balance <= 0): ?>
                                        <span class="badge bg-success">Fully Paid</span>
                                    <?php else: ?>
                                        <button class="btn btn-primary btn-sm" onclick="openPaymentModal(<?= $pid ?>, <?= $total ?>, <?= $payment['amount_paid'] ?>)">
                                            <i class="fas fa-plus"></i> Add Payment
                                        </button>
                                    <?php endif; ?>

                                    <hr>
                                    <h5>Clearance</h5>
                                    <div class="d-flex flex-wrap gap-3">
                                        <span class="badge <?= $consult_done ? 'bg-success' : 'bg-secondary' ?>">Consultation <?= $consult_done ? '✓' : '✗' ?></span>
                                        <span class="badge <?= $lab_done ? 'bg-success' : 'bg-secondary' ?>">Lab <?= $lab_done ? '✓' : '✗' ?></span>
                                        <span class="badge <?= $med_done ? 'bg-success' : 'bg-secondary' ?>">Medicine <?= $med_done ? '✓' : '✗' ?></span>
                                        <?php if ($total == 0): ?>
                                            <span class="badge bg-secondary">No Charges</span>
                                        <?php elseif ($balance <= 0): ?>
                                            <span class="badge bg-success">Payment ✓</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Payment ✗</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3">
                                        <?php
                                        $payment_cleared = ($total == 0) || ($balance <= 0);
                                        $cleared = $consult_done && $lab_done && $med_done && $payment_cleared;
                                        ?>
                                        <h5>Final Status: <span class="badge <?= $cleared ? 'bg-success' : 'bg-danger' ?>"><?= $cleared ? 'CLEARED' : 'NOT CLEARED' ?></span></h5>
                                        <?php if ($cleared): ?>
                                            <a href="print_clearance.php?patient_id=<?= $pid ?>" target="_blank" class="btn btn-success btn-sm"><i class="fas fa-print"></i> Print Clearance</a>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-danger btn-sm mt-3" onclick="resetPatient(<?= $pid ?>, '<?= htmlspecialchars($pdata['fullname']) ?>')"><i class="fas fa-undo"></i> Reset All Records</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointments Tab -->
                <div class="tab-pane fade <?= $tab == 'appointments' ? 'show active' : '' ?>" id="appointments">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-calendar-alt me-2"></i>Appointment History
                            <span class="badge bg-primary ms-2"><?= count($appointmentHistory) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="filter-bar d-flex flex-wrap gap-2 mb-3">
                                <input type="text" id="appt-search" class="form-control form-control-sm" placeholder="Search by doctor..." style="width:200px;">
                                <select id="appt-status-filter" class="form-select form-select-sm" style="width:150px;">
                                    <option value="">All Status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                <input type="date" id="appt-date-filter" class="form-control form-control-sm" style="width:150px;">
                                <button class="btn btn-sm btn-secondary" onclick="filterAppointments()">Filter</button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="resetApptFilters()">Reset</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle" id="apptTable">
                                    <thead>
                                        <tr>
                                            <th>Appointment ID</th>
                                            <th>Date</th>
                                            <th>Doctor</th>
                                            <th>Specialty</th>
                                            <th>Status</th>
                                            <th>Lab Required</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($appointmentHistory)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No appointments found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($appointmentHistory as $a): ?>
                                                <tr>
                                                    <td>#<?= $a['appointment_id'] ?></td>
                                                    <td><?= date('M j, Y g:i A', strtotime($a['appointment_date'])) ?></td>
                                                    <td><?= htmlspecialchars($a['doctor_name'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($a['specialization'] ?? 'N/A') ?></td>
                                                    <td><span class="badge-status <?= strtolower($a['status']) ?>"><?= $a['status'] ?></span></td>
                                                    <td><?= $a['lab_required'] ?? 'No' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Billing Tab -->
                <div class="tab-pane fade <?= $tab == 'billing' ? 'show active' : '' ?>" id="billing">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Billing & Transaction History
                            <span class="badge bg-primary ms-2"><?= count($billingHistory) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="filter-bar d-flex flex-wrap gap-2 mb-3">
                                <input type="text" id="billing-search" class="form-control form-control-sm" placeholder="Search by transaction #..." style="width:200px;">
                                <select id="billing-status-filter" class="form-select form-select-sm" style="width:150px;">
                                    <option value="">All Status</option>
                                    <option value="Paid">Paid</option>
                                    <option value="Partially Paid">Partially Paid</option>
                                    <option value="Unpaid">Unpaid</option>
                                    <option value="Refunded">Refunded</option>
                                </select>
                                <select id="billing-method-filter" class="form-select form-select-sm" style="width:150px;">
                                    <option value="">All Methods</option>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Maya">Maya</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                                <button class="btn btn-sm btn-secondary" onclick="filterBilling()">Filter</button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="resetBillingFilters()">Reset</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle" id="billingTable">
                                    <thead>
                                        <tr>
                                            <th>Transaction #</th>
                                            <th>Date</th>
                                            <th>Total</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Processed By</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($billingHistory)): ?>
                                            <tr><td colspan="9" class="text-center text-muted py-4">No billing records found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($billingHistory as $b): 
                                                $balance = $b['total_amount'] - $b['amount_paid'];
                                                $badgeStatus = strtolower(str_replace(' ', '-', $b['payment_status']));
                                            ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($b['transaction_number']) ?></strong></td>
                                                    <td><?= date('M j, Y', strtotime($b['transaction_date'])) ?></td>
                                                    <td>₱<?= number_format($b['total_amount'], 2) ?></td>
                                                    <td>₱<?= number_format($b['amount_paid'], 2) ?></td>
                                                    <td>₱<?= number_format($balance, 2) ?></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($b['payment_method']) ?></span></td>
                                                    <td><span class="badge-status <?= $badgeStatus ?>"><?= $b['payment_status'] ?></span></td>
                                                    <td><?= htmlspecialchars($b['processed_by_name'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <a href="billing_view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-primary" title="View"><i class="fas fa-eye"></i></a>
                                                        <a href="billing_receipt.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="Print"><i class="fas fa-print"></i></a>
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

                <!-- Laboratory Tab -->
                <div class="tab-pane fade <?= $tab == 'laboratory' ? 'show active' : '' ?>" id="laboratory">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-microscope me-2"></i>Laboratory Records</div>
                        <div class="card-body table-responsive">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Test</th><th>Status</th><th>Result</th><th>Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($lab_records as $l): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($l['laboratory_type']) ?></td>
                                            <td><span class="badge bg-<?= $l['status'] == 'Completed' ? 'success' : 'secondary' ?>"><?= $l['status'] ?></span></td>
                                            <td><?= htmlspecialchars($l['result'] ?: '—') ?></td>
                                            <td><?= date('M j, Y', strtotime($l['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($lab_records)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No laboratory records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Medicines Tab -->
                <div class="tab-pane fade <?= $tab == 'medicines' ? 'show active' : '' ?>" id="medicines">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-pills me-2"></i>Medicine Records</div>
                        <div class="card-body table-responsive">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($medicines as $m): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($m['medicine_name']) ?></td>
                                            <td><?= htmlspecialchars($m['dosage']) ?></td>
                                            <td><?= htmlspecialchars($m['frequency']) ?></td>
                                            <td><?= htmlspecialchars($m['duration']) ?></td>
                                            <td><span class="badge bg-<?= $m['status'] == 'Taken' ? 'success' : 'secondary' ?>"><?= $m['status'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($medicines)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No medicine records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div> <!-- tab-content -->
        </div> <!-- container -->
    </div> <!-- main-content -->

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

        // Filter appointments
        function filterAppointments() {
            const search = document.getElementById('appt-search').value.toLowerCase();
            const status = document.getElementById('appt-status-filter').value;
            const date = document.getElementById('appt-date-filter').value;
            const rows = document.querySelectorAll('#apptTable tbody tr');
            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) return;
                let show = true;
                const text = row.textContent.toLowerCase();
                if (search && !text.includes(search)) show = false;
                if (status) {
                    const statusCell = row.querySelector('td:nth-child(5) .badge-status');
                    if (statusCell && statusCell.textContent.trim() !== status) show = false;
                }
                if (date) {
                    const dateCell = row.querySelector('td:nth-child(2)');
                    const rowDate = dateCell ? dateCell.textContent.trim() : '';
                    const parts = rowDate.split(' ');
                    if (parts.length >= 4) {
                        const month = parts[0];
                        const day = parts[1].replace(',', '');
                        const year = parts[2];
                        const formatted = `${year}-${String(new Date(month + ' 1, 2000').getMonth()+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                        if (formatted !== date) show = false;
                    }
                }
                row.style.display = show ? '' : 'none';
            });
        }
        function resetApptFilters() {
            document.getElementById('appt-search').value = '';
            document.getElementById('appt-status-filter').value = '';
            document.getElementById('appt-date-filter').value = '';
            document.querySelectorAll('#apptTable tbody tr').forEach(row => row.style.display = '');
        }
        document.getElementById('appt-search').addEventListener('keyup', filterAppointments);
        document.getElementById('appt-status-filter').addEventListener('change', filterAppointments);
        document.getElementById('appt-date-filter').addEventListener('change', filterAppointments);

        // Filter billing
        function filterBilling() {
            const search = document.getElementById('billing-search').value.toLowerCase();
            const status = document.getElementById('billing-status-filter').value;
            const method = document.getElementById('billing-method-filter').value;
            const rows = document.querySelectorAll('#billingTable tbody tr');
            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) return;
                let show = true;
                const text = row.textContent.toLowerCase();
                if (search && !text.includes(search)) show = false;
                if (status) {
                    const statusCell = row.querySelector('td:nth-child(7) .badge-status');
                    if (statusCell && statusCell.textContent.trim() !== status) show = false;
                }
                if (method) {
                    const methodCell = row.querySelector('td:nth-child(6) .badge');
                    if (methodCell && methodCell.textContent.trim() !== method) show = false;
                }
                row.style.display = show ? '' : 'none';
            });
        }
        function resetBillingFilters() {
            document.getElementById('billing-search').value = '';
            document.getElementById('billing-status-filter').value = '';
            document.getElementById('billing-method-filter').value = '';
            document.querySelectorAll('#billingTable tbody tr').forEach(row => row.style.display = '');
        }
        document.getElementById('billing-search').addEventListener('keyup', filterBilling);
        document.getElementById('billing-status-filter').addEventListener('change', filterBilling);
        document.getElementById('billing-method-filter').addEventListener('change', filterBilling);
    </script>
</body>
</html> 