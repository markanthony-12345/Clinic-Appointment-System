<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-heartbeat brand-icon"></i>
        <div>
            <span class="brand-text">ClinicPro</span>
            <span class="brand-sub">Admin Panel</span>
        </div>
    </div>
    <ul class="sidebar-nav">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'patient_list.php' ? 'active' : '' ?>" href="patient_list.php">
                <i class="fas fa-users"></i> Patients
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'doctor_schedule.php' ? 'active' : '' ?>" href="doctor_schedule.php">
                <i class="fas fa-calendar-check"></i> Schedules
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'laboratory.php' ? 'active' : '' ?>" href="laboratory.php">
                <i class="fas fa-flask"></i> Laboratory
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'medicine.php' ? 'active' : '' ?>" href="medicine.php">
                <i class="fas fa-pills"></i> Medicines
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active' : '' ?>" href="billing.php">
                <i class="fas fa-file-invoice-dollar"></i> Transactions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'medical_records.php' ? 'active' : '' ?>" href="medical_records.php">
                <i class="fas fa-file-medical"></i> Medical Records
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                <i class="fas fa-chart-line"></i> Reports
            </a>
        </li>
        <li class="nav-item" style="flex:1;"></li>
        <li class="nav-item">
            <a class="nav-link text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
    <div class="sidebar-footer">
        <small>© <?= date('Y') ?> ClinicPro v2.0</small>
    </div>
</nav>