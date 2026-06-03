<?php
require_once 'config.php';
header('Content-Type: application/json');

$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$doctor_id || !$date) {
    echo json_encode([]);
    exit;
}

$all_slots = [
    '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
    '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'
];

// Convert AM/PM to 24h for DB comparison
$map = [
    '09:00 AM' => '09:00:00', '10:00 AM' => '10:00:00', '11:00 AM' => '11:00:00',
    '12:00 PM' => '12:00:00', '01:00 PM' => '13:00:00', '02:00 PM' => '14:00:00',
    '03:00 PM' => '15:00:00', '04:00 PM' => '16:00:00', '05:00 PM' => '17:00:00'
];

$stmt = $pdo->prepare("SELECT TIME(appointment_date) as t FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status NOT IN ('Cancelled','Completed')");
$stmt->execute([$doctor_id, $date]);
$booked_times_24 = $stmt->fetchAll(PDO::FETCH_COLUMN);

$available = [];
foreach ($all_slots as $slot) {
    if (!in_array($map[$slot], $booked_times_24)) {
        $available[] = $slot;
    }
}

echo json_encode($available);
?>