<?php
require_once 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

$apptService = new AppointmentService();
$patientService = new PatientService();

switch ($action) {
    // ===== APPOINTMENT & PATIENT =====
    case 'availability':
        if (!$doctor_id || !$date) {
            echo json_encode(['available' => false, 'remaining' => 0]);
            exit;
        }
        $result = $apptService->checkAvailability($doctor_id, $date);
        echo json_encode([
            'available' => $result['available'],
            'remaining' => $result['remaining']
        ]);
        break;

    case 'time_slots':
        if (!$doctor_id || !$date) {
            echo json_encode([]);
            exit;
        }
        $slots = $apptService->getAvailableTimeSlots($doctor_id, $date);
        echo json_encode($slots);
        break;

    case 'patient_name':
        $id = (int)($_GET['id'] ?? 0);
        echo json_encode(['fullname' => $patientService->getPatientName($id) ?: '']);
        break;

    // ===== NOTIFICATIONS =====
    case 'unread_count':
        $user_id = $_SESSION['user_logged']['user_id'] ?? 0;
        if (!$user_id) {
            echo json_encode(['count' => 0]);
            exit;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        echo json_encode(['count' => (int)$stmt->fetchColumn()]);
        break;

    case 'mark_read':
        $id = (int)($_GET['id'] ?? 0);
        $user_id = $_SESSION['user_logged']['user_id'] ?? 0;
        if (!$id || !$user_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => $result]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>