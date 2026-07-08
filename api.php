<?php
require_once 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

$apptService = new AppointmentService();
$patientService = new PatientService();

switch ($action) {
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

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>