<?php
require_once 'config.php';
requireLogin();

$user = $_SESSION['user_logged'];
$reportService = new ReportService();
$patientService = new PatientService();

$stats = $reportService->getDashboardStats();
$recentPatients = $patientService->getAll(10);
$doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();

$recentMedicines = $pdo->query("
    SELECT m.*, p.fullname 
    FROM medicines m 
    JOIN patients p ON m.patient_id = p.patient_id 
    ORDER BY m.medicine_id DESC LIMIT 5
")->fetchAll();

$apptByDoctor = $reportService->getAppointmentsByDoctor(30);
$paymentStatus = $reportService->getPaymentStatusBreakdown();
$labStatus = $reportService->getLabStatusBreakdown();
$registrations = $reportService->getPatientRegistrations(7);

// ===== NOTIFICATIONS (from DB) =====
$user_id = $user['user_id'];
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC LIMIT 5
");
$notifications->execute([$user_id]);
$notifications = $notifications->fetchAll();

$unreadCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadCount->execute([$user_id]);
$unreadCount = $unreadCount->fetchColumn();

// ===== CHART DATA =====
$doctorLabels = array_column($apptByDoctor, 'doctor_name') ?: ['No Data'];
$doctorCounts = array_column($apptByDoctor, 'count') ?: [0];
$paymentData = $paymentStatus ?: ['paid' => 0, 'partial' => 0, 'unpaid' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== (all your existing styles – keep them) ===== */
        .stat-card { background: white; border-radius: 1.25rem; padding: 1.5rem 1.25rem; box-shadow: 0 4px 16px rgba(0,0,0,0.04); transition: all 0.25s ease; border: 1px solid rgba(0,0,0,0.02); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.08); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .stat-icon.blue { background: linear-gradient(135deg, #0EA5E9, #2563EB); }
        .stat-icon.green { background: linear-gradient(135deg, #22C55E, #16A34A); }
        .stat-icon.orange { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .stat-icon.purple { background: linear-gradient(135deg, #8B5CF6, #7C3AED); }
        .stat-number { font-size: 2rem; font-weight: 700; color: #1F2937; }
        .stat-label { color: #6B7280; font-size: 0.9rem; font-weight: 500; }
        .trend { font-size: 0.8rem; font-weight: 600; }
        .trend.up { color: #22C55E; }
        .trend.down { color: #EF4444; }
        .badge-status { padding: 0.35rem 0.85rem; border-radius: 2rem; font-weight: 500; font-size: 0.75rem; }
        .badge-status.confirmed { background: #D1FAE5; color: #065F46; }
        .badge-status.pending { background: #FEF3C7; color: #92400E; }
        .badge-status.cancelled { background: #FEE2E2; color: #991B1B; }
        .badge-status.completed { background: #DBEAFE; color: #1E40AF; }
        .btn-primary { background: linear-gradient(135deg, #0EA5E9, #2563EB); border: none; border-radius: 2rem; padding: 0.5rem 1.2rem; font-weight: 600; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); transition: all 0.25s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35); color: white; }
        .btn-outline-primary { border: 1px solid #2563EB; color: #2563EB; border-radius: 2rem; padding: 0.4rem 1.2rem; transition: 0.2s; }
        .btn-outline-primary:hover { background: #2563EB; color: white; }
        .top-nav { background: white; border-radius: 1.25rem; padding: 0.6rem 1.5rem; box-shadow: 0 4px 16px rgba(0,0,0,0.04); margin-bottom: 1.5rem; }
        .top-nav .brand { font-weight: 700; font-size: 1.2rem; color: #1F2937; }
        .top-nav .brand i { color: #2563EB; }
        .top-nav .search-box { position: relative; flex: 1; max-width: 300px; }
        .top-nav .search-box .form-control { border-radius: 2rem; background: #F3F4F6; border: 1px solid transparent; padding-left: 2.5rem; font-size: 0.9rem; transition: 0.2s; }
        .top-nav .search-box .form-control:focus { background: white; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .top-nav .search-box .search-icon { position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .top-nav .nav-icons .icon-btn { background: #F3F4F6; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; color: #4B5563; transition: 0.2s; border: none; position: relative; }
        .top-nav .nav-icons .icon-btn:hover { background: #E5E7EB; color: #1F2937; }
        .top-nav .nav-icons .icon-btn .badge-dot { position: absolute; top: 2px; right: 2px; width: 10px; height: 10px; background: #EF4444; border-radius: 50%; border: 2px solid white; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #0EA5E9, #2563EB); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; }
        .card { border: none; background: white; }
        .rounded-4 { border-radius: 1.25rem; }
        .shadow-sm { box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .chart-container { position: relative; height: 200px; width: 100%; }
        .notif-dropdown { min-width: 320px; padding: 0.5rem 0; }
        .notif-dropdown .notif-item { padding: 0.6rem 1rem; border-bottom: 1px solid #F3F4F6; transition: 0.2s; text-decoration: none; color: #1F2937; display: flex; align-items: center; gap: 0.8rem; }
        .notif-dropdown .notif-item:hover { background: #F9FAFB; }
        .notif-dropdown .notif-item:last-child { border-bottom: none; }
        .notif-dropdown .notif-item .notif-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
        .notif-dropdown .notif-item .notif-icon.payment { background: #D1FAE5; color: #065F46; }
        .notif-dropdown .notif-item .notif-icon.appointment { background: #DBEAFE; color: #1E40AF; }
        .notif-dropdown .notif-item .notif-icon.reminder { background: #FEF3C7; color: #92400E; }
        .notif-dropdown .notif-item .notif-icon.alert { background: #FEE2E2; color: #991B1B; }
        .notif-dropdown .notif-item .notif-text { flex: 1; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7,1fr); gap:4px; text-align:center; margin-top:0.5rem; }
        .calendar-grid .day-header { font-weight:600; color:#6B7280; font-size:0.8rem; padding:0.3rem 0; }
        .calendar-grid .day { padding:0.4rem 0; border-radius:0.5rem; cursor:pointer; font-size:0.85rem; }
        .calendar-grid .day:hover { background:#E5E7EB; }
        .calendar-grid .day.selected { background:#2563EB; color:white; }
        .calendar-grid .day.other-month { color:#D1D5DB; }
        .calendar-grid .day.has-event { font-weight:600; position:relative; }
        .calendar-grid .day.has-event::after { content:''; position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:4px; height:4px; background:#EF4444; border-radius:50%; }
        .search-result-item { padding:0.6rem 1rem; border-bottom:1px solid #F3F4F6; }
        .search-result-item:last-child { border-bottom:none; }
        .search-result-item:hover { background:#F9FAFB; }
        @media (max-width:992px) { .sidebar { transform:translateX(-100%); width:280px; } .sidebar.open { transform:translateX(0); } .main-content { margin-left:0; } .top-nav .search-box { max-width:200px; } }
        @media (max-width:576px) { .main-content { padding:1rem; } .stat-number { font-size:1.5rem; } .stat-card { padding:1rem; } .top-nav .search-box { display:none; } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- ===== TOP NAVIGATION ===== -->
        <div class="top-nav d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <span class="brand"><i class="fas fa-heartbeat me-2"></i>ClinicPro</span>
                <span class="text-muted small d-none d-sm-inline">| Admin Panel</span>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="globalSearch" class="form-control" placeholder="Search patients, appointments..." autocomplete="off">
                <div id="searchResults" class="dropdown-menu w-100" style="display:none; max-height:300px; overflow-y:auto;"></div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small d-none d-md-inline"><?= date('F j, Y') ?> &nbsp;•&nbsp; <?= date('g:i A') ?></span>
                <div class="nav-icons d-flex gap-1">
                    <!-- Calendar -->
                    <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#calendarModal">
                        <i class="fas fa-calendar-alt"></i>
                    </button>
                    <!-- Notifications (no "View all" link) -->
                    <div class="dropdown">
                        <button class="icon-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="notifDropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge-dot" id="unreadBadge" style="display:<?= $unreadCount > 0 ? 'block' : 'none' ?>;"></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notif-dropdown" id="notifMenu">
                            <li><h6 class="dropdown-header fw-bold">Notifications <span class="badge bg-primary rounded-pill" id="unreadCountLabel"><?= $unreadCount ?></span></h6></li>
                            <?php if (empty($notifications)): ?>
                                <li><span class="dropdown-item text-muted">No notifications</span></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                    <li>
                                        <a class="dropdown-item notif-item" href="#" data-id="<?= $n['id'] ?>" data-url="<?= htmlspecialchars($n['url']) ?>" data-unread="<?= $n['is_read'] ? 0 : 1 ?>">
                                            <span class="notif-icon <?= $n['type'] ?>">
                                                <i class="fas <?= $n['type'] == 'payment' ? 'fa-credit-card' : ($n['type'] == 'appointment' ? 'fa-calendar-check' : 'fa-bell') ?>"></i>
                                            </span>
                                            <div class="notif-text">
                                                <div class="small fw-medium"><?= htmlspecialchars($n['message']) ?></div>
                                                <div class="small text-muted"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div>
                                            </div>
                                            <?php if (!$n['is_read']): ?>
                                                <span class="badge bg-primary">New</span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <!-- User Avatar -->
                    <button class="icon-btn" onclick="window.location.href='logout.php'">
                        <span class="avatar" style="width:32px;height:32px;font-size:0.8rem;"><?= strtoupper(substr($user['fullname'],0,2)) ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== Welcome ===== -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0">Welcome back, <?= htmlspecialchars($user['fullname']) ?> 👋</h4>
                <small class="text-muted">Here's what's happening with your clinic today.</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="#patient-form" class="btn btn-primary btn-sm" data-bs-toggle="modal"><i class="fas fa-user-plus me-1"></i> Register Patient</a>
                <a href="#appointment-form" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"><i class="fas fa-calendar-plus me-1"></i> New Appointment</a>
            </div>
        </div>

        <!-- ===== STATS CARDS ===== -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Patients</div>
                        <div class="stat-number"><?= $stats['total_patients'] ?></div>
                        <span class="trend up"><i class="fas fa-arrow-up"></i> 12%</span>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Today's Appointments</div>
                        <div class="stat-number"><?= $stats['pending_appointments'] ?></div>
                        <span class="trend up"><i class="fas fa-arrow-up"></i> 8%</span>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Available Doctors</div>
                        <div class="stat-number"><?= count($doctors) ?></div>
                        <span class="trend down"><i class="fas fa-arrow-down"></i> 2%</span>
                    </div>
                    <div class="stat-icon orange"><i class="fas fa-user-md"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-number">₱<?= number_format($stats['paid_patients'] * 500, 0) ?></div>
                        <span class="trend up"><i class="fas fa-arrow-up"></i> 23%</span>
                    </div>
                    <div class="stat-icon purple"><i class="fas fa-dollar-sign"></i></div>
                </div>
            </div>
        </div>

        <!-- ===== CHARTS ===== -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <h6 class="card-title fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Monthly Appointments</h6>
                        <div class="chart-container">
                            <canvas id="doctorChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <h6 class="card-title fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Appointment Status</h6>
                        <div class="chart-container">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== RECENT APPOINTMENTS TABLE ===== -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-clock me-2 text-primary"></i>Recent Appointments</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPatients)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No appointments found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentPatients as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                                        <td><?= date('M j, Y', strtotime($row['date_registered'])) ?></td>
                                        <td><?= date('g:i A', strtotime($row['date_registered'] . ' +1 hour')) ?></td>
                                        <td>
                                            <?php
                                            $status = $row['status'] ?? 'Pending';
                                            $badgeClass = strtolower($status);
                                            if ($badgeClass == 'confirmed') $badgeClass = 'confirmed';
                                            elseif ($badgeClass == 'pending') $badgeClass = 'pending';
                                            elseif ($badgeClass == 'cancelled') $badgeClass = 'cancelled';
                                            else $badgeClass = 'completed';
                                            ?>
                                            <span class="badge-status <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== MODALS ===== -->
        <!-- Register Patient Modal -->
        <div class="modal fade" id="patient-form" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Register Patient</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="patient_register.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="fullname" class="form-control" required pattern="[A-Za-z\s\-]+">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Age *</label>
                                    <input type="number" name="age" class="form-control" required min="0" max="150">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender *</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select...</option>
                                        <option>Male</option>
                                        <option>Female</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address *</label>
                                <textarea name="address" class="form-control" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Number *</label>
                                <input type="tel" name="contact_number" class="form-control" required pattern="[0-9]{11}" placeholder="09123456789">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Civil Status</label>
                                <select name="civil_status" class="form-select">
                                    <option value="">Select...</option>
                                    <option>Single</option>
                                    <option>Married</option>
                                    <option>Divorced</option>
                                    <option>Widowed</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Citizenship</label>
                                <input type="text" name="citizenship" class="form-control" placeholder="Filipino">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Place of Birth</label>
                                <input type="text" name="place_of_birth" class="form-control" placeholder="City, Province">
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>.</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Register Patient</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointment Modal -->
        <div class="modal fade" id="appointment-form" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>New Appointment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="appointment_process.php" method="POST" id="apptForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <div class="mb-3">
                                <label class="form-label">Patient ID</label>
                                <input type="number" name="patient_id" id="patient_id_input" class="form-control" required>
                                <div id="patient_name_display" class="form-text"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Doctor</label>
                                <select name="doctor_id" id="doctor_select" class="form-select" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $d): ?>
                                        <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Appointment Date</label>
                                <input type="date" name="appointment_date" id="appt_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                                <div id="availability_msg" class="form-text"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Appointment Time</label>
                                <select name="appointment_time" id="appt_time" class="form-select" required disabled>
                                    <option value="">First select a date</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Lab Required?</label>
                                <select name="laboratory_required" class="form-select">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" id="bookBtn" disabled><i class="fas fa-save me-1"></i>Book Appointment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms Modal -->
        <div class="modal fade" id="termsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5>Terms and Conditions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>By registering, you agree to the clinic's policies regarding patient data privacy, appointment scheduling, and payment terms.</p>
                        <p>All information provided will be kept confidential and used solely for medical and administrative purposes.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Modal -->
        <div class="modal fade" id="calendarModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-calendar-alt me-2 text-primary"></i>Calendar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button class="btn btn-sm btn-outline-secondary" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                            <span class="fw-bold" id="currentMonthYear">July 2026</span>
                            <button class="btn btn-sm btn-outline-secondary" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="calendar-grid" id="calendarGrid"></div>
                        <div class="mt-3 text-center">
                            <small class="text-muted">Click on a date to view appointments</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===== CHARTS =====
            const doctorCtx = document.getElementById('doctorChart');
            if (doctorCtx) {
                if (window.doctorChartInstance) window.doctorChartInstance.destroy();
                window.doctorChartInstance = new Chart(doctorCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($doctorLabels) ?>,
                        datasets: [{
                            label: 'Appointments',
                            data: <?= json_encode($doctorCounts) ?>,
                            backgroundColor: ['#0EA5E9', '#2563EB', '#60A5FA', '#93C5FD'],
                            borderRadius: 8,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            const paymentCtx = document.getElementById('paymentChart');
            if (paymentCtx) {
                if (window.paymentChartInstance) window.paymentChartInstance.destroy();
                const paymentData = <?= json_encode($paymentData) ?>;
                window.paymentChartInstance = new Chart(paymentCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'Pending', 'Cancelled'],
                        datasets: [{
                            data: [paymentData.paid || 0, paymentData.partial || 0, paymentData.unpaid || 0],
                            backgroundColor: ['#22C55E', '#F59E0B', '#EF4444'],
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }

            // ===== SEARCH =====
            const searchInput = document.getElementById('globalSearch');
            const searchResults = document.getElementById('searchResults');
            let searchTimeout = null;
            searchInput?.addEventListener('input', function() {
                const query = this.value.trim();
                clearTimeout(searchTimeout);
                if (query.length < 2) { searchResults.style.display = 'none'; return; }
                searchTimeout = setTimeout(() => {
                    fetch(`search.php?q=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(data => {
                            let html = '';
                            if (data.patients.length > 0) {
                                html += `<h6 class="dropdown-header">Patients</h6>`;
                                data.patients.forEach(p => {
                                    html += `<a class="dropdown-item search-result-item" href="patient_overview.php?patient_id=${p.patient_id}">
                                        <i class="fas fa-user me-2"></i> ${p.fullname} (ID: ${p.patient_id})
                                        <span class="text-muted small d-block">${p.age} yrs, ${p.gender}</span>
                                    </a>`;
                                });
                            }
                            if (data.appointments.length > 0) {
                                html += `<h6 class="dropdown-header">Appointments</h6>`;
                                data.appointments.forEach(a => {
                                    html += `<a class="dropdown-item search-result-item" href="#">
                                        <i class="fas fa-calendar-check me-2"></i> ${a.fullname} with ${a.doctor_name}
                                        <span class="text-muted small d-block">${new Date(a.appointment_date).toLocaleString()} - ${a.status}</span>
                                    </a>`;
                                });
                            }
                            if (!html) html = `<span class="dropdown-item text-muted">No results found.</span>`;
                            searchResults.innerHTML = html;
                            searchResults.style.display = 'block';
                        })
                        .catch(() => { searchResults.style.display = 'none'; });
                }, 300);
            });
            document.addEventListener('click', function(e) {
                if (!searchInput?.contains(e.target) && !searchResults?.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });

            // ===== CALENDAR =====
            let currentDate = new Date();
            function renderCalendar(date) {
                const year = date.getFullYear();
                const month = date.getMonth();
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const today = new Date();
                document.getElementById('currentMonthYear').textContent = 
                    new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(date);
                const grid = document.getElementById('calendarGrid');
                grid.innerHTML = '';
                const dayHeaders = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                dayHeaders.forEach(day => {
                    const div = document.createElement('div');
                    div.className = 'day-header';
                    div.textContent = day;
                    grid.appendChild(div);
                });
                for (let i=0; i<firstDay; i++) {
                    const div = document.createElement('div');
                    div.className = 'day other-month';
                    grid.appendChild(div);
                }
                for (let d=1; d<=daysInMonth; d++) {
                    const div = document.createElement('div');
                    div.className = 'day';
                    if (year===today.getFullYear() && month===today.getMonth() && d===today.getDate()) {
                        div.style.background = '#E5E7EB';
                        div.style.fontWeight = 'bold';
                    }
                    if ((d===5||d===12||d===20) && month===6) div.classList.add('has-event');
                    div.textContent = d;
                    div.dataset.date = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                    div.addEventListener('click', function() {
                        document.querySelectorAll('.calendar-grid .day').forEach(el => el.classList.remove('selected'));
                        this.classList.add('selected');
                        alert('Appointments for ' + this.dataset.date + '\n(Open the appointment list to see details)');
                    });
                    grid.appendChild(div);
                }
            }
            renderCalendar(currentDate);
            document.getElementById('prevMonth')?.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth()-1); renderCalendar(currentDate);
            });
            document.getElementById('nextMonth')?.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth()+1); renderCalendar(currentDate);
            });

            // =======================================================
            // ===== APPOINTMENT JAVASCRIPT (availability, time slots) =====
            // =======================================================
            document.getElementById('patient_id_input')?.addEventListener('blur', function() {
                fetch(`api.php?action=patient_name&id=${this.value}`)
                    .then(res => res.json())
                    .then(data => document.getElementById('patient_name_display').innerHTML = data.fullname ? `👤 ${data.fullname}` : '');
            });

            const doctorSelect = document.getElementById('doctor_select');
            const dateInput = document.getElementById('appt_date');
            const timeSelect = document.getElementById('appt_time');
            const msgDiv = document.getElementById('availability_msg');

            function checkAvailability() {
                const doctorId = doctorSelect.value;
                const date = dateInput.value;
                if (!doctorId || !date) {
                    msgDiv.innerHTML = '';
                    timeSelect.innerHTML = '<option value="">Select doctor and date first</option>';
                    timeSelect.disabled = true;
                    return;
                }
                msgDiv.innerHTML = '⏳ Checking...';
                timeSelect.innerHTML = '<option value="">Loading...</option>';
                timeSelect.disabled = true;
                fetch(`api.php?action=availability&doctor_id=${doctorId}&date=${date}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.available) {
                            msgDiv.innerHTML = `<span class="text-success">✅ ${data.remaining} slot(s) available</span>`;
                            return fetch(`api.php?action=time_slots&doctor_id=${doctorId}&date=${date}`);
                        } else {
                            msgDiv.innerHTML = `<span class="text-danger">❌ ${data.reason || 'No slots'}</span>`;
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
                            slots.forEach(s => { html += `<option value="${s}">${s}</option>`; });
                            timeSelect.innerHTML = html;
                            timeSelect.disabled = false;
                        }
                    })
                    .catch(err => console.log(err));
            }

            doctorSelect?.addEventListener('change', checkAvailability);
            dateInput?.addEventListener('change', checkAvailability);

            function checkBookButton() {
                const patientId = document.getElementById('patient_id_input').value;
                const doctor = doctorSelect.value;
                const date = dateInput.value;
                const time = timeSelect.value;
                document.getElementById('bookBtn').disabled = !(patientId && doctor && date && time && time !== '');
            }
            document.getElementById('patient_id_input')?.addEventListener('input', checkBookButton);
            doctorSelect?.addEventListener('change', checkBookButton);
            dateInput?.addEventListener('change', checkBookButton);
            timeSelect?.addEventListener('change', checkBookButton);

            // =======================================================
            // ===== NOTIFICATIONS (mark as read) =====
            // =======================================================
            const notifItems = document.querySelectorAll('.notif-item');
            const unreadBadge = document.getElementById('unreadBadge');
            const unreadCountLabel = document.getElementById('unreadCountLabel');

            function updateUnreadCount() {
                fetch('api.php?action=unread_count')
                    .then(res => res.json())
                    .then(data => {
                        const count = data.count || 0;
                        if (count > 0) {
                            unreadBadge.style.display = 'block';
                            if (unreadCountLabel) unreadCountLabel.textContent = count;
                        } else {
                            unreadBadge.style.display = 'none';
                            if (unreadCountLabel) unreadCountLabel.textContent = '0';
                        }
                    })
                    .catch(() => {});
            }

            notifItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.dataset.id;
                    const url = this.dataset.url;
                    const isUnread = this.dataset.unread == '1';
                    if (isUnread) {
                        fetch(`api.php?action=mark_read&id=${id}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    const badge = this.querySelector('.badge');
                                    if (badge) badge.remove();
                                    this.dataset.unread = '0';
                                    updateUnreadCount();
                                }
                            })
                            .catch(() => {});
                    }
                    setTimeout(() => { window.location.href = url; }, 200);
                });
            });

            updateUnreadCount();
        });
    </script>
</body>
</html>