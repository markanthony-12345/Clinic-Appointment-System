<?php
require_once 'config.php';
requireLogin();

$reportService = new ReportService();
$stats = $reportService->getDashboardStats();
$apptByDoctor = $reportService->getAppointmentsByDoctor(30);
$paymentStatus = $reportService->getPaymentStatusBreakdown();
$labStatus = $reportService->getLabStatusBreakdown();
$registrations = $reportService->getPatientRegistrations(7);
$recentPatients = $reportService->getRecentPatients(20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: none; }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .btn-success, .btn-info { border-radius: 2rem; }
    </style>
</head>
<body>
<div class="container py-4">
    <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <h1 class="h3"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">← Back to Dashboard</a>
    </header>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Total Patients</h5><p class="display-6"><?= $stats['total_patients'] ?></p></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Pending</h5><p class="display-6"><?= $stats['pending_appointments'] ?></p></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Cleared</h5><p class="display-6"><?= $stats['cleared_patients'] ?></p></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Paid</h5><p class="display-6"><?= $stats['paid_patients'] ?></p></div></div></div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-md-6"><div class="card"><div class="card-header"><i class="fas fa-chart-bar me-2"></i>Appointments per Doctor</div><div class="card-body"><canvas id="doctorChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-header"><i class="fas fa-chart-pie me-2"></i>Payment Status</div><div class="card-body"><canvas id="paymentChart"></canvas></div></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-6"><div class="card"><div class="card-header"><i class="fas fa-chart-doughnut me-2"></i>Lab Status</div><div class="card-body"><canvas id="labChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-header"><i class="fas fa-chart-line me-2"></i>Registrations (7 days)</div><div class="card-body"><canvas id="registrationChart"></canvas></div></div></div>
    </div>

    <!-- Export -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-download me-2"></i>Export Data</div>
        <div class="card-body">
            <a href="export_csv.php?type=patients" class="btn btn-success"><i class="fas fa-file-csv me-1"></i>Export Patients CSV</a>
            <a href="export_csv.php?type=appointments" class="btn btn-success"><i class="fas fa-file-csv me-1"></i>Export Appointments CSV</a>
            <a href="xml_export.php" class="btn btn-info"><i class="fas fa-file-code me-1"></i>Export XML</a>
        </div>
    </div>

    <!-- All Patients Table -->
    <div class="card">
        <div class="card-header"><i class="fas fa-table me-2"></i>All Patients</div>
        <div class="card-body table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Sex</th>
                        <th>Contact</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentPatients as $p): ?>
                    <?php
                        $amount_paid = (float)($p['amount_paid'] ?? 0);
                        $total_amount = (float)($p['total_amount'] ?? 0);
                        if ($total_amount > 0 && $amount_paid >= $total_amount) {
                            $paymentStatusText = 'Paid';
                            $paymentBadge = 'bg-success';
                        } elseif ($amount_paid > 0 && $amount_paid < $total_amount) {
                            $paymentStatusText = 'Partial';
                            $paymentBadge = 'bg-warning';
                        } else {
                            $paymentStatusText = 'Unpaid';
                            $paymentBadge = 'bg-danger';
                        }
                    ?>
                    <tr>
                        <td><?= $p['patient_id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['fullname']) ?></strong></td>
                        <td><?= $p['age'] ?></td>
                        <td><?= htmlspecialchars($p['sex'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['contact_number'] ?? '') ?></td>
                        <td><span class="badge <?= $paymentBadge ?>"><?= $paymentStatusText ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Charts
const doctorLabels = <?= json_encode(array_column($apptByDoctor, 'doctor_name')) ?>;
const doctorCounts = <?= json_encode(array_column($apptByDoctor, 'count')) ?>;
new Chart(document.getElementById('doctorChart'), {
    type: 'bar',
    data: { labels: doctorLabels, datasets: [{ label: 'Appointments', data: doctorCounts, backgroundColor: 'rgba(54, 162, 235, 0.5)' }] },
    options: { responsive: true }
});

const paymentData = <?= json_encode($paymentStatus) ?>;
new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: { labels: ['Paid','Partial','Unpaid'], datasets: [{ data: [paymentData.paid, paymentData.partial, paymentData.unpaid], backgroundColor: ['#28a745','#ffc107','#dc3545'] }] }
});

const labStatus = <?= json_encode($labStatus) ?>;
new Chart(document.getElementById('labChart'), {
    type: 'doughnut',
    data: { labels: labStatus.map(i => i.status), datasets: [{ data: labStatus.map(i => i.count), backgroundColor: ['#17a2b8','#ffc107','#28a745'] }] }
});

const regData = <?= json_encode($registrations) ?>;
new Chart(document.getElementById('registrationChart'), {
    type: 'line',
    data: { labels: regData.map(i => i.date), datasets: [{ label: 'Registrations', data: regData.map(i => i.count), borderColor: '#007bff', fill: false }] }
});
</script>
</body>
</html>