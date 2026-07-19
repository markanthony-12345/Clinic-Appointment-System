<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$specialty = $_GET['specialty'] ?? '';

$recommendationService = new RecommendationService();

if ($action === 'get_lab_tests') {
    $tests = $recommendationService->getLabTests($specialty);
    $note = $recommendationService->getNote($specialty);
    echo json_encode(['success' => true, 'tests' => $tests, 'note' => $note]);
    exit;
}

if ($action === 'get_medications') {
    $meds = $recommendationService->getMedications($specialty);
    $note = $recommendationService->getNote($specialty);
    echo json_encode(['success' => true, 'medications' => $meds, 'note' => $note]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
?>