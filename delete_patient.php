<?php
<<<<<<< HEAD
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
=======
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
requireAdmin(); // Tanging Admin lang ang pwedeng mag-delete ng patient

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No patient ID provided.'
    ]);
    exit;
}

$pid = $_GET['id'];

try {

    // delete appointments
    $stmt1 = $pdo->prepare("DELETE FROM appointments WHERE patient_id = ?");
    $stmt1->execute([$pid]);

    // delete patient
    $stmt2 = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
    $result = $stmt2->execute([$pid]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Patient deleted successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Delete failed.'
        ]);
    }

} catch(PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}
?>
>>>>>>> e47bb6c16c358686372866dd16fcde1ea2f9833b
