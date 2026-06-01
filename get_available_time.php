<?php
require_once 'config.php';
header('Content-Type: application/json');

$doctor_id = $_GET['doctor_id'] ?? 0;
$date = $_GET['date'] ?? '';

if (!$doctor_id || !$date) {
    echo json_encode([]);
    exit;
}

// All possible slots
$all_slots = ['09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'];

// Get booked times for this doctor on this date
$stmt = $pdo->prepare("SELECT TIME(appointment_date) as appt_time 
                      FROM appointments 
                      WHERE doctor_id = ? 
                      AND DATE(appointment_date) = ? 
                      AND status NOT IN ('Cancelled', 'Completed')");
$stmt->execute([$doctor_id, $date]);
$booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Convert to AM/PM format for comparison
$booked_formatted = array_map(function($t) {
    return date('h:i A', strtotime($t));
}, $booked);

$available = array_values(array_diff($all_slots, $booked_formatted));

echo json_encode($available);
?>
