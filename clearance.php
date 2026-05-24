<?php
require_once 'config.php';
requireLogin();

$pid = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$pdata = null;
$consult_done = $lab_done = $med_done = $pay_done = false;
$cleared = false;

if ($pid > 0) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->execute([$pid]);
    $pdata = $stmt->fetch();

    if ($pdata) {
        // Consultation
        $consult = $pdo->prepare("SELECT 1 FROM appointments WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $consult->execute([$pid]);
        $consult_done = $consult->rowCount() > 0;

        // Laboratory
        $lab = $pdo->prepare("SELECT 1 FROM laboratory WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $lab->execute([$pid]);
        $lab_done = $lab->rowCount() > 0;

        // Medicine
        $med = $pdo->prepare("SELECT 1 FROM medicines WHERE patient_id = ? AND status = 'Taken' LIMIT 1");
        $med->execute([$pid]);
        $med_done = $med->rowCount() > 0;

        // Payment
        $pay = $pdo->prepare("SELECT total_amount, amount_paid FROM payments WHERE patient_id = ? LIMIT 1");
        $pay->execute([$pid]);
        $pay_data = $pay->fetch();
        if ($pay_data) {
            $balance = $pay_data['total_amount'] - $pay_data['amount_paid'];
            $pay_done = ($balance <= 0);
        }

        $cleared = $consult_done && $lab_done && $med_done && $pay_done;
    }
}
$is_admin = ($_SESSION['user_logged']['role'] === 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Clearance</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .btn.danger { background: #e74c3c; color: #fff; border: none; }
        .btn.danger:hover { background: #c0392b; }
        .reset-section { margin-top: 18px; padding-top: 14px; border-top: 1px solid #eee; }
        .reset-warning { font-size: 0.85em; color: #888; margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1><i class="fas fa-check-circle"></i> Patient Clearance</h1>
        <a href="dashboard.php" class="btn primary">← Back</a>
    </header>
    <main>
        <!-- Search -->
        <div class="card">
            <h3>Search Patient</h3>
            <form method="GET">
                <input type="number" name="patient_id" placeholder="Patient ID" value="<?= $pid ?: '' ?>" required>
                <button type="submit" class="btn primary">Check</button>
            </form>
        </div>

        <?php if ($pid > 0 && !$pdata): ?>
            <div class="card alert error">Patient not found</div>
        <?php elseif ($pdata): ?>
            <div class="card">
                <h3><?= htmlspecialchars($pdata['fullname']) ?> (ID: <?= $pid ?>)</h3>

                <div class="checklist-section">
                    <div class="checklist-item">
                        <label>🩺 Consultation Completed</label>
                        <input type="checkbox" <?= $consult_done ? 'checked' : '' ?>
                               onchange="toggleItem('consult', <?= $pid ?>, this.checked)">
                    </div>
                    <div class="checklist-item">
                        <label>🔬 Laboratory Completed</label>
                        <input type="checkbox" <?= $lab_done ? 'checked' : '' ?>
                               onchange="toggleItem('lab', <?= $pid ?>, this.checked)">
                    </div>
                    <div class="checklist-item">
                        <label>💊 Medicine Taken</label>
                        <input type="checkbox" <?= $med_done ? 'checked' : '' ?>
                               onchange="toggleItem('medicine', <?= $pid ?>, this.checked)">
                    </div>
                    <div class="checklist-item">
                        <label>💰 Payment Completed</label>
                        <input type="checkbox" <?= $pay_done ? 'checked' : '' ?>
                               onchange="toggleItem('payment', <?= $pid ?>, this.checked)">
                    </div>
                </div>

                <div class="clearance-status">
                    <h4>Final Status:
                        <span class="<?= $cleared ? 'completed' : 'cancelled' ?>">
                            <?= $cleared ? '✅ CLEARED' : '❌ NOT CLEARED' ?>
                        </span>
                    </h4>
                    <?php if ($cleared): ?>
                        <button class="btn primary" onclick="printClearance(<?= $pid ?>)">
                            <i class="fas fa-print"></i> Print Clearance
                        </button>
                    <?php endif; ?>
                </div>

                <!-- RESET SECTION — Admin only -->
                <?php if ($is_admin): ?>
                <div class="reset-section">
                    <p class="reset-warning">⚠️ Admin only: This will reset all records of this patient back to their default state (Pending / Not Taken / Unpaid).</p>
                    <button class="btn danger" onclick="resetPatient(<?= $pid ?>, '<?= htmlspecialchars($pdata['fullname']) ?>')">
                        ↩ Reset / Undo All Records
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="assets/script.js"></script>
<script>
function toggleItem(type, patientId, checked) {
    fetch(`update_clearance_item.php?type=${type}&patient_id=${patientId}&value=${checked}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert("Update failed: " + (data.message || "Unknown error"));
        });
}
function printClearance(patientId) {
    window.open(`print_clearance.php?patient_id=${patientId}`, '_blank');
}
function resetPatient(patientId, name) {
    if (!confirm(`Are you sure you want to RESET all records of "${name}"?\n\nThis will:\n- Set all appointments back to Pending\n- Set all lab results to Not Yet Taken\n- Set all medicines to Not Taken\n- Clear payment back to Unpaid\n\nThis cannot be undone.`)) return;

    fetch(`reset_patient.php?patient_id=${patientId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Records have been reset successfully.');
                location.reload();
            } else {
                alert('Reset failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error. Please try again.'));
}
</script>
</body>
</html>
