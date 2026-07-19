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

// Additional data for revenue chart (example)
$revenueData = [
    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    'values' => [5000, 7000, 6000, 9000, 12000, 15000]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1.25rem; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.03); padding: 1.25rem 1.5rem; }
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
        .chart-container {
            position: relative;
            height: 220px;
            width: 100%;
        }
        .export-btn {
            border-radius: 2rem;
        }
        .badge-status {
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .badge-status.paid { background: #D1FAE5; color: #065F46; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.partial { background: #FEF3C7; color: #92400E; }
        .badge-status.completed { background: #D1FAE5; color: #065F46; }
        .badge-status.pending { background: #FEF3C7; color: #92400E; }
        .badge-status.cancelled { background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Reports & Analytics</h4>
            <div class="d-flex gap-2">
                <a href="export_csv.php?type=patients" class="btn btn-primary btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
                <a href="export_transactions_csv.php" class="btn btn-primary btn-sm"><i class="fas fa-file-csv me-1"></i>Export Transactions CSV</a>
                <a href="xml_export.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-code me-1"></i>Export XML</a>
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="card p-3 text-center">
                    <div class="text-muted">Total Patients</div>
                    <h3 class="fw-bold"><?= $stats['total_patients'] ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card p-3 text-center">
                    <div class="text-muted">Pending Appointments</div>
                    <h3 class="fw-bold"><?= $stats['pending_appointments'] ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card p-3 text-center">
                    <div class="text-muted">Cleared Patients</div>
                    <h3 class="fw-bold"><?= $stats['cleared_patients'] ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card p-3 text-center">
                    <div class="text-muted">Paid Patients</div>
                    <h3 class="fw-bold"><?= $stats['paid_patients'] ?></h3>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-bar me-2 text-primary"></i>Appointments per Doctor</div>
                    <div class="card-body chart-container">
                        <canvas id="doctorChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-pie me-2 text-primary"></i>Appointment Status</div>
                    <div class="card-body chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-line me-2 text-primary"></i>Revenue Overview</div>
                    <div class="card-body chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-area me-2 text-primary"></i>Patient Registrations</div>
                    <div class="card-body chart-container">
                        <canvas id="registrationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Patients Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users me-2 text-primary"></i>Recent Patients
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>Registered</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPatients as $p):
                            $amount_paid = (float)($p['amount_paid'] ?? 0);
                            $total_amount = (float)($p['total_amount'] ?? 0);
                            $balance = $total_amount - $amount_paid;
                            if ($total_amount == 0) {
                                $badge = 'bg-secondary';
                                $txt = 'No Charges';
                            } elseif ($balance <= 0) {
                                $badge = 'bg-success';
                                $txt = 'Paid';
                            } else {
                                $badge = 'bg-warning';
                                $txt = 'Partial';
                            }
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['fullname']) ?></strong></td>
                                <td><?= $p['age'] ?></td>
                                <td><?= htmlspecialchars($p['gender'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['contact_number'] ?? '') ?></td>
                                <td><?= date('M j, Y', strtotime($p['date_registered'])) ?></td>
                                <td><span class="badge <?= $badge ?>"><?= $txt ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Doctor chart
            const doctorCtx = document.getElementById('doctorChart');
            if (doctorCtx) {
                new Chart(doctorCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($apptByDoctor, 'doctor_name')) ?>,
                        datasets: [{
                            label: 'Appointments',
                            data: <?= json_encode(array_column($apptByDoctor, 'count')) ?>,
                            backgroundColor: ['#0EA5E9', '#2563EB', '#60A5FA', '#93C5FD'],
                            borderRadius: 8,
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
            }

            // Status chart
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                const data = <?= json_encode($paymentStatus) ?>;
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'Pending', 'Cancelled'],
                        datasets: [{
                            data: [data.paid || 0, data.partial || 0, data.unpaid || 0],
                            backgroundColor: ['#22C55E', '#F59E0B', '#EF4444'],
                            borderWidth: 0,
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            }

            // Revenue chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($revenueData['labels']) ?>,
                        datasets: [{
                            label: 'Revenue (₱)',
                            data: <?= json_encode($revenueData['values']) ?>,
                            borderColor: '#2563EB',
                            backgroundColor: 'rgba(37,99,235,0.05)',
                            fill: true,
                            tension: 0.3,
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
            }

            // Registrations chart
            const regCtx = document.getElementById('registrationChart');
            if (regCtx) {
                const regData = <?= json_encode($registrations) ?>;
                new Chart(regCtx, {
                    type: 'area',
                    data: {
                        labels: regData.map(i => i.date),
                        datasets: [{
                            label: 'Registrations',
                            data: regData.map(i => i.count),
                            borderColor: '#0EA5E9',
                            backgroundColor: 'rgba(14,165,233,0.1)',
                            fill: true,
                            tension: 0.3,
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
            }
        });
    </script>
</body>
</html>