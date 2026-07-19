<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin" && $_SESSION["user_logged"]["role"] !== "LabStaff") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $lab_id = (int)$_POST['lab_id'];
    $new_status = $_POST['status'];
    if (in_array($new_status, ['In Progress', 'Completed', 'Cancelled'])) {
        $stmt = $pdo->prepare("UPDATE laboratory SET status = ? WHERE lab_id = ?");
        $stmt->execute([$new_status, $lab_id]);

        if ($new_status === 'Completed') {
            $getLab = $pdo->prepare("SELECT patient_id FROM laboratory WHERE lab_id = ?");
            $getLab->execute([$lab_id]);
            $patient = $getLab->fetch();
            if ($patient) {
                $notif = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, message, url, is_read, created_at)
                    VALUES (?, 'lab', 'Your laboratory test (ID: $lab_id) has been completed.', 'laboratory.php', 0, NOW())
                ");
                $notif->execute([$patient['patient_id']]);
            }
        }
        header("Location: laboratory.php?success=status_updated");
        exit;
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$filter_doctor = (int)($_GET['doctor'] ?? 0);
$filter_procedure = trim($_GET['procedure'] ?? '');
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_has_appointment = isset($_GET['has_appointment']) ? (int)$_GET['has_appointment'] : -1;

$where = "1=1";
$params = [];
if ($search) {
    $where .= " AND (p.fullname LIKE ? OR d.doctor_name LIKE ? OR l.procedure_name LIKE ? OR l.laboratory_type LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
}
if ($filter_doctor) {
    $where .= " AND l.doctor_id = ?";
    $params[] = $filter_doctor;
}
if ($filter_procedure) {
    $where .= " AND (l.procedure_name LIKE ? OR l.laboratory_type LIKE ?)";
    $params[] = '%' . $filter_procedure . '%';
    $params[] = '%' . $filter_procedure . '%';
}
if ($filter_date_from) {
    $where .= " AND l.appointment_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where .= " AND l.appointment_date <= ?";
    $params[] = $filter_date_to;
}
if ($filter_status) {
    $where .= " AND l.status = ?";
    $params[] = $filter_status;
}
if ($filter_has_appointment === 1) {
    $where .= " AND l.appointment_id IS NOT NULL";
} elseif ($filter_has_appointment === 0) {
    $where .= " AND l.appointment_id IS NULL";
}

$sql = "
    SELECT l.*, 
           p.fullname AS patient_name, 
           d.doctor_name,
           a.appointment_id AS appt_id
    FROM laboratory l
    LEFT JOIN patients p ON l.patient_id = p.patient_id
    LEFT JOIN doctors d ON l.doctor_id = d.doctor_id
    LEFT JOIN appointments a ON l.appointment_id = a.appointment_id
    WHERE $where
    ORDER BY l.appointment_date DESC, l.appointment_time DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$doctors = $pdo->query("SELECT doctor_id, doctor_name FROM doctors ORDER BY doctor_name")->fetchAll();

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laboratory_records.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Lab ID', 'Appointment ID', 'Patient', 'Doctor', 'Procedure', 'Fee', 'Date', 'Time', 'Status', 'Payment Status']);
    foreach ($records as $r) {
        fputcsv($output, [
            $r['lab_id'],
            $r['appointment_id'] ?? '',
            $r['patient_name'] ?? '',
            $r['doctor_name'] ?? '',
            $r['procedure_name'] ?? $r['laboratory_type'] ?? '',
            $r['procedure_fee'] ?? 0,
            $r['appointment_date'] ?? '',
            $r['appointment_time'] ?? '',
            $r['status'] ?? 'Pending',
            $r['payment_status'] ?? 'Unpaid'
        ]);
    }
    fclose($output);
    exit;
}

$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1.25rem; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.03); padding: 1.25rem 1.5rem; font-weight: 600; color: #1e4a6e; }
        .btn-primary { background: linear-gradient(135deg, #0EA5E9, #2563EB); border: none; border-radius: 2rem; padding: 0.5rem 1.2rem; font-weight: 600; color: white; box-shadow: 0 4px 12px rgba(37,99,235,0.25); transition: all 0.25s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,99,235,0.35); color: white; }
        .badge-status { padding: 0.35rem 0.85rem; border-radius: 2rem; font-weight: 500; font-size: 0.75rem; }
        .badge-status.pending { background: #FEF3C7; color: #92400E; }
        .badge-status.in-progress { background: #DBEAFE; color: #1E40AF; }
        .badge-status.completed { background: #D1FAE5; color: #065F46; }
        .badge-status.cancelled { background: #FEE2E2; color: #991B1B; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.paid { background: #D1FAE5; color: #065F46; }
        .table th { font-weight: 600; color: #4a6f8c; border-bottom: 2px solid #e2e8f0; }
        .filter-bar { background: #f8f9fa; border-radius: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 1rem; }
        .btn-sm-action { border-radius: 2rem; padding: 0.2rem 0.8rem; }
        .filter-checkbox { margin-top: 1.7rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-flask me-2"></i>Laboratory Records</h1>
                <div>
                    <a href="?export=1&<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>Status updated successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="card mb-4">
                <div class="card-body filter-bar">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small">Search</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Patient, doctor, procedure..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Doctor</label>
                            <select name="doctor" class="form-select form-select-sm">
                                <option value="">All</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['doctor_id'] ?>" <?= $filter_doctor == $d['doctor_id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['doctor_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Procedure</label>
                            <input type="text" name="procedure" class="form-control form-control-sm" placeholder="e.g., CBC" value="<?= htmlspecialchars($filter_procedure) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Date From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $filter_date_from ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Date To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $filter_date_to ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="In Progress" <?= $filter_status == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= $filter_status == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Cancelled" <?= $filter_status == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i></button>
                        </div>
                    </form>
                    <div class="row mt-2">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="has_appointment" value="1" id="hasAppt" <?= $filter_has_appointment === 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label small" for="hasAppt">Only with Appointment</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="has_appointment" value="0" id="noAppt" <?= $filter_has_appointment === 0 ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label small" for="noAppt">Only without Appointment</label>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="laboratory.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>All Laboratory Records</span>
                    <span class="badge bg-primary rounded-pill"><?= count($records) ?></span>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Lab ID</th>
                                <th>Appt ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Procedure</th>
                                <th>Fee</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr><td colspan="11" class="text-center text-muted py-4">No laboratory records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($records as $r): ?>
                                    <tr>
                                        <td><?= $r['lab_id'] ?></td>
                                        <td><?= $r['appointment_id'] ?? '—' ?></td>
                                        <td><?= htmlspecialchars($r['patient_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['doctor_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($r['procedure_name'] ?? $r['laboratory_type'] ?? '') ?></td>
                                        <td>₱<?= number_format($r['procedure_fee'] ?? 0, 2) ?></td>
                                        <td><?= isset($r['appointment_date']) && $r['appointment_date'] ? date('M j, Y', strtotime($r['appointment_date'])) : '—' ?></td>
                                        <td><?= isset($r['appointment_time']) && $r['appointment_time'] ? date('g:i A', strtotime($r['appointment_time'])) : '—' ?></td>
                                        <td>
                                            <span class="badge-status <?= strtolower(str_replace(' ', '-', $r['status'] ?? 'Pending')) ?>">
                                                <?= $r['status'] ?? 'Pending' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?= strtolower($r['payment_status'] ?? 'Unpaid') ?>">
                                                <?= $r['payment_status'] ?? 'Unpaid' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline-block">
                                                <input type="hidden" name="lab_id" value="<?= $r['lab_id'] ?>">
                                                <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                    <option value="">Update</option>
                                                    <option value="In Progress" <?= ($r['status'] ?? '') == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="Completed" <?= ($r['status'] ?? '') == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                    <option value="Cancelled" <?= ($r['status'] ?? '') == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            <a href="lab_print_request.php?lab_id=<?= $r['lab_id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="Print Request"><i class="fas fa-print"></i></a>
                                            <a href="lab_print_report.php?lab_id=<?= $r['lab_id'] ?>" target="_blank" class="btn btn-sm btn-info" title="Print Report"><i class="fas fa-file-alt"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>