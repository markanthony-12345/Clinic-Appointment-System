<?php
require_once 'config.php';
requireLogin();

$transactionService = new TransactionService();

$outstanding = $transactionService->getOutstandingBalances();
$stats = $transactionService->getStats();

$search = trim($_GET['search'] ?? '');
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$paymentStatus = $_GET['payment_status'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';
$patientSearch = trim($_GET['patient_search'] ?? '');
$doctorSearch = trim($_GET['doctor_search'] ?? '');

$filters = [
    'search' => $search,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'payment_status' => $paymentStatus,
    'payment_method' => $paymentMethod,
];

if (!empty($patientSearch)) {
    $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE fullname LIKE ? LIMIT 1");
    $stmt->execute(['%' . $patientSearch . '%']);
    $row = $stmt->fetch();
    if ($row) $filters['patient_id'] = $row['patient_id'];
}
if (!empty($doctorSearch)) {
    $stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE doctor_name LIKE ? LIMIT 1");
    $stmt->execute(['%' . $doctorSearch . '%']);
    $row = $stmt->fetch();
    if ($row) $filters['doctor_id'] = $row['doctor_id'];
}

$transactions = $transactionService->getTransactions($filters);

$totalBalance = 0;
foreach ($transactions as $t) {
    $totalBalance += ($t['total_amount'] - $t['amount_paid']);
}

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$txn_id = isset($_GET['txn_id']) ? (int)$_GET['txn_id'] : 0;
$txn = null;
if ($txn_id) {
    $txn = $transactionService->getTransaction($txn_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .stat-card { background: white; border-radius: 1.25rem; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: 0.2s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.08); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .stat-icon.blue { background: linear-gradient(135deg, #0EA5E9, #2563EB); }
        .stat-icon.green { background: linear-gradient(135deg, #22C55E, #16A34A); }
        .stat-icon.orange { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .stat-icon.purple { background: linear-gradient(135deg, #8B5CF6, #6D28D9); }
        .stat-number { font-size: 1.8rem; font-weight: 700; color: #1F2937; }
        .stat-label { color: #6B7280; font-size: 0.9rem; font-weight: 500; }
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
        .badge-method { background: #E5E7EB; color: #1F2937; padding: 0.25rem 0.6rem; border-radius: 2rem; font-size: 0.7rem; }
        .table th { font-weight: 600; color: #4a6f8c; border-bottom: 2px solid #e2e8f0; }
        .btn-sm { border-radius: 2rem; padding: 0.2rem 0.8rem; }
        .filter-form .form-control, .filter-form .form-select { border-radius: 0.75rem; }
        .transaction-number { font-weight: 600; color: #1e4a6e; }
        .info-label { font-weight: 600; color: #4a6f8c; }
        .auto-fill-box { background: #f8f9fa; border-radius: 1rem; padding: 1rem; margin-bottom: 1rem; }
        #patient-info-card, #appointment-info-card, #billing-details-card { display: none; }
        #patient-info-card.active, #appointment-info-card.active, #billing-details-card.active { display: block; }
        .loading-spinner { display: none; }
        #patient-search-form .form-control { border-radius: 2rem; }
        .search-btn { border-radius: 2rem; }
        .payment-form .form-control, .payment-form .form-select { border-radius: 0.75rem; }
        .patient-history-table { font-size: 0.85rem; }
        .patient-history-table td, .patient-history-table th { padding: 0.4rem 0.5rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-file-invoice-dollar me-2"></i>Transactions</h1>
                <div>
                    <a href="billing_create.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Transaction</a>
                    <a href="dashboard.php" class="btn btn-outline-primary ms-2"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Total Transactions</div>
                            <div class="stat-number"><?= count($transactions) ?></div>
                        </div>
                        <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Today's Revenue</div>
                            <div class="stat-number">₱<?= number_format($stats['today_revenue'] ?? 0, 2) ?></div>
                            <span class="text-muted small"><?= $stats['today_count'] ?? 0 ?> today</span>
                        </div>
                        <div class="stat-icon green"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Monthly Revenue</div>
                            <div class="stat-number">₱<?= number_format($stats['month_revenue'] ?? 0, 2) ?></div>
                        </div>
                        <div class="stat-icon purple"><i class="fas fa-chart-line"></i></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Outstanding Balance</div>
                            <div class="stat-number">₱<?= number_format($totalBalance, 2) ?></div>
                        </div>
                        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>

            <!-- Main Transaction Panel -->
            <div class="row g-4">
                <!-- LEFT PANEL: Patient Lookup & Info -->
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-search me-2"></i>Patient Lookup</div>
                        <div class="card-body">
                            <form id="patient-search-form" method="GET" class="mb-3">
                                <div class="input-group">
                                    <input type="text" name="patient_id" id="patient_id_input" class="form-control" placeholder="Enter Patient ID" value="<?= $patient_id ? $patient_id : '' ?>">
                                    <button class="btn btn-primary search-btn" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Example: 1, 2, 3 ...</small>
                                </div>
                            </form>

                            <div id="loading-spinner" class="text-center py-3" style="display:none;">
                                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            </div>

                            <!-- Patient Info -->
                            <div id="patient-info-card" class="<?= $patient_id ? 'active' : '' ?>">
                                <div class="auto-fill-box">
                                    <h6 class="fw-bold"><i class="fas fa-user me-2 text-primary"></i>Patient Information</h6>
                                    <div id="patient-info-content">
                                        <div class="row">
                                            <div class="col-6"><span class="info-label">ID:</span> <span id="p_id">-</span></div>
                                            <div class="col-6"><span class="info-label">Name:</span> <span id="p_name">-</span></div>
                                            <div class="col-6"><span class="info-label">Age:</span> <span id="p_age">-</span></div>
                                            <div class="col-6"><span class="info-label">Gender:</span> <span id="p_gender">-</span></div>
                                            <div class="col-12"><span class="info-label">Contact:</span> <span id="p_contact">-</span></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Appointment Info -->
                                <div id="appointment-info-card" class="<?= $patient_id ? 'active' : '' ?>">
                                    <div class="auto-fill-box">
                                        <h6 class="fw-bold"><i class="fas fa-calendar-check me-2 text-primary"></i>Appointment Information</h6>
                                        <div id="appointment-info-content">
                                            <div class="row">
                                                <div class="col-6"><span class="info-label">Appointment ID:</span> <span id="a_id">-</span></div>
                                                <div class="col-6"><span class="info-label">Doctor:</span> <span id="a_doctor">-</span></div>
                                                <div class="col-6"><span class="info-label">Date:</span> <span id="a_date">-</span></div>
                                                <div class="col-6"><span class="info-label">Status:</span> <span id="a_status">-</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transaction Summary -->
                                <div id="billing-details-card" class="<?= $patient_id ? 'active' : '' ?>">
                                    <div class="auto-fill-box">
                                        <h6 class="fw-bold"><i class="fas fa-file-invoice me-2 text-primary"></i>Latest Transaction Summary</h6>
                                        <div id="billing-info-content">
                                            <div class="row">
                                                <div class="col-6"><span class="info-label">Transaction #:</span> <span id="txn_number">-</span></div>
                                                <div class="col-6"><span class="info-label">Status:</span> <span id="txn_status">-</span></div>
                                                <div class="col-6"><span class="info-label">Total:</span> <span id="txn_total">-</span></div>
                                                <div class="col-6"><span class="info-label">Paid:</span> <span id="txn_paid">-</span></div>
                                                <div class="col-6"><span class="info-label">Balance:</span> <span id="txn_balance">-</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transaction History Table -->
                                <div id="patient-transaction-history" style="display:none; margin-top:1rem;">
                                    <h6 class="fw-bold"><i class="fas fa-history me-2 text-primary"></i>Transaction History</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover patient-history-table" id="patientHistoryTable">
                                            <thead>
                                                <tr>
                                                    <th>Transaction #</th>
                                                    <th>Date</th>
                                                    <th>Total</th>
                                                    <th>Paid</th>
                                                    <th>Balance</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="patientHistoryBody">
                                                <!-- filled by JS -->
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="no-transaction-msg" class="text-center text-muted py-2" style="display:none;">
                                        <p>No transactions found for this patient.</p>
                                        <a href="billing_create.php?patient_id=<?= $patient_id ?>" class="btn btn-sm btn-primary">Create Transaction</a>
                                    </div>
                                </div>
                            </div>

                            <!-- No patient selected -->
                            <div id="no-patient-selected" class="<?= $patient_id ? 'd-none' : '' ?>">
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-search fa-2x mb-2"></i>
                                    <p>Enter a Patient ID above to load details.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Payment Processing -->
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-credit-card me-2"></i>Payment Processing</div>
                        <div class="card-body">
                            <?php if ($patient_id && $txn): ?>
                                <!-- Payment Form -->
                                <form action="billing_pay.php" method="POST" class="payment-form">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <input type="hidden" name="transaction_id" value="<?= $txn['id'] ?>">

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Transaction #</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($txn['transaction_number']) ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Patient</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($txn['patient_name'] ?? '') ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Total Amount</label>
                                            <input type="text" class="form-control" value="₱<?= number_format($txn['total_amount'], 2) ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Already Paid</label>
                                            <input type="text" class="form-control" value="₱<?= number_format($txn['amount_paid'], 2) ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Remaining Balance</label>
                                            <input type="text" class="form-control" value="₱<?= number_format($txn['total_amount'] - $txn['amount_paid'], 2) ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Payment Method</label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="Cash">Cash</option>
                                                <option value="GCash">GCash</option>
                                                <option value="Maya">Maya</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Reference # (optional)</label>
                                            <input type="text" name="reference_number" class="form-control" placeholder="For digital payments">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Amount to Pay</label>
                                            <input type="number" name="amount_paid" class="form-control" step="0.01" min="0.01" required value="<?= number_format($txn['total_amount'] - $txn['amount_paid'], 2) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Notes</label>
                                            <textarea name="notes" class="form-control" rows="2"></textarea>
                                        </div>
                                    </div>

                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Record Payment</button>
                                        <button type="button" class="btn btn-secondary" onclick="window.open('billing_receipt.php?id=<?= $txn['id'] ?>', '_blank')">
                                            <i class="fas fa-print me-1"></i>Print Receipt
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='billing.php'">Clear</button>
                                    </div>
                                </form>
                            <?php elseif ($patient_id && !$txn): ?>
                                <!-- No billing record -->
                                <div class="text-center py-4">
                                    <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No active transaction found for this patient.</p>
                                    <a href="billing_create.php?patient_id=<?= $patient_id ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Create Transaction
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- No patient selected -->
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-hand-holding-usd fa-3x mb-3"></i>
                                    <p>Select a patient from the left panel or the Outstanding Balances table below.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Balances Table -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>Outstanding Balances</span>
                    <span class="badge bg-primary rounded-pill"><?= count($outstanding) ?> patients</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <input type="text" id="outstanding-search" class="form-control" placeholder="Search patient, transaction #, doctor...">
                        </div>
                        <div class="col-md-3">
                            <select id="status-filter" class="form-select">
                                <option value="">All Status</option>
                                <option value="Unpaid">Unpaid</option>
                                <option value="Partially Paid">Partially Paid</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="period-filter" class="form-select">
                                <option value="">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-secondary w-100" onclick="applyFilters()"><i class="fas fa-filter me-1"></i>Filter</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle outstanding-table" id="outstandingTable">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Transaction #</th>
                                    <th>Doctor</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($outstanding)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">No outstanding balances found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($outstanding as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['patient_name']) ?></strong><br><small class="text-muted">ID: <?= $row['patient_id'] ?></small></td>
                                        <td><?= htmlspecialchars($row['transaction_number']) ?></td>
                                        <td><?= htmlspecialchars($row['doctor_name'] ?? 'N/A') ?></td>
                                        <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                        <td>₱<?= number_format($row['amount_paid'], 2) ?></td>
                                        <td><strong class="text-danger">₱<?= number_format($row['balance'], 2) ?></strong></td>
                                        <td><span class="badge-status <?= strtolower(str_replace(' ', '-', $row['payment_status'])) ?>"><?= $row['payment_status'] ?></span></td>
                                        <td><?= date('M j, Y', strtotime($row['transaction_date'])) ?></td>
                                        <td>
                                            <a href="billing.php?patient_id=<?= $row['patient_id'] ?>&txn_id=<?= $row['transaction_id'] ?>" class="btn btn-primary btn-sm btn-pay">
                                                <i class="fas fa-hand-holding-usd me-1"></i>Pay
                                            </a>
                                            <a href="billing_view.php?id=<?= $row['transaction_id'] ?>" class="btn btn-secondary btn-sm btn-pay" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- All Transactions Table -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-table me-2"></i>Transaction History</span>
                    <span class="badge bg-primary rounded-pill"><?= count($transactions) ?> records</span>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-form row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Txn #, Patient, Doctor" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From</label>
                            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To</label>
                            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="payment_status" class="form-select">
                                <option value="">All</option>
                                <option value="Paid" <?= $paymentStatus=='Paid'?'selected':'' ?>>Paid</option>
                                <option value="Partially Paid" <?= $paymentStatus=='Partially Paid'?'selected':'' ?>>Partially Paid</option>
                                <option value="Unpaid" <?= $paymentStatus=='Unpaid'?'selected':'' ?>>Unpaid</option>
                                <option value="Refunded" <?= $paymentStatus=='Refunded'?'selected':'' ?>>Refunded</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="">All</option>
                                <option value="Cash" <?= $paymentMethod=='Cash'?'selected':'' ?>>Cash</option>
                                <option value="GCash" <?= $paymentMethod=='GCash'?'selected':'' ?>>GCash</option>
                                <option value="Maya" <?= $paymentMethod=='Maya'?'selected':'' ?>>Maya</option>
                                <option value="Bank Transfer" <?= $paymentMethod=='Bank Transfer'?'selected':'' ?>>Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Patient</label>
                            <input type="text" name="patient_search" class="form-control" placeholder="Search patient" value="<?= htmlspecialchars($patientSearch) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Doctor</label>
                            <input type="text" name="doctor_search" class="form-control" placeholder="Search doctor" value="<?= htmlspecialchars($doctorSearch) ?>">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Apply</button>
                            <a href="billing.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i>Reset</a>
                            <a href="export_billing_csv.php<?= !empty($_GET) ? '?' . http_build_query($_GET) : '' ?>" class="btn btn-success ms-auto"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Transaction #</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr><td colspan="10" class="text-center text-muted py-4">No transactions found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $txn):
                                        $balance = $txn['total_amount'] - $txn['amount_paid'];
                                        $badgeStatus = strtolower(str_replace(' ', '-', $txn['payment_status']));
                                        if ($txn['is_refunded']) $badgeStatus = 'refunded';
                                    ?>
                                        <tr>
                                            <td><span class="transaction-number"><?= htmlspecialchars($txn['transaction_number']) ?></span></td>
                                            <td><?= htmlspecialchars($txn['patient_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($txn['doctor_name'] ?? 'N/A') ?></td>
                                            <td>₱<?= number_format($txn['total_amount'], 2) ?></td>
                                            <td>₱<?= number_format($txn['amount_paid'], 2) ?></td>
                                            <td>₱<?= number_format($balance, 2) ?></td>
                                            <td><span class="badge-method"><?= htmlspecialchars($txn['payment_method']) ?></span></td>
                                            <td><span class="badge-status <?= $badgeStatus ?>"><?= $txn['payment_status'] ?></span></td>
                                            <td><?= date('M j, Y g:i A', strtotime($txn['transaction_date'])) ?></td>
                                            <td>
                                                <a href="billing_view.php?id=<?= $txn['id'] ?>" class="btn btn-sm btn-primary" title="View"><i class="fas fa-eye"></i></a>
                                                <a href="billing_receipt.php?id=<?= $txn['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="Print"><i class="fas fa-print"></i></a>
                                                <a href="billing_edit.php?id=<?= $txn['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                                <?php if ($txn['payment_status'] != 'Refunded' && !$txn['is_refunded']): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="refundBilling(<?= $txn['id'] ?>, '<?= htmlspecialchars($txn['transaction_number'], ENT_QUOTES) ?>')" title="Refund"><i class="fas fa-undo"></i></button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-danger" onclick="deleteBilling(<?= $txn['id'] ?>, '<?= htmlspecialchars($txn['transaction_number'], ENT_QUOTES) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===== AUTO-LOAD PATIENT INFO IF ID IN URL =====
            const urlParams = new URLSearchParams(window.location.search);
            const patientIdFromUrl = urlParams.get('patient_id');
            if (patientIdFromUrl) {
                document.getElementById('patient_id_input').value = patientIdFromUrl;
                // Trigger the blur event after a short delay to let DOM render
                setTimeout(() => {
                    document.getElementById('patient_id_input').dispatchEvent(new Event('blur'));
                }, 300);
            }

            // ===== PATIENT SEARCH FORM =====
            document.getElementById('patient-search-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const input = document.getElementById('patient_id_input');
                const patientId = input.value.trim();
                if (patientId) {
                    window.location.href = 'billing.php?patient_id=' + encodeURIComponent(patientId);
                } else {
                    window.location.href = 'billing.php';
                }
            });

            // ===== BLUR EVENT TO LOAD PATIENT INFO =====
            document.getElementById('patient_id_input')?.addEventListener('blur', function() {
                const pid = this.value.trim();
                if (pid) {
                    loadPatientData(pid);
                } else {
                    resetPatientPanel();
                }
            });

            // ===== FUNCTION TO LOAD PATIENT DATA =====
            function loadPatientData(pid) {
                document.getElementById('loading-spinner').style.display = 'block';
                document.getElementById('patient-info-card').classList.remove('active');
                document.getElementById('appointment-info-card').classList.remove('active');
                document.getElementById('billing-details-card').classList.remove('active');
                document.getElementById('patient-transaction-history').style.display = 'none';
                document.getElementById('no-patient-selected').classList.add('d-none');

                // Fetch patient info and latest transaction
                fetch(`billing_ajax.php?action=get_patient_info&patient_id=${pid}`)
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('loading-spinner').style.display = 'none';
                        if (data.success) {
                            const info = data.data;
                            // Patient info
                            document.getElementById('p_id').textContent = info.patient.patient_id;
                            document.getElementById('p_name').textContent = info.patient.fullname;
                            document.getElementById('p_age').textContent = info.patient.age;
                            document.getElementById('p_gender').textContent = info.patient.gender || 'N/A';
                            document.getElementById('p_contact').textContent = info.patient.contact_number || 'N/A';
                            document.getElementById('patient-info-card').classList.add('active');

                            // Appointment info
                            if (info.appointment) {
                                document.getElementById('a_id').textContent = info.appointment.appointment_id;
                                document.getElementById('a_doctor').textContent = info.appointment.doctor_name || 'N/A';
                                document.getElementById('a_date').textContent = info.appointment.appointment_date ? new Date(info.appointment.appointment_date).toLocaleString() : 'N/A';
                                document.getElementById('a_status').textContent = info.appointment.status || 'N/A';
                                document.getElementById('appointment-info-card').classList.add('active');
                            } else {
                                document.getElementById('a_id').textContent = 'No appointment';
                                document.getElementById('a_doctor').textContent = '-';
                                document.getElementById('a_date').textContent = '-';
                                document.getElementById('a_status').textContent = '-';
                                document.getElementById('appointment-info-card').classList.add('active');
                            }

                            // Latest transaction summary
                            if (info.transaction) {
                                const txn = info.transaction;
                                document.getElementById('txn_number').textContent = txn.transaction_number || '-';
                                document.getElementById('txn_status').textContent = txn.payment_status || 'No transaction';
                                document.getElementById('txn_total').textContent = '₱' + parseFloat(txn.total_amount || 0).toFixed(2);
                                document.getElementById('txn_paid').textContent = '₱' + parseFloat(txn.amount_paid || 0).toFixed(2);
                                document.getElementById('txn_balance').textContent = '₱' + parseFloat(txn.balance || 0).toFixed(2);
                                document.getElementById('billing-details-card').classList.add('active');
                                // Redirect to include txn_id for payment form
                                const url = new URL(window.location);
                                if (!url.searchParams.has('txn_id')) {
                                    url.searchParams.set('txn_id', txn.transaction_id);
                                    window.history.replaceState({}, '', url);
                                }
                            } else {
                                document.getElementById('txn_number').textContent = 'No transaction';
                                document.getElementById('txn_status').textContent = '-';
                                document.getElementById('txn_total').textContent = '-';
                                document.getElementById('txn_paid').textContent = '-';
                                document.getElementById('txn_balance').textContent = '-';
                                document.getElementById('billing-details-card').classList.add('active');
                            }

                            // ===== FETCH ALL TRANSACTIONS FOR HISTORY TABLE =====
                            fetch(`billing_ajax.php?action=get_patient_transactions&patient_id=${pid}`)
                                .then(res => res.json())
                                .then(historyData => {
                                    const tbody = document.getElementById('patientHistoryBody');
                                    const historyDiv = document.getElementById('patient-transaction-history');
                                    const noMsg = document.getElementById('no-transaction-msg');
                                    tbody.innerHTML = '';
                                    if (historyData.success && historyData.data && historyData.data.length > 0) {
                                        historyDiv.style.display = 'block';
                                        noMsg.style.display = 'none';
                                        historyData.data.forEach(t => {
                                            const balance = t.total_amount - t.amount_paid;
                                            const badgeClass = t.payment_status.toLowerCase().replace(' ', '-');
                                            const row = document.createElement('tr');
                                            row.innerHTML = `
                                                <td><a href="billing_view.php?id=${t.id}">${t.transaction_number}</a></td>
                                                <td>${new Date(t.transaction_date).toLocaleDateString()}</td>
                                                <td>₱${parseFloat(t.total_amount).toFixed(2)}</td>
                                                <td>₱${parseFloat(t.amount_paid).toFixed(2)}</td>
                                                <td>₱${parseFloat(balance).toFixed(2)}</td>
                                                <td><span class="badge-status ${badgeClass}">${t.payment_status}</span></td>
                                                <td>
                                                    <a href="billing_view.php?id=${t.id}" class="btn btn-sm btn-primary" title="View"><i class="fas fa-eye"></i></a>
                                                    <a href="billing_receipt.php?id=${t.id}" target="_blank" class="btn btn-sm btn-secondary" title="Print"><i class="fas fa-print"></i></a>
                                                </td>
                                            `;
                                            tbody.appendChild(row);
                                        });
                                    } else {
                                        historyDiv.style.display = 'block';
                                        noMsg.style.display = 'block';
                                        tbody.innerHTML = '';
                                    }
                                })
                                .catch(err => {
                                    console.error('Error fetching transaction history:', err);
                                    document.getElementById('patient-transaction-history').style.display = 'block';
                                    document.getElementById('no-transaction-msg').style.display = 'block';
                                    document.getElementById('patientHistoryBody').innerHTML = '';
                                });
                        } else {
                            alert('Patient not found.');
                            resetPatientPanel();
                        }
                    })
                    .catch(err => {
                        document.getElementById('loading-spinner').style.display = 'none';
                        alert('Error loading patient info: ' + err.message);
                        resetPatientPanel();
                    });
            }

            function resetPatientPanel() {
                document.getElementById('patient-info-card').classList.remove('active');
                document.getElementById('appointment-info-card').classList.remove('active');
                document.getElementById('billing-details-card').classList.remove('active');
                document.getElementById('patient-transaction-history').style.display = 'none';
                document.getElementById('no-patient-selected').classList.remove('d-none');
                // Clear fields
                ['p_id','p_name','p_age','p_gender','p_contact'].forEach(id => document.getElementById(id).textContent = '-');
                ['a_id','a_doctor','a_date','a_status'].forEach(id => document.getElementById(id).textContent = '-');
                ['txn_number','txn_status','txn_total','txn_paid','txn_balance'].forEach(id => document.getElementById(id).textContent = '-');
            }

            // ===== OUTSTANDING BALANCES FILTERS =====
            function applyFilters() {
                const search = document.getElementById('outstanding-search').value.toLowerCase();
                const status = document.getElementById('status-filter').value;
                const period = document.getElementById('period-filter').value;
                const rows = document.querySelectorAll('#outstandingTable tbody tr');
                rows.forEach(row => {
                    if (row.querySelector('td[colspan]')) return;
                    let show = true;
                    const text = row.textContent.toLowerCase();
                    const statusCell = row.querySelector('td:nth-child(7) .badge-status');
                    const statusText = statusCell ? statusCell.textContent.trim() : '';
                    const dateCell = row.querySelector('td:nth-child(8)');
                    const dateText = dateCell ? dateCell.textContent.trim() : '';
                    if (search && !text.includes(search)) show = false;
                    if (status && statusText !== status) show = false;
                    if (period && show) {
                        const today = new Date();
                        let date = new Date(dateText);
                        if (isNaN(date)) date = new Date();
                        if (period === 'today') {
                            if (date.toDateString() !== today.toDateString()) show = false;
                        } else if (period === 'week') {
                            const weekStart = new Date(today);
                            weekStart.setDate(today.getDate() - today.getDay());
                            if (date < weekStart) show = false;
                        } else if (period === 'month') {
                            if (date.getMonth() !== today.getMonth() || date.getFullYear() !== today.getFullYear()) show = false;
                        }
                    }
                    row.style.display = show ? '' : 'none';
                });
            }
            document.getElementById('outstanding-search').addEventListener('keyup', applyFilters);
            document.getElementById('status-filter').addEventListener('change', applyFilters);
            document.getElementById('period-filter').addEventListener('change', applyFilters);

            // ===== DELETE AND REFUND FUNCTIONS =====
            window.deleteBilling = function(id, number) {
                if (!confirm(`Delete transaction ${number}? This is a soft delete and can be undone.`)) return;
                fetch(`billing_delete.php?id=${id}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) { alert('Deleted.'); location.reload(); }
                        else alert('Delete failed: ' + data.message);
                    })
                    .catch(() => alert('Network error.'));
            };
            window.refundBilling = function(id, number) {
                if (!confirm(`Refund transaction ${number}? This will set status to Refunded.`)) return;
                fetch(`billing_refund.php?id=${id}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) { alert('Refunded.'); location.reload(); }
                        else alert('Refund failed: ' + data.message);
                    })
                    .catch(() => alert('Network error.'));
            };
        });
    </script>
</body>
</html>