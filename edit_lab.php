<?php
require_once 'config.php';
requireLogin();

$id = $_GET['id'] ?? 0;
if (!$id) {
    header("Location: laboratory.php?error=Invalid ID");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM laboratory WHERE lab_id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("Record not found.");
}

// Get patient name for display
$patientName = '';
if (!empty($data['patient_id'])) {
    $pStmt = $pdo->prepare("SELECT fullname FROM patients WHERE patient_id = ?");
    $pStmt->execute([$data['patient_id']]);
    $patientName = $pStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lab Record</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1rem 1rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .form-label { font-weight: 500; color: #2c5f8a; }
        .btn-primary { background: #1e6f9f; border-color: #1e6f9f; border-radius: 2rem; }
        .btn-primary:hover { background: #155d85; }
        .btn-secondary { border-radius: 2rem; }
        .form-text { font-size: 0.8rem; color: #6c757d; }
    </style>
</head>
<body>
<div class="container py-4">
    <!-- Header -->
    <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <h1 class="h3"><i class="fas fa-vial me-2"></i>Edit Lab Record</h1>
        <a href="laboratory.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Laboratory</a>
    </header>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <i class="fas fa-edit me-2"></i>Lab Record #<?= $data['lab_id'] ?>
                    <?php if ($patientName): ?>
                        <span class="badge bg-info ms-2"><?= htmlspecialchars($patientName) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form action="update_lab.php" method="POST">
                        <input type="hidden" name="lab_id" value="<?= $data['lab_id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Patient ID</label>
                            <input type="number" name="patient_id" class="form-control" value="<?= $data['patient_id'] ?>" required>
                            <div class="form-text">Enter the patient's ID number.</div>
                        </div>

                        <?php if ($patientName): ?>
                            <div class="mb-3">
                                <label class="form-label">Patient Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($patientName) ?>" disabled>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Test Type</label>
                            <select name="laboratory_type" class="form-select" required>
                                <?php
                                $types = ['X-ray', 'Ultrasound', 'CBC', 'Urinalysis', 'Blood Chemistry', 'ECG'];
                                foreach ($types as $t):
                                    $selected = ($data['laboratory_type'] == $t) ? 'selected' : '';
                                ?>
                                    <option <?= $selected ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <?php
                                $statuses = ['Not Yet Taken', 'Ongoing', 'Completed'];
                                foreach ($statuses as $s):
                                    $selected = ($data['status'] == $s) ? 'selected' : '';
                                ?>
                                    <option <?= $selected ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Result</label>
                            <textarea name="result" class="form-control" rows="4"><?= htmlspecialchars($data['result'] ?? '') ?></textarea>
                            <div class="form-text">Enter the lab test result (optional).</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update</button>
                            <a href="laboratory.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>