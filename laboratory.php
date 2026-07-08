<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$labService = new LabService();
$patientService = new PatientService();

// Fetch lab records using service
$labRecords = $labService->getAllWithPatient();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: #f0f4f8;
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
        .form-label {
            font-weight: 500;
            color: #2c5f8a;
            font-size: 0.9rem;
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
        .table {
            font-size: 0.9rem;
        }
        .table th {
            font-weight: 600;
            color: #4a6f8c;
            border-bottom: 2px solid #e2e8f0;
        }
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-weight: 500;
        }
        .badge-status.completed {
            background: #e0f2e9;
            color: #1e6f3f;
        }
        .badge-status.ongoing {
            background: #fff3e0;
            color: #c26b1a;
        }
        .badge-status.not-yet-taken {
            background: #f0f0f0;
            color: #5b7f9c;
        }
        .btn-sm {
            border-radius: 2rem;
            padding: 0.2rem 0.8rem;
        }
        .result-text {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Header -->
    <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <h1 class="h3"><i class="fas fa-vial me-2"></i>Laboratory</h1>
        <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </header>

    <!-- Assign Lab Test Form -->
    <div class="card">
        <div class="card-header"><i class="fas fa-plus-circle me-2"></i>Assign Lab Test</div>
        <div class="card-body">
            <form action="lab_process.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Patient ID</label>
                        <input type="number" name="patient_id" id="lab_patient_id" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Patient Name</label>
                        <input type="text" id="lab_patient_name" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Test Type</label>
                        <select name="laboratory_type" class="form-select" required>
                            <option>X-ray</option>
                            <option>Ultrasound</option>
                            <option>CBC</option>
                            <option>Urinalysis</option>
                            <option>Blood Chemistry</option>
                            <option>ECG</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option>Not Yet Taken</option>
                            <option>Ongoing</option>
                            <option>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Results</label>
                        <textarea name="result" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Lab Test</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lab Records Table -->
    <div class="card">
        <div class="card-header"><i class="fas fa-table me-2"></i>Lab Records</div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Status</th>
                        <th>Result</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($labRecords as $row): ?>
                        <?php
                        // Fix: handle null values for htmlspecialchars
                        $fullname = htmlspecialchars($row['fullname'] ?? '');
                        $lab_type = htmlspecialchars($row['laboratory_type'] ?? '');
                        $result = htmlspecialchars($row['result'] ?? '');
                        $status = $row['status'] ?? 'Not Yet Taken';
                        $statusClass = strtolower(str_replace(' ', '-', $status));
                        ?>
                        <tr>
                            <td><?= $row['lab_id'] ?></td>
                            <td><?= $fullname ?></td>
                            <td><?= $lab_type ?></td>
                            <td><span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td><span class="result-text" title="<?= $result ?>"><?= $result ?: '—' ?></span></td>
                            <td><?= $row['created_at'] ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="editLab(<?= $row['lab_id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteLab(<?= $row['lab_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bootstrap & Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-fill patient name on ID blur
    document.getElementById('lab_patient_id')?.addEventListener('blur', function() {
        const pid = this.value.trim();
        if (pid) {
            fetch(`api.php?action=patient_name&id=${pid}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('lab_patient_name').value = data.fullname || '';
                })
                .catch(() => {});
        } else {
            document.getElementById('lab_patient_name').value = '';
        }
    });

    function editLab(id) {
        window.location.href = `edit_lab.php?id=${id}`;
    }

    function deleteLab(id) {
        if (confirm('Delete this lab record? This cannot be undone.')) {
            window.location.href = `delete_lab.php?id=${id}`;
        }
    }
</script>
</body>
</html>