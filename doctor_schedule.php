<?php
require_once 'config.php';
requireLogin();
if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$is_admin = true;
$message = '';

// Handle adding new doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $doctor_name = sanitize($_POST['doctor_name']);
    $specialization = sanitize($_POST['specialization']);
    $schedule = sanitize($_POST['schedule']);
    $max_patients = (int)$_POST['max_patients'];

    if ($doctor_name && $max_patients > 0) {
        $stmt = $pdo->prepare("INSERT INTO doctors (doctor_name, specialization, schedule, max_patients) VALUES (?, ?, ?, ?)");
        $stmt->execute([$doctor_name, $specialization, $schedule, $max_patients]);
        $message = '<div class="alert success">Doctor added successfully!</div>';
    } else {
        $message = '<div class="alert error">Please fill all required fields.</div>';
    }
}

// Get selected date (default = today)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Schedules & Appointments</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .appointment-list { margin-top: 10px; font-size: 0.9rem; }
        .appointment-list ul { margin: 5px 0 0 20px; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>Doctor Schedules & Appointments</h1>
        <a href="dashboard.php" class="btn primary">← Back to Dashboard</a>
    </header>
    <main>
        <?php echo $message; ?>

        <!-- Date picker -->
        <div class="card">
            <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
                <div class="form-group">
                    <label>View appointments for date:</label>
                    <input type="date" name="date" value="<?= $selected_date ?>" required>
                </div>
                <button type="submit" class="btn primary">Show</button>
            </form>
        </div>

        <?php foreach ($doctors as $d):
            // Count appointments for the selected date
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM appointments 
                WHERE doctor_id = ? 
                    AND DATE(appointment_date) = ? 
                    AND status != 'Cancelled'
            ");
            $stmt->execute([$d['doctor_id'], $selected_date]);
            $count = $stmt->fetchColumn();

            // Fetch actual appointment details for this date
            $stmt2 = $pdo->prepare("
                SELECT a.appointment_date, p.fullname, a.status
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ? AND a.status != 'Cancelled'
                ORDER BY a.appointment_date ASC
            ");
            $stmt2->execute([$d['doctor_id'], $selected_date]);
            $appointments = $stmt2->fetchAll();

            $max = $d['max_patients'] ?? 0;
            $remaining = ($max > 0) ? max(0, $max - $count) : 'Not set';
        ?>
            <div class="doctor-card">
                <h3><?= htmlspecialchars($d['doctor_name']) ?>
                    <span class="specialization"><?= htmlspecialchars($d['specialization']) ?></span>
                </h3>
                <p><strong>Schedule:</strong> <?= htmlspecialchars($d['schedule'] ?: 'Not specified') ?></p>
                <p><strong>Max patients/day:</strong> <?= $max ?: 'Not set' ?></p>
                <p>
                    <strong>Appointments on <?= date('M j, Y', strtotime($selected_date)) ?>:</strong> <?= $count ?> |
                    <strong>Remaining slots:</strong> <?= is_numeric($remaining) ? $remaining : 'N/A' ?>
                </p>

                <?php if (!empty($appointments)): ?>
                    <div class="appointment-list">
                        <strong>📋 Patient list:</strong>
                        <ul>
                            <?php foreach ($appointments as $app): ?>
                                <li>
                                    <?= htmlspecialchars($app['fullname']) ?> 
                                    at <?= date('g:i A', strtotime($app['appointment_date'])) ?>
                                    (<?= $app['status'] ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p><em>No appointments on this date.</em></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($is_admin): ?>
        <div class="add-doctor-form">
            <h3>➕ Add New Doctor (Admin only)</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group"><label>Doctor Name *</label><input type="text" name="doctor_name" required></div>
                    <div class="form-group"><label>Specialization</label><input type="text" name="specialization"></div>
                </div>
                <div class="form-group"><label>Schedule (e.g., Monday – Wednesday 8:00 AM – 2:00 PM)</label><input type="text" name="schedule" placeholder="Days and time range"></div>
                <div class="form-group"><label>Max Patients per Day *</label><input type="number" name="max_patients" min="1" required></div>
                <button type="submit" name="add_doctor" class="btn primary">Save Doctor</button>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>