<?php
require_once 'config.php';
requireLogin();

$user = $_SESSION['user_logged'];
$is_admin = ($user['role'] === 'Admin');

// Stats
$total_patients       = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$pending_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Pending'")->fetchColumn();
$paid_patients        = $pdo->query("SELECT COUNT(*) FROM payments WHERE amount_paid >= total_amount")->fetchColumn();

$cleared_today = $pdo->query("
    SELECT COUNT(DISTINCT p.patient_id)
    FROM patients p
    WHERE
        EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.patient_id AND a.status = 'Completed' AND DATE(a.appointment_date) = CURDATE())
        AND EXISTS (SELECT 1 FROM laboratory l WHERE l.patient_id = p.patient_id AND l.status = 'Completed')
        AND EXISTS (SELECT 1 FROM medicines m WHERE m.patient_id = p.patient_id AND m.status = 'Taken')
        AND EXISTS (SELECT 1 FROM payments py WHERE py.patient_id = p.patient_id AND py.amount_paid >= py.total_amount)
")->fetchColumn();

$recentPatients = $pdo->query("
    SELECT p.patient_id, p.fullname, p.age, p.gender, p.date_registered,
           COALESCE(py.total_amount, 0) AS total_amount,
           COALESCE(py.amount_paid, 0) AS amount_paid
    FROM patients p
    LEFT JOIN payments py ON p.patient_id = py.patient_id
    ORDER BY p.date_registered DESC LIMIT 10
")->fetchAll();

$doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
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
        <div>Welcome, <?= htmlspecialchars($user['fullname']) ?> (<?= $user['role'] ?>) | <a href="logout.php">Logout</a></div>
        <nav>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="doctor_schedule.php">Doctors</a>
            <a href="laboratory.php">Laboratory</a>
            <a href="patient_overview.php">Payment & Clearance</a>
            <a href="medicine.php">Medicines</a>
            <?php if ($is_admin): ?>
                <a href="xml_export.php">XML Export</a>
                <a href="xml_import.php">XML Import</a>
            <?php endif; ?>
        </nav>
    </header>
    <main>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">Success!</div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card"><h3><i class="fas fa-users"></i> Total Patients</h3><span class="stat-number"><?= $total_patients ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-calendar-times"></i> Pending Appointments</h3><span class="stat-number"><?= $pending_appointments ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-check-circle"></i> Cleared Today</h3><span class="stat-number"><?= $cleared_today ?></span></div>
            <div class="stat-card"><h3><i class="fas fa-dollar-sign"></i> Paid Patients</h3><span class="stat-number"><?= $paid_patients ?></span></div>
        </div>

        <div class="quick-actions">
            <a href="#patient-form" class="action-btn primary"><i class="fas fa-user-plus"></i> Register Patient</a>
            <!-- FIXED: Button opens appointment modal -->
            <button type="button" class="action-btn" onclick="openAppointmentModal()">
                <i class="fas fa-calendar-plus"></i> New Appointment
            </button>
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
                <thead><tr><th>ID</th><th>Name</th><th>Age</th><th>Gender</th><th>Registered</th><th>Payment Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($recentPatients as $row):
                    if ($row['amount_paid'] <= 0) { $statusClass = 'unpaid'; $statusText = 'Unpaid'; }
                    elseif ($row['amount_paid'] >= $row['total_amount']) { $statusClass = 'paid'; $statusText = 'Paid'; }
                    else { $statusClass = 'partial'; $statusText = 'Partial'; }
                ?>
                    <tr id="patient-row-<?= $row['patient_id'] ?>">
                        <td><?= $row['patient_id'] ?></td>
                        <td><?= htmlspecialchars($row['fullname']) ?></td>
                        <td><?= $row['age'] ?></td>
                        <td><?= $row['gender'] ?></td>
                        <td><?= date('M j', strtotime($row['date_registered'])) ?></td>
                        <td><span class="status <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td>
                            <a href="patient_overview.php?patient_id=<?= $row['patient_id'] ?>" class="btn primary">View</a>
                            <?php if ($is_admin): ?>
                                <button class="btn delete-btn" onclick="deletePatient(<?= $row['patient_id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')">🗑 Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Register Patient Modal (existing) -->
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

<!-- NEW: Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal-panel">
        <div class="modal-panel-header">
            <h2>New Appointment – Weekly View</h2>
            <button class="modal-close-btn" onclick="closeAppointmentModal()">&times;</button>
        </div>
        <div class="modal-panel-body">
            <form id="appointmentForm" action="appointment_process.php" method="POST" onsubmit="return validateAppointmentForm()">
                
                <!-- Patient ID -->
                <div class="form-group">
                    <label>Patient ID</label>
                    <input type="number" name="patient_id" id="apptPatientId" placeholder="Enter Patient ID" required oninput="checkAppointmentValid()">
                </div>
                
                <!-- Doctor -->
                <div class="form-group">
                    <label>Doctor</label>
                    <select name="doctor_id" id="apptDoctorSelect" required onchange="loadAppointmentCalendar()">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Weekly Calendar -->
                <div class="form-group">
                    <label>Select Date (Next 7 days)</label>
                    <div class="weekly-calendar-grid" id="appointmentCalendar">
                        <!-- JS generates day cards here -->
                    </div>
                    <input type="hidden" name="appointment_date" id="apptSelectedDate" required>
                </div>
                
                <!-- Time -->
                <div class="form-group">
                    <label>Time</label>
                    <div id="timeContainer">
                        <div class="time-display-box" id="timeDisplay">
                            <span>--:-- --</span>
                            <span style="font-size: 1.2rem;">&#x23F0;</span>
                        </div>
                    </div>
                    <input type="hidden" name="appointment_time" id="apptSelectedTime" required>
                </div>
                
                <!-- Lab Required -->
                <div class="form-group">
                    <label>Lab Required?</label>
                    <select name="laboratory_required">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                
                <div id="appointmentStatus"></div>
                
                <button type="submit" class="btn-book-appointment" id="apptBookBtn" disabled>Book Appointment</button>
            </form>
        </div>
    </div>
</div>

<script src="assets/script.js"></script>
<script>
// ==================== APPOINTMENT MODAL ====================
function openAppointmentModal() {
    const modal = document.getElementById('appointmentModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        resetAppointmentForm();
    }
}

function closeAppointmentModal() {
    const modal = document.getElementById('appointmentModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        resetAppointmentForm();
    }
}

function resetAppointmentForm() {
    const form = document.getElementById('appointmentForm');
    if (form) form.reset();
    
    const calendar = document.getElementById('appointmentCalendar');
    if (calendar) calendar.innerHTML = '';
    
    const timeDisplay = document.getElementById('timeDisplay');
    if (timeDisplay) {
        timeDisplay.innerHTML = '<span>--:-- --</span><span style="font-size: 1.2rem;">&#x23F0;</span>';
        timeDisplay.classList.remove('active');
    }
    
    document.getElementById('apptSelectedDate').value = '';
    document.getElementById('apptSelectedTime').value = '';
    document.getElementById('appointmentStatus').innerHTML = '';
    
    const bookBtn = document.getElementById('apptBookBtn');
    if (bookBtn) bookBtn.disabled = true;
}

// Close on outside click
document.getElementById('appointmentModal').addEventListener('click', function(e) {
    if (e.target === this) closeAppointmentModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAppointmentModal();
});

// ==================== CALENDAR ====================
function loadAppointmentCalendar() {
    const doctorId = document.getElementById('apptDoctorSelect').value;
    const calendar = document.getElementById('appointmentCalendar');
    
    if (!calendar) return;
    
    if (!doctorId) {
        calendar.innerHTML = '<div style="grid-column:span 7; text-align:center; color:#718096; padding:20px;">Select a doctor first</div>';
        return;
    }
    
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const months = ['1','2','3','4','5','6','7','8','9','10','11','12'];
    let html = '';
    
    for (let i = 0; i < 7; i++) {
        const date = new Date();
        date.setDate(date.getDate() + i);
        
        const dayName = days[date.getDay()];
        const month = months[date.getMonth()];
        const dayNum = date.getDate();
        const fullDate = date.toISOString().split('T')[0];
        
        // Get slots from server
        const slots = getSimulatedSlots(fullDate, doctorId);
        const isFull = slots === 0;
        const statusClass = isFull ? 'full' : 'available';
        const statusText = isFull ? 'Full' : `${slots} slots`;
        
        html += `
            <div class="day-card ${statusClass}" 
                 data-date="${fullDate}"
                 ${!isFull ? `onclick="selectAppointmentDate('${fullDate}', this)"` : ''}>
                <div class="day-name">${dayName}</div>
                <div class="day-date">${month}/${dayNum}</div>
                <div class="slots">${statusText}</div>
            </div>
        `;
    }
    
    calendar.innerHTML = html;
}

function getSimulatedSlots(date, doctorId) {
    const seed = date.split('-').join('') + doctorId;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    return Math.abs(hash % 12);
}

// ==================== DATE SELECTION ====================
function selectAppointmentDate(date, element) {
    // Remove previous selection
    document.querySelectorAll('#appointmentCalendar .day-card').forEach(d => {
        d.classList.remove('selected');
    });
    
    // Add selected
    element.classList.add('selected');
    
    // Set hidden date
    document.getElementById('apptSelectedDate').value = date;
    
    // Load times
    loadAppointmentTimes(date);
    
    // Update status
    document.getElementById('appointmentStatus').innerHTML = 
        `<span style="color:#2d6a4f; font-weight:500;">✓ Date selected: ${date}</span>`;
    
    checkAppointmentValid();
}

// ==================== TIME SLOTS ====================
function loadAppointmentTimes(date) {
    const doctorId = document.getElementById('apptDoctorSelect').value;
    const container = document.getElementById('timeContainer');
    
    const allTimes = ['09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'];
    
    // Simulate available times (replace with: fetch(`get_available_time.php?doctor_id=${doctorId}&date=${date}`))
    const available = getSimulatedAvailableTimes(date, doctorId, allTimes);
    
    if (available.length === 0) {
        container.innerHTML = `
            <div class="time-display-box" style="color:#c53030; border-color:#feb2b2;">
                <span>No slots available for this date</span>
            </div>
        `;
        return;
    }
    
    let html = '<div class="time-slots-grid">';
    allTimes.forEach(time => {
        const isAvailable = available.includes(time);
        const className = isAvailable ? '' : 'taken';
        const onclick = isAvailable ? `onclick="selectAppointmentTime('${time}', this)"` : '';
        
        html += `<div class="time-slot-btn ${className}" ${onclick}>${time}</div>`;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function getSimulatedAvailableTimes(date, doctorId, allTimes) {
    const seed = date + doctorId;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    const removeCount = 2 + (Math.abs(hash) % 3);
    const taken = new Set();
    for (let i = 0; i < removeCount; i++) {
        taken.add(allTimes[Math.abs((hash + i * 7) % allTimes.length)]);
    }
    return allTimes.filter(t => !taken.has(t));
}

function selectAppointmentTime(time, element) {
    // Remove previous
    document.querySelectorAll('.time-slot-btn').forEach(t => {
        t.classList.remove('selected');
    });
    
    // Add selected
    element.classList.add('selected');
    
    // Set hidden time
    document.getElementById('apptSelectedTime').value = time;
    
    // Update display
    const timeDisplay = document.getElementById('timeDisplay');
    if (timeDisplay) {
        timeDisplay.innerHTML = `<span>${time}</span><span style="font-size: 1.2rem;">&#x23F0;</span>`;
        timeDisplay.classList.add('active');
    }
    
    // Update status
    document.getElementById('appointmentStatus').innerHTML = 
        `<span style="color:#1e6f9f; font-weight:500;">✓ Time selected: ${time}</span>`;
    
    checkAppointmentValid();
}

// ==================== VALIDATION ====================
function checkAppointmentValid() {
    const patientId = document.getElementById('apptPatientId').value.trim();
    const doctorId = document.getElementById('apptDoctorSelect').value;
    const date = document.getElementById('apptSelectedDate').value;
    const time = document.getElementById('apptSelectedTime').value;
    const bookBtn = document.getElementById('apptBookBtn');
    
    const isValid = patientId && doctorId && date && time;
    bookBtn.disabled = !isValid;
    
    return isValid;
}

function validateAppointmentForm() {
    if (!checkAppointmentValid()) {
        document.getElementById('appointmentStatus').innerHTML = 
            `<span style="color:#c53030;">Please fill all required fields</span>`;
        return false;
    }
    return true;
}

// ==================== EXISTING FUNCTIONS ====================
function deletePatient(id, name) {
    if (!confirm(`DELETE patient "${name}"? This cannot be undone.`)) return;
    fetch(`delete_patient.php?patient_id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('patient-row-' + id).remove();
                alert('Patient deleted successfully.');
            } else {
                alert('Delete failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error.'));
}
</script>
</body>
</html>