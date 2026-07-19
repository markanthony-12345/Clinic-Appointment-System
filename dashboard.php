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

$transactionService = new TransactionService();
$txnStats = $transactionService->getStats();
$recentTransactions = $transactionService->getRecentTransactions(5);

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .badge-status { padding: 0.35rem 0.85rem; border-radius: 2rem; font-weight: 500; font-size: 0.75rem; }
        .badge-status.paid { background: #D1FAE5; color: #065F46; }
        .badge-status.partial { background: #FEF3C7; color: #92400E; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.refunded { background: #E5E7EB; color: #6B7280; }
        .badge-status.pending { background: #FEF3C7; color: #92400E; }
        .badge-status.cancelled { background: #FEE2E2; color: #991B1B; }
        .badge-status.completed { background: #D1FAE5; color: #065F46; }
        .badge-status.confirmed { background: #DBEAFE; color: #1E40AF; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .calendar-grid .day { text-align: center; padding: 6px 0; border-radius: 4px; cursor: pointer; }
        .calendar-grid .day-header { text-align: center; font-weight: 600; font-size: 0.8rem; color: #6B7280; padding: 6px 0; }
        .calendar-grid .day:hover { background: #E5E7EB; }
        .calendar-grid .day.selected { background: #2563EB; color: white; }
        .calendar-grid .day.other-month { color: #D1D5DB; }
        .calendar-grid .day.has-event { background: #DBEAFE; }
        .patient-info-box { background: #f8f9fa; border-radius: 0.75rem; padding: 0.75rem 1rem; margin-top: 0.5rem; border-left: 3px solid #2563EB; display: none; }
        .patient-info-box.active { display: block; }
        .slot-available { color: #16A34A; font-weight: 500; }
        .slot-full { color: #DC2626; font-weight: 500; }
        .slot-limited { color: #F59E0B; font-weight: 500; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="d-flex align-items-center gap-3">
            <span class="brand"><i class="fas fa-heartbeat me-2"></i>ClinicPro</span>
            <span class="text-muted small d-none d-sm-inline">| Admin Panel</span>
        </div>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="globalSearch" class="form-control" placeholder="Search patients, appointments..." autocomplete="off">
            <div id="searchResults" class="dropdown-menu w-100" style="display:none; max-height:300px; overflow-y:auto;"></div>
        </div>
        <div class="nav-icons">
            <span class="text-muted small d-none d-md-inline"><?= date('F j, Y') ?> &nbsp;•&nbsp; <?= date('g:i A') ?></span>
            <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#calendarModal">
                <i class="fas fa-calendar-alt"></i>
            </button>
            <div class="dropdown">
                <button class="icon-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="notifDropdown">
                    <i class="fas fa-bell"></i>
                    <span class="badge-dot" id="unreadBadge" style="display:<?= $unreadCount > 0 ? 'block' : 'none' ?>;"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notif-dropdown" id="notifMenu" style="min-width:320px; padding:0.5rem 0;">
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
            <button class="icon-btn" onclick="window.location.href='logout.php'">
                <span class="avatar" style="width:32px;height:32px;font-size:0.8rem;"><?= strtoupper(substr($user['fullname'],0,2)) ?></span>
            </button>
        </div>
    </div>

    <!-- Welcome -->
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

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Patients</div>
                    <div class="stat-number"><?= $stats['total_patients'] ?></div>
                    <span class="trend up text-success small"><i class="fas fa-arrow-up"></i> 12%</span>
                </div>
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Today's Appointments</div>
                    <div class="stat-number"><?= $stats['pending_appointments'] ?></div>
                    <span class="trend up text-success small"><i class="fas fa-arrow-up"></i> 8%</span>
                </div>
                <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Available Doctors</div>
                    <div class="stat-number"><?= count($doctors) ?></div>
                    <span class="trend down text-danger small"><i class="fas fa-arrow-down"></i> 2%</span>
                </div>
                <div class="stat-icon orange"><i class="fas fa-user-md"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-number">₱<?= number_format($stats['paid_patients'] * 500, 0) ?></div>
                    <span class="trend up text-success small"><i class="fas fa-arrow-up"></i> 23%</span>
                </div>
                <div class="stat-icon purple"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Today's Transactions</div>
                    <div class="stat-number"><?= $txnStats['today_count'] ?></div>
                    <span class="trend up text-success small"><i class="fas fa-arrow-up"></i> ₱<?= number_format($txnStats['today_revenue'], 2) ?></span>
                </div>
                <div class="stat-icon purple"><i class="fas fa-receipt"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-number"><?= $txnStats['pending_payments'] ?></div>
                    <span class="trend down text-danger small"><i class="fas fa-arrow-down"></i> Unpaid</span>
                </div>
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-title fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Monthly Appointments</h6>
                    <div class="chart-container">
                        <canvas id="doctorChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-title fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Appointment Status</h6>
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Appointments -->
    <div class="card shadow-sm">
        <div class="card-header">
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

    <!-- Recent Transactions -->
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h6 class="fw-bold mb-0"><i class="fas fa-receipt me-2 text-primary"></i>Recent Transactions</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Transaction #</th>
                            <th>Patient</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTransactions)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentTransactions as $txn): ?>
                                <tr>
                                    <td><a href="billing_view.php?id=<?= $txn['id'] ?>"><?= htmlspecialchars($txn['transaction_number']) ?></a></td>
                                    <td><?= htmlspecialchars($txn['patient_name'] ?? 'N/A') ?></td>
                                    <td>₱<?= number_format($txn['total_amount'], 2) ?></td>
                                    <td><span class="badge-status <?= strtolower(str_replace(' ', '-', $txn['payment_status'])) ?>"><?= $txn['payment_status'] ?></span></td>
                                    <td><?= date('M j, Y g:i A', strtotime($txn['transaction_date'])) ?></td>
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

    <!-- ====== APPOINTMENT MODAL (Updated) ====== -->
    <div class="modal fade" id="appointment-form" tabindex="-1">
        <div class="modal-dialog modal-lg">
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
                            <div id="patient-info-box" class="patient-info-box">
                                <div class="row small">
                                    <div class="col-6"><strong>Name:</strong> <span id="p_name">-</span></div>
                                    <div class="col-6"><strong>Age:</strong> <span id="p_age">-</span></div>
                                    <div class="col-6"><strong>Gender:</strong> <span id="p_gender">-</span></div>
                                    <div class="col-6"><strong>Contact:</strong> <span id="p_contact">-</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Doctor</label>
                            <select name="doctor_id" id="doctor_select" class="form-select" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['doctor_id'] ?>" data-specialty="<?= htmlspecialchars($d['specialization']) ?>">
                                        <?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" name="appointment_date" id="appt_date" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                            <div id="availability_msg" class="form-text"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Appointment Time</label>
                            <select name="appointment_time" id="appt_time" class="form-select" required disabled>
                                <option value="">Select a date and doctor first</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lab Required?</label>
                            <select name="laboratory_required" id="lab_required" class="form-select">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>

                        <!-- ===== SUGGESTED LABORATORY TESTS ONLY ===== -->
                        <div id="lab-suggestions-panel" style="display: none; border: 1px solid #dee2e6; border-radius: 0.75rem; padding: 1rem; background: #f8f9fa; margin-bottom: 1rem;">
                            <h6 class="fw-bold"><i class="fas fa-flask me-2 text-primary"></i>Suggested Laboratory Tests</h6>
                            <div id="lab-suggestion-list" class="row g-2">
                                <!-- dynamically filled -->
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-lab">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-all-lab">Clear All</button>
                                <span class="text-muted ms-2" id="lab-note"></span>
                            </div>
                        </div>

                        <!-- Hidden fields (medications removed) -->
                        <input type="hidden" name="lab_tests" id="selected_lab_tests" value="">
                        <input type="hidden" name="lab_fee_total" id="lab_fee_total" value="0">

                        <button type="submit" class="btn btn-primary" id="bookBtn" disabled><i class="fas fa-save me-1"></i>Book Appointment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End Appointment Modal -->

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

    <!-- Appointments by Date Modal -->
    <div class="modal fade" id="appointmentsByDateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-day me-2 text-primary"></i>Appointments for <span id="selectedDateLabel"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentsList">
                    <p class="text-muted">Loading...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                div.textContent = d;
                div.dataset.date = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                div.addEventListener('click', function() {
                    document.querySelectorAll('.calendar-grid .day').forEach(el => el.classList.remove('selected'));
                    this.classList.add('selected');
                    const date = this.dataset.date;
                    fetch(`api.php?action=appointments_by_date&date=${date}`)
                        .then(res => {
                            if (!res.ok) {
                                return res.json().then(errData => {
                                    throw new Error(errData.error || `HTTP ${res.status}: ${res.statusText}`);
                                }).catch(() => {
                                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                                });
                            }
                            return res.json();
                        })
                        .then(data => {
                            const modal = new bootstrap.Modal(document.getElementById('appointmentsByDateModal'));
                            document.getElementById('selectedDateLabel').textContent = date;
                            const listContainer = document.getElementById('appointmentsList');
                            if (data.error) {
                                listContainer.innerHTML = `<p class="text-danger">⚠️ ${data.error}</p>`;
                            } else if (data.length === 0) {
                                listContainer.innerHTML = '<p class="text-muted">No appointments on this day.</p>';
                            } else {
                                let html = '<ul class="list-group">';
                                data.forEach(app => {
                                    const time = new Date(app.appointment_date).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                                    const statusClass = app.status.toLowerCase();
                                    html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>${app.patient_name}</strong>
                                                    <span class="text-muted ms-2">${time}</span>
                                                    <br><small class="text-muted">Dr. ${app.doctor_name}</small>
                                                </div>
                                                <span class="badge-status ${statusClass}">${app.status}</span>
                                             </li>`;
                                });
                                html += '</ul>';
                                listContainer.innerHTML = html;
                            }
                            modal.show();
                        })
                        .catch(error => {
                            alert('Failed to load appointments: ' + error.message);
                        });
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

        // ============================================================
        // ===== APPOINTMENT JAVASCRIPT (UPDATED) =====
        // ============================================================

        // ---- Patient Info ----
        const patientInput = document.getElementById('patient_id_input');
        const patientInfoBox = document.getElementById('patient-info-box');
        const pName = document.getElementById('p_name');
        const pAge = document.getElementById('p_age');
        const pGender = document.getElementById('p_gender');
        const pContact = document.getElementById('p_contact');
        const patientNameDisplay = document.getElementById('patient_name_display');

        function fetchPatientInfo(pid) {
            if (!pid) {
                patientNameDisplay.innerHTML = '';
                patientInfoBox.classList.remove('active');
                return;
            }
            fetch(`billing_ajax.php?action=get_patient_info&patient_id=${pid}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const p = data.data.patient;
                        pName.textContent = p.fullname;
                        pAge.textContent = p.age;
                        pGender.textContent = p.gender || 'N/A';
                        pContact.textContent = p.contact_number || 'N/A';
                        patientInfoBox.classList.add('active');
                        patientNameDisplay.innerHTML = `👤 ${p.fullname}`;
                    } else {
                        patientNameDisplay.innerHTML = '❌ Patient not found';
                        patientInfoBox.classList.remove('active');
                    }
                })
                .catch(err => {
                    patientNameDisplay.innerHTML = '⚠️ Error fetching patient';
                    patientInfoBox.classList.remove('active');
                });
        }

        patientInput?.addEventListener('blur', function() {
            const pid = this.value.trim();
            fetchPatientInfo(pid);
        });

        // ---- Availability & Time Slots (FIXED) ----
        const doctorSelect = document.getElementById('doctor_select');
        const dateInput = document.getElementById('appt_date');
        const timeSelect = document.getElementById('appt_time');
        const msgDiv = document.getElementById('availability_msg');

        const apptModal = document.getElementById('appointment-form');
        apptModal?.addEventListener('show.bs.modal', function() {
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
            dateInput.min = today;
            if (doctorSelect.value && dateInput.value) {
                checkAvailability();
            }
        });

        function checkAvailability() {
            const doctorId = doctorSelect.value;
            const date = dateInput.value;
            if (!doctorId || !date) {
                msgDiv.innerHTML = '';
                timeSelect.innerHTML = '<option value="">Select doctor and date first</option>';
                timeSelect.disabled = true;
                return;
            }

            msgDiv.innerHTML = '⏳ Checking availability...';
            timeSelect.innerHTML = '<option value="">Loading...</option>';
            timeSelect.disabled = true;

            // 1. Check availability
            fetch(`api.php?action=availability&doctor_id=${doctorId}&date=${date}`)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status} - ${res.statusText}`);
                    return res.json();
                })
                .then(data => {
                    console.log('Availability response:', data);
                    if (data.available) {
                        msgDiv.innerHTML = `<span class="text-success">✅ ${data.remaining} slot(s) available</span>`;
                        // 2. Fetch time slots
                        return fetch(`api.php?action=time_slots&doctor_id=${doctorId}&date=${date}`);
                    } else {
                        msgDiv.innerHTML = `<span class="text-danger">❌ ${data.reason || 'No slots available'}</span>`;
                        timeSelect.innerHTML = '<option value="">No available times</option>';
                        timeSelect.disabled = true;
                        throw new Error('No slots');
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status} - ${res.statusText}`);
                    return res.json();
                })
                .then(slots => {
                    console.log('Time slots response:', slots);
                    if (!slots || !slots.length) {
                        timeSelect.innerHTML = '<option value="">No available times</option>';
                        timeSelect.disabled = true;
                        msgDiv.innerHTML = `<span class="text-danger">❌ No time slots available for this date.</span>`;
                    } else {
                        let html = '<option value="">Select time</option>';
                        slots.forEach(slot => {
                            html += `<option value="${slot}" class="slot-available">${slot}</option>`;
                        });
                        timeSelect.innerHTML = html;
                        timeSelect.disabled = false;
                        msgDiv.innerHTML = `<span class="text-success">✅ ${slots.length} time slot(s) available</span>`;
                    }
                })
                .catch(err => {
                    console.error('Error fetching time slots:', err);
                    msgDiv.innerHTML = `<span class="text-danger">❌ Error loading time slots: ${err.message}</span>`;
                    timeSelect.innerHTML = '<option value="">Error loading times</option>';
                    timeSelect.disabled = true;
                });
        }

        doctorSelect?.addEventListener('change', checkAvailability);
        dateInput?.addEventListener('change', checkAvailability);

        // Enable/disable book button
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

        // ---- Lab Suggestions (Only) ----
        const labSuggestionsPanel = document.getElementById('lab-suggestions-panel');
        const labList = document.getElementById('lab-suggestion-list');
        const labNote = document.getElementById('lab-note');
        const labFeeTotal = document.getElementById('lab_fee_total');

        function updateLabTotal() {
            const checks = document.querySelectorAll('.lab-suggestion-checkbox:checked');
            let total = 0;
            checks.forEach(cb => total += parseFloat(cb.dataset.price) || 0);
            labFeeTotal.value = total.toFixed(2);
        }

        doctorSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const specialty = selectedOption.getAttribute('data-specialty');
            if (!specialty) {
                labSuggestionsPanel.style.display = 'none';
                return;
            }
            fetch(`recommendation_ajax.php?action=get_lab_tests&specialty=${encodeURIComponent(specialty)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        labList.innerHTML = '';
                        data.tests.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'col-md-6';
                            div.innerHTML = `
                                <div class="form-check">
                                    <input class="form-check-input lab-suggestion-checkbox" type="checkbox" value="${item.name}" data-price="${item.price}" id="lab_${item.name.replace(/\s/g, '_')}">
                                    <label class="form-check-label" for="lab_${item.name.replace(/\s/g, '_')}">${item.name} (₱${item.price})</label>
                                </div>
                            `;
                            labList.appendChild(div);
                        });
                        labNote.textContent = data.note || '';
                        document.querySelectorAll('.lab-suggestion-checkbox').forEach(cb => cb.addEventListener('change', updateLabTotal));
                        updateLabTotal();
                        labSuggestionsPanel.style.display = 'block';
                    } else {
                        labSuggestionsPanel.style.display = 'none';
                    }
                })
                .catch(err => console.error(err));
        });

        document.getElementById('select-all-lab')?.addEventListener('click', function() {
            document.querySelectorAll('.lab-suggestion-checkbox').forEach(cb => cb.checked = true);
            updateLabTotal();
        });
        document.getElementById('clear-all-lab')?.addEventListener('click', function() {
            document.querySelectorAll('.lab-suggestion-checkbox').forEach(cb => cb.checked = false);
            updateLabTotal();
        });

        document.getElementById('apptForm')?.addEventListener('submit', function(e) {
            const labChecks = document.querySelectorAll('.lab-suggestion-checkbox:checked');
            const labValues = Array.from(labChecks).map(cb => cb.value);
            document.getElementById('selected_lab_tests').value = labValues.join(', ');
        });

        // ===== NOTIFICATIONS =====
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