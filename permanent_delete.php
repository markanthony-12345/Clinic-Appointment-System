<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$pid = (int)($_GET['patient_id'] ?? 0);
if (!$pid) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

// Double confirmation required
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo json_encode(['success' => false, 'message' => 'Confirmation required']);
    exit;
}

try {
    $patientService = new PatientService();
    $result = $patientService->permanentlyDelete($pid);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Patient permanently deleted.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Deletion failed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>