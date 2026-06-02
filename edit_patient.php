<?php
require_once 'config.php';
requireAdmin(); // Admin only ang pwedeng mag-edit ng patient

$patient_id = (int)($_GET['id'] ?? 0);

if (!$patient_id) {
    header("Location: dashboard.php");
    exit;
}

// Kunin ang current data ng patient
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
    <title>Edit Patient</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <header>
        <h1><i class="fas fa-user-edit"></i> Edit Patient</h1>
        <a href="dashboard.php" class="btn primary">← Back to Dashboard</a>
    </header>
    <main>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert error">❌ Something went wrong. Please try again.</div>
        <?php endif; ?>

        <div class="card" style="max-width: 600px;">
            <h3>Patient ID: #<?= $patient['patient_id'] ?></h3>

            <form action="update_patient.php" method="POST">
                <!-- Hidden field para malaman ng update_patient.php kung sino ang ine-edit -->
                <input type="hidden" name="patient_id" value="<?= $patient['patient_id'] ?>">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname"
                           value="<?= htmlspecialchars($patient['fullname']) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" min="1" max="150"
                               value="<?= $patient['age'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="Male"   <?= $patient['gender'] === 'Male'   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other"  <?= $patient['gender'] === 'Other'  ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address"
                           value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number"
                           value="<?= htmlspecialchars($patient['contact_number'] ?? '') ?>">
                </div>

                <button type="submit" class="btn primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="dashboard.php" class="btn" style="margin-left: 8px;">Cancel</a>
            </form>
        </div>
    </main>
</div>
</body>
</html>