<?php
require_once 'config.php';
requireAdmin();

$patient_id = (int)($_GET['id'] ?? 0);
if (!$patient_id) {
    header("Location: dashboard.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    header("Location: dashboard.php?error=not_found");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; border: none; }
        .form-label { font-weight: 500; color: #2c5f8a; }
        .btn-primary { background: #1e6f9f; border-color: #1e6f9f; border-radius: 2rem; }
        .btn-primary:hover { background: #155d85; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container py-4">
            <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-user-edit me-2"></i>Edit Patient</h1>
                <a href="dashboard.php" class="btn btn-outline-primary">← Back</a>
            </header>
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Patient ID: #<?= $patient['patient_id'] ?></h5>
                            <form action="update_patient.php" method="POST">
                                <input type="hidden" name="patient_id" value="<?= $patient['patient_id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="fullname" class="form-control" required pattern="[A-Za-z\s\-]+" value="<?= htmlspecialchars($patient['fullname']) ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Age *</label>
                                        <input type="number" name="age" class="form-control" required min="0" max="150" value="<?= $patient['age'] ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender *</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="Male" <?= ($patient['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($patient['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= ($patient['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address *</label>
                                    <input type="text" name="address" class="form-control" required value="<?= htmlspecialchars($patient['address']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Number *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+63</span>
                                        <input type="tel" name="contact_number" class="form-control" required pattern="[0-9]{11}" value="<?= htmlspecialchars($patient['contact_number']) ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Civil Status</label>
                                    <select name="civil_status" class="form-select">
                                        <option value="">Select...</option>
                                        <option value="Single" <?= ($patient['civil_status'] ?? '') == 'Single' ? 'selected' : '' ?>>Single</option>
                                        <option value="Married" <?= ($patient['civil_status'] ?? '') == 'Married' ? 'selected' : '' ?>>Married</option>
                                        <option value="Divorced" <?= ($patient['civil_status'] ?? '') == 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                                        <option value="Widowed" <?= ($patient['civil_status'] ?? '') == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Citizenship</label>
                                    <input type="text" name="citizenship" class="form-control" value="<?= htmlspecialchars($patient['citizenship'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Place of Birth</label>
                                    <input type="text" name="place_of_birth" class="form-control" value="<?= htmlspecialchars($patient['place_of_birth'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>