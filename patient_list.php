<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

// Fetch comprehensive patient list
$patientsList = $pdo->query("
    SELECT 
        p.patient_id,
        p.fullname,
        p.age,
        p.gender,
        p.date_registered,
        p.contact_number,
        (SELECT d.doctor_name 
         FROM appointments a 
         JOIN doctors d ON a.doctor_id = d.doctor_id 
         WHERE a.patient_id = p.patient_id 
           AND a.status != 'Cancelled' 
         ORDER BY a.appointment_date DESC 
         LIMIT 1) AS latest_doctor,
        (SELECT status 
         FROM laboratory 
         WHERE patient_id = p.patient_id 
         ORDER BY lab_id DESC 
         LIMIT 1) AS latest_lab_status,
        (SELECT status 
         FROM medicines 
         WHERE patient_id = p.patient_id 
         ORDER BY medicine_id DESC 
         LIMIT 1) AS latest_medicine_status,
        (SELECT 
            CASE 
                WHEN amount_paid >= total_amount THEN 'Paid'
                WHEN amount_paid > 0 THEN 'Partial'
                ELSE 'Unpaid'
            END
         FROM payments 
         WHERE patient_id = p.patient_id 
         LIMIT 1) AS payment_status
    FROM patients p
    ORDER BY p.date_registered DESC
")->fetchAll();

// Get counts for summary
$totalPatients = count($patientsList);
$withDoctor = 0;
$withLab = 0;
$withMedicine = 0;
foreach ($patientsList as $p) {
    if ($p['latest_doctor']) $withDoctor++;
    if ($p['latest_lab_status']) $withLab++;
    if ($p['latest_medicine_status']) $withMedicine++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: none; margin-bottom: 1.5rem; }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .badge-status { padding: 0.35rem 0.75rem; border-radius: 2rem; font-weight: 500; }
        .badge-status.paid { background: #e0f2e9; color: #1e6f3f; }
        .badge-status.unpaid { background: #fee2e2; color: #b91c1c; }
        .badge-status.partial { background: #fff3e0; color: #c26b1a; }
        .badge-status.taken { background: #e0f2e9; color: #1e6f3f; }
        .badge-status.not-taken { background: #f0f0f0; color: #5b7f9c; }
        .badge-status.completed { background: #28a745; color: white; }
        .badge-status.ongoing { background: #ffc107; color: #212529; }
        .badge-status.not-yet-taken { background: #dc3545; color: white; }
        .btn-sm { border-radius: 2rem; padding: 0.2rem 0.8rem; }
        .table { font-size: 0.9rem; }
        .table th { font-weight: 600; color: #4a6f8c; border-bottom: 2px solid #e2e8f0; }
        .summary-box { background: white; border-radius: 1rem; padding: 1rem; text-align: center; }
        .summary-box .number { font-size: 1.8rem; font-weight: 700; color: #1e4a6e; }
        @media (max-width: 768px) { .table-responsive { font-size: 0.8rem; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-users me-2"></i>Patient List</h1>
                <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
            </div>

            <!-- Summary Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="summary-box">
                        <div class="number"><?= $totalPatients ?></div>
                        <div class="text-muted">Total Patients</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-box">
                        <div class="number"><?= $withDoctor ?></div>
                        <div class="text-muted">Have Doctor Assigned</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-box">
                        <div class="number"><?= $withLab ?></div>
                        <div class="text-muted">Have Lab Records</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-box">
                        <div class="number"><?= $withMedicine ?></div>
                        <div class="text-muted">Have Medicines</div>
                    </div>
                </div>
            </div>

            <!-- Full Patient List -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>All Patients
                    <span class="badge bg-primary ms-2"><?= $totalPatients ?> records</span>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle" id="patientTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Contact</th>
                                <th>Registered</th>
                                <th>Doctor</th>
                                <th>Lab Status</th>
                                <th>Medicine</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patientsList as $row):
                                // Payment status badge
                                $payment = $row['payment_status'] ?? 'Unpaid';
                                $paymentBadge = strtolower($payment);
                                
                                // Lab status badge
                                $labStatus = $row['latest_lab_status'] ?? 'Not Yet Taken';
                                $labBadgeClass = strtolower(str_replace(' ', '-', $labStatus));
                                $labBadgeClass = in_array($labBadgeClass, ['completed', 'ongoing', 'not-yet-taken']) ? $labBadgeClass : 'not-yet-taken';
                                
                                // Medicine status badge
                                $medStatus = $row['latest_medicine_status'] ?? 'Not Taken';
                                $medBadgeClass = strtolower(str_replace(' ', '-', $medStatus));
                                $medBadgeClass = in_array($medBadgeClass, ['taken', 'not-taken']) ? $medBadgeClass : 'not-taken';
                            ?>
                            <tr>
                                <td><?= $row['patient_id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                                <td><?= $row['age'] ?></td>
                                <td><?= htmlspecialchars($row['gender'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['contact_number'] ?? '') ?></td>
                                <td><?= date('M j, Y', strtotime($row['date_registered'])) ?></td>
                                <td><?= htmlspecialchars($row['latest_doctor'] ?? '—') ?></td>
                                <td>
                                    <span class="badge-status <?= $labBadgeClass ?>">
                                        <?= htmlspecialchars($labStatus) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?= $medBadgeClass ?>">
                                        <?= htmlspecialchars($medStatus) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?= $paymentBadge ?>">
                                        <?= $payment ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Removed "View" button – only Edit and Archive -->
                                    <a href="edit_patient.php?id=<?= $row['patient_id'] ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-warning" onclick="archivePatient(<?= $row['patient_id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-archive"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function archivePatient(id, name) {
            if (!confirm(`Archive patient "${name}"?\n\nThe patient will be hidden but all records will be preserved.\n\nYou can restore them later.`)) return;
            fetch(`delete_patient.php?patient_id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Patient archived successfully.');
                        location.reload();
                    } else {
                        alert('Archive failed: ' + data.message);
                    }
                })
                .catch(() => alert('Network error.'));
        }
    </script>
</body>
</html>