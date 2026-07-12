<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$medicineService = new MedicineService();
$patientService = new PatientService();

// Fetch medicine records using service
$medicines = $medicineService->getAllWithPatient();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines</title>
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
        .btn-success { border-radius: 2rem; }
        .btn-danger { border-radius: 2rem; }
        .table { font-size: 0.9rem; }
        .table th { font-weight: 600; color: #4a6f8c; border-bottom: 2px solid #e2e8f0; }
        .badge-status { padding: 0.35rem 0.75rem; border-radius: 2rem; font-weight: 500; }
        .badge-status.taken { background: #e0f2e9; color: #1e6f3f; }
        .badge-status.not-taken { background: #f0f0f0; color: #5b7f9c; }
        .btn-sm { border-radius: 2rem; padding: 0.2rem 0.8rem; }
        .medicine-name { font-weight: 500; }
        .dosage-info { font-size: 0.85rem; color: #5b7f9c; }
        @media (max-width: 768px) { .table-responsive { font-size: 0.8rem; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-pills me-2"></i>Medicines</h1>
                <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
            </div>

            <!-- Assign Medicine Form -->
            <div class="card">
                <div class="card-header"><i class="fas fa-plus-circle me-2"></i>Assign Medicine</div>
                <div class="card-body">
                    <form action="medicine_process.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Patient ID</label>
                                <input type="number" name="patient_id" id="med_patient_id" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Patient Name</label>
                                <input type="text" id="med_patient_name" class="form-control" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Medicine Name</label>
                                <input type="text" name="medicine_name" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Dosage</label>
                                <input type="text" name="dosage" class="form-control" placeholder="500mg">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Frequency</label>
                                <input type="text" name="frequency" class="form-control" placeholder="Twice daily">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration" class="form-control" placeholder="7 days">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option>Not Taken</option>
                                    <option>Taken</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Medicine</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Medicine Records Table -->
            <div class="card">
                <div class="card-header"><i class="fas fa-table me-2"></i>Medicine Records</div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Medicine</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicines as $m): ?>
                                <?php
                                $fullname = htmlspecialchars($m['fullname'] ?? '');
                                $medicine_name = htmlspecialchars($m['medicine_name'] ?? '');
                                $dosage = htmlspecialchars($m['dosage'] ?? '');
                                $frequency = htmlspecialchars($m['frequency'] ?? '');
                                $duration = htmlspecialchars($m['duration'] ?? '');
                                $status = $m['status'] ?? 'Not Taken';
                                $statusClass = strtolower(str_replace(' ', '-', $status));
                                $isTaken = ($status === 'Taken');
                                ?>
                                <tr id="row-<?= $m['medicine_id'] ?>">
                                    <td><strong><?= $fullname ?></strong></td>
                                    <td><span class="medicine-name"><?= $medicine_name ?></span></td>
                                    <td class="dosage-info"><?= $dosage ?></td>
                                    <td class="dosage-info"><?= $frequency ?></td>
                                    <td class="dosage-info"><?= $duration ?></td>
                                    <td>
                                        <span class="badge-status <?= $statusClass ?>" id="status-<?= $m['medicine_id'] ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-success" 
                                                id="btn-<?= $m['medicine_id'] ?>" 
                                                onclick="markTaken(<?= $m['medicine_id'] ?>)" 
                                                <?= $isTaken ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                                            <i class="fas fa-check me-1"></i>Mark Taken
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteMedicine(<?= $m['medicine_id'] ?>, '<?= addslashes($medicine_name) ?>')">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill patient name on ID blur
        document.getElementById('med_patient_id')?.addEventListener('blur', function() {
            const pid = this.value.trim();
            if (pid) {
                fetch(`api.php?action=patient_name&id=${pid}`)
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('med_patient_name').value = data.fullname || '';
                    })
                    .catch(() => {});
            } else {
                document.getElementById('med_patient_name').value = '';
            }
        });

        function deleteMedicine(id, name) {
            if (!confirm(`Delete medicine "${name}"? This cannot be undone.`)) return;
            fetch(`delete_medicine.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('row-' + id).remove();
                    } else {
                        alert('Failed: ' + data.message);
                    }
                })
                .catch(() => alert('Network error.'));
        }

        function markTaken(id) {
            if (!confirm('Mark this medicine as Taken?')) return;
            fetch(`update_medicine_status.php?id=${id}&status=Taken`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const span = document.getElementById('status-' + id);
                    span.textContent = 'Taken';
                    span.className = 'badge-status taken';
                    const btn = document.getElementById('btn-' + id);
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                } else {
                    alert('Failed to update status.');
                }
            })
            .catch(() => alert('Network error.'));
        }
    </script>
</body>
</html>