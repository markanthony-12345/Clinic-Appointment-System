<?php
require_once 'config.php';
requireLogin();

$pid = $_GET['patient_id'] ?? 0;
if (!$pid) die("No patient ID provided.");

// Fetch patient
$stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$pid]);
$patient = $stmt->fetch();
if (!$patient) die("Invalid patient.");

// 1. Consultation
$consult = $pdo->prepare("SELECT 1 FROM appointments WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
$consult->execute([$pid]);
$consult_done = $consult->rowCount() > 0;

// 2. Laboratory
$lab = $pdo->prepare("SELECT 1 FROM laboratory WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
$lab->execute([$pid]);
$lab_done = $lab->rowCount() > 0;

// 3. Medicine
$med = $pdo->prepare("SELECT 1 FROM medicines WHERE patient_id = ? AND status = 'Taken' LIMIT 1");
$med->execute([$pid]);
$med_done = $med->rowCount() > 0;

// 4. Payment (balance <= 0)
$pay = $pdo->prepare("SELECT total_amount, amount_paid FROM payments WHERE patient_id = ? LIMIT 1");
$pay->execute([$pid]);
$pay_data = $pay->fetch();
$balance = $pay_data ? ($pay_data['total_amount'] - $pay_data['amount_paid']) : 999999;
$pay_done = ($balance <= 0);

$cleared = $consult_done && $lab_done && $med_done && $pay_done;

if (!$cleared) {
    // Build a helpful error message
    $missing = [];
    if (!$consult_done) $missing[] = "Consultation (no completed appointment)";
    if (!$lab_done) $missing[] = "Laboratory (no completed test)";
    if (!$med_done) $missing[] = "Medicine (no medicine marked Taken)";
    if (!$pay_done) $missing[] = "Payment (balance = ₱" . number_format($balance, 2) . ")";
    die("Patient not cleared for discharge.<br>Missing: " . implode(", ", $missing));
}

// If cleared, print the certificate
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clearance Certificate</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 0 auto; padding: 2rem; }
        .header { text-align: center; border-bottom: 2px solid #3498db; padding-bottom: 1rem; margin-bottom: 2rem; }
        .cleared { color: green; font-size: 1.5rem; font-weight: bold; margin-top: 2rem; text-align: center; }
        .signature { margin-top: 3rem; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1>Clinic Management System</h1>
        <h2>Patient Clearance Certificate</h2>
    </div>
    <div class="patient-info">
        <p><strong>Name:</strong> <?= htmlspecialchars($patient['fullname']) ?></p>
        <p><strong>ID:</strong> <?= $pid ?></p>
        <p><strong>Age:</strong> <?= $patient['age'] ?> | <strong>Gender:</strong> <?= $patient['gender'] ?></p>
    </div>
    <div class="checklist">
        <h3>Checklist</h3>
        <ul>
            <li>✓ Consultation Completed</li>
            <li>✓ Laboratory Tests Completed</li>
            <li>✓ Medicines Released</li>
            <li>✓ Payment Fully Settled</li>
        </ul>
    </div>
    <div class="cleared">
        ✅ PATIENT CLEARED FOR DISCHARGE
    </div>
    <div class="signature">
        <p>Date: <?= date('F d, Y') ?></p>
        <p>Clinic Staff Signature: ___________________</p>
    </div>
</body>
</html>