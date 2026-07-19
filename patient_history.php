<?php
require_once 'config.php';
requireLogin();

$pid = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if (!$pid) {
    header("Location: dashboard.php?error=No patient selected");
    exit;
}

$patientService = new PatientService();
$transactionService = new TransactionService();

// Fetch patient basic info
$patient = $patientService->getById($pid);
if (!$patient) {
    header("Location: dashboard.php?error=Patient not found");
    exit;
}

// Fetch all history data
$appointments = $patientService->getAppointmentHistory($pid);
$lab_records = $pdo->prepare("SELECT * FROM laboratory WHERE patient_id = ? ORDER BY created_at DESC");
$lab_records->execute([$pid]);
$lab_records = $lab_records->fetchAll();

$medicines = $pdo->prepare("SELECT * FROM medicines WHERE patient_id = ? ORDER BY prescription_date DESC");
$medicines->execute([$pid]);
$medicines = $medicines->fetchAll();

$transactions = $transactionService->getPatientTransactions($pid);

// Get current tab from URL
$tab = $_GET['tab'] ?? 'appointments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient History - <?= htmlspecialchars($patient['fullname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1.25rem; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1.25rem 1.25rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .btn-primary { background: linear-gradient(135deg, #1e6f9f, #155d85); border: none; border-radius: 2rem; }
        .btn-primary:hover { background: linear-gradient(135deg, #155d85, #0f4a6e); }
        .btn-outline-primary { border-radius: 2rem; }
        .badge-status { padding: 0.35rem 0.85rem; border-radius: 2rem; font-weight: 500; font-size: 0.75rem; }
        .badge-status.paid { background: #D1FAE5; color: #065F46; }
        .badge-status.partial { background: #FEF3C7; color: #92400E; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.refunded { background: #E5E7EB; color: #6B7280; }
        .badge-status.completed { background: #DBEAFE; color: #1E40AF; }
        .badge-status.pending { background: #FEF3C7; color: #92400E; }
        .badge-status.cancelled { background: #FEE2E2; color: #991B1B; }
        .badge-status.scheduled { background: #E0F2FE; color: #0369A1; }
        .badge-status.taken { background: #e0f2e9; color: #1e6f3f; }
        .badge-status.not-taken { background: #f0f0f0; color: #5b7f9c; }
        .badge-status.ongoing { background: #FEF3C7; color: #92400E; }
        .badge-status.not-yet-taken { background: #FEE2E2; color: #991B1B; }
        .nav-tabs .nav-link { color: #1e4a6e; font-weight: 500; }
        .nav-tabs .nav-link.active { color: #1e6f9f; font-weight: 600; border-bottom: 2px solid #1e6f9f; }
        .filter-bar { background: #f8f9fa; border-radius: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 1rem; }
        .stat-summary { background: white; border-radius: 1rem; padding: 1rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .stat-summary .number { font-size: 1.8rem; font-weight: 700; color: #1e4a6e; }
        .stat-summary .label { color: #6B7280; font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <div>
                    <h1 class="h3"><i class="fas fa-history me-2"></i>Patient History</h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($patient['fullname']) ?> (ID: <?= $pid ?>)</p>
                </div>
                <div>
                    <a href="patient_overview.php?patient_id=<?= $pid ?>" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Profile</a>
                    <a href="dashboard.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-home me-1"></i>Dashboard</a>
                </div>
            </header>

            <!-- Summary Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-summary">
                        <div class="number"><?= count($appointments) ?></div>
                        <div class="label">Appointments</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-summary">
                        <div class="number"><?= count($lab_records) ?></div>
                        <div class="label">Lab Tests</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-summary">
                        <div class="number"><?= count($medicines) ?></div>
                        <div class="label">Medicines</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-summary">
                        <div class="number"><?= count($transactions) ?></div>
                        <div class="label">Transactions</div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="historyTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'appointments' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=appointments" role="tab">Appointments</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'laboratory' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=laboratory" role="tab">Laboratory</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'medicines' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=medicines" role="tab">Medicines</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $tab == 'billing' ? 'active' : '' ?>" href="?patient_id=<?= $pid ?>&tab=billing" role="tab">Billing</a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">

                <!-- Appointments Tab -->
                <div class="tab-pane fade <?= $tab == 'appointments' ? 'show active' : '' ?>" id="appointments">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-alt me-2"></i>Appointment History</span>
                            <span class="badge bg-primary rounded-pill"><?= count($appointments) ?></span>
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
                                <button class="btn btn-sm btn-secondary" onclick="filterAppointments()"><i class="fas fa-filter me-1"></i>Filter</button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="resetApptFilters()"><i class="fas fa-undo me-1"></i>Reset</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle" id="apptTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Doctor</th>
                                            <th>Specialty</th>
                                            <th>Status</th>
                                            <th>Lab Required</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($appointments)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No appointments found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($appointments as $a): ?>
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

                <!-- Laboratory Tab -->
                <div class="tab-pane fade <?= $tab == 'laboratory' ? 'show active' : '' ?>" id="laboratory">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-flask me-2"></i>Laboratory History</span>
                            <span class="badge bg-primary rounded-pill"><?= count($lab_records) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Test</th>
                                            <th>Date</th>
                                            <th>Doctor</th>
                                            <th>Status</th>
                                            <th>Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($lab_records)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No laboratory records found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($lab_records as $l): 
                                                $doc = $pdo->prepare("SELECT doctor_name FROM doctors WHERE doctor_id = ?");
                                                $doc->execute([$l['doctor_id']]);
                                                $doc_name = $doc->fetchColumn();
                                            ?>
                                                <tr>
                                                    <td>#<?= $l['lab_id'] ?></td>
                                                    <td><?= htmlspecialchars($l['laboratory_type']) ?></td>
                                                    <td><?= date('M j, Y', strtotime($l['created_at'])) ?></td>
                                                    <td><?= htmlspecialchars($doc_name ?? 'N/A') ?></td>
                                                    <td><span class="badge-status <?= strtolower(str_replace(' ', '-', $l['status'])) ?>"><?= $l['status'] ?></span></td>
                                                    <td><?= htmlspecialchars($l['result'] ?: '—') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medicines Tab -->
                <div class="tab-pane fade <?= $tab == 'medicines' ? 'show active' : '' ?>" id="medicines">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-pills me-2"></i>Medication History</span>
                            <span class="badge bg-primary rounded-pill"><?= count($medicines) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Medicine</th>
                                            <th>Dosage</th>
                                            <th>Frequency</th>
                                            <th>Duration</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($medicines)): ?>
                                            <tr><td colspan="7" class="text-center text-muted py-4">No medicine records found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($medicines as $m): ?>
                                                <tr>
                                                    <td>#<?= $m['medicine_id'] ?></td>
                                                    <td><?= htmlspecialchars($m['medicine_name']) ?></td>
                                                    <td><?= htmlspecialchars($m['dosage'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($m['frequency'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($m['duration'] ?? '') ?></td>
                                                    <td><?= date('M j, Y', strtotime($m['prescription_date'])) ?></td>
                                                    <td><span class="badge-status <?= strtolower(str_replace(' ', '-', $m['status'])) ?>"><?= $m['status'] ?></span></td>
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-file-invoice-dollar me-2"></i>Billing & Transaction History</span>
                            <span class="badge bg-primary rounded-pill"><?= count($transactions) ?></span>
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
                                <button class="btn btn-sm btn-secondary" onclick="filterBilling()"><i class="fas fa-filter me-1"></i>Filter</button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="resetBillingFilters()"><i class="fas fa-undo me-1"></i>Reset</button>
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
                                        <?php if (empty($transactions)): ?>
                                            <tr><td colspan="9" class="text-center text-muted py-4">No billing records found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($transactions as $b): 
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

            </div> <!-- tab-content -->
        </div> <!-- container -->
    </div> <!-- main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== APPOINTMENT FILTERS =====
        function filterAppointments() {
            const search = document.getElementById('appt-search').value.toLowerCase();
            const status = document.getElementById('appt-status-filter').value;
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
                row.style.display = show ? '' : 'none';
            });
        }
        function resetApptFilters() {
            document.getElementById('appt-search').value = '';
            document.getElementById('appt-status-filter').value = '';
            document.querySelectorAll('#apptTable tbody tr').forEach(row => row.style.display = '');
        }
        document.getElementById('appt-search').addEventListener('keyup', filterAppointments);
        document.getElementById('appt-status-filter').addEventListener('change', filterAppointments);

        // ===== BILLING FILTERS =====
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