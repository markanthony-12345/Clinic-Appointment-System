<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

// Fetch lab records with patient and doctor names
$labRecords = $pdo->query("
    SELECT l.*, p.fullname, d.doctor_name 
    FROM laboratory l
    LEFT JOIN patients p ON l.patient_id = p.patient_id
    LEFT JOIN doctors d ON l.doctor_id = d.doctor_id
    ORDER BY l.lab_id DESC
")->fetchAll();

// Fetch doctors for dropdown
$doctors = $pdo->query("SELECT doctor_id, doctor_name, specialization FROM doctors ORDER BY doctor_name")->fetchAll();
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
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: none; margin-bottom: 1.5rem; }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .form-label { font-weight: 500; color: #2c5f8a; font-size: 0.9rem; }
        .btn-primary { background: linear-gradient(135deg, #1e6f9f, #155d85); border: none; border-radius: 2rem; }
        .btn-primary:hover { background: linear-gradient(135deg, #155d85, #0f4a6e); }
        .btn-sm { border-radius: 2rem; padding: 0.2rem 0.8rem; }
        .status-badge {
            display: inline-block;
            padding: 0.35rem 1.2rem;
            border-radius: 2rem;
            font-weight: 500;
            font-size: 0.85rem;
            min-width: 120px;
            text-align: center;
            color: white;
        }
        .status-badge.completed { background: #28a745; }
        .status-badge.ongoing { background: #ffc107; color: #212529; }
        .status-badge.not-yet-taken { background: #dc3545; }
        .table { font-size: 0.9rem; }
        .table th { font-weight: 600; color: #4a6f8c; border-bottom: 2px solid #e2e8f0; }
        .result-text { max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; }
        @media (max-width: 768px) { .table-responsive { font-size: 0.8rem; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-vial me-2"></i>Laboratory</h1>
                <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
            </div>

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
                                <label class="form-label">Doctor</label>
                                <select name="doctor_id" class="form-select">
                                    <option value="">Select Doctor (optional)</option>
                                    <?php foreach ($doctors as $d): ?>
                                        <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
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
                                <th>Doctor</th>
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
                                $fullname = htmlspecialchars($row['fullname'] ?? '');
                                $doctor_name = htmlspecialchars($row['doctor_name'] ?? '—');
                                $lab_type = htmlspecialchars($row['laboratory_type'] ?? '');
                                $result = htmlspecialchars($row['result'] ?? '');
                                $status = $row['status'] ?? 'Not Yet Taken';
                                $statusClass = strtolower(str_replace(' ', '-', $status));
                                $lab_id = $row['lab_id'];
                                ?>
                                <tr>
                                    <td><?= $lab_id ?></td>
                                    <td><?= $fullname ?></td>
                                    <td><?= $doctor_name ?></td>
                                    <td><?= $lab_type ?></td>
                                    <td>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td><span class="result-text" title="<?= $result ?>"><?= $result ?: '—' ?></span></td>
                                    <td><?= $row['created_at'] ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="editLab(<?= $lab_id ?>)"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteLab(<?= $lab_id ?>)"><i class="fas fa-trash"></i></button>
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
        // Auto-fill patient name
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