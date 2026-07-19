<?php
require_once 'config.php';
requireLogin();

$lab_id = (int)($_GET['lab_id'] ?? 0);
if (!$lab_id) die("Invalid lab ID.");

$stmt = $pdo->prepare("
    SELECT l.*, p.fullname AS patient_name, d.doctor_name
    FROM laboratory l
    JOIN patients p ON l.patient_id = p.patient_id
    JOIN doctors d ON l.doctor_id = d.doctor_id
    WHERE l.lab_id = ?
");
$stmt->execute([$lab_id]);
$lab = $stmt->fetch();
if (!$lab) die("Lab record not found.");

$clinic_name = "ClinicPro Medical Center";
$clinic_address = "123 Health St., City, Country";
$clinic_contact = "Tel: (02) 123-4567";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laboratory Request #<?= $lab_id ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #1e4a6e; }
        .row { display: flex; justify-content: space-between; margin: 5px 0; }
        .label { font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 0.9em; color: #6c757d; border-top: 1px solid #ddd; padding-top: 15px; }
        .status-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 2rem; background: #FEF3C7; color: #92400E; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1><?= $clinic_name ?></h1>
        <p><?= $clinic_address ?><br><?= $clinic_contact ?></p>
        <h2>Laboratory Request Form</h2>
    </div>

    <div>
        <div class="row"><span class="label">Request ID:</span> <span>#<?= $lab['lab_id'] ?></span></div>
        <div class="row"><span class="label">Patient:</span> <span><?= htmlspecialchars($lab['patient_name']) ?></span></div>
        <div class="row"><span class="label">Doctor:</span> <span><?= htmlspecialchars($lab['doctor_name']) ?></span></div>
        <div class="row"><span class="label">Procedure:</span> <span><?= htmlspecialchars($lab['procedure_name'] ?: $lab['laboratory_type']) ?></span></div>
        <div class="row"><span class="label">Date:</span> <span><?= date('F j, Y', strtotime($lab['appointment_date'])) ?></span></div>
        <div class="row"><span class="label">Time:</span> <span><?= date('g:i A', strtotime($lab['appointment_time'])) ?></span></div>
        <div class="row"><span class="label">Status:</span> <span class="status-badge"><?= $lab['status'] ?></span></div>
        <div class="row"><span class="label">Payment Status:</span> <span><?= $lab['payment_status'] ?></span></div>
    </div>

    <div class="footer">
        <p>This is a computer-generated request. Valid only with official stamp.</p>
        <p>Generated on <?= date('F j, Y g:i A') ?></p>
    </div>
</body>
</html>