<?php
require_once 'config.php';
header('Content-Type: application/json');

$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$doctor_id || !$date) {
    echo json_encode(['available' => false, 'remaining' => 0]);
    exit;
}

$result = doctorAvailable($pdo, $doctor_id, $date);
echo json_encode([
    'available' => $result['available'],
    'remaining' => $result['remaining']
]);
?>