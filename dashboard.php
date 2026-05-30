<?php
require_once 'config.php';
requireLogin();

$user = $_SESSION['user_logged'];
$is_admin = ($user['role'] === 'Admin');

// Stats
$total_patients       = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$pending_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Pending'")->fetchColumn();
$paid_patients        = $pdo->query("SELECT COUNT(*) FROM payments WHERE amount_paid >= total_amount")->fetchColumn();

// Cleared Today
$cleared_today = $pdo->query("
    SELECT COUNT(DISTINCT p.patient_id)
    FROM patients p
    WHERE
        EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.patient_id AND a.status = 'Completed' AND DATE(a.appointment_date) = CURDATE())
        AND EXISTS (SELECT 1 FROM laboratory l WHERE l.patient_id = p.patient_id AND l.status = 'Completed')
        AND EXISTS (SELECT 1 FROM medicines m WHERE m.patient_id = p.patient_id AND m.status = 'Taken')
        AND EXISTS (SELECT 1 FROM payments py WHERE py.patient_id = p.patient_id AND py.amount_paid >= py.total_amount)
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Existing button styles + new calendar styles */
        .btn.delete-btn { background: #e74c3c; color: #fff; border: none; margin-left: 4px; }
        .btn.delete-btn:hover { background: #c0392b; }
        
        /* Weekly calendar day boxes */
        .calendar-day {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            text-align: center;
            transition: 0.2s;
            cursor: pointer;
        }
        .calendar-day.available {
            background-color: #e0f2e9;
            cursor: pointer;
        }
        .calendar-day.available:hover {
            transform: scale(1.02);
            background-color: #c8e6d9 !important;
            border-color: #1e6f9f;
        }
        .calendar-day.unavailable {
            background-color: #fee2e2;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .calendar-day.selected {
            border: 2px solid #1e6f9f;
            background-color: #c8e6d9;
        }
        #weekly-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        @media (max-width: 768px) {
            #weekly-calendar {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div>Welcome, <?= htmlspecialchars($user['fullname']) ?> (<?= $user['role'] ?>) | <a href="logout.php">Logout</a></div>
        <nav>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="doctor_schedule.php">Doctors</a>
            <a href="laboratory.php">Laboratory</a>
            <a href="payments.php">Payments</a>
            <a href="medicine.php">Medicines</a>
            <a href="clearance.php">Clearance</a>
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
            <a href="#appointment-form" class="action-btn"><i class="fas fa-calendar-plus"></i> New Appointment</a>
        </div>

        <div class="card">
            <h3><i class="fas fa-search"></i> Verify Patient Record</h3>
            <form method="GET" action="clearance.php">
                <div class="form-row">
                    <input type="number" name="patient_id" placeholder="Enter Patient ID" required>
                    <button type="submit" class="btn primary">Check Records</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Recent Patients</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Registered</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $pdo->query("
                    SELECT
                        p.patient_id, p.fullname, p.age, p.gender, p.date_registered,
                        COALESCE(py.total_amount, 0) AS total_amount,
                        COALESCE(py.amount_paid, 0) AS amount_paid
                    FROM patients p
                    LEFT JOIN payments py ON p.patient_id = py.patient_id
                    ORDER BY p.date_registered DESC
                    LIMIT 10
                ");
                while ($row = $stmt->fetch()):
                    if ($row['amount_paid'] <= 0) {
                        $payment_status = 'Unpaid'; $status_class = 'unpaid';
                    } elseif ($row['amount_paid'] >= $row['total_amount']) {
                        $payment_status = 'Paid'; $status_class = 'paid';
                    } else {
                        $payment_status = 'Partial'; $status_class = 'partial';
                    }
                ?>
                    <tr id="patient-row-<?= $row['patient_id'] ?>">
                        <td><?= $row['patient_id'] ?></td>
                        <td><?= htmlspecialchars($row['fullname']) ?></td>
                        <td><?= $row['age'] ?></td>
                        <td><?= $row['gender'] ?></td>
                        <td><?= date('M j', strtotime($row['date_registered'])) ?></td>
                        <td><span class="status <?= $status_class ?>"><?= $payment_status ?></span></td>
                        <td>
                            <a href="clearance.php?patient_id=<?= $row['patient_id'] ?>" class="btn primary">View</a>
                            <?php if ($is_admin): ?>
                            <button class="btn delete-btn"
                                onclick="deletePatient(<?= $row['patient_id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')">
                                🗑 Delete
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Register Patient Modal (unchanged) -->
<div id="patient-form" class="modal"><div class="modal-content"><span class="close">&times;</span><h2>Register Patient</h2><form action="patient_register.php" method="POST"><div class="form-group"><label>Full Name</label><input type="text" name="fullname" required></div><div class="form-row"><div class="form-group"><label>Age</label><input type="number" name="age" required></div><div class="form-group"><label>Gender</label><select name="gender" required><option>Male</option><option>Female</option></select></div></div><div class="form-group"><label>Address</label><textarea name="address" required></textarea></div><div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" required></div><button type="submit" class="btn primary">Register</button></form></div></div>

<!-- NEW APPOINTMENT MODAL – WEEKLY CALENDAR VIEW -->
<div id="appointment-form" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>New Appointment – Weekly View</h2>
        <form action="appointment_process.php" method="POST" id="appointment-form-inner">
            <div class="form-group">
                <label>Patient ID</label>
                <input type="number" name="patient_id" required>
            </div>
            <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" id="weekly_doctor_select" required>
                    <option value="">Select Doctor</option>
                    <?php $docs = $pdo->query("SELECT * FROM doctors"); while($d = $docs->fetch()): ?>
                        <option value="<?= $d['doctor_id'] ?>"><?= $d['doctor_name'] ?> (<?= $d['specialization'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Select Date (Next 7 days)</label>
                <div id="weekly-calendar">
                    <!-- Calendar will load via JavaScript -->
                </div>
                <input type="hidden" name="appointment_date" id="selected_date" required>
            </div>
            <div class="form-group">
                <label>Time</label>
                <input type="time" name="appointment_time" required>
            </div>
            <div class="form-group">
                <label>Lab Required?</label>
                <select name="laboratory_required">
                    <option>No</option>
                    <option>Yes</option>
                </select>
            </div>
            <div id="availability-status" style="margin:10px 0"></div>
            <button type="submit" class="btn primary" id="weekly-book-btn" disabled>Book Appointment</button>
        </form>
    </div>
</div>

<script src="assets/script.js"></script>
<script>
// Weekly calendar loader
document.getElementById('weekly_doctor_select').addEventListener('change', loadWeeklyCalendar);

function loadWeeklyCalendar() {
    const doctorId = document.getElementById('weekly_doctor_select').value;
    const calendarDiv = document.getElementById('weekly-calendar');
    if (!doctorId) {
        calendarDiv.innerHTML = '<div style="grid-column:span 7; text-align:center;">Please select a doctor first</div>';
        return;
    }
    
    // Generate next 7 days (starting from tomorrow, or today? We'll start from today)
    const days = [];
    for (let i = 0; i < 7; i++) {
        let d = new Date();
        d.setDate(d.getDate() + i);
        days.push(d);
    }
    
    calendarDiv.innerHTML = '<div style="grid-column:span 7; text-align:center;">Loading availability...</div>';
    
    Promise.all(days.map(day => {
        let dateStr = day.toISOString().split('T')[0];
        return fetch(`check_availability.php?doctor_id=${doctorId}&date=${dateStr}`)
            .then(res => res.json())
            .then(data => ({ 
                date: dateStr, 
                day: day, 
                available: data.available, 
                remaining: data.remaining, 
                reason: data.reason || (data.available ? 'Available' : 'Full') 
            }));
    })).then(results => {
        calendarDiv.innerHTML = '';
        results.forEach(r => {
            const dayName = r.day.toLocaleDateString('en-US', { weekday: 'short' });
            const dateNum = r.day.getDate();
            const month = r.day.getMonth() + 1;
            const displayDate = `${month}/${dateNum}`;
            const box = document.createElement('div');
            box.className = `calendar-day ${r.available ? 'available' : 'unavailable'}`;
            box.innerHTML = `<strong>${dayName}</strong><br>${displayDate}<br><small>${r.available ? r.remaining+' slots' : 'Full'}</small>`;
            if (r.available) {
                box.onclick = () => {
                    document.getElementById('selected_date').value = r.date;
                    document.getElementById('availability-status').innerHTML = `<span style="color:green;">✅ Selected: ${r.date}. Now choose time.</span>`;
                    document.getElementById('weekly-book-btn').disabled = false;
                    // Remove highlight from all then add to this one
                    document.querySelectorAll('.calendar-day').forEach(b => b.classList.remove('selected'));
                    box.classList.add('selected');
                };
            } else {
                box.onclick = null;
            }
            calendarDiv.appendChild(box);
        });
    }).catch(err => {
        calendarDiv.innerHTML = '<div style="grid-column:span 7; color:red;">Error loading calendar. Check console.</div>';
        console.error(err);
    });
}

// Ensure the book button is enabled only when a date is selected
document.getElementById('selected_date').addEventListener('change', function() {
    if (this.value) document.getElementById('weekly-book-btn').disabled = false;
});

// Keep existing deletePatient function
function deletePatient(id, name) {
    if (!confirm(`DELETE patient "${name}"?\n\nThis will permanently remove ALL records including:\n- Appointments\n- Laboratory results\n- Medicines\n- Payments\n\nThis CANNOT be undone!`)) return;
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
        .catch(() => alert('Network error. Please try again.'));
}
</script>
</body>
</html>