<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$pid = (int)($_GET['patient_id'] ?? 0);

if (!$pid) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

// Only Admin can delete patients
if ($_SESSION['user_logged']['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access only']);
    exit;
}

try {
    // CASCADE delete will handle appointments, lab, medicines, payments automatically
    $stmt = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
    $result = $stmt->execute([$pid]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Patient deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
