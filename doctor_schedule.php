<?php
require_once 'config.php';
requireLogin();
if ($_SESSION["user_logged"]["role"] !== "Admin") { header("Location: dashboard.php?error=access_denied"); exit; }

$is_admin = ($_SESSION['user_logged']['role'] === 'Admin');
$message = '';

// eto naghahandle ng mag add ng new doctor (Admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $doctor_name = sanitize($_POST['doctor_name']);
    $specialization = sanitize($_POST['specialization']);
    $schedule = sanitize($_POST['schedule']);
    $max_patients = (int)$_POST['max_patients'];

    if ($doctor_name && $max_patients > 0) {
        $stmt = $pdo->prepare("INSERT INTO doctors (doctor_name, specialization, schedule, max_patients) VALUES (?, ?, ?, ?)");
        $stmt->execute([$doctor_name, $specialization, $schedule, $max_patients]);
        $message = '<div class="alert success">Doctor added successfully!</div>';
    } else {
        $message = '<div class="alert error">Please fill all required fields (name and max patients).</div>';
    }
}

// eto fineFetch lahat ng doctors
$doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Schedules & Availability</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <header>
        <h1>Doctor Schedules & Availability</h1>
        <a href="dashboard.php" class="btn primary">← Back to Dashboard</a>
    </header>
    <main>
        <?php echo $message; ?>

        <?php foreach ($doctors as $d): 
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM appointments 
                WHERE doctor_id = ? 
                    AND DATE(appointment_date) = CURDATE() 
                    AND status != 'Cancelled'
            ");
            $stmt->execute([$d['doctor_id']]);
            $today = $stmt->fetchColumn();
            $max = $d['max_patients'] ?? 0;
            $remaining = ($max > 0) ? max(0, $max - $today) : 'Not set';
        ?>
            <div class="doctor-card">
                <h3><?= htmlspecialchars($d['doctor_name']) ?>
                    <span class="specialization"><?= htmlspecialchars($d['specialization']) ?></span>
                </h3>
                <p><strong>Schedule:</strong> <?= htmlspecialchars($d['schedule'] ?: 'Not specified') ?></p>
                <p><strong>Max patients/day:</strong> <?= $max ?: 'Not set' ?></p>
                <p>
                    <strong>Today's appointments:</strong> <?= (int)$today ?> |
                    <strong>Remaining slots:</strong> <?= is_numeric($remaining) ? $remaining : 'N/A' ?>
                </p>
            </div>
        <?php endforeach; ?>

        <?php if ($is_admin): ?>
        <div class="add-doctor-form">
            <h3>➕ Add New Doctor (Admin only)</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Doctor Name *</label>
                        <input type="text" name="doctor_name" required>
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization">
                    </div>
                </div>
                <div class="form-group">
                    <label>Schedule (e.g., Monday – Wednesday 8:00 AM – 2:00 PM)</label>
                    <input type="text" name="schedule" placeholder="Days and time range">
                </div>
                <div class="form-group">
                    <label>Max Patients per Day *</label>
                    <input type="number" name="max_patients" min="1" required>
                </div>
                <button type="submit" name="add_doctor" class="btn primary">Save Doctor</button>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>