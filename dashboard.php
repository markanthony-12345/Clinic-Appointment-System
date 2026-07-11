<?php
require_once 'config.php';
requireLogin();

$user = $_SESSION['user_logged'];
$reportService = new ReportService();
$patientService = new PatientService();

$stats = $reportService->getDashboardStats();
$recentPatients = $patientService->getAll(10);
$doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();

// Data for charts
$apptByDoctor = $reportService->getAppointmentsByDoctor(30);
$paymentStatus = $reportService->getPaymentStatusBreakdown();
$labStatus = $reportService->getLabStatusBreakdown();
$registrations = $reportService->getPatientRegistrations(7);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f0f4f8;
        }
        .stat-card {
            border-radius: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: none;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e4a6e;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: none;
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eef2f6;
            border-radius: 1rem 1rem 0 0 !important;
            font-weight: 600;
            color: #1e4a6e;
            padding: 1rem 1.25rem;
        }
        .btn-primary {
            background: #1e6f9f;
            border-color: #1e6f9f;
            border-radius: 2rem;
        }
        .btn-primary:hover {
            background: #155d85;
            border-color: #155d85;
        }
        .btn-sm {
            border-radius: 2rem;
            padding: 0.2rem 0.8rem;
        }
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-weight: 500;
        }
        .badge-status.paid {
            background: #e0f2e9;
            color: #1e6f3f;
        }
        .badge-status.unpaid {
            background: #fee2e2;
            color: #b91c1c;
        }
        .badge-status.partial {
            background: #fff3e0;
            color: #c26b1a;
        }
        .badge-status.archived {
            background: #e8e8e8;
            color: #666;
        }
        .quick-actions .btn {
            border-radius: 2rem;
        }
        @media (max-width: 768px) {
            .stat-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Header -->
    <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <span class="fs-5">Welcome, <?= htmlspecialchars($user['fullname']) ?></span>
            <span class="badge bg-primary ms-2"><?= $user['role'] ?></span>
        </div>
        <nav class="d-flex flex-wrap gap-2">
            <a href="dashboard.php" class="btn btn-primary active">Dashboard</a>
            <a href="reports.php" class="btn btn-outline-primary">Reports</a>
            <a href="doctor_schedule.php" class="btn btn-outline-primary">Schedules</a>
            <a href="laboratory.php" class="btn btn-outline-primary">Lab</a>
            <a href="medicine.php" class="btn btn-outline-primary">Medicines</a>
            <a href="archive_patients.php" class="btn btn-outline-secondary"><i class="fas fa-archive"></i> Archived</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </nav>
    </header>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card text-center h-100">
                <div class="card-body">
                    <div class="stat-icon text-primary"><i class="fas fa-users"></i></div>
                    <h5 class="card-title">Total Patients</h5>
                    <p class="stat-number"><?= $stats['total_patients'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-center h-100">
                <div class="card-body">
                    <div class="stat-icon text-warning"><i class="fas fa-calendar-times"></i></div>
                    <h5 class="card-title">Pending Appointments</h5>
                    <p class="stat-number"><?= $stats['pending_appointments'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-center h-100">
                <div class="card-body">
                    <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                    <h5 class="card-title">Cleared Patients</h5>
                    <p class="stat-number"><?= $stats['cleared_patients'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-center h-100">
                <div class="card-body">
                    <div class="stat-icon text-info"><i class="fas fa-dollar-sign"></i></div>
                    <h5 class="card-title">Paid Patients</h5>
                    <p class="stat-number"><?= $stats['paid_patients'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions d-flex gap-2 mb-4 flex-wrap">
        <a href="#patient-form" class="btn btn-primary" data-bs-toggle="modal"><i class="fas fa-user-plus me-1"></i>Register Patient</a>
        <a href="#appointment-form" class="btn btn-primary" data-bs-toggle="modal"><i class="fas fa-calendar-plus me-1"></i>New Appointment</a>
        <a href="archive_patients.php" class="btn btn-secondary"><i class="fas fa-archive me-1"></i>View Archived</a>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Appointments per Doctor (30 days)</div>
                <div class="card-body">
                    <canvas id="doctorChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Payment Status</div>
                <div class="card-body">
                    <canvas id="paymentChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-chart-doughnut me-2"></i>Lab Status</div>
                <div class="card-body">
                    <canvas id="labChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-chart-line me-2"></i>Patient Registrations (7 days)</div>
                <div class="card-body">
                    <canvas id="registrationChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Patients -->
    <div class="card">
        <div class="card-header"><i class="fas fa-table me-2"></i>Recent Patients</div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Sex</th>
                        <th>Registered</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentPatients as $row):
                    $amount_paid = (float)($row['amount_paid'] ?? 0);
                    $total_amount = (float)($row['total_amount'] ?? 0);
                    
                    if ($total_amount > 0 && $amount_paid >= $total_amount) {
                        $sc = 'paid';
                        $st = 'Paid';
                    } elseif ($amount_paid > 0 && $amount_paid < $total_amount) {
                        $sc = 'partial';
                        $st = 'Partial';
                    } else {
                        $sc = 'unpaid';
                        $st = 'Unpaid';
                    }
                ?>
                <tr id="patient-row-<?= $row['patient_id'] ?>">
                    <td><?= $row['patient_id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                    <td><?= $row['age'] ?></td>
                    <td><?= htmlspecialchars($row['sex'] ?? '') ?></td>
                    <td><?= date('M j, Y', strtotime($row['date_registered'])) ?></td>
                    <td><span class="badge-status <?= $sc ?>"><?= $st ?></span></td>
                    <td>
                        <a href="patient_overview.php?patient_id=<?= $row['patient_id'] ?>" class="btn btn-sm btn-primary">View</a>
                        <a href="edit_patient.php?id=<?= $row['patient_id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <button class="btn btn-sm btn-warning" onclick="archivePatient(<?= $row['patient_id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')">
                            <i class="fas fa-archive"></i> Archive
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Register Patient Modal -->
<div class="modal fade" id="patient-form" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Register Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="patient_register.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sex</label>
                            <select name="sex" class="form-select" required>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Register</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Modal -->
<div class="modal fade" id="appointment-form" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="appointment_process.php" method="POST" id="apptForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="mb-3">
                        <label class="form-label">Patient ID</label>
                        <input type="number" name="patient_id" id="patient_id_input" class="form-control" required>
                        <div id="patient_name_display" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Doctor</label>
                        <select name="doctor_id" id="doctor_select" class="form-select" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Appointment Date</label>
                        <input type="date" name="appointment_date" id="appt_date" class="form-control" required>
                        <div id="availability_msg" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Appointment Time</label>
                        <select name="appointment_time" id="appt_time" class="form-select" required disabled>
                            <option value="">First select a date</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lab Required?</label>
                        <select name="laboratory_required" class="form-select">
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" id="bookBtn" disabled><i class="fas fa-save me-1"></i>Book Appointment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Chart.js data
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
    data: {
        labels: ['Paid', 'Partial', 'Unpaid'],
        datasets: [{ data: [paymentData.paid, paymentData.partial, paymentData.unpaid], backgroundColor: ['#28a745', '#ffc107', '#dc3545'] }]
    }
});

const labStatus = <?= json_encode($labStatus) ?>;
new Chart(document.getElementById('labChart'), {
    type: 'doughnut',
    data: {
        labels: labStatus.map(i => i.status),
        datasets: [{ data: labStatus.map(i => i.count), backgroundColor: ['#17a2b8', '#ffc107', '#28a745'] }]
    }
});

const regData = <?= json_encode($registrations) ?>;
new Chart(document.getElementById('registrationChart'), {
    type: 'line',
    data: {
        labels: regData.map(i => i.date),
        datasets: [{ label: 'Registrations', data: regData.map(i => i.count), borderColor: '#007bff', fill: false }]
    },
    options: { responsive: true }
});

// Patient name fetch
document.getElementById('patient_id_input')?.addEventListener('blur', function() {
    fetch(`api.php?action=patient_name&id=${this.value}`)
        .then(res => res.json())
        .then(data => document.getElementById('patient_name_display').innerHTML = data.fullname ? `👤 ${data.fullname}` : '');
});

// Availability check
const doctorSelect = document.getElementById('doctor_select');
const dateInput = document.getElementById('appt_date');
const timeSelect = document.getElementById('appt_time');
const msgDiv = document.getElementById('availability_msg');

function checkAvailability() {
    const doctorId = doctorSelect.value;
    const date = dateInput.value;
    if (!doctorId || !date) {
        msgDiv.innerHTML = '';
        timeSelect.innerHTML = '<option value="">Select doctor and date first</option>';
        timeSelect.disabled = true;
        return;
    }
    msgDiv.innerHTML = '<span class="text-secondary">⏳ Checking...</span>';
    timeSelect.innerHTML = '<option value="">Loading...</option>';
    timeSelect.disabled = true;
    fetch(`api.php?action=availability&doctor_id=${doctorId}&date=${date}`)
        .then(res => res.json())
        .then(data => {
            if (data.available) {
                msgDiv.innerHTML = `<span class="text-success">✅ ${data.remaining} slot(s) available</span>`;
                return fetch(`api.php?action=time_slots&doctor_id=${doctorId}&date=${date}`);
            } else {
                msgDiv.innerHTML = `<span class="text-danger">❌ ${data.reason || 'No slots'}</span>`;
                timeSelect.innerHTML = '<option value="">No available times</option>';
                timeSelect.disabled = true;
                throw new Error('No slots');
            }
        })
        .then(res => res.json())
        .then(slots => {
            if (!slots.length) {
                timeSelect.innerHTML = '<option value="">No available times</option>';
                timeSelect.disabled = true;
            } else {
                let html = '<option value="">Select time</option>';
                slots.forEach(s => { html += `<option value="${s}">${s}</option>`; });
                timeSelect.innerHTML = html;
                timeSelect.disabled = false;
            }
        })
        .catch(err => console.log(err));
}

doctorSelect?.addEventListener('change', checkAvailability);
dateInput?.addEventListener('change', checkAvailability);

function checkBookButton() {
    const patientId = document.getElementById('patient_id_input').value;
    const doctor = doctorSelect.value;
    const date = dateInput.value;
    const time = timeSelect.value;
    document.getElementById('bookBtn').disabled = !(patientId && doctor && date && time && time !== '');
}
document.getElementById('patient_id_input')?.addEventListener('input', checkBookButton);
doctorSelect?.addEventListener('change', checkBookButton);
dateInput?.addEventListener('change', checkBookButton);
timeSelect?.addEventListener('change', checkBookButton);

// Archive patient function
function archivePatient(id, name) {
    if (!confirm(`Archive patient "${name}"?\n\nThe patient will be hidden but all records will be preserved.\n\nYou can restore them later.`)) return;
    fetch(`delete_patient.php?patient_id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('patient-row-' + id)?.remove();
                alert('Patient archived successfully.');
                location.reload();
            } else {
                alert('Archive failed: ' + data.message);
            }
        })
        .catch(() => alert('Network error.'));
}

function deletePatient(id, name) {
    if (!confirm(`DELETE patient "${name}"? This cannot be undone!`)) return;
    fetch(`delete_patient.php?patient_id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('patient-row-' + id)?.remove();
                alert('Deleted.');
            } else alert('Delete failed: ' + data.message);
        });
}
</script>
</body>
</html>