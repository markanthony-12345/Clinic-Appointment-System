<?php
require_once 'config.php';
requireAdmin();

$type = $_GET['type'] ?? 'patients';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $type . '_export.csv"');

$output = fopen('php://output', 'w');

if ($type === 'patients') {
    fputcsv($output, ['ID', 'Full Name', 'Age', 'Gender', 'Address', 'Contact', 'Registered Date']);
    $stmt = $pdo->query("SELECT * FROM patients WHERE is_archived = 0 ORDER BY patient_id DESC");
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['patient_id'],
            $row['fullname'],
            $row['age'],
            $row['gender'],
            $row['address'],
            $row['contact_number'],
            $row['date_registered']
        ]);
    }
} elseif ($type === 'appointments') {
    fputcsv($output, ['ID', 'Patient', 'Doctor', 'Date', 'Status']);
    $stmt = $pdo->query("
        SELECT a.appointment_id, p.fullname AS patient, d.doctor_name AS doctor, a.appointment_date, a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        ORDER BY a.appointment_id DESC
    ");
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['appointment_id'],
            $row['patient'],
            $row['doctor'],
            $row['appointment_date'],
            $row['status']
        ]);
    }
}

fclose($output);
exit;
?>