<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$pid = (int)($_GET['patient_id'] ?? 0);
if (!$pid) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

try {
    $patientService = new PatientService();
    $patient = $patientService->getById($pid);
    
    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit;
    }
    
    $result = $patientService->restore($pid);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Patient restored successfully.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Restore failed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>