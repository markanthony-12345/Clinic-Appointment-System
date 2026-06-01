<?php
require_once 'config.php';
requireLogin();

$user = $_SESSION['user_logged'];
$is_admin = ($user['role'] === 'Admin');

// dito ung stats (admin only)
if ($is_admin) {
    $total_patients       = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $pending_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Pending'")->fetchColumn();
    $paid_patients        = $pdo->query("SELECT COUNT(*) FROM payments WHERE amount_paid >= total_amount")->fetchColumn();
    $cleared_today = $pdo->query("
        SELECT COUNT(DISTINCT p.patient_id) FROM patients p
        WHERE EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.patient_id AND a.status = 'Completed' AND DATE(a.appointment_date) = CURDATE())
        AND EXISTS (SELECT 1 FROM laboratory l WHERE l.patient_id = p.patient_id AND l.status = 'Completed')
        AND EXISTS (SELECT 1 FROM medicines m WHERE m.patient_id = p.patient_id AND m.status = 'Taken')
        AND EXISTS (SELECT 1 FROM payments py WHERE py.patient_id = p.patient_id AND py.amount_paid >= py.total_amount)
    ")->fetchColumn();
    $recentPatients = $pdo->query("
        SELECT p.patient_id, p.fullname, p.age, p.gender, p.date_registered,
               COALESCE(py.total_amount,0) AS total_amount, COALESCE(py.amount_paid,0) AS amount_paid
        FROM patients p LEFT JOIN payments py ON p.patient_id = py.patient_id
        ORDER BY p.date_registered DESC LIMIT 10
    ")->fetchAll();
    $doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
} else {
    // Customer: find their patient record by matching fullname to user fullname
    $stmt = $pdo->prepare("SELECT p.*, COALESCE(py.total_amount,0) AS total_amount, COALESCE(py.amount_paid,0) AS amount_paid
        FROM patients p LEFT JOIN payments py ON p.patient_id = py.patient_id
        WHERE p.fullname = ? LIMIT 1");
    $stmt->execute([$user['fullname']]);
    $myRecord = $stmt->fetch();

    // Customer appointments
    $myAppointments = [];
    if ($myRecord) {
        $stmt2 = $pdo->prepare("SELECT a.*, d.doctor_name, d.specialization FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ? ORDER BY a.appointment_date DESC");
        $stmt2->execute([$myRecord['patient_id']]);
        $myAppointments = $stmt2->fetchAll();
    }
    $doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .calendar-day { border:2px solid #e8eef4; border-radius:12px; padding:0.7rem 0.3rem; text-align:center; cursor:not-allowed; background:#f8f9fb; color:#a0aec0; font-size:0.8rem; transition:all 0.2s; user-select:none; }
        .calendar-day.available { background:white; color:#1a2c3e; cursor:pointer; border-color:#d5e0eb; }
        .calendar-day.available:hover { border-color:#1e6f9f; background:#e8f4fc; transform:translateY(-2px); box-shadow:0 6px 16px -6px rgba(30,111,159,0.2); }
        .calendar-day.selected { border-color:#1e6f9f !important; background:linear-gradient(135deg,#e8f4fc,#d6ecf8) !important; color:#1e6f9f !important; font-weight:700; transform:translateY(-2px); box-shadow:0 6px 16px -6px rgba(30,111,159,0.25); cursor:pointer !important; }
        .calendar-day small { display:block; font-size:0.7rem; margin-top:2px; color:#38a169; }
        .calendar-day:not(.available) small { color:#e53e3e; }
        .calendar-day.selected small { color:#1e6f9f; }
        .calendar-loading { grid-column:span 7; text-align:center; color:#718096; padding:10px; font-size:0.85rem; }
        .role-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:0.75rem; font-weight:600; }
        .role-badge.admin { background:#fef3c7; color:#b45309; }
        .role-badge.user { background:#e0f2fe; color:#0369a1; }
        .my-status-card { background:linear-gradient(135deg,#e8f4fc,#f0f9ff); border:1.5px solid #bdd4e6; border-radius:16px; padding:1.5rem; margin-bottom:1.5rem; }
        .status-row { display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; border-bottom:1px solid #e8eff6; }
        .status-row:last-child { border-bottom:none; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div>
            Welcome, <?= htmlspecialchars($user['fullname']) ?>
            <span class="role-badge <?= $is_admin ? 'admin' : 'user' ?>"><?= $user['role'] ?></span>
            | <a href="logout.php">Logout</a>
        </div>
        <nav>
            <a href="dashboard.php" class="active">Dashboard</a>
            <?php if ($is_admin): ?>
                <a href="doctor_schedule.php">Doctors</a>
                <a href="laboratory.php">Laboratory</a>
                <a href="patient_overview.php">Payment & Clearance</a>
                <a href="medicine.php">Medicines</a>
                <a href="xml_export.php">XML Export</a>
                <a href="xml_import.php">XML Import</a>
            <?php else: ?>
                <a href="#appointment-form">Book Appointment</a>
                <?php if (isset($myRecord) && $myRecord): ?>
                    <a href="patient_overview.php?patient_id=<?= $myRecord['patient_id'] ?>">My Status</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </header>
    <main>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">✅ Done!</div>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <!-- ===================== ADMIN VIEW ===================== -->
        <div class="dashboard-stats">
            <div class="stat-card"><h3><i class="fas fa-users"></i> Total Patients</h3><span class="stat-number"><?= $total_patients ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-calendar-times"></i> Pending Appointments</h3><span class="stat-number"><?= $pending_appointments ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-check-circle"></i> Cleared Today</h3><span class="stat-number"><?= $cleared_today ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-dollar-sign"></i> Paid Patients</h3><span class="stat-number"><?= $paid_patients ?></span></div>
        </div>

        <div class="quick-actions">
            <a href="#patient-form" class="action-btn primary"><i class="fas fa-user-plus"></i> Register Patient</a>
            <a href="#appointment-form" class="action-btn"><i class="fas fa-calendar-plus"></i> New Appointment</a>
            <a href="doctor_schedule.php" class="action-btn"><i class="fas fa-user-md"></i> Doctor Schedules</a>
            <a href="laboratory.php" class="action-btn"><i class="fas fa-vial"></i> Laboratory</a>
            <a href="medicine.php" class="action-btn"><i class="fas fa-pills"></i> Medicines</a>
            <a href="patient_overview.php" class="action-btn"><i class="fas fa-file-invoice-dollar"></i> Payment & Clearance</a>
        </div>

        <div class="card">
            <h3><i class="fas fa-search"></i> Verify Patient Record</h3>
            <form method="GET" action="patient_overview.php">
                <div class="form-row">
                    <input type="number" name="patient_id" placeholder="Enter Patient ID" required>
                    <button type="submit" class="btn primary">Check Records</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Recent Patients</h3>
            <table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Age</th><th>Gender</th><th>Registered</th><th>Payment</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($recentPatients as $row):
                    if ($row['amount_paid'] <= 0) { $sc = 'unpaid'; $st = 'Unpaid'; }
                    elseif ($row['amount_paid'] >= $row['total_amount']) { $sc = 'paid'; $st = 'Paid'; }
                    else { $sc = 'partial'; $st = 'Partial'; }
                ?>
                <tr id="patient-row-<?= $row['patient_id'] ?>">
                    <td><?= $row['patient_id'] ?></td>
                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                    <td><?= $row['age'] ?></td>
                    <td><?= $row['gender'] ?></td>
                    <td><?= date('M j', strtotime($row['date_registered'])) ?></td>
                    <td><span class="status <?= $sc ?>"><?= $st ?></span></td>
                    <td>
                        <a href="patient_overview.php?patient_id=<?= $row['patient_id'] ?>" class="btn primary">View</a>
                        <button class="btn delete-btn" onclick="deletePatient(<?= $row['patient_id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')">🗑 Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- ===================== CUSTOMER VIEW ===================== -->
        <?php if (!isset($myRecord) || !$myRecord): ?>
            <div class="card" style="text-align:center; padding:3rem;">
                <h3 style="color:#1e6f9f;">👋 Welcome, <?= htmlspecialchars($user['fullname']) ?>!</h3>
                <p style="color:#718096; margin:1rem 0;">Your patient record has not been created yet. Please visit the clinic or contact the admin to register you as a patient.</p>
                <p style="color:#a0aec0; font-size:0.85rem;">Once registered, you can view your appointments, lab results, and clearance status here.</p>
            </div>
        <?php else: ?>
            <!-- My Summary Card -->
            <div class="my-status-card">
                <h3 style="color:#1e4a6e; margin-bottom:1rem;"><i class="fas fa-id-card"></i> My Clinic Status</h3>
                <div class="status-row">
                    <span>Patient ID</span>
                    <strong>#<?= $myRecord['patient_id'] ?></strong>
                </div>
                <div class="status-row">
                    <span>Total Bill</span>
                    <strong>₱<?= number_format($myRecord['total_amount'], 2) ?></strong>
                </div>
                <div class="status-row">
                    <span>Amount Paid</span>
                    <strong style="color:#1e6f3f;">₱<?= number_format($myRecord['amount_paid'], 2) ?></strong>
                </div>
                <div class="status-row">
                    <span>Balance</span>
                    <?php $bal = $myRecord['total_amount'] - $myRecord['amount_paid']; ?>
                    <strong style="color:<?= $bal <= 0 ? '#1e6f3f' : '#c0392b' ?>;">₱<?= number_format($bal, 2) ?></strong>
                </div>
                <div style="margin-top:1rem;">
                    <a href="patient_overview.php?patient_id=<?= $myRecord['patient_id'] ?>" class="btn primary">
                        <i class="fas fa-eye"></i> View Full Status & Clearance
                    </a>
                </div>
            </div>

            <!-- My Appointments -->
            <div class="card">
                <h3><i class="fas fa-calendar-check"></i> My Appointments</h3>
                <?php if (empty($myAppointments)): ?>
                    <p style="color:#718096;">No appointments yet. <a href="#appointment-form" style="color:#1e6f9f;">Book one now →</a></p>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>Doctor</th><th>Specialization</th><th>Date & Time</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($myAppointments as $appt): ?>
                    <tr>
                        <td><?= htmlspecialchars($appt['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($appt['specialization']) ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($appt['appointment_date'])) ?></td>
                        <td><span class="status <?= strtolower($appt['status']) ?>"><?= $appt['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php if ($is_admin): ?>
<!-- Register Patient Modal (Admin only) -->
<div id="patient-form" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Register Patient</h2>
        <form action="patient_register.php" method="POST">
            <div class="form-group"><label>Full Name</label><input type="text" name="fullname" required></div>
            <div class="form-row">
                <div class="form-group"><label>Age</label><input type="number" name="age" required></div>
                <div class="form-group"><label>Gender</label><select name="gender" required><option>Male</option><option>Female</option></select></div>
            </div>
            <div class="form-group"><label>Address</label><textarea name="address" required></textarea></div>
            <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" required></div>
            <button type="submit" class="btn primary">Register</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Appointment Modal (Both Admin and Customer) -->
<div id="appointment-form" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>New Appointment – Weekly View</h2>
        <form action="appointment_process.php" method="POST" id="apptForm">
            <?php if ($is_admin): ?>
            <div class="form-group">
                <label>Patient ID</label>
                <input type="number" name="patient_id" id="appt_patient_id" required>
            </div>
            <?php else: ?>
            <!-- Customer: auto-fill their own patient ID -->
            <input type="hidden" name="patient_id" id="appt_patient_id" value="<?= isset($myRecord) && $myRecord ? $myRecord['patient_id'] : '' ?>">
            <?php if (!isset($myRecord) || !$myRecord): ?>
                <div class="alert error">You are not registered as a patient yet. Please contact the clinic admin.</div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" id="weekly_doctor_select" required onchange="loadWeeklyCalendar()">
                    <option value="">Select Doctor</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Select Date (Next 7 days)</label>
                <div id="weekly-calendar" style="display:grid;grid-template-columns:repeat(7,1fr);gap:0.5rem;margin-top:0.5rem;">
                    <div class="calendar-loading">Select a doctor first</div>
                </div>
                <input type="hidden" name="appointment_date" id="selected_date">
            </div>
            <div class="form-group">
                <label>Time</label>
                <input type="time" name="appointment_time" id="appt_time" required onchange="checkBookBtn()">
            </div>
            <div class="form-group">
                <label>Lab Required?</label>
                <select name="laboratory_required"><option>No</option><option>Yes</option></select>
            </div>
            <div id="availability-status" style="margin:8px 0;font-size:0.9rem;"></div>
            <button type="submit" class="btn primary" id="weekly-book-btn" disabled style="width:100%;padding:0.9rem;font-size:1rem;">Book Appointment</button>
        </form>
    </div>
</div>

<script src="assets/script.js"></script>
<script>
function loadWeeklyCalendar() {
    const doctorId = document.getElementById('weekly_doctor_select').value;
    const calendarDiv = document.getElementById('weekly-calendar');
    document.getElementById('selected_date').value = '';
    document.getElementById('availability-status').innerHTML = '';
    checkBookBtn();
    if (!doctorId) {
        calendarDiv.innerHTML = '<div class="calendar-loading">Select a doctor first</div>';
        return;
    }
    calendarDiv.innerHTML = '<div class="calendar-loading">⏳ Checking availability...</div>';
    const days = [];
    for (let i = 0; i < 7; i++) {
        let d = new Date(); d.setDate(d.getDate() + i); days.push(d);
    }
    Promise.all(days.map(day => {
        const dateStr = day.toISOString().split('T')[0];
        return fetch(`check_availability.php?doctor_id=${doctorId}&date=${dateStr}`)
            .then(res => res.json())
            .then(data => ({ dateStr, day, available: data.available, remaining: data.remaining }))
            .catch(() => ({ dateStr, day, available: false, remaining: 0 }));
    })).then(results => {
        calendarDiv.innerHTML = '';
        results.forEach(r => {
            const dayName = r.day.toLocaleDateString('en-US', { weekday: 'short' });
            const displayDate = `${r.day.getMonth()+1}/${r.day.getDate()}`;
            const box = document.createElement('div');
            box.className = `calendar-day ${r.available ? 'available' : 'unavailable'}`;
            box.dataset.date = r.dateStr;
            box.innerHTML = `<strong>${dayName}</strong><br>${displayDate}<br><small>${r.available ? r.remaining+' slots' : 'Full'}</small>`;
            if (r.available) {
                box.addEventListener('click', function() {
                    document.querySelectorAll('#weekly-calendar .calendar-day').forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('selected_date').value = this.dataset.date;
                    document.getElementById('availability-status').innerHTML = `<span style="color:green;">✅ Selected: ${this.dataset.date}. Now choose time.</span>`;
                    checkBookBtn();
                });
            }
            calendarDiv.appendChild(box);
        });
    }).catch(() => {
        calendarDiv.innerHTML = '<div class="calendar-loading" style="color:red;">Error loading calendar.</div>';
    });
}

function checkBookBtn() {
    const date = document.getElementById('selected_date').value;
    const time = document.getElementById('appt_time').value;
    const patientId = document.getElementById('appt_patient_id').value;
    const doctor = document.getElementById('weekly_doctor_select').value;
    document.getElementById('weekly-book-btn').disabled = !(date && time && patientId && doctor);
}

const patientInput = document.getElementById('appt_patient_id');
if (patientInput && patientInput.type !== 'hidden') {
    patientInput.addEventListener('input', checkBookBtn);
}

function deletePatient(id, name) {
    if (!confirm(`DELETE patient "${name}"?\n\nThis cannot be undone!`)) return;
    fetch(`delete_patient.php?patient_id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) { document.getElementById('patient-row-' + id).remove(); alert('Deleted.'); }
            else alert('Delete failed: ' + (data.message || 'Unknown error'));
        }).catch(() => alert('Network error.'));
}
</script>
</body>
</html>