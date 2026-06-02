<?php
require_once 'config.php';
requireLogin();

$user = $_SESSION['user_logged'];
$is_admin = ($user['role'] === 'Admin');

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
    $stmt = $pdo->prepare("SELECT p.*, COALESCE(py.total_amount,0) AS total_amount, COALESCE(py.amount_paid,0) AS amount_paid
        FROM patients p LEFT JOIN payments py ON p.patient_id = py.patient_id
        WHERE p.fullname = ? LIMIT 1");
    $stmt->execute([$user['fullname']]);
    $myRecord = $stmt->fetch();
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
                <a href="doctor_schedule.php">Doctor Schedules</a>
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
            <div class="alert success">✅ <?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert error">❌ <?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <!-- STATS -->
        <div class="dashboard-stats">
            <div class="stat-card"><h3><i class="fas fa-users"></i> Total Patients</h3><span class="stat-number"><?= $total_patients ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-calendar-times"></i> Pending Appointments</h3><span class="stat-number"><?= $pending_appointments ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-check-circle"></i> Cleared Today</h3><span class="stat-number"><?= $cleared_today ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-dollar-sign"></i> Paid Patients</h3><span class="stat-number"><?= $paid_patients ?></span></div>
        </div>

        <!-- QUICK ACTIONS (only two main actions) -->
        <div class="quick-actions">
            <a href="#patient-form" class="action-btn primary"><i class="fas fa-user-plus"></i> Register Patient</a>
            <a href="#appointment-form" class="action-btn primary"><i class="fas fa-calendar-plus"></i> New Appointment</a>
        </div>

        <!-- SEARCH -->
        <div class="card">
            <h3><i class="fas fa-search"></i> Verify Patient Record</h3>
            <form method="GET" action="patient_overview.php">
                <div class="form-row">
                    <input type="number" name="patient_id" placeholder="Enter Patient ID" required>
                    <button type="submit" class="btn primary">Check Records</button>
                </div>
            </form>
        </div>

        <!-- RECENT PATIENTS TABLE -->
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
                        <a href="edit_patient.php?id=<?= $row['patient_id'] ?>" class="btn">✏️ Edit</a>
                        <button class="btn delete-btn" onclick="deletePatient(<?= $row['patient_id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')">🗑 Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- CUSTOMER VIEW (unchanged) -->
        <?php if (!isset($myRecord) || !$myRecord): ?>
            <div class="card" style="text-align:center; padding:3rem;">
                <h3 style="color:#1e6f9f;">👋 Welcome, <?= htmlspecialchars($user['fullname']) ?>!</h3>
                <p style="color:#718096; margin:1rem 0;">Your patient record has not been created yet. Please visit the clinic or contact the admin to register you as a patient.</p>
            </div>
        <?php else: ?>
            <div class="my-status-card">
                <h3><i class="fas fa-id-card"></i> My Clinic Status</h3>
                <div class="status-row"><span>Patient ID</span><strong>#<?= $myRecord['patient_id'] ?></strong></div>
                <div class="status-row"><span>Total Bill</span><strong>₱<?= number_format($myRecord['total_amount'], 2) ?></strong></div>
                <div class="status-row"><span>Amount Paid</span><strong>₱<?= number_format($myRecord['amount_paid'], 2) ?></strong></div>
                <div class="status-row"><span>Balance</span><?php $bal = $myRecord['total_amount'] - $myRecord['amount_paid']; ?><strong style="color:<?= $bal <= 0 ? '#1e6f3f' : '#c0392b' ?>;">₱<?= number_format($bal, 2) ?></strong></div>
                <div style="margin-top:1rem;"><a href="patient_overview.php?patient_id=<?= $myRecord['patient_id'] ?>" class="btn primary">View Full Status</a></div>
            </div>
            <div class="card">
                <h3>My Appointments</h3>
                <?php if (empty($myAppointments)): ?>
                    <p>No appointments yet. <a href="#appointment-form">Book one now →</a></p>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>Doctor</th><th>Specialization</th><th>Date & Time</th><th>Status</th></tr></thead>
                    <tbody><?php foreach ($myAppointments as $appt): ?>
                    <tr><td><?= htmlspecialchars($appt['doctor_name']) ?></td><td><?= htmlspecialchars($appt['specialization']) ?></td><td><?= date('M j, Y g:i A', strtotime($appt['appointment_date'])) ?></td><td><span class="status <?= strtolower($appt['status']) ?>"><?= $appt['status'] ?></span></td></tr>
                    <?php endforeach; ?></tbody>
                </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php if ($is_admin): ?>
<!-- Register Patient Modal -->
<div id="patient-form" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Register Patient</h2>
        <form action="patient_register.php" method="POST">
            <div class="form-group"><label>Full Name</label><input type="text" name="fullname" required></div>
            <div class="form-row"><div class="form-group"><label>Age</label><input type="number" name="age" required></div><div class="form-group"><label>Gender</label><select name="gender" required><option>Male</option><option>Female</option></select></div></div>
            <div class="form-group"><label>Address</label><textarea name="address" required></textarea></div>
            <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" required></div>
            <button type="submit" class="btn primary">Register</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Appointment Modal (Real Time Availability) -->
<div id="appointment-form" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>New Appointment – Real Time Availability</h2>
        <form action="appointment_process.php" method="POST" id="apptForm">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <?php if ($is_admin): ?>
            <div class="form-group">
                <label>Patient ID</label>
                <input type="number" name="patient_id" id="patient_id_input" required>
                <div id="patient_name_display" style="margin-top:5px; font-size:0.75rem; color:#2c7a47;"></div>
            </div>
            <?php else: ?>
                <input type="hidden" name="patient_id" id="patient_id_input" value="<?= $myRecord['patient_id'] ?? '' ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" id="doctor_select" required>
                    <option value="">Select Doctor</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Appointment Date</label>
                <input type="date" name="appointment_date" id="appt_date" required>
                <div id="availability_msg" style="margin-top:5px; font-size:0.75rem;"></div>
            </div>
            <div class="form-group">
                <label>Appointment Time</label>
                <select name="appointment_time" id="appt_time" required disabled>
                    <option value="">First select a date</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lab Required?</label>
                <select name="laboratory_required">
                    <option value="No">No</option>
                    <option value="Yes">Yes</option>
                </select>
            </div>
            <button type="submit" class="btn primary" id="bookBtn" disabled>Book Appointment</button>
        </form>
    </div>
</div>

<script>
// Modal opener
document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        const modal = document.querySelector(targetId);
        if (modal && modal.classList.contains('modal')) {
            e.preventDefault();
            modal.style.display = 'flex';
        }
    });
});
// Close modal
document.querySelectorAll('.modal .close').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) e.target.style.display = 'none';
});

// Patient name auto-fill
const patientInput = document.getElementById('patient_id_input');
if (patientInput && patientInput.type !== 'hidden') {
    patientInput.addEventListener('blur', function() {
        let pid = this.value.trim();
        if (pid) {
            fetch(`get_patient.php?id=${pid}`)
                .then(res => res.json())
                .then(data => {
                    let nameDiv = document.getElementById('patient_name_display');
                    nameDiv.innerHTML = data.fullname ? `👤 ${data.fullname}` : '❌ Not found';
                });
        } else {
            document.getElementById('patient_name_display').innerHTML = '';
        }
        checkBookButton();
    });
}

const doctorSelect = document.getElementById('doctor_select');
const dateInput = document.getElementById('appt_date');
const timeSelect = document.getElementById('appt_time');
const msgDiv = document.getElementById('availability_msg');

if (doctorSelect) {
    doctorSelect.addEventListener('change', function() {
        dateInput.value = '';
        timeSelect.innerHTML = '<option value="">First select a date</option>';
        timeSelect.disabled = true;
        if (msgDiv) msgDiv.innerHTML = '';
        checkBookButton();
    });
}

if (dateInput) {
    dateInput.addEventListener('change', function() {
        const doctorId = doctorSelect.value;
        const date = this.value;
        if (!doctorId || !date) {
            if (msgDiv) msgDiv.innerHTML = '';
            timeSelect.innerHTML = '<option value="">Select doctor and date first</option>';
            timeSelect.disabled = true;
            checkBookButton();
            return;
        }
        if (msgDiv) msgDiv.innerHTML = '<span style="color:#718096;">⏳ Checking...</span>';
        timeSelect.innerHTML = '<option value="">Loading...</option>';
        timeSelect.disabled = true;
        fetch(`check_availability.php?doctor_id=${doctorId}&date=${date}`)
            .then(res => res.json())
            .then(data => {
                if (data.available) {
                    if (msgDiv) msgDiv.innerHTML = `<span style="color:green;">✅ ${data.remaining} slot(s) available</span>`;
                    return fetch(`get_available_time.php?doctor_id=${doctorId}&date=${date}`);
                } else {
                    if (msgDiv) msgDiv.innerHTML = `<span style="color:red;">❌ No slots available</span>`;
                    timeSelect.innerHTML = '<option value="">No available times</option>';
                    timeSelect.disabled = true;
                    throw new Error('No slots');
                }
            })
            .then(res => res.json())
            .then(slots => {
                if (!slots.length) {
                    timeSelect.innerHTML = '<option value="">No available times</option>';
                    timeSelect.disabled = true;
                } else {
                    let html = '<option value="">Select time</option>';
                    slots.forEach(slot => { html += `<option value="${slot}">${slot}</option>`; });
                    timeSelect.innerHTML = html;
                    timeSelect.disabled = false;
                }
                checkBookButton();
            })
            .catch(err => console.log(err));
    });
}

function checkBookButton() {
    const patientId = document.getElementById('patient_id_input').value;
    const doctor = doctorSelect ? doctorSelect.value : '';
    const date = dateInput ? dateInput.value : '';
    const time = timeSelect ? timeSelect.value : '';
    const bookBtn = document.getElementById('bookBtn');
    if (bookBtn) bookBtn.disabled = !(patientId && doctor && date && time && time !== '');
}

patientInput?.addEventListener('input', checkBookButton);
doctorSelect?.addEventListener('change', checkBookButton);
dateInput?.addEventListener('change', checkBookButton);
timeSelect?.addEventListener('change', checkBookButton);

function deletePatient(id, name) {
    if (!confirm(`DELETE patient "${name}"? This cannot be undone!`)) return;
    fetch(`delete_patient.php?patient_id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('patient-row-' + id)?.remove();
                alert('Deleted.');
            } else alert('Delete failed: ' + data.message);
        });
}
</script>
</body>
</html>