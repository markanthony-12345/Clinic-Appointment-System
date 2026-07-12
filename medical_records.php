<?php
require_once 'config.php';
requireLogin();

// Fetch all patients with their latest lab, medicine, and clearance status
$records = $pdo->query("
    SELECT 
        p.patient_id,
        p.fullname,
        p.age,
        p.gender,
        p.contact_number,
        (SELECT status FROM laboratory WHERE patient_id = p.patient_id ORDER BY lab_id DESC LIMIT 1) AS latest_lab,
        (SELECT status FROM medicines WHERE patient_id = p.patient_id ORDER BY medicine_id DESC LIMIT 1) AS latest_medicine,
        (SELECT 
            CASE 
                WHEN amount_paid >= total_amount THEN 'Cleared'
                WHEN amount_paid > 0 THEN 'Partial'
                ELSE 'Unpaid'
            END
         FROM payments WHERE patient_id = p.patient_id LIMIT 1) AS payment_status
    FROM patients p
    WHERE p.is_archived = 0
    ORDER BY p.fullname ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1.25rem; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.03); padding: 1.25rem 1.5rem; }
        .btn-primary {
            background: linear-gradient(135deg, #0EA5E9, #2563EB);
            border: none;
            border-radius: 2rem;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.25s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
            color: white;
        }
        .btn-outline-primary {
            border: 1px solid #2563EB;
            color: #2563EB;
            border-radius: 2rem;
            padding: 0.4rem 1.2rem;
            transition: 0.2s;
        }
        .btn-outline-primary:hover {
            background: #2563EB;
            color: white;
        }
        .badge-status {
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .badge-status.cleared { background: #D1FAE5; color: #065F46; }
        .badge-status.partial { background: #FEF3C7; color: #92400E; }
        .badge-status.unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-status.completed { background: #DBEAFE; color: #1E40AF; }
        .badge-status.pending { background: #FEF3C7; color: #92400E; }
        .badge-status.not-taken { background: #F3F4F6; color: #6B7280; }
        .table th { font-weight: 600; color: #4B5563; border-bottom-width: 2px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><i class="fas fa-file-medical me-2 text-primary"></i>Medical Records</h4>
            <a href="dashboard.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-list me-2"></i>Patient Records Summary</span>
                <span class="badge bg-primary rounded-pill"><?= count($records) ?> patients</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Contact</th>
                                <th>Latest Lab</th>
                                <th>Latest Medicine</th>
                                <th>Payment Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No patient records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($records as $row):
                                    $labBadge = strtolower(str_replace(' ', '-', $row['latest_lab'] ?? 'Not Yet Taken'));
                                    $labBadge = in_array($labBadge, ['completed', 'ongoing', 'not-yet-taken']) ? $labBadge : 'not-taken';
                                    $medBadge = strtolower(str_replace(' ', '-', $row['latest_medicine'] ?? 'Not Taken'));
                                    $medBadge = in_array($medBadge, ['taken', 'not-taken']) ? $medBadge : 'not-taken';
                                    $paymentBadge = strtolower($row['payment_status'] ?? 'Unpaid');
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                                        <td><?= $row['age'] ?></td>
                                        <td><?= htmlspecialchars($row['gender'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['contact_number'] ?? '') ?></td>
                                        <td><span class="badge-status <?= $labBadge ?>"><?= htmlspecialchars($row['latest_lab'] ?? 'Not Yet Taken') ?></span></td>
                                        <td><span class="badge-status <?= $medBadge ?>"><?= htmlspecialchars($row['latest_medicine'] ?? 'Not Taken') ?></span></td>
                                        <td><span class="badge-status <?= $paymentBadge ?>"><?= $row['payment_status'] ?? 'Unpaid' ?></span></td>
                                        <td>
                                            <a href="patient_overview.php?patient_id=<?= $row['patient_id'] ?>" class="btn btn-primary btn-sm">View Full Record</a>
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