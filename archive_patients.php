<?php
require_once 'config.php';
requireLogin();

$patientService = new PatientService();
$archivedPatients = $patientService->getArchived();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Patients</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: none; }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .btn-sm { border-radius: 2rem; padding: 0.2rem 0.8rem; }
    </style>
</head>
<body>
<div class="container py-4">
    <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <h1 class="h3"><i class="fas fa-archive me-2"></i>Archived Patients</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
    </header>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <?php if (empty($archivedPatients)): ?>
        <div class="alert alert-info">No archived patients found.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-users me-2"></i>Archived Patient Records</div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Sex</th>
                            <th>Contact</th>
                            <th>Archived Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archivedPatients as $p): ?>
                            <tr id="row-<?= $p['patient_id'] ?>">
                                <td><?= $p['patient_id'] ?></td>
                                <td><strong><?= htmlspecialchars($p['fullname']) ?></strong></td>
                                <td><?= $p['age'] ?></td>
                                <td><?= htmlspecialchars($p['sex'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['contact_number'] ?? '') ?></td>
                                <td><?= date('M j, Y g:i A', strtotime($p['date_registered'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="restorePatient(<?= $p['patient_id'] ?>)">
                                        <i class="fas fa-undo me-1"></i>Restore
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="permanentDelete(<?= $p['patient_id'] ?>, '<?= htmlspecialchars($p['fullname'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-trash me-1"></i>Delete Forever
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function restorePatient(id) {
    if (!confirm('Restore this patient? They will appear in the main dashboard.')) return;
    fetch(`restore_patient.php?patient_id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('row-' + id).remove();
                alert('Patient restored successfully.');
                location.reload();
            } else {
                alert('Restore failed: ' + data.message);
            }
        })
        .catch(() => alert('Network error.'));
}

function permanentDelete(id, name) {
    if (!confirm(`⚠️ PERMANENTLY DELETE "${name}"?\n\nThis will remove ALL records forever.\n\nThis CANNOT be undone!`)) return;
    if (!confirm('Are you absolutely sure?')) return;
    fetch(`permanent_delete.php?patient_id=${id}&confirm=yes`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('row-' + id).remove();
                alert('Patient permanently deleted.');
                if (document.querySelectorAll('#row-' + id).length === 0) {
                    location.reload();
                }
            } else {
                alert('Delete failed: ' + data.message);
            }
        })
        .catch(() => alert('Network error.'));
}
</script>
</body>
</html>