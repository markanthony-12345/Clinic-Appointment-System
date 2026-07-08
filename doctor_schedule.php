<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$message = '';
$apptService = new AppointmentService();
$patientService = new PatientService();

// Handle adding new doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $doctor_name = sanitize($_POST['doctor_name']);
    $specialization = sanitize($_POST['specialization']);
    $schedule = sanitize($_POST['schedule']);
    $max_patients = (int)$_POST['max_patients'];

    if ($doctor_name && $max_patients > 0) {
        $stmt = $pdo->prepare("INSERT INTO doctors (doctor_name, specialization, schedule, max_patients) VALUES (?, ?, ?, ?)");
        $stmt->execute([$doctor_name, $specialization, $schedule, $max_patients]);
        $message = '<div class="alert alert-success">Doctor added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Please fill all required fields.</div>';
    }
}

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$doctors = $pdo->query("SELECT * FROM doctors ORDER BY doctor_id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Schedules & Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: #f0f4f8;
        }
        .schedule-card {
            border-left: 4px solid #1e6f9f;
            transition: transform 0.2s;
        }
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .doctor-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a2c3e;
        }
        .specialization-badge {
            font-size: 0.75rem;
            background: #eef2f8;
            color: #2c5f8a;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
        }
        .stat-label {
            font-weight: 500;
            color: #4a6f8c;
        }
        .stat-value {
            font-weight: 700;
            color: #1e4a6e;
        }
        .appointment-list {
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid #e2e8f0;
        }
        .appointment-list ul {
            list-style: none;
            padding-left: 0;
        }
        .appointment-list li {
            padding: 0.3rem 0;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }
        .appointment-list li:last-child {
            border-bottom: none;
        }
        .add-doctor-form {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .add-doctor-form h4 {
            margin-bottom: 1rem;
            color: #1e4a6e;
        }
        .date-picker-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .date-picker-card label {
            font-weight: 500;
            color: #2c5f8a;
            margin-right: 0.5rem;
        }
        .date-picker-card input[type="date"] {
            border: 1px solid #cddae9;
            border-radius: 0.5rem;
            padding: 0.4rem 0.8rem;
        }
        .date-picker-card .btn {
            border-radius: 2rem;
        }
        @media (max-width: 576px) {
            .date-picker-card {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Header -->
    <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <h1 class="h3"><i class="fas fa-calendar-check me-2"></i>Doctor Schedules & Appointments</h1>
        <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </header>

    <!-- Messages -->
    <?= $message ?>

    <!-- Date Picker -->
    <div class="date-picker-card">
        <div>
            <label for="datePicker"><i class="fas fa-calendar-day me-1"></i>View appointments for:</label>
            <input type="date" id="datePicker" name="date" value="<?= $selected_date ?>" 
                   onchange="this.form.submit()" class="form-control d-inline-block" style="width:auto; display:inline-block;">
        </div>
        <span class="text-muted small">Showing schedule for <strong><?= date('F j, Y', strtotime($selected_date)) ?></strong></span>
    </div>

    <!-- Doctor Cards -->
    <?php foreach ($doctors as $d): 
        // Count appointments for selected date
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE doctor_id = ? 
                AND DATE(appointment_date) = ? 
                AND status != 'Cancelled'
        ");
        $stmt->execute([$d['doctor_id'], $selected_date]);
        $count = $stmt->fetchColumn();

        // Fetch actual appointments
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
        $remaining = ($max > 0) ? max(0, $max - $count) : '∞';
        $availabilityClass = ($remaining > 0) ? 'text-success' : 'text-danger';
    ?>
        <div class="card schedule-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start">
                    <div>
                        <span class="doctor-name"><?= htmlspecialchars($d['doctor_name']) ?></span>
                        <span class="specialization-badge ms-2"><?= htmlspecialchars($d['specialization']) ?></span>
                    </div>
                    <div>
                        <span class="badge bg-primary">Max: <?= $max ?: 'Unlimited' ?></span>
                    </div>
                </div>

                <div class="row mt-3 g-2">
                    <div class="col-md-6">
                        <p><i class="fas fa-clock me-1 text-secondary"></i> <strong>Schedule:</strong> <?= htmlspecialchars($d['schedule'] ?: 'Not specified') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><i class="fas fa-users me-1 text-secondary"></i> <strong>Appointments on <?= date('M j', strtotime($selected_date)) ?>:</strong> <?= $count ?> 
                            | <strong>Remaining:</strong> <span class="<?= $availabilityClass ?>"><?= is_numeric($remaining) ? $remaining : '∞' ?></span></p>
                    </div>
                </div>

                <?php if (!empty($appointments)): ?>
                    <div class="appointment-list">
                        <strong><i class="fas fa-list-ul me-1"></i>Patient list:</strong>
                        <ul>
                            <?php foreach ($appointments as $app): ?>
                                <li>
                                    <span><?= htmlspecialchars($app['fullname']) ?></span>
                                    <span>
                                        <span class="badge bg-secondary"><?= date('g:i A', strtotime($app['appointment_date'])) ?></span>
                                        <span class="badge <?= $app['status'] == 'Completed' ? 'bg-success' : ($app['status'] == 'Pending' ? 'bg-warning' : 'bg-secondary') ?>">
                                            <?= $app['status'] ?>
                                        </span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p class="text-muted mt-2"><i class="fas fa-info-circle me-1"></i>No appointments on this date.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Add Doctor Form (Admin only) -->
    <?php if ($_SESSION["user_logged"]["role"] === "Admin"): ?>
        <div class="add-doctor-form">
            <h4><i class="fas fa-user-md me-2"></i>Add New Doctor</h4>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Doctor Name *</label>
                        <input type="text" name="doctor_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control" placeholder="e.g., Cardiologist">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Schedule (e.g., Monday – Wednesday 8:00 AM – 2:00 PM)</label>
                        <input type="text" name="schedule" class="form-control" placeholder="Days and time range">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Max Patients per Day *</label>
                        <input type="number" name="max_patients" class="form-control" min="1" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_doctor" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Save Doctor
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Bootstrap JS (optional for toggles) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Auto-submit date picker (if you prefer no button) -->
<script>
    document.getElementById('datePicker')?.addEventListener('change', function() {
        this.form.submit();
    });
</script>
</body>
</html>