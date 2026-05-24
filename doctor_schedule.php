<?php
require_once 'config.php';
requireLogin();

// Fetch all doctors
$doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Schedules</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Doctor Schedules & Availability</h1>
            <a href="dashboard.php" class="btn primary">← Back to Dashboard</a>
        </header>
        <main>
            <?php foreach ($doctors as $d): ?>
                <?php
                // Count today's appointments
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM appointments 
                    WHERE doctor_id = ? 
                        AND DATE(appointment_date) = CURDATE() 
                        AND status != 'Cancelled'
                ");
                $stmt->execute([$d['doctor_id']]);
                $today = $stmt->fetchColumn();
                $remaining = $d['max_patients'] - $today;
                ?>
                <div class="card">
                    <h3><?= htmlspecialchars($d['doctor_name']) ?>
                        <span class="specialization"><?= htmlspecialchars($d['specialization']) ?></span>
                    </h3>
                    <p><strong>Schedule:</strong> <?= htmlspecialchars($d['schedule']) ?></p>
                    <p><strong>Max patients/day:</strong> <?= (int)$d['max_patients'] ?></p>
                    <p>
                        <strong>Today's appointments:</strong> <?= (int)$today ?> |
                        <strong>Remaining slots:</strong> <?= max(0, $remaining) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </main>
    </div>
</body>
</html>