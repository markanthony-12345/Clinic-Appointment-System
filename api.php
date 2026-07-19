<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'patient_name') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("SELECT fullname FROM patients WHERE patient_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        echo json_encode(['fullname' => $row['fullname'] ?? '']);
    } else {
        echo json_encode(['fullname' => '']);
    }
    exit;
}

if ($action === 'availability') {
    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
    $date = $_GET['date'] ?? '';
    if (!$doctor_id || !$date) {
        echo json_encode(['available' => false, 'reason' => 'Missing parameters']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT max_patients FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $max = $stmt->fetchColumn();
    if ($max === false) {
        echo json_encode(['available' => false, 'reason' => 'Doctor not found']);
        exit;
    }
    if ($max === null) {
        echo json_encode(['available' => true, 'remaining' => 999]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status != 'Cancelled'");
    $stmt->execute([$doctor_id, $date]);
    $count = $stmt->fetchColumn();
    $remaining = max(0, $max - $count);
    echo json_encode(['available' => $remaining > 0, 'remaining' => $remaining]);
    exit;
}

if ($action === 'time_slots') {
    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
    $date = $_GET['date'] ?? '';
    if (!$doctor_id || !$date) {
        echo json_encode([]);
        exit;
    }
    // Generate time slots: 9:00 AM to 5:00 PM, 30-min intervals
    $slots = [];
    $start = strtotime('09:00');
    $end = strtotime('17:00');
    while ($start < $end) {
        $slots[] = date('g:i A', $start);
        $start = strtotime('+30 minutes', $start);
    }
    // Remove already booked slots
    $stmt = $pdo->prepare("SELECT TIME(appointment_date) as t FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status != 'Cancelled'");
    $stmt->execute([$doctor_id, $date]);
    $booked = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $bookedFormatted = array_map(function($t) {
        return date('g:i A', strtotime($t));
    }, $booked);
    $available = array_diff($slots, $bookedFormatted);
    echo json_encode(array_values($available));
    exit;
}

if ($action === 'appointments_by_date') {
    $date = $_GET['date'] ?? '';
    if (!$date) {
        echo json_encode(['error' => 'Missing date']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT a.*, p.fullname AS patient_name, d.doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE DATE(a.appointment_date) = ?
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($action === 'mark_read') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'unread_count') {
    $user_id = $_SESSION['user_logged']['user_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    echo json_encode(['count' => $stmt->fetchColumn()]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
exit;
?>